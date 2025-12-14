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
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_reduce;
use function array_reverse;
use function array_search;
use function array_unique;
use function array_values;
use function asort;
use function assert;
use function basename;
use function chr;
use function count;
use function date;
use DateTime;
use ErrorException;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function in_array;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;
use const JSON_PRETTY_PRINT;
use function mb_substr;
use function min;
use function preg_match;
use function realpath;
use RuntimeException;
use SimpleXmlElement;
use function sprintf;
use const STR_PAD_LEFT;
use function str_replace;
use function strnatcasecmp;
use function strtotime;
use function time;
use function touch;
use function uasort;
use function uksort;
use UnexpectedValueException;
use function usleep;
use function usort;

require_once(__DIR__ . '/../vendor/autoload.php');

$stat_start = microtime(true);

register_shutdown_function(static function () use ($stat_start) {
	echo "\n", sprintf('done in %s seconds', microtime(true) - $stat_start), "\n";
});

$api = new YouTubeApiWrapper();
echo 'YouTube API Wrapper instantiated', "\n";

$slugify = new Slugify();

$skipping = SkippingTranscriptions::i();
echo 'SkippingTranscriptions instantiated', "\n";

$injected = new Injected($api, $slugify, $skipping);
echo 'Injected instantiated', "\n";

$questions = new Questions($injected);
echo 'Questions instantiated', "\n";

$jsonify = new Jsonify($injected, $questions);
echo 'Jsonify instantiated', "\n";

$cache = $injected->cache;
$global_topic_hierarchy = $injected->topics_hierarchy;

$not_a_livestream = $injected->not_a_livestream;
$not_a_livestream_date_lookup = $injected->not_a_livestream_date_lookup;
file_put_contents(
	__DIR__ . '/data/play.json',
	json_encode_pretty(
			$injected->format_play()
	)
);
echo 'app/data/play.json updated', "\n";

$playlist_satisfactory =
	realpath(
		__DIR__
		. '/playlists/youtube.json'
	);

if ( ! is_string($playlist_satisfactory)) {
	throw new RuntimeException('Satisfactory playlist not found!');
}

/** @var array<string, string> */
$playlists = $api->dated_playlists();

/** @var array<string, list<array{0:string, 1:int}>> */
$playlist_history = json_decode(
	file_get_contents(__DIR__ . '/playlist-date-history.json'),
	true
);

foreach ($playlists as $playlist_id => $playlist_date) {
	if (false === strtotime($playlist_date)) {
		throw new RuntimeException(sprintf(
			'Invalid path? %s',
			$playlist_id
		));
	}

	if ( ! isset($playlist_history[$playlist_id])) {
		$playlist_history[$playlist_id] = [];
	}

	$playlist_dates = array_map(
		static function (array $data) : string {
			return $data[0];
		},
		$playlist_history[$playlist_id]
	);

	if ( ! in_array($playlist_date, $playlist_dates, true)) {
		$playlist_history[$playlist_id][] = [$playlist_date, time()];
	}
}

$playlist_history = array_map(
	/**
	 * @param list<array{0:string, 1:int}> $data
	 *
	 * @return list<array{0:string, 1:int}>
	 */
	static function (array $data) : array {
		usort(
			$data,
			/**
			 * @psalm-type IN = array{0:string, 1:int}
			 *
			 * @param IN $a
			 * @param IN $b
			 */
			static function (array $a, array $b) : int {
				return $a[1] - $b[1];
			}
		);

		return $data;
	},
	$playlist_history
);

file_put_contents(__DIR__ . '/playlist-date-history.json', json_encode(
	$playlist_history,
	JSON_PRETTY_PRINT
));

$grouped_dated_data_for_json = [];

$process_externals_result = process_externals(
	$cache,
	$not_a_livestream,
	$not_a_livestream_date_lookup,
	$skipping,
	$injected
);
echo "\n", 'done processing externals', "\n";

$externals_values = get_externals();
echo 'done getting externals', "\n";
$externals_dates = array_keys($externals_values);

$sorting = new Sorting($cache);

$sorting->cache = $cache;
$sorting->playlists_date_ref = $api->dated_playlists();

echo "\n",'done setting up Sorting', "\n";

$no_topics = [];

foreach (
	array_unique(array_merge(
		array_keys($cache['playlistItems']),
		array_keys($cache['videoTags']),
		array_keys($cache['legacyAlts'])
	)) as $video_id
) {
	$found = false;

	foreach ($cache['playlists'] as $data) {
		$found = in_array($video_id, $data[2], true);

		if ($found) {
			break;
		}
	}

	if ( ! $found) {
		$no_topics[] = $video_id;
	}
}

if (count($no_topics)) {
	echo "\n", implode("\n", $no_topics), "\n";
	throw new RuntimeException('Found video with no topics!');
}

$all_topic_ids = array_unique(array_merge(
	array_keys($cache['playlists']),
	array_keys($cache['stubPlaylists'] ?? []),
	array_keys($global_topic_hierarchy)
));

$topics_without_direct_content = array_filter(
	$all_topic_ids,
	static function (string $topic_id) use ($cache) {
		return count($cache['playlists'][$topic_id] ?? []) < 1;
	}
);

echo "\n", 'prepping topic nesting', "\n";

/**
 * @var array<string, array{
 *	children: list<string>,
 *	left: positive-int,
 *	right: positive-int,
 *	level: int
 * }>
 */
$topic_nesting = [];

$dated_playlists = $api->dated_playlists();

foreach ($all_topic_ids as $topic_id) {
	if (in_array($topic_id, $dated_playlists, true)) {
		continue;
	}

	$topic_nesting[$topic_id] = [
		'children' => [],
		'left' => -1,
		'right' => -1,
		'level' => -1,
		'hdepth_for_templates' => 1,
		'clips' => 0,
		'id' => $topic_id,
	];
}

/** @var list<string> */
$missing_topics = [];

foreach ($global_topic_hierarchy as $topic_id => $topic_ancestors) {
	if ( ! isset($topic_nesting[$topic_id])) {
		$missing_topics[] = $topic_id;

		continue;
	}

	$video_ids = ($cache['playlists'][$playlist_id] ?? [2 => []])[2] ?? [];
	$topic_nesting[$topic_id]['clips'] = count($video_ids);

	$topic_ancestors = array_filter($topic_ancestors, 'is_string');

	$topic_nesting[$topic_id]['level'] = count($topic_ancestors);
	$topic_nesting[$topic_id]['hdepth_for_templates'] = min(
		6,
		($topic_nesting[$topic_id]['level'] + 1)
	);

	$topic_ancestors = array_reverse($topic_ancestors);

	$topic_descendant_id = $topic_id;

	foreach ($topic_ancestors as $topic_ancestor_name) {
		[$topic_ancestor_id] = determine_playlist_id(
			$topic_ancestor_name,
			$cache,
			$not_a_livestream,
			$not_a_livestream_date_lookup
		);

		if (
			! in_array(
				$topic_descendant_id,
				$topic_nesting[$topic_ancestor_id]['children'],
				true
			)
		) {
			$topic_nesting[$topic_ancestor_id]['children'][] =
				$topic_descendant_id;
		}

		$topic_descendant_id = $topic_ancestor_id;
	}
}

