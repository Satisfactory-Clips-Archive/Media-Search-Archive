<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

use Cocur\Slugify\Slugify;

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/global-topic-hierarchy.php');

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
 *	urls: list<string,
 *	topics: list<string>,
 *	quotes: list<string>
 * }>
 */
$out = [];

$date = '0000-00-00';
$title = '';
$urls = [];
$quotes = [];

$is_multipart = false;
$append = false;

$do_append = static function (
	array $out,
	string $date,
	string $title,
	array $urls,
	array $quotes,
	string $topic_path
) : array {
	$id = 'tc-' . implode(':', array_map(
		static function (string $url) : string {
			return mb_substr($url, 24);
		},
		$urls
	));

	if ( ! isset($out[$id])) {
		$out[$id] = [
			'id' => $id,
			'game' => 'satisfactory',
			'date' => $date,
			'title' => $title,
			'transcription' => '',
			'urls' => $urls,
			'topics' => [],
			'quotes' => $quotes,
		];
	}

	$out[$id]['topics'][] = mb_substr($topic_path, 0, -3);

	return [
		$out,
	];
};

foreach ($global_topic_append as $game => $game_data) {
	foreach ($game_data as $topic_path => $topic_lines) {
		$append = true;

		foreach ($topic_lines as $line) {
			if ('' === $line) {
				[$out] = $do_append(
					$out,
					$date,
					$title,
					$urls,
					$quotes,
					$topic_path
				);
			} elseif (preg_match('/^# /', $line)) {
				$date = date('Y-m-d', (int) strtotime(mb_substr($line, 2, -11)));
			} elseif (preg_match('/^(?:## (.+)|### (Q&A: .+))/', $line, $matches)) {
				$title = $matches[1];
				$is_multipart = true;
				$urls = [];
				$quotes = [];
			} elseif (preg_match('/^\* Part \d+: (.+)/', $line, $matches)) {
				if ($is_multipart) {
					$urls[] = $matches[1];
				} else {
					var_dump($line);exit(1);
				}
			} elseif (preg_match('/^\* (.+) (https:.+)$/', $line, $matches)) {
				$urls = [$matches[2]];
				$title = $matches[1];
				$quotes = [];
			} elseif ('### Quotes' === $line) {
				$quotes = [];
			} elseif (preg_match('/^> (.+)$/', $line)) {
				$quotes[] = mb_substr($line, 2);
			} elseif (
				'---' === $line
				|| '*answers in these clips are impaired by the technical difficulties experienced by Snutt throughout the stream.*' === $line
			) {
				continue;
			}
		}

		if ($append) {
			[$out] = $do_append(
				$out,
				$date,
				$title,
				$urls,
				$quotes,
				$topic_path
			);
		}
	}
}

/**
 * @var array{
 *	playlists: array<string, array{0:string, 1:string, 2:list<string>}>,
 *	playlistItems: array<string, array{0:string, 1:string}>,
 *	videoTags: array<string, array{0:string, 1:list<string>}>
 * }
 */
$cache = json_decode(file_get_contents(__DIR__ . '/cache.json'), true);

/** @var array<string, string> */
$dated_playlists = json_decode(
	file_get_contents(
		__DIR__ .
		'/playlists/coffeestainstudiosdevs/satisfactory.json'
	),
	true
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

foreach ($cache['playlistItems'] as $video_id => $video_data) {
	[, $title] = $video_data;

	$urls = [sprintf('https://youtu.be/%s', rawurlencode($video_id))];
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

	$transcription_file = (
		__DIR__ .
		'/../coffeestainstudiosdevs/satisfactory/transcriptions/yt-' .
		$video_id .
		'.md'
	);

	if (is_file($transcription_file)) {
		$transcription = trim(implode(' ', array_map(
			static function (string $line) : string {
				$line = preg_replace('/^> /', '', $line);

				if ('>' === $line) {
					return "\n";
				}

				return trim($line);
			},
			array_slice(
				explode(
				"\n",
				file_get_contents($transcription_file)
				),
				3
			)
		)));
	}

	$out['yt-' . $video_id] = [
		'id' => 'yt-' . $video_id,
		'game' => 'satisfactory',
		'date' => $date,
		'title' => $title,
		'transcription' => $transcription,
		'urls' => $urls,
		'topics' => $topics_for_video,
		'quotes' => $quotes,
	];
}

file_put_contents(
	__DIR__ . '/lunr-docs-preload.json',
	json_encode(array_values($out), JSON_PRETTY_PRINT)
);
