<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_filter;
use const ARRAY_FILTER_USE_KEY;
use function array_intersect;
use function array_keys;
use function array_merge;
use function array_search;
use function array_values;
use function count;
use function file_put_contents;
use function in_array;
use function is_string;
use function ob_flush;
use function ob_get_clean;
use function ob_get_contents;
use function ob_start;
use function preg_match;
use RuntimeException;
use function sprintf;

require_once(__DIR__ . '/../vendor/autoload.php');

$stat_start = microtime(true);

register_shutdown_function(static function () use ($stat_start) {
	echo "\n", sprintf('done in %s seconds', microtime(true) - $stat_start), "\n";
});

$filtering = new Filtering();

$api = new YouTubeApiWrapper();
$slugify = new Slugify();
$skipping = SkippingTranscriptions::i();
$injected = new Injected($api, $slugify, $skipping);
$questions = new Questions($injected);
$sub_questions = new SubQuestions($injected);
$jsonify = new Jsonify($injected, $questions);

$global_topic_hierarchy = $injected->topics_hierarchy;

$cache = $injected->cache;

$sorting = new Sorting($cache);

$sorting->cache = $cache;
$sorting->playlists_date_ref = $api->dated_playlists();

$playlists = $api->dated_playlists();

$all_video_ids = $injected->all_video_ids();

echo sprintf('Ready after %s seconds' . "\n", microtime(true) - $stat_start);

[$existing, $duplicates] = $questions->process();

echo sprintf('Processed after %s seconds' . "\n", microtime(true) - $stat_start);

$sub_questions->update();

echo sprintf('sub-questions extracted after %s seconds' . "\n", microtime(true) - $stat_start);

$by_topic = [];

foreach (array_keys($injected->all_topics()) as $topic_id) {
	$by_topic[$topic_id] = array_values(array_intersect(
		$all_video_ids,
		$cache['playlists'][$topic_id][2]
	));
}

uksort($existing, [$sorting, 'sort_video_ids_by_date']);

$data = json_encode_pretty($existing);

file_put_contents(__DIR__ . '/data/q-and-a.json', $data);

echo sprintf('Written after %s seconds' . "\n", microtime(true) - $stat_start);

/**
 * @var array<string, array{
 *	previous:string|null,
 *	next:string,
 *	title:string,
 *	date:string
 * }>
 */
$part_continued = json_decode(
	file_get_contents(__DIR__ . '/data/part-continued.json'),
	true
);

$is_a_subsequent_part = array_keys(array_filter(
	$part_continued,
	/**
	 * @param array{
	 *	previous:null|string,
	 *	next:string,
	 *	title:string,
	 *	date:string
	 * } $maybe
	 */
	static function (array $maybe) : bool {
		return is_string($maybe['previous']);
	}
));

$filtered = array_filter(
	$existing,
	static function (array $data) : bool {
		return
			! in_array('trolling', $data['topics'] ?? [], true)
			&& ! in_array('off-topic', $data['topics'] ?? [], true);
	});

$filtered = array_filter(
	$filtered,
	static function (string $maybe) use ($is_a_subsequent_part) : bool {
		return ! in_array($maybe, $is_a_subsequent_part, true);
	},
	ARRAY_FILTER_USE_KEY
);

ob_start();

echo sprintf(
		'* %s questions found out of %s clips',
		count($existing),
		count($cache['playlistItems'])
	),
	"\n",
	sprintf(
		'* %s non-trolling & on-topic questions found out of %s total questions',
		count($filtered),
		count($existing)
	),
	"\n",
	sprintf(
		'* %s questions found with no other references',
		count(array_filter(
			$filtered,
			[$filtering, 'QuestionDataNoReferences']
		))
	),
	"\n"
;

$grouped = [];

foreach ($filtered as $data) {
	if ( ! isset($grouped[$data['date']])) {
		$grouped[$data['date']] = [];
	}

	$grouped[$data['date']][] = $data;
}

echo '## grouped by date', "\n";

foreach ($grouped as $date => $data) {
	echo sprintf(
			'* %s: %s of %s questions found with no other references',
			$date,
			count(array_filter(
				$data,
				[$filtering, 'QuestionDataNoReferences']
			)),
			count($data)
		),
		"\n"
	;
}

file_put_contents(
	__DIR__ . '/q-and-a.md',
	'# Progress' . "\n" . ob_get_contents()
);

ob_flush();

$faq = $questions->faq_threshold($duplicates);

/** @var string|null */
$last_faq_date = null;

/**
 * @var array<string, array<string, array{
 *	0:string,
 *	1:string,
 *	2:string,
 *	3:list<string>,
 *	4:string,
 *	5:array<string, array{0:string, 1:string, 2:string}>,
 *	6:array<empty, empty>|array{
 *		0:string,
 *		1:array{0:string, 1:string, 2:string}
 *	}
 * }>>
 */
$faq_json = [];

$playlist_topic_strings_reverse_lookup = [];

