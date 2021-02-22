<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_combine;
use function array_diff;
use function array_filter;
use const ARRAY_FILTER_USE_BOTH;
use const ARRAY_FILTER_USE_KEY;
use function array_intersect;
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
use function count;
use function date;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function in_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;
use const JSON_PRETTY_PRINT;
use function mb_substr;
use function min;
use function mkdir;
use function natcasesort;
use function natsort;
use function realpath;
use RuntimeException;
use function sprintf;
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

require_once (__DIR__ . '/../vendor/autoload.php');
require_once (__DIR__ . '/global-topic-hierarchy.php');

$start = time();

$api = new YouTubeApiWrapper();

$slugify = new Slugify();

[
	$cache,
	$global_topic_hierarchy,
] = prepare_injections($api, $slugify);

$playlist_satisfactory =
	realpath(
		__DIR__
		. '/playlists/coffeestainstudiosdevs/satisfactory.json'
	);

if ( ! is_string($playlist_satisfactory)) {
	throw new RuntimeException('Satisfactory playlist not found!');
}

/** @var array<string, string> */
$playlist_metadata = [
	$playlist_satisfactory => __DIR__ . '/../video-clip-notes/coffeestainstudiosdevs/satisfactory/',
];

/** @var array<string, string> */
$playlists = array_map(
	static function (string $filename) : string {
		return
			__DIR__
			. '/../video-clip-notes/coffeestainstudiosdevs/satisfactory/'
			. $filename
		;
	},
	$api->dated_playlists()
);

foreach ($playlists as $playlist_path) {
	$dirname = dirname($playlist_path);
	$basename = basename($playlist_path);

	if ( ! is_file($dirname . '/' . $basename)) {
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

process_externals(
	$cache,
	$global_topic_hierarchy,
	$not_a_livestream,
	$not_a_livestream_date_lookup,
	$slugify
);
$externals_dates = array_keys(get_externals());

$sorting = new Sorting($cache);

$cache['playlists'] = array_map(
	static function (array $data) use ($sorting) : array {
		uasort($data[2], [$sorting, 'sort_video_ids_alphabetically']);

		return $data;
	},
	$cache['playlists']
);

$sorting->cache = $cache;

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
	$topic_nesting['satisfactory'][$topic_id] = [
		'children' => [],
		'left' => -1,
		'right' => -1,
		'level' => -1,
	];
}

foreach ($global_topic_hierarchy as $basename => $topics) {
	foreach ($topics as $topic_id => $topic_ancestors) {
		if ( ! isset($topic_nesting[$basename][$topic_id])) {
			throw new RuntimeException(sprintf(
				'topic %s not already added!',
				$topic_id
			));
		}

		$topic_nesting[$basename][$topic_id]['level'] = count($topic_ancestors);

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
					$topic_nesting[$basename][$topic_ancestor_id]['children'],
					true
				)
			) {
				$topic_nesting[$basename][$topic_ancestor_id]['children'][] =
					$topic_descendant_id;
			}

			$topic_descendant_id = $topic_ancestor_id;
		}
	}

	$basename_topics_nesting_ids = array_keys($topic_nesting[$basename]);

	$topic_nesting[$basename] = array_map(
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
		$topic_nesting[$basename]
	);

	$topic_nesting[$basename] = array_filter(
		$topic_nesting[$basename],
		static function (string $maybe) use ($playlists) : bool {
			return ! isset($playlists[$maybe]);
		},
		ARRAY_FILTER_USE_KEY
	);

	$topic_nesting_roots = array_keys(array_filter(
		$topic_nesting[$basename],
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
		[$current_left, $topic_nesting[$basename]] = adjust_nesting(
			$topic_nesting[$basename],
			$topic_id,
			$current_left,
			$global_topic_hierarchy[$basename],
			$cache
		);
	}

	$topics = $topic_nesting[$basename];

	uasort(
		$topics,
		[$sorting, 'sort_by_nleft']
	);

	$topic_nesting[$basename] = $topics;
}

file_put_contents(__DIR__ . '/topics-nested.json', json_encode(
	$topic_nesting,
	JSON_PRETTY_PRINT
));

$api->sort_playlists_by_nested_data($topic_nesting['satisfactory']);

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
	$nested_a = $topic_nesting['satisfactory'][$a] ?? null;

	/**
	 * @var null|array{
	 *	children: list<string>,
	 *	left: positive-int,
	 *	right: positive-int,
	 *	level: int
	 * }
	 */
	$nested_b = $topic_nesting['satisfactory'][$b] ?? null;

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
		$global_topic_hierarchy['satisfactory'],
		$slugify
	);

	if ( ! isset($playlists[$topic_id])) {
		$topics_json[$slug_string] = $slug;
	}
	$playlist_topic_strings[$topic_id] = $slug_string;
	$playlist_topic_strings_reverse_lookup[$slug_string] = $topic_id;
}

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

