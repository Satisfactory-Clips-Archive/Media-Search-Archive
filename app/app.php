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
use function array_slice;
use function array_unique;
use function array_values;
use function asort;
use function basename;
use function chr;
use function count;
use function date;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function hash;
use function hash_file;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;
use const JSON_PRETTY_PRINT;
use function mb_substr;
use function min;
use function mkdir;
use const PHP_EOL;
use function preg_match;
use function realpath;
use RuntimeException;
use function sprintf;
use const STR_PAD_LEFT;
use function str_repeat;
use function str_replace;
use function strnatcasecmp;
use function strtotime;
use function time;
use function touch;
use function uasort;
use function uksort;
use function usleep;
use function usort;

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/global-topic-hierarchy.php');

$stat_start = time();

$api = new YouTubeApiWrapper();

$slugify = new Slugify();

$injected = new Injected($api, $slugify);

$markdownify = new Markdownify($injected);
$questions = new Questions($injected);
$jsonify = new Jsonify($injected, $questions);

$cache = $injected->cache;
$global_topic_hierarchy = $injected->topics_hierarchy;
file_put_contents(
	__DIR__ . '/data/play.json',
	str_replace(
		PHP_EOL,
		"\n",
		json_encode(
			$injected->format_play(),
			JSON_PRETTY_PRINT
		)
	)
);

$playlist_satisfactory =
	realpath(
		__DIR__
		. '/playlists/youtube.json'
	);

if ( ! is_string($playlist_satisfactory)) {
	throw new RuntimeException('Satisfactory playlist not found!');
}

/** @var array<string, string> */
$playlists = array_map(
	static function (string $filename) : string {
		return
			__DIR__
			. '/../video-clip-notes/docs/'
			. $filename
			. '.md'
		;
	},
	$api->dated_playlists()
);

foreach ($playlists as $playlist_path) {
	if ( ! is_file($playlist_path)) {
		touch($playlist_path);
	}
}

$playlists = array_map(
	'realpath',
	$playlists
);

asort($playlists);

$playlists = array_reverse($playlists);

/** @var array<string, list<array{0:string, 1:int}>> */
$playlist_history = json_decode(
	file_get_contents(__DIR__ . '/playlist-date-history.json'),
	true
);