echo "\n", 'topic nesting data populated', "\n";

foreach (TopicData::VIDEO_IS_FROM_A_LIVESTREAM as $video_id) {
	$csv = get_dated_csv(
		determine_date_for_video(
			$video_id,
			$cache['playlists'],
			$api->dated_playlists()
		),
		$video_id
	);

	foreach ($csv[1] as $offset => $csv_entry) {
		if ( ! is_array($csv[2]['topics'][$offset] ?? null)) {
			continue;
		}

		foreach ($csv[2]['topics'][$offset] as $topic_name) {
			if (
				! in_array(
					determine_playlist_id(
						$topic_name,
						$cache,
						$not_a_livestream,
						$not_a_livestream_date_lookup
					)[0],
					$all_topic_ids,
					true
				)
				&& ! in_array(
					$topic_name,
					$missing_topics,
					true
				)
			) {
				$missing_topics[] = $topic_name;
			}
		}
	}
}

if (count($missing_topics)) {
	throw new RuntimeException(sprintf(
		'topics %s not already added!',
		implode(', ', $missing_topics)
	));
}

$basename_topics_nesting_ids = array_keys($topic_nesting);

$topic_nesting = array_map(
	static function (
		array $data
	) use (
		$basename_topics_nesting_ids
	) : array {
		usort(
			$data['children'],
			static function (
				string $a,
				string $b
			) use (
				$basename_topics_nesting_ids
			) : int {
				return
					(int) array_search(
						$a,
						$basename_topics_nesting_ids, true
					) - (int) array_search(
						$b,
						$basename_topics_nesting_ids, true
					);
			}
		);

		return $data;
	},
	$topic_nesting
);

$topic_nesting = array_filter(
	$topic_nesting,
	static function (string $maybe) use ($playlists) : bool {
		return ! isset($playlists[$maybe]);
	},
	ARRAY_FILTER_USE_KEY
);

$topic_nesting_roots = array_keys(array_filter(
	$topic_nesting,
	static function (array $maybe) : bool {
		return -1 === $maybe['level'];
	}
));

usort(
	$topic_nesting_roots,
	static function (
		string $a,
		string $b
	) use ($cache) : int {
		return strnatcasecmp(
			determine_topic_name($a, $cache),
			determine_topic_name($b, $cache)
		);
	}
);

$current_left = 0;

foreach ($topic_nesting_roots as $topic_id) {
	[$current_left, $topic_nesting] = adjust_nesting(
		$topic_nesting,
		$topic_id,
		$current_left,
		$global_topic_hierarchy,
		$cache
	);
}

$topics = $topic_nesting;

uasort(
	$topics,
	[$sorting, 'sort_by_nleft']
);

$topic_nesting = $topics;

file_put_contents(__DIR__ . '/topics-nested.json', json_encode(
	$topic_nesting,
	JSON_PRETTY_PRINT
));

file_put_contents(__DIR__ . '/../11ty/data/topicsNested.json', json_encode(
	array_values($topic_nesting),
	JSON_PRETTY_PRINT
));

$api->sort_playlists_by_nested_data($topic_nesting);

usort($all_topic_ids, static function (
	string $a,
	string $b
) use ($topic_nesting, $cache) : int {
	/**
	 * @var null|array{
	 *	children: list<string>,
	 *	left: positive-int,
	 *	right: positive-int,
	 *	level: int
	 * }
	 */
	$nested_a = $topic_nesting[$a] ?? null;

	/**
	 * @var null|array{
	 *	children: list<string>,
	 *	left: positive-int,
	 *	right: positive-int,
	 *	level: int
	 * }
	 */
	$nested_b = $topic_nesting[$b] ?? null;

	if ( ! isset($nested_a, $nested_b)) {
		return strnatcasecmp(
			$cache['playlists'][$a][1] ?? $a,
			$cache['playlists'][$b][1] ?? $b
		);
	}

	return
		$nested_a['left']
		- $nested_b['left'];
});

echo "\n", 'done with topic nesting', "\n";

echo "\n", 'grouping videos by topic', "\n";

$video_playlists = [];

foreach (array_keys($api->fetch_all_playlists()) as $playlist_id) {
	$video_ids = ($cache['playlists'][$playlist_id] ?? [2 => []])[2] ?? [];

	foreach ($video_ids as $video_id) {
		if ( ! isset($video_playlists[$video_id])) {
			$video_playlists[$video_id] = [];
		}

		$video_playlists[$video_id][] = $playlist_id;
	}
}

$topics_json = [];
$playlist_topic_strings = [];
$playlist_topic_strings_reverse_lookup = [];
$topic_statistics = [];

foreach ($all_topic_ids as $topic_id) {
	[$slug_string, $slug] = topic_to_slug(
		$topic_id,
		$cache,
		$global_topic_hierarchy,
		$slugify
	);

	if (
		! isset($playlists[$topic_id])
		&& !in_array($topic_id, $dated_playlists, true)
	) {
		$topics_json[$slug_string] = $slug;
	}
	$playlist_topic_strings[$topic_id] = $slug_string;
	$playlist_topic_strings_reverse_lookup[$slug_string] = $topic_id;

	if ( ! in_array($topic_id, $dated_playlists, true)) {
		$topic_video_ids = ($cache['playlists'][$topic_id] ?? [2 => []])[2] ?? [];

		usort($topic_video_ids, [$sorting, 'sort_video_ids_by_date']);

		$topic_video_ids_count = count($topic_video_ids);

		$topic_statistics['/topics/' . $slug_string . '.svg'] = [
			$topic_video_ids_count,
			(
				$topic_video_ids_count > 0
					? date('jS M, Y', strtotime(determine_date_for_video(
						current($topic_video_ids),
						$cache['playlists'],
						$dated_playlists
					)))
					: false
			),
			(
				$topic_video_ids_count > 0
					? date('jS M, Y', strtotime(determine_date_for_video(
						end($topic_video_ids),
						$cache['playlists'],
						$dated_playlists
					)))
					: false
			),
		];
	}
}

echo "\n", 'writing video & topic data', "\n";