natcasesort($all_video_ids);

echo sprintf('processing 0 of %s transcriptions', count($all_video_ids)), "\n";

foreach ($all_video_ids as $video_id) {
	++$checked;

	echo sprintf(
			'processing %s of %s transcriptions (%s seconds elapsed)',
			$checked,
			count($all_video_ids),
			time() - $start
		),
		"\r"
	;

	if (in_array($video_id, $skipping, true)) {
		continue;
	}

	$transcriptions_file = transcription_filename($video_id);

	/** @var list<string> */
	$transcription_lines = [];

	$caption_lines = captions($video_id);

	if (count($caption_lines) < 1) {
		$skipping[] = $video_id;

		continue;
	}

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
		throw new RuntimeException(sprintf(
			'Video found on no dates! (%s)',
			$video_id
		));
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
				date('F jS, Y', (int) strtotime($date))
				. (
					isset($not_a_livestream[$playlist_id])
						? (
							' '
							. $not_a_livestream[$playlist_id]
							. ' '
						)
						: ' Livestream '
				)
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
		. '# [' . date('F jS, Y', (int) strtotime($date))
		. ' '
		. (
			$not_a_livestream[$playlist_id]
				?? 'Livestream'
		)
		. '](../' . $date . '.md)'
		. "\n"
		. '## ' . $cache['playlistItems'][$video_id][1]
		. "\n"
		. video_url_from_id($video_id)
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

	file_put_contents(
		$transcriptions_file,
		implode('', $transcription_lines)
	);
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
		count($cache['playlistItems'])
	),
	"\n"
;

