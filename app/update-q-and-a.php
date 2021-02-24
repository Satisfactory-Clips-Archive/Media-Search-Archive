<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_diff;
use function array_filter;
use function array_intersect;
use function array_keys;
use function array_search;
use function array_values;
use function count;
use function current;
use function date;
use function end;
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
use function strtotime;
use function uasort;

require_once (__DIR__ . '/../vendor/autoload.php');
require_once (__DIR__ . '/global-topic-hierarchy.php');

$filtering = new Filtering();

$api = new YouTubeApiWrapper();
$slugify = new Slugify();
$injected = new Injected($api, $slugify);

$cache = $injected->cache;

$playlists = $api->dated_playlists();

$all_video_ids = $injected->all_video_ids();

$questions = new Questions($injected);

$existing = $questions->append_new_questions();
$existing = $questions->process_legacyalts($existing, $cache['legacyAlts']);
[$existing, $duplicates] = $questions->process_duplicates($existing);
[$existing] = $questions->process_seealsos($existing);
$existing = $questions->finalise($existing, $cache);

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

foreach ($faq as $video_id => $faq_duplicates) {
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

	if (has_other_part($video_id)) {
		$video_part_info = cached_part_continued()[$video_id];
		$video_other_parts = other_video_parts($video_id);

		echo "\n",
			'<details>',
			"\n",
			'<summary>';

		if (count($video_other_parts) > 2) {
			echo sprintf(
				'This video is part of a series of %s videos.',
				count($video_other_parts)
			);
		} elseif (null !== $video_part_info['previous']) {
			echo 'This video is a continuation of a previous video';
		} else {
			echo 'This video continues in another video';
		}

		echo '</summary>', "\n\n";

		if (count($video_other_parts) > 2) {
			$video_other_parts = other_video_parts($video_id, false);
		}

		foreach ($video_other_parts as $other_video_id) {
			echo '* ',
				preg_replace('/\.md\)/', ')', str_replace(
					'./',
					'https://archive.satisfactory.video/',
					maybe_transcript_link_and_video_url(
						$other_video_id,
						(
							$injected->friendly_dated_playlist_name(
								$playlist_id
							)
							. $cache['playlistItems'][$other_video_id][1]
						)
					)
				)),
				"\n"
			;
		}
	}

	if (count($transcription) > 0) {
		echo "\n", '<details>', "\n";
		echo "\n", '<summary>A transcript is available</summary>', "\n";
		echo "\n", markdownify_transcription_lines(...$transcription), "\n";
		echo "\n", '</details>', "\n";
	}

	uasort($faq_duplicates, [$injected->sorting, 'sort_video_ids_by_date']);

	$faq_duplicate_dates = [];

	$faq_duplicates_for_date_checking = array_diff(
		$faq_duplicates,
		[
			$video_id,
		]
	);

	foreach ($faq_duplicates_for_date_checking as $other_video_id) {
		$faq_duplicate_video_date = determine_date_for_video(
			$other_video_id,
			$cache['playlists'],
			$playlists
		);

		if (
			! in_array($faq_duplicate_video_date, $faq_duplicate_dates, true)
		) {
			$faq_duplicate_dates[] = $faq_duplicate_video_date;
		}
	}

	echo "\n",
		'<details>',
		"\n",
		'<summary>',
		sprintf(
			'This question may have been asked previously at least %s other %s',
			count($faq_duplicates_for_date_checking),
			count($faq_duplicates_for_date_checking) > 1 ? 'times' : 'time'
		),
		sprintf(
			', as recently as %s%s',
			date('F Y', strtotime(current($faq_duplicate_dates))),
			(
				count($faq_duplicate_dates) > 1
					? (
						' and as early as '
						. date('F Y.', strtotime(end($faq_duplicate_dates)))
					)
					: '.'
			)
		),
		'</summary>',
		"\n"
	;

	foreach ($faq_duplicates_for_date_checking as $other_video_id) {
		$other_video_date =
			determine_date_for_video(
				$other_video_id,
				$cache['playlists'],
				$playlists
		);
		$playlist_id = array_search(
			$other_video_date,
			$playlists, true
		);

		if ( ! is_string($playlist_id)) {
			throw new RuntimeException(sprintf(
				'Could not find playlist id for %s',
				$video_id
			));
		}

		echo "\n",
			'* ',
			preg_replace('/\.md\)/', ')', str_replace(
				'./',
				'https://archive.satisfactory.video/',
				maybe_transcript_link_and_video_url(
					$other_video_id,
					(
						$injected->friendly_dated_playlist_name($playlist_id)
						. ' '
						. $cache['playlistItems'][$other_video_id][1]
					)
				)
			))
		;
	}

	echo "\n", '</details>', "\n";

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