file_put_contents(
	__DIR__ . '/../11ty/img-data/topicStatistics.json',
	json_encode_pretty(
			$topic_statistics
	)
);
file_put_contents(
	__DIR__ . '/../11ty/data/topicStrings.json',
	json_encode($playlist_topic_strings, JSON_PRETTY_PRINT)
);
file_put_contents(
	__DIR__ . '/../11ty/data/topicStrings_reverse.json',
	json_encode($playlist_topic_strings_reverse_lookup, JSON_PRETTY_PRINT)
);
file_put_contents(
	__DIR__ . '/../11ty/data/topics.json',
	json_encode($topics_json, JSON_PRETTY_PRINT)
);

$video_playlists = array_map(
	static function (array $topic_ids) use ($playlist_topic_strings) : array {
		usort(
			$topic_ids,
			static function (
				string $a,
				string $b
			) use (
				$playlist_topic_strings
			) : int {
				return strnatcasecmp(
					$playlist_topic_strings[$a],
					$playlist_topic_strings[$b],
				);
			}
		);

		return $topic_ids;
	},
	$video_playlists
);

file_put_contents(__DIR__ . '/topics-satisfactory.json', json_encode($topics_json, JSON_PRETTY_PRINT));

/** @var array<string, array<string, int>> */
$topic_slug_history = json_decode(
	file_get_contents(__DIR__ . '/topic-slug-history.json'),
	true
);

$checked = 0;

$all_video_ids = array_keys($video_playlists);

echo "\n", 'getting statistics', "\n";

$statistics = $api->getStatistics(...$all_video_ids);

echo "\n", 'done getting statistics', "\n";

/**
 * @var array<string, array{
 *	id:string,
 *	url:string,
 *	title:string,
 *	topics:array<string, string>,
 *	other_parts:string,
 *	is_replaced:string,
 *	is_duplicate:string,
 *	has_duplicates:string,
 *	transcript: list<string>
 * }>
 */
$transcripts_json = [];

usort($all_video_ids, [$sorting, 'sort_video_ids_by_date']);

$all_video_ids = array_reverse($all_video_ids);

echo "\n", 'determining what needs fresh captions', "\n";

$needs_fresh_data = prepare_uncached_captions_html_video_ids(
	$all_video_ids,
	'yes' === getenv('VCN_FRESH_CAPTIONS'),
	$injected
);

echo "\n", count($needs_fresh_data), ' fresh data needed', "\n";

if (count($needs_fresh_data)) {
	file_put_contents(
		__DIR__ . '/data/needs-fetching.json',
		json_encode_pretty(
			$needs_fresh_data
		)
	);

	echo 'fresh data needed', "\n";
	exit(1);
}

file_put_contents(
	(
		__DIR__
		. '/data/info-cards.json'
	),
	json_encode_pretty(
		array_combine(
			$all_video_ids,
			array_map(
				static function (string $video_id) use ($injected) {
					return yt_cards($video_id, $injected);
				},
				$all_video_ids
			)
		)
	)
);

file_put_contents(
	(__DIR__ . '/data/info-cards--augmented.json'),
	json_encode_pretty(
		array_map(
			static function(string $video_id) use($cache, $api, $video_playlists, $playlists, $injected) : array {
				return [
					'id' => vendor_prefixed_video_id($video_id),
					'date' => determine_date_for_video(
						vendor_prefixed_video_id($video_id),
						$cache['playlists'],
						$api->dated_playlists()
					),
					'title' => $cache['playlistItems'][$video_id][1],
					'video_url_from_id' => video_url_from_id(vendor_prefixed_video_id($video_id)),
					'cards' => yt_cards($video_id, $injected),
					'topics' => array_values(array_filter(
						$video_playlists[$video_id],
						static function (string $maybe) use ($playlists) : bool {
							return ! isset($playlists[$maybe]);
						}
					)),
				];
			},
			$all_video_ids
		)
	)
);

echo "\n",
	sprintf(
		'compiling transcription 0 of %s videos (%s seconds elapsed)',
		count($all_video_ids),
		0
	)
;

$last_compile_date = null;
$carriage_return = true;

$all_video_ids = array_reverse($all_video_ids);

/** @var array<string, string> */
$erroring = [];

/** @var int|null */
$title_unix_min = null;

/** @var int|null */
$title_unix_max = null;

$transcriptable_video_ids = $all_video_ids;

foreach (TopicData::VIDEO_IS_FROM_A_LIVESTREAM as $video_id) {
	$csv = get_dated_csv(
		determine_date_for_video(
			$video_id,
			$cache['playlists'],
			$api->dated_playlists()
		),
		$video_id
	);

	foreach ($csv[1] as $offset => $csv_entry) {
		if ( ! is_array($csv[2]['topics'][$offset] ?? null)) {
			continue;
		}

		[$start, $end] = $csv_entry;


		$transcriptable_video_ids[] = sprintf('' !== $end ? '%s,%s,%s' : '%s,%s', $video_id, $start, $end);
	}
}

usort($transcriptable_video_ids, [$sorting, 'sort_video_ids_by_date']);

