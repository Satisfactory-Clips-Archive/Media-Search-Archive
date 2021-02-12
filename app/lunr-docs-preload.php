<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_combine;
use function array_diff;
use function array_filter;
use const ARRAY_FILTER_USE_KEY;
use function array_keys;
use function array_map;
use function array_merge;
use function array_merge_recursive;
use function array_reduce;
use function array_unique;
use function count;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function in_array;
use function is_file;
use function json_decode;
use function json_encode;
use const JSON_PRETTY_PRINT;
use function mb_strpos;
use function mb_substr;
use function preg_replace;
use function trim;

require_once (__DIR__ . '/vendor/autoload.php');
require_once (__DIR__ . '/global-topic-hierarchy.php');

$global_topic_hierarchy = array_merge_recursive(
	$global_topic_hierarchy,
	$injected_global_topic_hierarchy
);

$slugify = new Slugify();

/** @var array{satisfactory:array<string, list<string>>} */
$global_topic_append = json_decode(
	file_get_contents(__DIR__ . '/global-topic-append.json'),
	true
);

/**
 * @var array<string, array{
 *	game: 'satisfactory',
 *	date: string,
 *	title: string,
 *	transcription: string,
 *	urls: list<string>,
 *	topics: list<string>,
 *	quotes: list<string>
 * }>
 */
$out = [];

$date = '0000-00-00';
$title = '';
$urls = [];
$quotes = [];

/**
 * @var array{
 *	playlists: array<string, array{0:string, 1:string, 2:list<string>}>,
 *	playlistItems: array<string, array{0:string, 1:string}>,
 *	videoTags: array<string, array{0:string, 1:list<string>}>
 * }
 */
$cache = json_decode(file_get_contents(__DIR__ . '/cache.json'), true);

$cache = inject_caches(
	$cache,
	json_decode(file_get_contents(__DIR__ . '/cache-injection.json'), true)
);

$cache = inject_caches(
	$cache,
	process_externals(
		$cache,
		$global_topic_hierarchy,
		$not_a_livestream,
		$not_a_livestream_date_lookup,
		$slugify,
		false
	)
);

/** @var array<string, string> */
$dated_playlists = json_decode(
	file_get_contents(
		__DIR__ .
		'/playlists/coffeestainstudiosdevs/satisfactory.json'
	),
	true
);

$dated_playlists = array_merge(
	$dated_playlists,
	json_decode(
		file_get_contents(
			__DIR__ .
			'/playlists/coffeestainstudiosdevs/satisfactory.injected.json'
		),
		true
	)
);

$dated_playlists = array_map(
	static function (string $filename) : string {
		return mb_substr($filename, 0, -3);
	},
	$dated_playlists
);

$topics = [];

foreach (
	array_filter(
		$cache['playlists'],
		static function ($playlist_id) use ($dated_playlists) : bool {
			return ! isset($dated_playlists[$playlist_id]);
		},
		ARRAY_FILTER_USE_KEY
	) as $playlist_id => $playlist_data
) {
	[, $playlist_title] = $playlist_data;

	$slug = [];

	if (isset($global_topic_hierarchy['satisfactory'][$playlist_id])) {
		$slug = array_filter($global_topic_hierarchy['satisfactory'][$playlist_id], 'is_string');
	}

	if (($slug[0] ?? '') !== $playlist_title) {
		$slug[] = $playlist_title;
	}

	$topics[$playlist_id] = implode('/', array_map([$slugify, 'slugify'], $slug));
}

$video_ids = array_keys($cache['playlistItems']);

$has_legacy_alts = array_filter(
	$video_ids,
	static function (string $maybe) use ($cache) : bool {
		return isset($cache['legacyAlts'][$maybe]);
	}
);

if (count($has_legacy_alts)) {
	$legacy_alts = array_unique(array_reduce(
		$has_legacy_alts,
		static function (
			array $out,
			string $video_id
		) use ($cache) : array {
			return array_merge($out, $cache['legacyAlts'][$video_id]);
		},
		[]
	));

	$video_ids = array_diff($video_ids, $legacy_alts);
}

foreach ($video_ids as $video_id) {
	$video_data = $cache['playlistItems'][$video_id];
	[, $title] = $video_data;

	$urls = [video_url_from_id($video_id)];
	$quotes = [];
	$transcription = '';
	$date = '0000-00-00';

	$playlists_for_video = array_keys(array_filter(
		$cache['playlists'],
		static function (array $playlist_data) use ($video_id) : bool {
			return in_array($video_id, $playlist_data[2], true);
		}
	));

	foreach ($playlists_for_video as $playlist_id) {
		if (isset($dated_playlists[$playlist_id])) {
			$date = $dated_playlists[$playlist_id];
			break;
		}
	}

	$topics_for_video = [];

	foreach ($playlists_for_video as $playlist_id) {
		if (isset($topics[$playlist_id])) {
			$topics_for_video[] = $topics[$playlist_id];
		}
	}

	$transcription_file = transcription_filename($video_id);

	if (is_file($transcription_file)) {
		$transcription_raw = file_get_contents($transcription_file);

		$transcription_raw = mb_substr(
			$transcription_raw,
			mb_strpos($transcription_raw, '---', 4)
		);

		$transcription_raw = mb_substr(
			$transcription_raw,
			mb_strpos($transcription_raw, "\n" . '>')
		);

		$transcription = trim(implode("\n", array_map(
			static function (string $line) : string {
				$line = preg_replace('/^> /', '', $line);

				if ('>' === $line) {
					return "\n";
				}

				return trim($line);
			},
			explode(
				"\n",
				$transcription_raw
			)
		)));
	}

	$vendor_video_id = vendor_prefixed_video_id($video_id);

	$out[$vendor_video_id] = [
		'id' => $vendor_video_id,
		'game' => 'satisfactory',
		'date' => $date,
		'title' => $title,
		'transcription' => $transcription,
		'urls' => $urls,
		'topics' => $topics_for_video,
		'quotes' => $quotes,
		'alts' => array_map(
			__NAMESPACE__ . '\\vendor_prefixed_video_id',
			($cache['legacyAlts'][$video_id] ?? [])
		),
	];
}

$dated = [];

foreach ($out as $id => $data) {
	$date = $data['date'];

	if ( ! isset($dated[$date])) {
		$dated[$date] = [];
	}

	$dated[$date][$id] = $data;
}

foreach ($dated as $date => $data) {
	file_put_contents(
		(__DIR__ . '/lunr/docs-' . $date . '.json'),
		json_encode($data, JSON_PRETTY_PRINT)
	);
}

file_put_contents(
	__DIR__ . '/lunr/search.json',
	json_encode(
		array_combine(
			array_map(
				static function (string $date) : string {
					return 'docs-' . $date . '.json';
				},
				array_keys($dated)
			),
			array_map(
				static function (string $date) : string {
					return 'lunr-' . $date . '.json';
				},
				array_keys($dated)
			)
		),
		JSON_PRETTY_PRINT
	)
);