foreach ($playlists as $playlist_id => $path) {
	if ( ! is_string($path)) {
		throw new RuntimeException(sprintf(
			'Invalid path? %s',
			$playlist_id
		));
	}

	$playlist_date = mb_substr(basename($path), 0, -3);

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

/** @var array<string, string> */
$playlists = $playlists;

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

		$captions = raw_captions($video_id);

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
					return
						isset($data_for_external['skip'][$k])
							? ( ! $data_for_external['skip'][$k])
							: (false !== ($data_for_external['topics'][$k] ?? false));
				},
				ARRAY_FILTER_USE_KEY
			)
		);

		foreach ($externals_csv as $i => $line) {
			[$start, $end, $clip_title] = $line;
			$clip_id = sprintf(
				'%s,%s',
				$video_id,
				$start . ('' === $end ? '' : (',' . $end))
			);

			$start = ($start ?: '0.0');

			$start_hours = str_pad((string) floor(((float) $start) / 3600), 2, '0', STR_PAD_LEFT);
			$start_minutes = str_pad((string) floor((((float) $start) % 3600) / 60), 2, '0', STR_PAD_LEFT);
			$start_seconds = str_pad((string) (((float) $start) % 60), 2, '0', STR_PAD_LEFT);

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
							$line[0],
							$line[1]
						)
						: timestamp_link($video_id, $start)
				),
			];

			if ('' === $embed_data['end']) {
				unset($embed_data['end']);
			} else {
				$embed_data['end'] = ceil((float) $embed_data['end']);
			}

			if (
				isset($csv_captions[$i])
				&& '' !== trim($csv_captions[$i][3])
				&& is_file(
					__DIR__
					. '/../video-clip-notes/docs/transcriptions/'
					. $clip_id
					. '.md'
				)
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

process_externals(
	$cache,
	$global_topic_hierarchy,
	$not_a_livestream,
	$not_a_livestream_date_lookup,
	$slugify
);
$externals_values = get_externals();
$externals_dates = array_keys($externals_values);

$sorting = new Sorting($cache);

$sorting->cache = $cache;
$sorting->playlists_date_ref = $api->dated_playlists();

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

$all_topic_ids = array_merge(
	array_keys($cache['playlists']),
	array_keys($cache['stubPlaylists'] ?? [])
);

$topics_without_direct_content = array_filter(
	$all_topic_ids,
	static function (string $topic_id) use ($cache) {
		return count($cache['playlists'][$topic_id] ?? []) < 1;
	}
);

/**
 * @var array{satisfactory: array<string, array{
 *	children: list<string>,
 *	left: positive-int,
 *	right: positive-int,
 *	level: int
 * }>}
 */
$topic_nesting = [];

foreach ($all_topic_ids as $topic_id) {
	$topic_nesting[$topic_id] = [
		'children' => [],
		'left' => -1,
		'right' => -1,
		'level' => -1,
	];
}

foreach ($global_topic_hierarchy as $topic_id => $topic_ancestors) {
	if ( ! isset($topic_nesting[$topic_id])) {
		throw new RuntimeException(sprintf(
			'topic %s not already added!',
			$topic_id
		));
	}

	$topic_nesting[$topic_id]['level'] = count($topic_ancestors);

	$topic_ancestors = array_filter($topic_ancestors, 'is_string');

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

foreach ($all_topic_ids as $topic_id) {
	[$slug_string, $slug] = topic_to_slug(
		$topic_id,
		$cache,
		$global_topic_hierarchy,
		$slugify
	);

	if ( ! isset($playlists[$topic_id])) {
		$topics_json[$slug_string] = $slug;
	}
	$playlist_topic_strings[$topic_id] = $slug_string;
	$playlist_topic_strings_reverse_lookup[$slug_string] = $topic_id;
}

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

/** @var list<string> */
$skipping = json_decode(
	file_get_contents(__DIR__ . '/skipping-transcriptions.json'),
	true
);

$checked = 0;

$all_video_ids = array_keys($video_playlists);

$statistics = $api->getStatistics(...$all_video_ids);

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

prepare_uncached_captions_html($all_video_ids);

$cards = array_combine(
	$all_video_ids,
	array_map(
		__NAMESPACE__ . '\yt_cards',
		$all_video_ids
	)
);

file_put_contents(
	(
		__DIR__
		. '/data/info-cards.json'
	),
	str_replace(
		PHP_EOL,
		"\n",
		json_encode(
			array_combine(
				$all_video_ids,
				array_map(
					__NAMESPACE__ . '\yt_cards',
					$all_video_ids
				)
			),
			JSON_PRETTY_PRINT
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

foreach ($all_video_ids as $video_id) {
	++$checked;

	$current_compile_date = determine_date_for_video(
		$video_id,
		$cache['playlists'],
		$api->dated_playlists()
	);

	if ($last_compile_date !== $current_compile_date) {
		echo "\n\n",
			sprintf('compiling transcrptions for %s', $current_compile_date),
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

	$caption_lines = captions(
		$video_id,
		$playlist_topic_strings_reverse_lookup
	);

	if (in_array($video_id, $skipping, true)) {
		continue;
	}

	if (count($caption_lines) < 1) {
		$skipping[] = $video_id;

		continue;
	}

	$date = determine_date_for_video(
		$video_id,
		$cache['playlists'],
		$api->dated_playlists()
	);

	$transcripts_json[$video_id] = [
		'id' => vendor_prefixed_video_id($video_id),
		'url' => video_url_from_id($video_id, true),
		'date' => $date,
		'dateTitle' => determine_playlist_id(
			$date,
			$cache,
			$not_a_livestream,
			$not_a_livestream_date_lookup
		)[1],
		'title' => $cache['playlistItems'][$video_id][1],
		'description' => $injected->determine_video_description($video_id),
		'topics' => array_values(array_filter(
			$video_playlists[$video_id],
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
		'transcript' => array_map(
			static function (string $line) : string {
				return str_replace(
					'](/topics/',
					'](../topics/',
					$line
				);
			},
			$caption_lines
		),
		'like_count' => (int) (
			$statistics[vendor_prefixed_video_id($video_id)]['likeCount'] ?? 0
		),
		'video_object' => null,
	];

	/** @var string|null */
	$thumbnail_url = null;

	if (preg_match('/^yt-/', vendor_prefixed_video_id($video_id))) {
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
			'@context' => 'https://schema.org',
			'@type' => 'VideoObject',
			'name' => $transcripts_json[$video_id]['title'],
			'description' => $transcripts_json[$video_id]['description'],
			'thumbnailUrl' => $thumbnail_url,
			'contentUrl' => timestamp_link($video_id, -1),
			'url' => [
				timestamp_link($video_id, -1),
				sprintf(
					'https://archive.satisfactory.video/transcriptions/%s/',
					vendor_prefixed_video_id($video_id)
				),
			],
			'uploadDate' => determine_date_for_video(
				$video_id,
				$cache['playlists'],
				$api->dated_playlists()
			),
		];
	}
}

$all_video_ids = array_reverse($all_video_ids);

file_put_contents(
	(
		__DIR__
		. '/../11ty/data/transcriptions.json'
	),
	str_replace(
		PHP_EOL,
		"\n",
		json_encode(
			array_values($transcripts_json),
			JSON_PRETTY_PRINT
		)
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

foreach ($transcripts_json as $video_id => $video_data) {
	++$checked;

	$caption_lines = $video_data['transcript'];

	echo "\r",
		sprintf(
			'processing %s of %s transcriptions (%s seconds elapsed)',
			$checked,
			count($transcripts_json),
			time() - $stat_start
		)
	;

	$transcriptions_file = transcription_filename($video_id);

	/** @var list<string> */
	$transcription_lines = [];

	$maybe_playlist_id = array_values(array_filter(
		$video_playlists[$video_id],
		static function (string $maybe) use ($playlists) : bool {
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

	[$playlist_id] = $maybe_playlist_id;

	$date = mb_substr(basename($playlists[$playlist_id]), 0, -3);

	$transcript_topic_strings = array_filter(
		$video_playlists[$video_id],
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

	$transcription_lines[] = (
		'---' . "\n"
		. sprintf(
			'title: "%s"' . "\n",
			(
				$injected->friendly_dated_playlist_name($playlist_id)
				. ' '
				. str_replace(
					'"',
					'\\"',
					$cache['playlistItems'][$video_id][1]
				)
			)
		)
		. sprintf(
			'date: "%s"' . "\n",
			date('Y-m-d', (int) strtotime($date))
		)
		. 'layout: transcript' . "\n"
		. sprintf(
			'topics:' . "\n" . '    - "%s"' . "\n",
			implode('"' . "\n" . '    - "', array_map(
				static function (
					string $playlist_id
				) use (
					$playlist_topic_strings
				) {
					return $playlist_topic_strings[
						$playlist_id
					];
				},
				$transcript_topic_strings
			))
		)
		. '---' . "\n"
		. '# ['
		. $injected->friendly_dated_playlist_name($playlist_id)
		. '](../' . $date . '.md)'
		. "\n"
		. '## ' . $cache['playlistItems'][$video_id][1]
		. "\n"
		. video_url_from_id($video_id)
		. str_replace(
			'./transcriptions/',
			'./',
			$markdownify->content_if_video_has_other_parts($video_id)
		)
		. str_replace(
			'./transcriptions/',
			'./',
			$markdownify->content_if_video_is_replaced($video_id)
		)
		. str_replace(
			'./transcriptions/',
			'./',
			$markdownify->content_if_video_is_a_duplicate($video_id)
		)
		. str_replace(
			'./transcriptions/',
			'./',
			$markdownify->content_if_video_has_duplicates($video_id)
		)
		. "\n\n"
		. '### Topics' . "\n"
		. implode("\n", array_map(
			static function (
				string $playlist_id
			) use (
				$topics_json,
				$playlist_topic_strings
			) {
				return
					'* ['
					. implode(
						' > ',
						$topics_json[$playlist_topic_strings[
							$playlist_id
						]]
					)
					. '](../topics/'
					. $playlist_topic_strings[$playlist_id]
					. '.md)';
			},
			array_filter(
				$video_playlists[$video_id],
				static function (
					string $playlist_id
				) use (
					$playlist_topic_strings,
					$topics_json
				) : bool {
					return isset(
						$playlist_topic_strings[$playlist_id],
						$topics_json[$playlist_topic_strings[
							$playlist_id
						]]
					);
				}
			)
		))
		. "\n\n"
		. '### Transcript'
		. "\n\n"
	);

	$transcription_lines[] = markdownify_transcription_lines(...$caption_lines);

	$transcription_content = implode('', $transcription_lines);

	if (
		! is_file($transcriptions_file)
		|| hash(
			'sha512',
			$transcription_content
		) !== hash_file(
			'sha512',
			$transcriptions_file
		)
	) {
		file_put_contents(
			$transcriptions_file,
			$transcription_content
		);
	}
}

echo "\n";

$skipping = array_unique($skipping);

file_put_contents(__DIR__ . '/skipping-transcriptions.json', json_encode(
	$skipping,
	JSON_PRETTY_PRINT
));

echo sprintf(
		'%s subtitles checked of %s videos cached',
		$checked,
		count($all_video_ids)
	),
	"\n"
;

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

	$title_unix = (int) strtotime(mb_substr(
		basename($playlists[$playlist_id]),
		0,
		-3
	));

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

	/** @var list<string> */
	$playlist_lines = [];

	$playlist_lines[] = (
		'---' . "\n"
		. sprintf('title: "%s"' . "\n", str_replace('"', '\"', $title))
		. sprintf('date: "%s"' . "\n", date('Y-m-d', $title_unix))
		. 'layout: livestream' . "\n"
		. '---' . "\n"
	);

	if (in_array(date('Y-m-d', $title_unix), $externals_dates, true)) {
		foreach (
			get_externals()[date('Y-m-d', $title_unix)] as $external_for_date
		) {
			[, $lines_to_write] = process_dated_csv(
				date('Y-m-d', $title_unix),
				[
					'playlists' => [],
					'playlistItems' => [],
					'videoTags' => [],
				],
				$external_for_date,
				$cache,
				$global_topic_hierarchy,
				$not_a_livestream,
				$not_a_livestream_date_lookup,
				$slugify,
				true,
				false,
				true,
				false
			);

			[$lines_to_write] = $lines_to_write;

			$playlist_lines[] = implode('', $lines_to_write) . "\n";
		}
	}

	$playlist_lines[] = (
		'# '
		. $title
		. "\n"
	);

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

	foreach ($topics_for_date as $topic_id => $data) {
		$title = determine_topic_name($topic_id, $cache);
		$content_arrays['Related answer clips'][$title] = [
			$topic_id,
			array_combine(
				$data['videos'],
				array_map(
					static function (string $video_id) use ($cache) : string {
						return maybe_transcript_link_and_video_url(
							$video_id,
							$cache['playlistItems'][$video_id][1]
						);
					},
					$data['videos']
				)
			),
		];
	}

	foreach ($content_arrays['Related answer clips'] as $data) {
		[$topic_id, $video_data] = $data;

		$depth = min(6, $topics_for_date[$topic_id]['level'] + 2);

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
				array_keys($video_data)
			),
		];

		if (
			! isset($cache['playlists'][$topic_id])
			|| count($cache['playlists'][$topic_id][2]) < 1
		) {
			$topic_title = ' ' . determine_topic_name($topic_id, $cache);
		} else {
			$topic_title =
				' ['
				. determine_topic_name($topic_id, $cache)
				. '](./topics/'
				. topic_to_slug(
					$topic_id,
					$cache,
					$global_topic_hierarchy,
					$slugify
				)[0]
				. '.md)';
		}

		$playlist_lines[] = (
			"\n"
			. str_repeat('#', $depth)
			. $topic_title
			. "\n"
		);

		$playlist_lines[] = implode('', array_map(
			static function (string $video_line) : string {
				return
					'* '
					. $video_line
					. "\n"
				;
			},
			$video_data
		));
	}

	$data_for_dated_json['categorised'] = array_values(
		$data_for_dated_json['categorised']
	);

	if (count($content_arrays['Single video clips']) > 0) {
		$data_for_dated_json['uncategorised'] = [
			'title' => determine_topic_name($topic_id, $cache),
			'clips' => array_map(
				static function (string $video_id) use ($cache) : array {
					return maybe_transcript_link_and_video_url_data(
						$video_id,
						$cache['playlistItems'][$video_id][1]
					);
				},
				$content_arrays['Single video clips']
			),
		];

		$playlist_lines[] =
			(
				''
				. '## Uncategorised'
				. "\n"
		);
	}

	$playlist_lines[] =
		implode('', array_map(
			static function (string $video_id) use ($cache) : string {
				return
					'* '
					. $cache['playlistItems'][$video_id][1]
					. ' '
					. video_url_from_id($video_id)
					. "\n"
				;
			},
			$content_arrays['Single video clips']
	));

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

	file_put_contents($playlists[$playlist_id], implode('', $playlist_lines));
}

file_put_contents(__DIR__ . '/../11ty/data/dated.json', json_encode(
	array_values($grouped_dated_data_for_json),
	JSON_PRETTY_PRINT
));

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

file_put_contents(__DIR__ . '/topic-slug-history.json', json_encode(
	$topic_slug_history,
	JSON_PRETTY_PRINT
));

usleep(100);

$save_path =
	__DIR__
	. '/../video-clip-notes/docs/'
;
$data = $api->dated_playlists();

$file_path = $save_path . '/topics.md';

/** @var list<string> */
$file_lines = [];

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

$playlist_ids = array_keys(($cache['playlists'] ?? []));

foreach (
	array_merge(
		$playlist_ids,
		$topics_without_direct_content
	) as $playlist_id
) {
	if (isset($data[$playlist_id])) {
		continue;
	} elseif ( ! isset($cache['playlists'][$playlist_id])) {
		$playlist_data = [
			'',
			determine_playlist_id(
				$playlist_id,
				$cache,
				$not_a_livestream,
				$not_a_livestream_date_lookup
			)[1],
			[],
		];
	} else {
		$playlist_data = $cache['playlists'][$playlist_id];
	}

	[, $playlist_title, $playlist_items] = $playlist_data;

	[, $slug] = topic_to_slug(
		$playlist_id,
		$cache,
		$global_topic_hierarchy,
		$slugify
	);

	$slug_count = count($slug);

	$slug_title = implode(' > ', $slug);

	echo 'rebuilding ', $slug_title, "\n";

	$slug_parents = array_slice($slug, 0, -1);

	$slug = array_map(
		[$slugify, 'slugify'],
		$slug
	);

	$slug_string = implode('/', $slug);

	$slug_path =
		realpath(
			$save_path
			. '/topics/'
		)
		. '/'
		. $slug_string
		. '.md';

	/** @var list<string> */
	$slug_lines = [];

	$playlist_items_data = [];

	foreach ($playlists_by_date as $other_playlist_id => $other_playlist_items) {
		foreach ($playlist_items as $video_id) {
			if (in_array($video_id, $other_playlist_items, true)) {
				if ( ! isset($playlist_items_data[$other_playlist_id])) {
					$playlist_items_data[$other_playlist_id] = [];
				}
				$playlist_items_data[$other_playlist_id][] = $video_id;
			}
		}
	}

	$slug_dir = dirname($slug_path);

	if ( ! is_dir($slug_dir)) {
		mkdir($slug_dir, 0755, true);
	}

	$slug_lines[] =
		(
			'---' . "\n"
			. sprintf(
				'title: "%s"' . "\n",
				$playlist_title
			)
			. (
				preg_match('/^PLbjDnnBIxiE/', $playlist_id)
					? sprintf(
						'external_link: %s' . "\n",
						sprintf(
							'https://www.youtube.com/playlist?list=%s',
							rawurlencode($playlist_id)
						)
					)
					: ''
			)
			. 'date: Last Modified' . "\n"
			. '---' . "\n"
			. '# [Topics]('
			. str_repeat('../', $slug_count)
			. 'topics.md)'
			. implode('', array_map(
				static function (
					string $slug_parent
				) use (
					$cache,
					$global_topic_hierarchy,
					$not_a_livestream,
					$not_a_livestream_date_lookup,
					$slug_count,
					$slugify
				) : string {
					[$parent_id] = determine_playlist_id(
						$slug_parent,
						$cache,
						$not_a_livestream,
						$not_a_livestream_date_lookup
					);
					if (count(($cache['playlists'][$parent_id] ?? [2 => []])[2]) < 1) {
						return ' > ' . $slug_parent;
					}

					[, $parent_parts] = topic_to_slug(
						$parent_id,
						$cache,
						$global_topic_hierarchy,
						$slugify
					);

					return
						' > ['
						. $slug_parent
						. ']('
						. str_repeat('../', $slug_count)
						. 'topics/'
						. implode('/', array_map(
							[$slugify, 'slugify'],
							$parent_parts
						))
						. '.md)';
				},
				$slug_parents
			))
			. ' > '
			. $playlist_title
			. "\n"
	);

	$topic_children = nesting_children(
		$playlist_id,
		$topic_nesting,
		false
	);

	usort(
		$topic_children,
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

	if (count($topic_children) > 0) {
		$slug_lines[] =
			(
				implode("\n", array_map(
					static function (
						string $subtopic_id
					) use (
						$cache,
						$global_topic_hierarchy,
						$slugify,
						$slug_count
					) : string {
						[, $sub_slug] = topic_to_slug(
							$subtopic_id,
							$cache,
							$global_topic_hierarchy,
							$slugify
						);

						return
							'* ['
							. determine_topic_name($subtopic_id, $cache)
							. ']('
							. str_repeat('../', $slug_count)
							. 'topics/'
							. implode('/', array_map(
								[$slugify, 'slugify'],
								$sub_slug
							))
							. '.md)';
					},
					$topic_children
				))
				. "\n"
		);
	}

	foreach ($playlist_items_data as $playlist_id => $video_ids) {
		$video_ids = filter_video_ids_for_legacy_alts(
			$cache,
			...$video_ids
		);

		usort($video_ids, [$sorting, 'sort_video_ids_by_date']);

		$slug_lines[] =
			(
				"\n"
				. '## '
				. $injected->friendly_dated_playlist_name($playlist_id)
				. "\n"
			)
			. implode('', array_map(
				static function (string $video_id) use ($cache, $slug_count) : string {
					return
						'* '
						. maybe_transcript_link_and_video_url(
							$video_id,
							$cache['playlistItems'][$video_id][1],
							$slug_count
						)
						. "\n"
					;
				},
				$video_ids
			))
		;
	}

	$slug_content = implode('', $slug_lines);

	if (
		! is_file($slug_path)
		|| hash(
			'sha512',
			$slug_content
		) !== hash_file(
			'sha512',
			$slug_path
		)
	) {
		file_put_contents($slug_path, $slug_content);
	}
}

$file_lines[] = (
	'---' . "\n"
	. 'title: "Browse Topics"' . "\n"
	. 'date: Last Modified' . "\n"
	. '---' . "\n"
);

$basename_topic_nesting = $topic_nesting;

$past_first = false;

$last_level = 0;

foreach ($basename_topic_nesting as $topic_id => $nesting_data) {
	if (isset($playlists[$topic_id])) {
		continue;
	}

	if ($nesting_data['level'] < $last_level) {
		$file_lines[] = '---' . "\n";
	}

	$last_level = $nesting_data['level'];

	$include_heading = count($nesting_data['children']) > 0;

	if (
		! isset($cache['playlists'][$topic_id])
		|| count($cache['playlists'][$topic_id][2]) < 1
	) {
		$topic_title = ' ' . determine_topic_name($topic_id, $cache);
	} else {
		$topic_title =
			' ['
			. determine_topic_name($topic_id, $cache)
			. '](./topics/'
			. $playlist_topic_strings[$topic_id]
			. '.md)';
	}

	if ($include_heading) {
		$depth = min(6, $nesting_data['level'] + 1);

		if ($past_first) {
			$file_lines[] = "\n";
		} else {
			$past_first = true;
		}

		$file_lines[] = (
			str_repeat('#', $depth)
			. $topic_title
			. "\n"
		);
	} else {
		$file_lines[] = (
			'*'
			. $topic_title
			. "\n"
		);
	}
}

file_put_contents($file_path, implode('', $file_lines));

echo 'rebuilding index', "\n";

$file_path = __DIR__ . '/../video-clip-notes/docs/index.md';

/** @var list<string> */
$lines = [
	(
		'---' . "\n"
		. 'title: Browse' . "\n"
		. 'date: Last Modified' . "\n"
		. 'layout: index' . "\n"
		. '---' . "\n"
	),
];

$grouped = [];

$sortable = [];

foreach ($playlists as $filename) {
	$unix = strtotime(mb_substr(basename($filename), 0, -3));
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

	$grouped[$year][$readable_month][] = [$readable_date, basename($filename), $unix];
}

$grouped = array_reverse($grouped, true);

$grouped = array_map(
	static function (array $year) : array {
		return array_map(
			static function (array $month) : array {
				usort(
					$month,
					static function (array $a, array $b) : int {
						return $b[2] <=> $a[2];
					}
				);

				return $month;
			},
			$year
		);
	},
	$grouped
);

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

$past_first = false;

foreach ($sortable as $year => $months) {
	if ($past_first) {
		$lines[] = "\n";
	} else {
		$past_first = true;
	}

	$lines[] = sprintf('# %s', $year);

	foreach (array_keys($months) as $readable_month) {
		$lines[] = sprintf("\n" . '## %s' . "\n", $readable_month);

		foreach ($grouped[$year][$readable_month] as $line_data) {
			[$readable_date, $filename] = $line_data;

			$lines[] =
				sprintf(
					'* [%s](%s)' . "\n",
					$readable_date,
					$filename
			);
		}
	}
}

file_put_contents($file_path, implode('', $lines));

echo sprintf('completed in %s seconds', time() - $stat_start), "\n";