foreach ($transcriptable_video_ids as $video_id) {
	++$checked;

	$current_compile_date = determine_date_for_video(
		$video_id,
		$cache['playlists'],
		$api->dated_playlists()
	);

	$current_compile_date_unix = strtotime($current_compile_date);

	if (null === $title_unix_min) {
		$title_unix_min = $title_unix_max = $current_compile_date_unix;
	}

	$title_unix_min = min($title_unix_min, $current_compile_date_unix);
	$title_unix_max = max($title_unix_max, $current_compile_date_unix);

	if ($last_compile_date !== $current_compile_date) {
		/*
		if (count($erroring)) {
			echo "\n", implode(',' . "\n", array_keys($erroring)), ',', "\n";

			throw new RuntimeException(sprintf(
				'Errored on %s videos',
				count($erroring)
			));
		}
		*/

		echo "\n\n",
			sprintf('compiling transcriptions for %s', $current_compile_date),
			"\n";

		$last_compile_date = $current_compile_date;
		$carriage_return = false;
	}

	echo($carriage_return ? "\r" : ''),
		sprintf(
			'compiling transcription %s of %s videos (%s seconds elapsed)',
			$checked,
			count($all_video_ids),
			time() - $stat_start
		)
	;

	$carriage_return = true;

	$caption_lines = [];

	try {
		$caption_lines = captions(
			$video_id,
			$playlist_topic_strings_reverse_lookup,
			$skipping,
			$injected
		);
	} catch (ErrorException $e) {
		if (
			false !== mb_strpos($e->getMessage(), 'failed to open stream: HTTP request failed! HTTP/1.0 404 Not Found')
			|| false !== mb_strpos($e->getMessage(), 'Failed to open stream: HTTP request failed! HTTP/1.1 404 Not Found')
		) {
			$erroring[$video_id] = $e->getMessage();

			continue;
		}
		throw $e;
	}

	if (in_array($video_id, $skipping->video_ids, true)) {
		continue;
	}

	if (count($caption_lines) < 1) {
		$skipping->video_ids[] = $video_id;

		continue;
	}

	$date = determine_date_for_video(
		$video_id,
		$cache['playlists'],
		$api->dated_playlists()
	);

	$vendor_prefixed_video_id = vendor_prefixed_video_id($video_id);

	if (
		isset($statistics[$vendor_prefixed_video_id])
		&& false === $statistics[$vendor_prefixed_video_id]
	) {
		throw new UnexpectedValueException(sprintf(
			'No like count available for %s',
			$vendor_prefixed_video_id
		));
	}

	$transcripts_json[$video_id] = [
		'id' => $vendor_prefixed_video_id,
		'url' => video_url_from_id($video_id, true),
		'date' => $date,
		'dateTitle' => determine_playlist_id(
			$date,
			$cache,
			$not_a_livestream,
			$not_a_livestream_date_lookup
		)[1],
		'title' => $injected->determine_video_title($video_id, true),
		'description' => $injected->determine_video_description($video_id),
		'topics' => array_values(array_filter(
			$injected->determine_video_topics($video_id),
			static function (string $maybe) use ($playlists) : bool {
				return ! isset($playlists[$maybe]);
			}
		)),
		'other_parts' => (
			$jsonify->content_if_video_has_other_parts($video_id)
		),
		'is_replaced' => (
			$jsonify->content_if_video_is_replaced($video_id)
		),
		'is_duplicate' => (
			$jsonify->content_if_video_is_a_duplicate($video_id)
		),
		'has_duplicates' => (
			$jsonify->content_if_video_has_duplicates($video_id)
		),
		'seealsos' => $jsonify->content_if_video_has_seealsos($video_id),
		'transcript' => maybe_dehesitate($video_id, ...array_map(
			static function (string $line) : string {
				return str_replace(
					'](/topics/',
					'](../topics/',
					$line
				);
			},
			$caption_lines
		)),
		'like_count' => (int) (
			(($statistics[$vendor_prefixed_video_id] ?? []) ?: [])['likeCount'] ?? 0
		),
		'video_object' => null,
	];

	/** @var string|null */
	$thumbnail_url = null;

	if (preg_match('/^yt-/', $vendor_prefixed_video_id)) {
		$thumbnail_url = sprintf(
			'https://img.youtube.com/vi/%s/hqdefault.jpg',
			preg_replace('/,.*$/', '', mb_substr($video_id, 3))
		);
	}

	if (
		null !== $thumbnail_url
		&& null !== $transcripts_json[$video_id]['description']
	) {
		$transcripts_json[$video_id]['video_object'] = [
			'@type' => 'VideoObject',
			'name' => $transcripts_json[$video_id]['title'],
			'description' => $injected->determine_video_description($video_id, false),
			'thumbnailUrl' => $thumbnail_url,
			'contentUrl' => timestamp_link($video_id, -1),
			'uploadDate' => determine_date_for_video(
				$video_id,
				$cache['playlists'],
				$api->dated_playlists()
			),
		];
		$transcripts_json[$video_id]['video_object'] = [
			'@context' => 'https://schema.org',
			'@type' => 'WebPage',
			'name' => $transcripts_json[$video_id]['title'],
			'url' => [
				sprintf(
					'https://archive.satisfactory.video/transcriptions/%s/',
					$vendor_prefixed_video_id
				),
			],
			'about' => [
				$transcripts_json[$video_id]['video_object'],
			],
		];

		if ('' !== $transcripts_json[$video_id]['description']) {
			$transcripts_json[$video_id]['video_object']['description'] = $transcripts_json[$video_id]['description'];
		}
	}

	usleep(1);
}

if (count($erroring)) {
	echo "\n", implode(',' . "\n", array_keys($erroring)), ',', "\n";

	file_put_contents(__DIR__ . '/data/erroring-on-transcriptions.json', json_encode_pretty(
		$erroring
	));
}

file_put_contents(
	(
		__DIR__
		. '/../11ty/data/transcriptions.json'
	),
	json_encode_pretty(
		array_values($transcripts_json)
	)
);

echo "\n",
	sprintf(
		'processing %s of %s transcriptions (%s seconds elapsed)',
		$checked,
		count($all_video_ids),
		time() - $stat_start
	)
;

$checked = 0;

$transcripts_json_count = count($transcripts_json);

foreach (array_keys($transcripts_json) as $video_id) {
	$video_data = $transcripts_json[$video_id];

	++$checked;

	echo "\r",
		sprintf(
			'processing %s of %s transcriptions (%s seconds elapsed)',
			$checked,
			$transcripts_json_count,
			time() - $stat_start
		)
	;

	$maybe_playlist_id = array_values(array_filter(
		(
			(
				$video_playlists[$video_id] ?? $video_playlists[preg_replace('/^yt-/', '', preg_replace('/,.+$/', '', $video_id))]
			)
		),
		static function (string $maybe) use ($playlists, $video_id) : bool {
			if (
				in_array(
					$video_id,
					[
						'V_YXOp7VQqc',
						'6xKMiQJdZxg',
					],
					true,
				)
				&& 'PLbjDnnBIxiEqJudZvNZcnhrq0tQG_JSBY' === $maybe
			) {
				return false;
			}

			return isset($playlists[$maybe]);
		}
	));

	if (count($maybe_playlist_id) > 1) {
		throw new RuntimeException(
			'Video found on multiple dates!'
		);
	} elseif (count($maybe_playlist_id) < 1) {
		$normalised = vendor_prefixed_video_id(preg_replace(
			'/^yt-([^,]+).*/',
			'$1',
			vendor_prefixed_video_id($video_id)
		));

		$maybe_playlist_id = array_keys(array_filter(
			$externals_values,
			static function (array $data) use ($normalised) : bool {
				return count(array_filter(
					$data,
					static function (
						array $maybe
					) use (
						$normalised
					) : bool {
						return $normalised === $maybe[0];
					}
				)) > 0;
			}
		));

		if (1 !== count($maybe_playlist_id)) {
			throw new RuntimeException(sprintf(
				'Video found on no dates! (%s)',
				$video_id
			));
		}
	}

	$transcript_topic_strings = array_filter(
		(
			(
				$video_playlists[$video_id] ?? $video_playlists[preg_replace('/^yt-/', '', preg_replace('/,.+$/', '', $video_id))]
			)
		),
		static function (
			string $playlist_id
		) use (
			$playlist_topic_strings,
			$playlists
		) : bool {
			return
				! isset($playlists[$playlist_id])
				&& isset(
				$playlist_topic_strings[
					$playlist_id
				]
			);
		}
	);

	usort(
		$transcript_topic_strings,
		static function (
			string $a,
			string $b
		) use (
			$playlist_topic_strings
		) : int {
			return strnatcasecmp(
				$playlist_topic_strings[$a],
				$playlist_topic_strings[$b],
			);
		}
	);

	unset(
		$transcripts_json[$video_id],
		$video_data,
	);
}

