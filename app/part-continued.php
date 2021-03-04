<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_combine;
use function array_diff;
use function array_filter;
use const ARRAY_FILTER_USE_BOTH;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_reduce;
use function array_unique;
use function array_values;
use function count;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use const JSON_PRETTY_PRINT;
use function preg_match;
use function sprintf;
use function strtotime;
use function uksort;

require_once (__DIR__ . '/../vendor/autoload.php');

$api = new YouTubeApiWrapper();

[$cache] = prepare_injections(
	$api,
	new Slugify()
);

/**
 * @var array<string, array{
 *	title:string,
 *	date:string,
 *	previous:string|null,
 *	next:string|null
 * }>
 */
$existing = array_filter(
	(array) json_decode(
		file_get_contents(
			__DIR__
			. '/data/part-continued.json'
		),
		true
	),
	/**
	 * @param scalar|array|object|null $maybe
	 * @param array-key $maybe_key
	 */
	static function ($maybe, $maybe_key) use ($cache) : bool {
		return
			is_string($maybe_key)
			&& isset($cache['playlistItems'][$maybe_key])
			&& is_array($maybe)
			&& 4 === count($maybe)
			&& isset($maybe['title'])
			&& is_string($maybe['title'])
			&& isset($maybe['date'])
			&& is_string($maybe['date'])
			&& false !== strtotime($maybe['date'])
			&& array_key_exists('previous', $maybe)
			&& array_key_exists('next', $maybe)
			&& (
				null === $maybe['previous']
				|| (
					is_string($maybe['previous'])
					&& isset($cache['playlistItems'][$maybe['previous']])
				)
			)
			&& (
				null === $maybe['next']
				|| (
					is_string($maybe['next'])
					&& isset($cache['playlistItems'][$maybe['next']])
				)
			)
		;
	},
	ARRAY_FILTER_USE_BOTH
);

$maybe_with_part = array_diff(
	array_keys(array_filter(
		$cache['playlistItems'],
		static function (array $maybe) : bool {
			return (bool) preg_match('/part \d+/i', $maybe[1]);
		}
	)),
	array_keys($existing),
	array_reduce(
		$cache['legacyAlts'],
		/**
		 * @param list<string> $out
		 * @param list<string> $alts
		 *
		 * @return list<string>
		 */
		static function (array $out, array $alts) : array {
			return array_values(array_unique(array_merge(
				$out,
				array_diff($alts, $out)
			)));
		},
		[]
	)
);

$maybe_with_part = array_combine($maybe_with_part, array_map(
	/**
	 * @todo check if this can be replaced with array_fill
	 *
	 * @return array{previous:null, next:null, title:string, date:string}
	 */
	static function (string $_video_id) : array {
		return [
			'previous' => null,
			'next' => null,
			'title' => '',
			'date' => 'now',
		];
	},
	$maybe_with_part
));

$existing = array_merge($existing, $maybe_with_part);

$playlists = $api->dated_playlists();

foreach (array_keys($existing) as $video_id) {
	$existing[$video_id]['title'] = $cache['playlistItems'][$video_id][1];
	$existing[$video_id]['date'] = determine_date_for_video(
		$video_id,
		$cache['playlists'],
		$playlists
	);
}

$sorting = new Sorting($cache);
$sorting->playlists_date_ref = $playlists;

$existing['ZaVKeo3QXqg']['previous'] = '1dUNmBBbExs'; // not in index but is what it refers to

uksort($existing, [$sorting, 'sort_video_ids_by_date']);

file_put_contents(
	__DIR__ . '/data/part-continued.json',
	json_encode($existing, JSON_PRETTY_PRINT)
);

$no_parts_specified = array_filter(
	$existing,
	/**
	 * @param array{previous:string|null, next:string|null} $maybe
	 */
	static function (array $maybe) : bool {
		return null === $maybe['previous'] && null === $maybe['next'];
	}
);

echo sprintf(
		'%s of %s videos found with no part information',
		count($no_parts_specified),
		count($existing)
	),
	"\n"
;
