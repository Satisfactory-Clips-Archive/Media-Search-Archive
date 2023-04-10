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
use function array_reduce;
use function array_unique;
use function count;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function in_array;
use function json_encode;
use const JSON_PRETTY_PRINT;

require_once(__DIR__ . '/../vendor/autoload.php');

$api = new YouTubeApiWrapper();
$slugify = new Slugify();

$skipping = SkippingTranscriptions::i();

$injected = new Injected($api, $slugify, $skipping);
$questions = new Questions($injected);

/**
 * @var array<string, array{
 *	game: 'satisfactory',
 *	date: string,
 *	title: string,
 *	transcription: string,
 *	urls: list<string>,
 *	topics: list<string>,
 *	alts: list<string>
 * }>
 */
$out = [];

[
	$cache,
	$global_topic_hierarchy,
] = prepare_injections($api, $slugify, $skipping, $injected);

$dated_playlists = $api->dated_playlists();

$topics = [];

foreach (
	array_filter(
		$cache['playlists'],
		static function (string $playlist_id) use ($dated_playlists) : bool {
			return ! isset($dated_playlists[$playlist_id]);
		},
		ARRAY_FILTER_USE_KEY
	) as $playlist_id => $playlist_data
) {
	[, $playlist_title] = $playlist_data;

	$slug = [];

	if (isset($global_topic_hierarchy[$playlist_id])) {
		$slug = array_filter($global_topic_hierarchy[$playlist_id], 'is_string');
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

$transcriptions = json_decode(
	file_get_contents(__DIR__ . '/../11ty/data/transcriptions.json'),
	true
);

$transcriptions = array_combine(
	array_map(
		/**
		 * @param array{id:string} $data
		 */
		static function (array $data) : string {
			return $data['id'];
		},
		$transcriptions
	),
	$transcriptions
);

foreach ($video_ids as $video_id) {
	$video_data = $cache['playlistItems'][$video_id];
	[, $title] = $video_data;

	$urls = [video_url_from_id($video_id, true)];
	$transcription = '';
	$date = determine_date_for_video(
		$video_id,
		$cache['playlists'],
		$dated_playlists
	);

	$playlists_for_video = array_keys(array_filter(
		$cache['playlists'],
		static function (array $playlist_data) use ($video_id) : bool {
			return in_array($video_id, $playlist_data[2], true);
		}
	));

	$topics_for_video = [];

	foreach ($playlists_for_video as $playlist_id) {
		if (isset($topics[$playlist_id])) {
			$topics_for_video[] = $topics[$playlist_id];
		}
	}

	$vendor_video_id = vendor_prefixed_video_id($video_id);

	if (isset($transcriptions[$vendor_video_id])) {
		$transcription = implode(
			"\n",
			$transcriptions[$vendor_video_id]['transcript']
		);
	}

	$out[$vendor_video_id] = [
		'id' => $vendor_video_id,
		'game' => 'satisfactory',
		'date' => $date,
		'title' => $title,
		'transcription' => $transcription,
		'urls' => $urls,
		'topics' => $topics_for_video,
		'alts' => array_map(
			__NAMESPACE__ . '\\vendor_prefixed_video_id',
			($cache['legacyAlts'][$video_id] ?? [])
		),
	];
}

$dated = [];

foreach (Questions::REGEX_TYPES as $category) {
	$dated[$category] = [];

	foreach ($questions->filter_video_ids(array_keys($out), $category) as $id) {
		$data = $out[$id];
		$date = $data['date'];

		if ( ! isset($dated[$category][$date])) {
			$dated[$category][$date] = [];
		}

		$dated[$category][$date][$id] = $data;
	}
}

$search_json = [];

foreach ($dated as $category => $dated_category) {
	foreach ($dated_category as $date => $data) {
		$filename = 'docs-' . $category . '-' . $date . '.json';
		file_put_contents(
			__DIR__ . '/lunr/' . $filename,
		json_encode($data, JSON_PRETTY_PRINT)
	);
		$search_json[$filename] = 'lunr-' . $category . '-' . $date . '.json';
	}
}

file_put_contents(
	__DIR__ . '/lunr/search.json',
	json_encode(
		$search_json,
		JSON_PRETTY_PRINT
	)
);