unset($transcripts_json);

echo "\n";

$skipping->sync($sorting, $injected);

echo sprintf(
		'%s subtitles checked of %s videos cached',
		$checked,
		count($all_video_ids)
	),
	"\n"
;

foreach (get_externals() as $date => $externals_data_groups) {
	if ( ! isset($grouped_dated_data_for_json[$date])) {
		$grouped_dated_data_for_json[$date] = [
			'date' => $date,
			'date_friendly' => date('F jS, Y', (int) strtotime($date)),
			'externals' => [],
			'internals' => [],
		];
	}

	foreach ($externals_data_groups as $externals_data) {
		[$video_id, $externals_csv, $data_for_external] = $externals_data;

		$externals_for_json = [
			'title' => $data_for_external['title'],
			'embed_data' => [],
			'contentUrl' => timestamp_link($video_id, -1),
			'thumbnail' => false,
		];

		if (preg_match('/^yt-/', vendor_prefixed_video_id($video_id))) {
			$externals_for_json['thumbnail'] = sprintf(
				'https://img.youtube.com/vi/%s/hqdefault.jpg',
				preg_replace('/,.*$/', '', mb_substr($video_id, 3))
			);
		}

		$captions = raw_captions($video_id, $skipping, $injected);

		/**
		 * @var list<array{
		 *	0:numeric-string|'',
		 *	1:numeric-string|'',
		 *	2:string,
		 *	3?:bool
		 * }>
		 */
		$captions_with_start_time = [];

		if (
			array_key_exists(0, $captions)
			&& array_key_exists(1, $captions)
			&& null === $captions[0]
		) {
			/**
			 * @var array{0:null, 1:list<array{
			 *	text:string|list<string|array{text:string, about?:string}>,
			 *	startTime:string,
			 *	endTime:string,
			 *	speaker?:list<string>,
			 *	followsOnFromPrevious?:bool,
			 *	webvtt?: array{
			 *		position?:positive-int,
			 *		line?:int,
			 *		size?:positive-int,
			 *		align?:'start'|'middle'|'end'
			 *	}
			 * }>}
			 */
			$captions = $captions;

			$captions_with_start_time = array_map(
				/**
				 * @param array{
				 *	text:string|array,
				 *	startTime:string,
				 *	endTime:string,
				 *	followsOnFromPrevious?:bool,
				 * } $caption_line
				 *
				 * @return array{
				 *	0:numeric-string,
				 *	1:numeric-string,
				 *	2:string,
				 *	3:bool
				 * }
				 */
				static function (array $caption_line) : array {
					if (is_array($caption_line['text'])) {
						throw new RuntimeException(
							'JSON transcription not yet supported here!'
						);
					}

					/**
					 * @var array{
					 *	0:numeric-string,
					 *	1:numeric-string,
					 *	2:string,
					 *	3:bool
					 * }
					 */
					return [
						mb_substr($caption_line['startTime'], 2, -1),
						mb_substr($caption_line['endTime'], 2, -1),
						$caption_line['text'],
						$caption_line['followsOnFromPrevious'] ?? false,
					];
				},
				$captions[1]
			);
		} else {
			/** @var list<SimpleXmlElement> */
			$lines = ($captions[1] ?? []);

			foreach ($lines as $caption_line) {
				$attrs = iterator_to_array(
					$caption_line->attributes()
				);

				/** @var array{0:numeric-string, 1:numeric-string, 2:string} */
				$captions_with_start_time_row = [
					(string) $attrs['start'],
					(string) $attrs['dur'],
					(string) preg_replace_callback(
						'/&#(\d+);/',
						static function (array $match) : string {
							return chr((int) $match[1]);
						},
						(string) $caption_line
					),
				];

				$captions_with_start_time[] = $captions_with_start_time_row;
			}
		}

		$csv_captions = array_map(
			/**
			 * @param array{
			 *	0:numeric-string|'',
			 *	1:numeric-string|'',
			 *	2:string
			 * } $csv_line
			 *
			 * @return array{
			 *	0:numeric-string|'',
			 *	1:numeric-string|'',
			 *	2:string,
			 *	3:string
			 * }
			 */
			static function (array $csv_line) use ($captions_with_start_time) : array {
				$csv_line_captions = trim(implode("\n", array_reduce(
					array_filter(
						$captions_with_start_time,
						static function (array $maybe) use ($csv_line) : bool {
							[$start, $end] = $csv_line;

							$start = (float) $start;

							$from = (float) $maybe[0];
							$to = $from + (float) $maybe[1];

							if ('' === $end) {
								return $from >= $start;
							}

							return $from >= $start && $to <= (float) $end;
						}
					),
					/**
					 * @param non-empty-list<string> $out
					 * @param array{2:string, 3?:bool} $caption_line
					 *
					 * @return non-empty-list<string>
					 */
					static function (array $out, array $caption_line) : array {
						$follows_on_from_previous = $caption_line[3] ?? false;

						if ($follows_on_from_previous) {
							$out[] = preg_replace(
								'/\s+/',
								' ',
								array_pop($out) . ' ' . $caption_line[2]
							);
						} else {
							$out[] = $caption_line[2];
						}

						return $out;
					},
					['']
				)));

				$csv_line[3] = $csv_line_captions;

				return $csv_line;
			},
			array_filter(
				$externals_csv,
				static function (int $k) use ($data_for_external) : bool {
					return false !== ($data_for_external['topics'][$k] ?? false);
				},
				ARRAY_FILTER_USE_KEY
			)
		);

		foreach ($externals_csv as $i => $line) {
			[$start, $end, $clip_title] = $line;
			$clip_id = sprintf(
				'%s,%s',
				vendor_prefixed_video_id($video_id),
				$start . ('' === $end ? '' : (',' . $end))
			);

			assert(
				is_numeric($line[0]) || '' === $line[0],
				new UnexpectedValueException(sprintf(
					'non-numeric, non-null value found (%s)!',
					var_export($line[0], true)
				))
			);

			assert(
				is_numeric($line[1]) || '' === $line[1],
				new UnexpectedValueException(sprintf(
					'non-numeric, non-null value found (%s)!',
					var_export($line[1], true)
				))
			);

			/** @var numeric-string|null */
			$embed_link_line_0 = '' === $line[0] ? null : $line[0];

			/** @var numeric-string|null */
			$embed_link_line_1 = '' === $line[1] ? null : $line[1];

			/** @var numeric-string */
			$start = ($start ?: '0.0');

			$decimals = mb_strlen(explode('.', $start)[1] ?? '');

			$start_hours = str_pad((string) floor(((float) $start) / 3600), 2, '0', STR_PAD_LEFT);
			$start_minutes = str_pad((string) floor((float) bcdiv(bcmod($start, '3600', $decimals) ?? '0', '60', $decimals)), 2, '0', STR_PAD_LEFT);
			$start_seconds = str_pad((string) floor((float) bcmod($start, '60', $decimals)), 2, '0', STR_PAD_LEFT);

			$embed_data = [
				'autoplay' => 1,
				'start' => floor((float) ($start ?: '0')),
				'end' => $end,
				'title' => $clip_title,
				'has_captions' => false,
				'started_formated' => sprintf(
					'%s:%s:%s',
					$start_hours,
					$start_minutes,
					$start_seconds
				),
				'link' => (
					(
						is_array($data_for_external['topics'][$i] ?? null)
						&& preg_match('/^ts\-\d+$/', $video_id)
						&& '' !== $end
					)
						? embed_link(
							$video_id,
							$embed_link_line_0,
							$embed_link_line_1
						)
						: timestamp_link($video_id, $start)
				),
				'timestamp_link' => timestamp_link($video_id, $start),
				'video_url_from_id' => video_url_from_id(vendor_prefixed_video_id($video_id)),
				'topics' => $data_for_external['topics'][$i] ?? [],
			];

			if ('' === $embed_data['end']) {
				unset($embed_data['end']);
			} else {
				$embed_data['end'] = ceil((float) $embed_data['end']);
			}

			if (
				isset($csv_captions[$i])
				&& '' !== trim($csv_captions[$i][3])
				&& captions_json_cache_exists($clip_id)
			) {
				$embed_data['has_captions'] = sprintf(
					'../transcriptions/%s',
					sprintf(
						'%s/',
						$clip_id
					)
				);
			}

			$externals_for_json['embed_data'][$i] = $embed_data;
		}

		$grouped_dated_data_for_json[$date]['externals'][] = $externals_for_json;
	}
}

