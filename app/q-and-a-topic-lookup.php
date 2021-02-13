<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_diff;
use function array_filter;
use const ARRAY_FILTER_USE_KEY;
use function array_keys;
use function array_map;
use function array_merge;
use function array_merge_recursive;
use function array_reduce;
use function array_reverse;
use function array_slice;
use function array_values;
use function asort;
use function count;
use function file_get_contents;
use function in_array;
use function is_string;
use function json_decode;
use function mb_strtolower;
use function preg_match;
use function preg_quote;
use function sprintf;

require_once (__DIR__ . '/../vendor/autoload.php');
require_once (__DIR__ . '/global-topic-hierarchy.php');

/**
 * @var array<string, array{
 *	title:string,
 *	date:string,
 *	topics:list<string>,
 *	duplicates:list<string>,
 *	replaces:list<string>,
 *	seealso:list<string>
 * }>
 */
$questions = json_decode(
	file_get_contents(__DIR__ . '/data/q-and-a.json'),
	true
);

/**
 * @var array<string, list<string>>
 */
$videos_by_topic = json_decode(
	file_get_contents(__DIR__ . '/data/video-id-by-topic.json'),
	true
);

/** @var array<string, string> */
$topic_slugs = json_decode(
	file_get_contents(__DIR__ . '/data/all-topic-slugs.json'),
	true
);

$lookup = array_slice($argv, 1)[0] ?? null;

$api = new YouTubeApiWrapper();

$api->update();

$slugify = new Slugify();

$cache = $api->toLegacyCacheFormat();

/**
 * @var array{
 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	playlistItems:array<string, array{0:string, 1:string}>,
 *	videoTags:array<string, array{0:string, list<string>}>,
 *	stubPlaylists?:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	legacyAlts?:array<string, list<string>>,
 *	internalxref?:array<string, string>
 * }
 */
$injected_cache = json_decode(
	file_get_contents(__DIR__ . '/cache-injection.json'),
	true
);

$cache = inject_caches($cache, $injected_cache);

$global_topic_hierarchy = array_merge_recursive(
	$global_topic_hierarchy,
	$injected_global_topic_hierarchy
);

$externals_cache = process_externals(
	$cache,
	$global_topic_hierarchy,
	$not_a_livestream,
	$not_a_livestream_date_lookup,
	$slugify,
	false
);

$cache = inject_caches($cache, $externals_cache);

if ( ! is_string($lookup)) {
	echo 'no video id specified!'
	;

	exit(1);
} elseif ( ! isset($questions[$lookup])) {
	echo 'the specified video id was not identified as a question!'
	;

	exit(1);
}

$other_topics = array_keys(array_filter(
	$topic_slugs,
	static function (string $maybe) use ($lookup, $questions) : bool {
		return in_array($maybe, $questions[$lookup]['topics'], true);
	}
));

$other_videos = [];

foreach ($other_topics as $topic_id) {
	foreach ($videos_by_topic[$topic_id] as $video_id) {
		if ($video_id !== $lookup) {
			if ( ! isset($other_videos[$video_id])) {
				$other_videos[$video_id] = 0;
			}

			++$other_videos[$video_id];
		}
	}
}

$legacy_alts = array_reduce(
	$cache['legacyAlts'],
	/**
	 * @param list<string> $out
	 * @param list<string> $alts
	 *
	 * @return list<string>
	 */
	static function (array $out, array $alts) : array {
		return array_values(array_merge($out, array_diff($alts, $out)));
	},
	[]
);

$other_videos = array_filter(
	$other_videos,
	static function (string $maybe) use ($legacy_alts) : bool {
		return ! in_array($maybe, $legacy_alts, true);
	},
	ARRAY_FILTER_USE_KEY
);

asort($other_videos);
$other_videos = array_reverse($other_videos, true);

if (count($other_videos) < 1) {
	echo 'no other videos identified on related topics!',
		"\n";

	exit(1);
}

$filter = array_slice($argv, 2);

if (count($filter) > 0) {
	$filter = array_map(
		static function (string $word) : string {
			return '/\b' . preg_quote(mb_strtolower($word), '/') . '\b/i';
		},
		$filter
	);

	$other_videos = array_filter(
		$other_videos,
		static function (string $maybe) use ($cache, $filter) : bool {
			foreach ($filter as $regex) {
				if (preg_match($regex, $cache['playlistItems'][$maybe][1])) {
					return true;
				}
			}

			return false;
		},
		ARRAY_FILTER_USE_KEY
	);
}

echo sprintf('%s other videos identified!', count($other_videos)), "\n";

foreach ($other_videos as $video_id => $topic_matches) {
	echo sprintf(
			'%s %s (%s topics in common)',
			$video_id,
			$cache['playlistItems'][$video_id][1],
			$topic_matches
		),
		"\n"
	;
}
