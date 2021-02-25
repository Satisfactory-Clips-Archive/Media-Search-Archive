<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_filter;
use function array_intersect;
use function array_keys;
use function array_search;
use function array_values;
use function count;
use const FILE_APPEND;
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
use function preg_replace;
use RuntimeException;
use function sprintf;
use function str_replace;

require_once (__DIR__ . '/../vendor/autoload.php');
require_once (__DIR__ . '/global-topic-hierarchy.php');

$filtering = new Filtering();

$api = new YouTubeApiWrapper();
$slugify = new Slugify();
$injected = new Injected($api, $slugify);
$markdownify = new Markdownify($injected);

$cache = $injected->cache;

$playlists = $api->dated_playlists();

$all_video_ids = $injected->all_video_ids();

$questions = new Questions($injected);

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

echo "\n", '# prototype replacement for faq markdown file', "\n";

$faq = $questions->faq_threshold($duplicates);

echo "\n";

/** @var string|null */
$last_faq_date = null;

foreach (array_keys($faq) as $video_id) {
	$transcription = captions($video_id);

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

	if ($faq_date !== $last_faq_date) {
		$last_faq_date = $faq_date;

		echo '## [',
			$injected->friendly_dated_playlist_name($playlist_id),
			'](',
			'https://archive.satisfactory.video/',
			$faq_date,
			')',
			"\n"
		;
	}

	echo '### ',
		preg_replace('/\.md\)/', ')', str_replace(
			'./',
			'https://archive.satisfactory.video/',
			maybe_transcript_link_and_video_url(
				$video_id,
				$cache['playlistItems'][$video_id][1]
			)
		)),
		"\n"
	;

	echo preg_replace('/\.md\)/', ')', str_replace(
		'./',
		'https://archive.satisfactory.video/',
		$markdownify->content_if_video_has_other_parts($video_id, true)
	));

	if (count($transcription) > 0) {
		echo "\n", '<details>', "\n";
		echo "\n", '<summary>A transcript is available</summary>', "\n";
		echo "\n", markdownify_transcription_lines(...$transcription), "\n";
		echo "\n", '</details>', "\n";
	}

	echo preg_replace('/\.md\)/', ')', str_replace(
		'./',
		'https://archive.satisfactory.video/',
		$markdownify->content_if_video_has_duplicates($video_id, $questions)
	));

	echo "\n";
}

file_put_contents(
	__DIR__ . '/q-and-a.md',
	ob_get_clean(),
	FILE_APPEND
);

$data = str_replace(PHP_EOL, "\n", json_encode($by_topic, JSON_PRETTY_PRINT));

file_put_contents(__DIR__ . '/data/video-id-by-topic.json', $data);

$data = str_replace(PHP_EOL, "\n", json_encode(
	$injected->all_topics(),
	JSON_PRETTY_PRINT
));

file_put_contents(__DIR__ . '/data/all-topic-slugs.json', $data);