foreach (array_keys($playlists) as $playlist_id) {
	if (in_array($playlist_id, $externals_dates, true)) {
		continue;
	}

	$video_ids = ($cache['playlists'][$playlist_id] ?? [2 => []])[2];
	$video_ids = filter_video_ids_for_legacy_alts($cache, ...$video_ids);

	$content_arrays = [
		'Related answer clips' => [],
		'Single video clips' => [],
	];

	$title_unix = (int) strtotime($playlists[$playlist_id]);

	if (null === $title_unix_min) {
		$title_unix_min = $title_unix_max = $title_unix;
	}

	$title_unix_min = min($title_unix_min, $title_unix);
	$title_unix_max = max($title_unix_max, $title_unix);

	$title = $injected->friendly_dated_playlist_name(
		$playlist_id,
		'Livestream clips (non-exhaustive)'
	);

	$data_for_dated_json = [
		'title' => $title,
		'categorised' => [],
		'uncategorised' => [],
	];

	if (preg_match('/^PLbjDnnBIxiE/', $playlist_id)) {
		$data_for_dated_json['contentUrl'] = sprintf(
			'https://www.youtube.com/playlist?list=%s',
			rawurlencode($playlist_id)
		);
	}

	/**
	 * @var array<string, array{
	 *	children: list<string>,
	 *	videos: list<string>,
	 *	left: positive-int,
	 *	right: positive-int,
	 *	level: int
	 * }>
	 */
	$topics_for_date = [];

	if (count($video_ids) > 0) {
		$topics_for_date = filter_nested(
			$playlist_id,
			$topic_nesting,
			$cache,
			$global_topic_hierarchy,
			...$video_ids
		);
	}

	$nested_video_ids = array_unique(array_reduce(
		$topics_for_date,
		/**
		 * @param list<string> $out
		 * @param array{videos:list<string>} $data
		 *
		 * @return list<string>
		 */
		static function (array $out, array $data) : array {
			foreach ($data['videos'] as $video_id) {
				if ( ! in_array($video_id, $out, true)) {
					$out[] = $video_id;
				}
			}

			return $out;
		},
		[]
	));

	$content_arrays['Single video clips'] = array_diff(
		$video_ids,
		$nested_video_ids
	);

	/** @var array<string, array{0:string, 1:array<string, string>}> */
	$related_answer_clips = [];

	foreach ($topics_for_date as $topic_id => $data) {
		$title = determine_topic_name($topic_id, $cache);
		$related_answer_clips[$title] = [
			$topic_id,
			$data['videos'],
		];
	}

	$content_arrays['Related answer clips'] = $related_answer_clips;

	foreach ($content_arrays['Related answer clips'] as $data) {
		[$topic_id, $video_data_ids] = $data;

		$data_for_dated_json['categorised'][$topic_id] = [
			'title' => determine_topic_name($topic_id, $cache),
			'depth' => $topics_for_date[$topic_id]['level'],
			'slug' => topic_to_slug(
				$topic_id,
				$cache,
				$global_topic_hierarchy,
				$slugify
			)[0],
			'clips' => array_map(
				static function (string $video_id) use ($cache) : array {
					return maybe_transcript_link_and_video_url_data(
						$video_id,
						$cache['playlistItems'][$video_id][1]
					);
				},
				$video_data_ids
			),
		];
	}

	$data_for_dated_json['categorised'] = array_values(
		$data_for_dated_json['categorised']
	);

	if (count($content_arrays['Single video clips']) > 0) {
		$data_for_dated_json['uncategorised'] = [
			'title' => 'Uncategorised',
			'clips' => array_values(array_map(
				static function (string $video_id) use ($cache) : array {
					return maybe_transcript_link_and_video_url_data(
						$video_id,
						$cache['playlistItems'][$video_id][1]
					);
				},
				$content_arrays['Single video clips']
			)),
		];
	}

	if ( ! isset($grouped_dated_data_for_json[
		date('Y-m-d', $title_unix)
	])) {
		$grouped_dated_data_for_json[
			date('Y-m-d', $title_unix)
		] = [
			'date' => date('Y-m-d', $title_unix),
			'date_friendly' => date('F jS, Y', $title_unix),
			'externals' => [],
			'internals' => [],
		];
	}

	$grouped_dated_data_for_json[
		date('Y-m-d', $title_unix)
	]['internals'][] = $data_for_dated_json;
}

