<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_filter;
use function array_intersect;
use function array_keys;
use function array_merge;
use function array_search;
use function array_values;
use function count;
use function file_put_contents;
use function in_array;
use function is_string;
use function json_encode;
use const JSON_PRETTY_PRINT;
use function ob_flush;
use function ob_get_clean;
use function ob_get_contents;
use function ob_start;
use const PHP_EOL;
use function preg_match;
use RuntimeException;
use function sprintf;
use function str_replace;

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/global-topic-hierarchy.php');

$filtering = new Filtering();

$api = new YouTubeApiWrapper();
$slugify = new Slugify();
$injected = new Injected($api, $slugify);
$questions = new Questions($injected);
$markdownify = new Markdownify($injected, $questions);
$jsonify = new Jsonify($injected, $questions);

$cache = $injected->cache;

$playlists = $api->dated_playlists();

$all_video_ids = $injected->all_video_ids();

[$existing, $duplicates] = $questions->process();

$by_topic = [];

foreach (array_keys($injected->all_topics()) as $topic_id) {
	$by_topic[$topic_id] = array_values(array_intersect(
		$all_video_ids,
		$cache['playlists'][$topic_id][2]
	));
}

$data = str_replace(PHP_EOL, "\n", json_encode($existing, JSON_PRETTY_PRINT));

file_put_contents(__DIR__ . '/data/q-and-a.json', $data);

$filtered = array_filter(
	$existing,
	static function (array $data) : bool {
		return
			! in_array('trolling', $data['topics'] ?? [], true)
			&& ! in_array('off-topic', $data['topics'] ?? [], true);
	});

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

ob_start();

$faq = $questions->faq_threshold($duplicates);

echo '---', "\n",
	'title: "Frequently Asked Questions"', "\n",
	'date: Last Modified', "\n",
	'---', "\n",
	'';

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
	$transcription = captions($video_id, $playlist_topic_strings_reverse_lookup);

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

		echo '## [',
			$friendly_playist_name,
			'](',
			'./',
			$faq_date,
			'.md',
			')',
			"\n"
		;
	}

	if ( ! isset($faq_json[$faq_date . '_' . $friendly_playist_name])) {
		$faq_json[$faq_date . '_' . $friendly_playist_name] = [];
	}

	$link = maybe_transcript_link_and_video_url(
		$video_id,
		$cache['playlistItems'][$video_id][1]
	);

	echo '### ', $link,
		"\n"
	;

	if ( ! preg_match(Jsonify::link_part_regex, $link, $link_parts)) {
		throw new RuntimeException('Could not determine link parts!');
	}

	$faq_json[$faq_date . '_' . $friendly_playist_name][$video_id] = [
		$link_parts[1],
		'',
		$link_parts[2],
		$transcription,
		$jsonify->description_if_video_has_duplicates($video_id),
		[],
		$jsonify->content_if_video_has_other_parts($video_id),
	];

	if (preg_match(Jsonify::transcript_part_regex, $link_parts[1], $link_parts)) {
		$faq_json[
			$faq_date
			. '_'
			. $friendly_playist_name
		][$video_id][0] = $link_parts[1];
		$faq_json[
			$faq_date
			. '_'
			. $friendly_playist_name
		][$video_id][1] = $link_parts[2];
	}

	foreach ($filtered[$video_id]['duplicates'] ?? [] as $other_video_id) {
		if (
			! preg_match(
				Jsonify::link_part_regex,
				maybe_transcript_link_and_video_url(
					$other_video_id,
					$cache['playlistItems'][$other_video_id][1]
				),
				$link_parts
			)
		) {
			throw new RuntimeException('Could not determine link parts!');
		}

		$faq_json[
			$faq_date
			. '_'
			. $friendly_playist_name
		][$video_id][5][$other_video_id] = [
			$link_parts[1],
			'',
			$link_parts[2],
		];

		if (
			preg_match(
				Jsonify::transcript_part_regex,
				$link_parts[1],
				$link_parts
			)
		) {
			$faq_json[
				$faq_date
				. '_'
				. $friendly_playist_name
			][$video_id][5][$other_video_id][0] = $link_parts[1];
			$faq_json[
				$faq_date
				. '_'
				. $friendly_playist_name
			][$video_id][5][$other_video_id][1] = $link_parts[2];
		}
	}

	echo $markdownify->content_if_video_has_other_parts($video_id, true)
	;

	if (count($transcription) > 0) {
		echo "\n", '<details>', "\n";
		echo "\n", '<summary>A transcript is available</summary>', "\n";
		echo "\n", markdownify_transcription_lines(...$transcription), "\n";
		echo "\n", '</details>', "\n";
	}

	echo $markdownify->content_if_video_has_duplicates($video_id, $questions)
	;

	echo "\n";
}

file_put_contents(
	(
		__DIR__
		. '/../video-clip-notes/docs/FAQ.md'
	),
	ob_get_clean()
);

$data = str_replace(PHP_EOL, "\n", json_encode($by_topic, JSON_PRETTY_PRINT));

file_put_contents(__DIR__ . '/data/video-id-by-topic.json', $data);

$data = str_replace(PHP_EOL, "\n", json_encode(
	$injected->all_topics(),
	JSON_PRETTY_PRINT
));

file_put_contents(__DIR__ . '/data/all-topic-slugs.json', $data);

$data = str_replace(PHP_EOL, "\n", json_encode($faq_json, JSON_PRETTY_PRINT));

file_put_contents(__DIR__ . '/../11ty/data/faq.json', $data);