foreach (array_keys($playlists) as $playlist_id) {
	if (in_array($playlist_id, $externals_dates, true)) {
		continue;
	}

	$video_ids = ($cache['playlists'][$playlist_id] ?? [2 => []])[2];
	$video_ids = filter_video_ids_for_legacy_alts($cache, ...$video_ids);

	usort($video_ids, [$sorting, 'sort_video_ids_alphabetically']);

	$content_arrays = [
		'Related answer clips' => [],
		'Single video clips' => [],
	];

	$title_unix = (int) strtotime(mb_substr(
		basename($playlists[$playlist_id]),
		0,
		-3
	));

	$title = (
		date(
			'F jS, Y',
			$title_unix
		)
		. (
			isset($not_a_livestream[$playlist_id])
				? (' ' . $not_a_livestream[$playlist_id])
				: ' Livestream clips (non-exhaustive)'
		)
	);

	/** @var list<string> */
	$playlist_lines = [];

	$playlist_lines[] =
		(
			'---' . "\n"
			. sprintf('title: "%s"' . "\n", $title)
			. sprintf('date: "%s"' . "\n", date('Y-m-d', $title_unix))
			. 'layout: livestream' . "\n"
			. '---' . "\n"
			. '# '
			. $title
			. "\n"
	);

	$xref_video_id = $cache['internalxref'][$playlist_id] ?? null;

	if (null !== $xref_video_id) {
		[, $lines_to_write] = process_dated_csv(
			date('Y-m-d', $title_unix),
			[
				'playlists' => [],
				'playlistItems' => [],
				'videoTags' => [],
			],
			get_dated_csv(date('Y-m-d', $title_unix), $xref_video_id, false),
			$cache,
			$global_topic_hierarchy,
			$not_a_livestream,
			$not_a_livestream_date_lookup,
			$slugify,
			true,
			false,
			true
		);

		[$lines_to_write] = $lines_to_write;

		$playlist_lines[] = implode('', $lines_to_write);
	}

	$topics_for_date = [];

	if (count($video_ids) > 0) {
		$topics_for_date = filter_nested(
			$playlist_id,
			$topic_nesting['satisfactory'],
			$cache,
			$global_topic_hierarchy['satisfactory'],
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
					$global_topic_hierarchy['satisfactory'],
					$slugify
				)[0]
				. '.md)';
		}

		$playlist_lines[] =
			(
				"\n"
				. str_repeat('#', $depth)
				. $topic_title
				. "\n"
		);

		$playlist_lines[] =
			implode('', array_map(
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

	if (count($content_arrays['Single video clips']) > 0) {
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

	file_put_contents($playlists[$playlist_id], implode('', $playlist_lines));
}

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

/** @var list<string> */
$faq_dates = [];

foreach (array_keys($playlist_metadata) as $metadata_path) {
	/** @var array<string, string> */
	$faq_playlist_data = json_decode(file_get_contents($metadata_path), true);

	foreach ($faq_playlist_data as $filename) {
		$faq_playlist_date = mb_substr($filename, 0, -3);
		$faq_dates[] = $faq_playlist_date;
	}
}

$faq_dates = array_unique($faq_dates);

natsort($faq_dates);

$faq_filepath = __DIR__ . '/../video-clip-notes/coffeestainstudiosdevs/satisfactory/FAQ.md';

/** @var list<string> */
$faq_lines = [];

usleep(100);

$faq_lines[] =
	(
		'---' . "\n"
		. 'title: "Frequently Asked Questions"' . "\n"
		. 'date: Last Modified' . "\n"
		. '---' . "\n"
);

$faq_video_ids = [];
foreach ($cache['videoTags'] as $video_id => $tags) {
	if (in_array('faq', $tags[1], true)) {
		$faq_video_ids[] = $video_id;
	}
}

$legacy_alts = array_reduce(
	$cache['legacyAlts'],
	static function (array $out, array $video_ids) : array {
		return array_merge($out, array_diff($video_ids, $out));
	},
	[]
);

$faq_video_ids = array_filter(
	$faq_video_ids,
	static function (string $maybe) use ($legacy_alts) : bool {
		return ! in_array($maybe, $legacy_alts, true);
	}
);

$faq_video_topics = [];

foreach ($cache['playlists'] as $topic_id => $data) {
	$faq_video_topics[$topic_id] = array_intersect($faq_video_ids, $data[2]);
}

$faq_video_topic_nesting = array_keys(array_filter(
	$faq_video_topics,
	static function (array $data, string $maybe) use ($playlists) : bool {
		return count($data) > 0 && ! isset($playlists[$maybe]);
	},
	ARRAY_FILTER_USE_BOTH
));

foreach (array_values($faq_video_topic_nesting) as $topic_id) {
	foreach (
		nesting_parents(
			$topic_id,
			$topic_nesting['satisfactory']
		) as $maybe
	) {
		if ( ! in_array($maybe, $faq_video_topic_nesting, true)) {
			$faq_video_topic_nesting[] = $maybe;
		}
	}
}

/**
 * @var array<string, array{
 *	children:list<string>,
 *	level:int,
 *	left:positive-int,
 *	right:positive-int
 * }>
 */
$faq_video_topic_nesting = array_combine(
	$faq_video_topic_nesting,
	array_map(
		static function (
			string $topic_id
		) use (
			$topic_nesting,
			$faq_video_topic_nesting
		) : array {
			$existing = $topic_nesting['satisfactory'][$topic_id];

			return [
				'children' => array_filter(
					$existing['children'],
					static function (
						string $maybe
					) use (
						$faq_video_topic_nesting
					) : bool {
						return in_array(
							$maybe,
							$faq_video_topic_nesting,
							true
						);
					}
				),
				'level' => $existing['level'],
				'left' => -1,
				'right' => -1,
			];
		},
		$faq_video_topic_nesting
	)
);

$faq_video_topic_nesting_roots = array_keys(array_filter(
	$faq_video_topic_nesting,
	static function (array $maybe) : bool {
		return 0 === $maybe['level'];
	}
));

usort(
	$faq_video_topic_nesting_roots,
	static function (string $a, string $b) use ($cache) : int {
		return strnatcasecmp(
			determine_topic_name($a, $cache),
			determine_topic_name($b, $cache),
		);
	}
);

$current_left = 0;

foreach ($faq_video_topic_nesting_roots as $topic_id) {
	[$current_left, $faq_video_topic_nesting] = adjust_nesting(
		$faq_video_topic_nesting,
		$topic_id,
		$current_left,
		$global_topic_hierarchy['satisfactory'],
		$cache
	);
}

uasort(
	$faq_video_topic_nesting,
	[$sorting, 'sort_by_nleft']
);

$past_first = false;

foreach ($faq_video_topic_nesting as $topic_id => $data) {
	$depth = min(6, $data['level'] + 1);

	if (
		! isset($cache['playlists'][$topic_id])
		|| count($cache['playlists'][$topic_id][2]) < 1
	) {
		$topic_title = determine_topic_name($topic_id, $cache);
	} else {
		$topic_title =
			'['
			. determine_topic_name($topic_id, $cache)
			. '](./topics/'
			. $playlist_topic_strings[$topic_id]
			. '.md)';
	}

	if ($past_first) {
		$faq_lines[] = "\n";
	} else {
		$past_first = true;
	}

	$faq_lines[] =
		sprintf(
			'%s %s' . "\n",
			str_repeat('#', $depth),
			$topic_title
	);

	$faq_topic_videos = ($faq_video_topics[$topic_id] ?? []);

	if (count($faq_topic_videos)) {
		$grouped_faq_videos = array_filter(array_combine(
			array_keys($playlists),
			array_map(
				static function (
					string $dated_id
				) use ($faq_topic_videos, $cache) : array {
					return array_filter(
						($cache['playlists'][$dated_id] ?? [2 => []])[2],
						static function (
							string $video_id
						) use (
							$faq_topic_videos
						) : bool {
							return in_array(
								$video_id,
								$faq_topic_videos,
								true
							);
						}
					);
				},
				array_keys($playlists)
			)
		));

		foreach ($grouped_faq_videos as $dated_id => $video_ids) {
			$topic_title =
				'['
				. determine_topic_name($dated_id, $cache)
				. '](./'
				. basename($playlists[$dated_id])
				. ')';

				$faq_lines[] = "\n";

			if (6 === $depth) {
				$faq_lines[] =
					sprintf(
						'**%s**' . "\n",
						$topic_title
				);
			} else {
				$faq_lines[] =
					sprintf(
						'%s %s' . "\n",
						str_repeat('#', $depth + 1),
						$topic_title
				);
			}

			foreach ($video_ids as $video_id) {
				$faq_lines[] =
					sprintf(
						'* %s' . "\n",
						maybe_transcript_link_and_video_url(
							$video_id,
							$cache['playlistItems'][$video_id][1]
						)
				);
			}
		}
	}
}

file_put_contents($faq_filepath, implode('', $faq_lines));

$save_path =
	__DIR__
	. '/../video-clip-notes/coffeestainstudiosdevs/satisfactory/'
;
$data = $api->dated_playlists();


	$basename = basename($save_path);

	$file_path = $save_path . '/../' . $basename . '/topics.md';

	/** @var list<string> */
	$file_lines = [];

	$data_by_date = [];

	$playlists_by_date = [];

	foreach ($api->dated_playlists() as $playlist_id => $filename) {
		$unix = strtotime(mb_substr($filename, 0, -3));
		$readable_date = date('F jS, Y', $unix);

		$data_by_date[$playlist_id] = [$unix, $readable_date];

		$playlists_by_date[$playlist_id] = ($cache['playlists'][$playlist_id] ?? [2 => []])[2] ?? [];
	}

	uksort(
		$playlists_by_date,
		static function (string $a, string $b) use ($data_by_date) : int {
			return $data_by_date[$b][0] - $data_by_date[$a][0];
		}
	);

	$playlist_ids = array_keys(($cache['playlists'] ?? []));

	foreach ($playlist_ids as $playlist_id) {
		if (isset($data[$playlist_id])) {
			continue;
		} elseif ( ! isset($cache['playlists'][$playlist_id])) {
			throw new RuntimeException(sprintf(
				'Playlist cache data not found! (%s)',
				$playlist_id
			));
		}

		$playlist_data = $cache['playlists'][$playlist_id];

		[, $playlist_title, $playlist_items] = $playlist_data;

		[, $slug] = topic_to_slug(
			$playlist_id,
			$cache,
			$global_topic_hierarchy[$basename],
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
				. '/../'
				. $basename
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
						$slugify,
						$basename
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
							$global_topic_hierarchy[$basename],
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
			$topic_nesting[$basename],
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
							$basename,
							$cache,
							$global_topic_hierarchy,
							$slugify,
							$slug_count
						) : string {
							[, $sub_slug] = topic_to_slug(
								$subtopic_id,
								$cache,
								$global_topic_hierarchy[$basename],
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

			$slug_lines[] =
				(
					"\n"
					. '## '
					. $data_by_date[$playlist_id][1]
					. ' '
					. (
						$not_a_livestream[$playlist_id]
							?? 'Livestream'
					)
					. ''
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

		file_put_contents($slug_path, implode('', $slug_lines));
	}

	$file_lines[] =
		(
			'---' . "\n"
			. 'title: "Browse Topics"' . "\n"
			. 'date: Last Modified' . "\n"
			. '---' . "\n"
	);

	$basename_topic_nesting = $topic_nesting[$basename];

	$past_first = false;

	foreach ($basename_topic_nesting as $topic_id => $nesting_data) {
		if (isset($playlists[$topic_id])) {
			continue;
		}

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

			$file_lines[] =
				(
					str_repeat('#', $depth)
					. $topic_title
					. "\n"
			);
		} else {
			$file_lines[] =
				(
					'*'
					. $topic_title
					. "\n"
			);
		}
	}

	file_put_contents($file_path, implode('', $file_lines));

echo 'rebuilding index', "\n";

$file_path = __DIR__ . '/../video-clip-notes/coffeestainstudiosdevs/satisfactory/index.md';

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

echo sprintf('completed in %s seconds', time() - $start), "\n";