$grouped_dated_data_for_json_alt_layout = [];
$transcription_data_for_json_alt_layout = [];

foreach ($process_externals_result['externals_needing_alt_layout'] as $date => $externals_needing_alt_layout) {
	$grouped_dated_data_for_json_alt_layout[$date] = $grouped_dated_data_for_json[$date];
	$grouped_dated_data_for_json_alt_layout[$date]['externals'] = $externals_needing_alt_layout;

	foreach ($externals_needing_alt_layout as $i => $maybe_remap) {
		$grouped_dated_data_for_json_alt_layout[$date]['externals'][$i]['sections'] = array_map(
			static function (array|VideoSection $maybe_convert) : array {
				if ($maybe_convert instanceof VideoSection) {
					return $maybe_convert->jsonSerialize();
				}

				return $maybe_convert;
			},
			$maybe_remap['sections']
		);
	}
}

foreach (TopicData::VIDEO_IS_FROM_A_LIVESTREAM as $video_id) {
	$date = determine_date_for_video(
		$video_id,
		$cache['playlists'],
		$dated_playlists
	);

	$inject = [
		'externals_needing_alt_layout' => [],
		'playlists' => [],
		'playlistItems' => [],
		'videoTags' => [],
	];

	$externals_data = get_dated_csv($date, $video_id);

	[
		,
		,
		$embed_data_set,
	] = process_dated_csv(
		$date,
		$inject,
		$externals_data,
		$cache,
		$not_a_livestream,
		$not_a_livestream_date_lookup,
		$skipping,
		$injected
	);

	$transcription_data_for_json_alt_layout[$video_id] = process_dated_csv_for_alt_layout(
		$date,
		$externals_data,
		$embed_data_set,
		$injected
	);
	$transcription_data_for_json_alt_layout[$video_id]['id'] = vendor_prefixed_video_id($video_id);
	$transcription_data_for_json_alt_layout[$video_id]['url'] = video_url_from_id($video_id, true);

	foreach ($transcription_data_for_json_alt_layout[$video_id]['sections'] as $offset => $section) {
		if ($section instanceof VideoSection) {
			$transcription_data_for_json_alt_layout[$video_id]['sections'][$offset] = $section->jsonSerialize();
		}
	}
}

file_put_contents(__DIR__ . '/../11ty/data/dated_alt.json', json_encode(
	array_values($grouped_dated_data_for_json_alt_layout),
	JSON_PRETTY_PRINT
));
file_put_contents(__DIR__ . '/../11ty/data/transcriptions_alt.json', json_encode(
	array_values($transcription_data_for_json_alt_layout),
	JSON_PRETTY_PRINT
));
file_put_contents(__DIR__ . '/../11ty/data/dated.json', json_encode(
	array_values($grouped_dated_data_for_json),
	JSON_PRETTY_PRINT
));
file_put_contents(__DIR__ . '/../11ty/data/friendly_dates.json', json_encode_pretty(array_reduce(
	$all_video_ids,
	static function (array $was, string $video_id) use ($cache, $dated_playlists) {
		$date = determine_date_for_video(
			$video_id,
			$cache['playlists'],
			$dated_playlists
		);

		if ( ! isset($was[$date])) {
			$was[$date] = date('F jS, Y', strtotime($date));
		}

		return $was;
	},
	[]
)));

if (null === $title_unix_min || null === $title_unix_max) {
	throw new RuntimeException('No min/max dates!');
}

$date = new DateTime(date('Y-m-d', $title_unix_min));
$date->setDate(
	(int) $date->format('Y'),
	(int) $date->format('n'),
	1
);
$end = new DateTime(date('Y-m-d', $title_unix_max));
$end->setDate(
	(int) $end->format('Y'),
	(int) $end->format('n'),
	(int) $end->format('t')
);
$end = $end->getTimestamp();

/**
 * @var array<
 *	int,
 *	array<
 *		'January'|'February'|'March'|'April'|'May'|'June'|'July'|'August'|'September'|'October'|'November'|'December',
 *		list<array{
 *	 		0:array{0:numeric-string, 1:false},
 *	 		1:array{0:numeric-string, 1:false},
 *	 		2:array{0:numeric-string, 1:false},
 *	 		3:array{0:numeric-string, 1:false},
 *	 		4:array{0:numeric-string, 1:false},
 *	 		5:array{0:numeric-string, 1:false},
 *	 		6:array{0:numeric-string, 1:false}
 *		}>
 * >>
 */
$grouped_dated_data_for_index_json = [];

$reset = false;

while ($date->getTimestamp() <= $end) {
	$date_initial_Y = $date->format('Y');
	$date_initial_month = $date->format('F');
	$date_initial_Ym = $date->format('Y-m');

	if ( ! isset($grouped_dated_data_for_index_json[$date_initial_Y])) {
		$grouped_dated_data_for_index_json[$date_initial_Y] = [];
	}
	if ( ! isset($grouped_dated_data_for_index_json[$date_initial_Y][$date_initial_month])) {
		$grouped_dated_data_for_index_json[$date_initial_Y][$date_initial_month] = [];
	}

	while ('1' !== $date->format('N')) {
		$date->modify('-1 day');
	}

	$j = (int) $date->format('j');

	if ( ! $reset && $j > 1 && $j <= 7) {
		$date->modify('-1 week');

		$reset = true;
	} elseif ($reset && $j > 7) {
		$reset = false;
	}

	$date_row = [];
	$date_row_Ymd = $date->format('Y-m-d');

	for ($i = 0; $i < 7; ++$i) {
		$date_Ymd = $date->format('Y-m-d');

		$date_row[$date_Ymd] = [
			$date->format('j'),
			$date->format('Y-m') === $date_initial_Ym && isset(
				$grouped_dated_data_for_json[$date_Ymd]
			),
		];
		$date->modify('+1 day');
		$date->setTime(0, 0, 0);
	}

	$grouped_dated_data_for_index_json[$date_initial_Y][$date_initial_month][
		$date_row_Ymd
	] = $date_row;
}

krsort($grouped_dated_data_for_index_json);

file_put_contents(
	__DIR__ . '/../11ty/data/indexDated.json',
	json_encode_pretty($grouped_dated_data_for_index_json)
);

file_put_contents(
	__DIR__ . '/../11ty/data/indexDatedKeys.json',
	json_encode_pretty(array_keys($grouped_dated_data_for_index_json))
);