$all_topic_ids = array_merge(
	array_keys($cache['playlists']),
	array_keys($cache['stubPlaylists'] ?? [])
);

foreach ($all_topic_ids as $topic_id) {
	[$slug_string] = topic_to_slug(
		$topic_id,
		$cache,
		$global_topic_hierarchy,
		$slugify
	);

	$playlist_topic_strings_reverse_lookup[$slug_string] = $topic_id;
}

foreach (array_keys($faq) as $video_id) {
	$transcription = captions(
		$video_id,
		$playlist_topic_strings_reverse_lookup,
		$skipping
	);

	$faq_date = determine_date_for_video(
		$video_id,
		$cache['playlists'],
		$playlists
	);

	$playlist_id = array_search(
		$faq_date,
		$playlists, true
	);

	if ( ! is_string($playlist_id)) {
		throw new RuntimeException(sprintf(
			'Could not find playlist id for %s',
			$video_id
		));
	}

	$friendly_playist_name = $injected->friendly_dated_playlist_name(
		$playlist_id
	);

	if ($faq_date !== $last_faq_date) {
		$last_faq_date = $faq_date;
	}

	if ( ! isset($faq_json[$faq_date . '_' . $friendly_playist_name])) {
		$faq_json[$faq_date . '_' . $friendly_playist_name] = [];
	}

	[$link_part_title, $has_cached_captions, $link_part_url] = maybe_transcript_link_and_video_url_data(
		$video_id,
		$cache['playlistItems'][$video_id][1]
	);

	$faq_json[$faq_date . '_' . $friendly_playist_name][$video_id] = [
		$link_part_title,
		'',
		$link_part_url,
		$transcription,
		$jsonify->description_if_video_has_duplicates($video_id),
		[],
		$jsonify->content_if_video_has_other_parts($video_id),
	];

	if ($has_cached_captions) {
		$faq_json[
			$faq_date
			. '_'
			. $friendly_playist_name
		][$video_id][0] = $link_part_title;
		$faq_json[
			$faq_date
			. '_'
			. $friendly_playist_name
		][$video_id][1] = $link_part_url;
	}

	$thingsWithOtherVideoIds = [
		$filtered[$video_id]['duplicates'] ?? [],
		$filtered[$video_id]['replaces'] ?? [],
	];

	$deepCheck = [];
	$doubleChecked = [$video_id];

	while (count($deepCheck) > count($doubleChecked)) {
		foreach ($thingsWithOtherVideoIds as $thingWithOtherVideoId) {
			foreach ($thingWithOtherVideoId as $other_video_id) {
				if (in_array($other_video_id, $doubleChecked, true)) {
					continue;
				}

				if (isset($filtered[$other_video_id]['duplicates'])) {
					$deepCheck = array_merge($deepCheck, $filtered[$other_video_id]['duplicates']);
				}

				if (isset($filtered[$other_video_id]['replaces'])) {
					$deepCheck = array_merge($deepCheck, $filtered[$other_video_id]['replaces']);
				}

				$doubleChecked[] = $other_video_id;
			}
		}

		foreach ($deepCheck as $other_video_id) {
			if (in_array($other_video_id, $doubleChecked, true)) {
				continue;
			}

			if (isset($filtered[$other_video_id]['duplicates'])) {
				$deepCheck = array_merge($deepCheck, $filtered[$other_video_id]['duplicates']);
			}

			if (isset($filtered[$other_video_id]['replaces'])) {
				$deepCheck = array_merge($deepCheck, $filtered[$other_video_id]['replaces']);
			}

			$doubleChecked[] = $other_video_id;
		}
	}

	if (count($deepCheck)) {
		$thingsWithOtherVideoIds[] = $deepCheck;
	}

	foreach ($thingsWithOtherVideoIds as $thingWithOtherVideoIds) {
		foreach ($thingWithOtherVideoIds as $other_video_id) {
			[$link_part_title, $has_cached_captions, $link_part_url] = maybe_transcript_link_and_video_url_data(
				$other_video_id,
				$cache['playlistItems'][$other_video_id][1]
			);

			$faq_json[
				$faq_date
				. '_'
				. $friendly_playist_name
			][$video_id][5][$other_video_id] = [
				$link_part_title,
				'',
				$link_part_url
			];

			if (
				$has_cached_captions
			) {
				$faq_json[
					$faq_date
					. '_'
					. $friendly_playist_name
				][$video_id][5][$other_video_id][0] = $link_part_title;
				$faq_json[
					$faq_date
					. '_'
					. $friendly_playist_name
				][$video_id][5][$other_video_id][1] = $link_part_url;
			}
		}
	}
}

$data = json_encode_pretty($by_topic);

file_put_contents(__DIR__ . '/data/video-id-by-topic.json', $data);

$data = json_encode_pretty(
	$injected->all_topics()
);

file_put_contents(__DIR__ . '/data/all-topic-slugs.json', $data);

$data = json_encode_pretty($faq_json);

file_put_contents(__DIR__ . '/../11ty/data/faq.json', $data);