$now = time();

foreach ($playlist_topic_strings_reverse_lookup as $slug_string => $topic_id) {
	if ( ! isset($topics_json[$slug_string])) {
		continue;
	}

	if ( ! isset($topic_slug_history[$topic_id])) {
		$topic_slug_history[$topic_id] = [];
	}

	if ( ! isset($topic_slug_history[$topic_id][$slug_string])) {
		$topic_slug_history[$topic_id][$slug_string] = $now;
	}
}

$topic_slug_history = array_map(
	static function (array $to_sort) : array {
		asort($to_sort);

		return $to_sort;
	},
	$topic_slug_history
);

file_put_contents(__DIR__ . '/topic-slug-history.json', json_encode_pretty(
	$topic_slug_history
) . "\n");

usleep(100);

$data = $api->dated_playlists();

$data_by_date = [];

$playlists_by_date = [];

foreach ($api->dated_playlists() as $playlist_id => $date) {
	$unix = strtotime($date);
	$readable_date = date('F jS, Y', $unix);

	$data_by_date[$playlist_id] = [$unix, $readable_date];

	$playlists_by_date[$playlist_id] = ($cache['playlists'][$playlist_id] ?? [2 => []])[2] ?? [];
}

uksort(
	$playlists_by_date,
	static function (string $a, string $b) use ($data_by_date) : int {
		$sort = $data_by_date[$b][0] <=> $data_by_date[$a][0];

		if (0 === $sort) {
			$is_date_a = preg_match('/^\d{4,}\-\d{2}\-\d{2}$/', $a);
			$is_date_b = preg_match('/^\d{4,}\-\d{2}\-\d{2}$/', $b);

			if ($is_date_a !== $is_date_b) {
				return $is_date_a ? 1 : -1;
			}
		}

		return $sort;
	}
);

echo 'rebuilding index', "\n";

$grouped = [];

/** @var array<string, array{0:int, 1:int}> */
$total_statistics = [];

$sortable = [];

foreach ($playlists as $playlist_date) {
	$unix = strtotime($playlist_date);
	$year = (int) date('Y', $unix);
	$readable_month = date('F', $unix);
	$readable_date = date('F jS', $unix);

	if ( ! isset($grouped[$year])) {
		$grouped[$year] = [];
		$sortable[$year] = [];
	}

	if ( ! isset($grouped[$year][$readable_month])) {
		$grouped[$year][$readable_month] = [];
		$sortable[$year][$readable_month] = strtotime(date('Y-m-01', $unix));
	}

	$grouped[$year][$readable_month][] = [$readable_date, $playlist_date, $unix];

	$total_statistics[date('Y-m-d', $unix)] = [
		0,
		0,
	];
}

ksort($total_statistics);

$sortable = array_map(
	/**
	 * @param array<string, int> $year
	 */
	static function (array $year) : array {
		uasort($year, static function (int $a, int $b) : int {
			return $b - $a;
		});

		return $year;
	},
	$sortable
);

$past_first = count($sortable);

/**
 * @var array<
 *	string,
 *	array{
 *		0:array<string, string>,
 *		1:array<string, array{0:int, 1:int}>
 *	}
 * >
 */
$topic_statistics = [];

$undated_topic_ids = array_filter(
	$all_topic_ids,
	static function (string $maybe) use ($dated_playlists) : bool {
		return ! isset($dated_playlists[$maybe]);
	}
);

usort($undated_topic_ids, static function (
	string $a,
	string $b
) use ($topic_nesting, $cache) : int {
	/**
	 * @var null|array{
	 *	children: list<string>,
	 *	left: positive-int,
	 *	right: positive-int,
	 *	level: int
	 * }
	 */
	$nested_a = $topic_nesting[$a] ?? null;

	/**
	 * @var null|array{
	 *	children: list<string>,
	 *	left: positive-int,
	 *	right: positive-int,
	 *	level: int
	 * }
	 */
	$nested_b = $topic_nesting[$b] ?? null;

	if ( ! isset($nested_a, $nested_b)) {
		return strnatcasecmp(
			$cache['playlists'][$a][1] ?? $a,
			$cache['playlists'][$b][1] ?? $b
		);
	}

	return
		$nested_a['left']
		- $nested_b['left'];
});

foreach ($undated_topic_ids as $topic_id) {
	[$slug_string] = topic_to_slug(
		$topic_id,
		$cache,
		$global_topic_hierarchy,
		$slugify
	);

	$slug_breadcrumbs = [];

	$sub_slugs = [];

	foreach (explode('/', $slug_string) as $sub_slug) {
		$sub_slugs[] = $sub_slug;

		$sub_slug_string = implode('/', $sub_slugs);

		if ( ! isset($playlist_topic_strings_reverse_lookup[$sub_slug_string])) {
			throw new UnexpectedValueException(sprintf('Could not find topic id %s', $sub_slug_string));
		}

		$slug_breadcrumbs[$sub_slug_string] = determine_topic_name(
			$playlist_topic_strings_reverse_lookup[$sub_slug_string],
			$cache
		);
	}

	$topic_statistics[$topic_id] = [
		$slug_breadcrumbs,
		array_combine(
			array_keys($total_statistics),
			array_values($total_statistics)
		),
	];
}

foreach (filter_video_ids_for_legacy_alts($cache, ...$all_video_ids) as $video_id) {
	$date = date('Y-m-d', strtotime(determine_date_for_video(
		$video_id,
		$cache['playlists'],
		$dated_playlists
	)));

	if ( ! isset($total_statistics[$date])) {
		throw new UnexpectedValueException(sprintf(
			'Video found without prefilled statistics date! (%s, %s)',
			$video_id,
			$date
		));
	}

	++$total_statistics[$date][0];

	$is_question = false;

	if ($questions->string_is_probably_question($cache['playlistItems'][$video_id][1])) {
		$is_question = true;

		++$total_statistics[$date][1];
	}

	foreach ($video_playlists[$video_id] as $topic_id) {
		if (isset($topic_statistics[$topic_id])) {
			++$topic_statistics[$topic_id][1][$date][0];

			if ($is_question) {
				++$topic_statistics[$topic_id][1][$date][1];
			}
		}
	}
}

file_put_contents(
	__DIR__ . '/data/dated-video-statistics.json',
	json_encode_pretty($total_statistics)
);

file_put_contents(
	__DIR__ . '/data/dated-topic-statistics.json',
	json_encode_pretty($topic_statistics)
);

file_put_contents(
	__DIR__ . '/../src/data/dated-topic-statistics.json',
	json_encode($topic_statistics)
);

echo sprintf('completed in %s seconds', time() - $stat_start), "\n";
