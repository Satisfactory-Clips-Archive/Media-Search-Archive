<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_filter;
use const ARRAY_FILTER_USE_BOTH;
use function array_intersect;
use function array_keys;
use function array_map;
use function array_merge;
use function array_merge_recursive;
use function array_reduce;
use function array_search;
use function array_unique;
use function array_values;
use function count;
use function date;
use function file_get_contents;
use function file_put_contents;
use function in_array;
use InvalidArgumentException;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use const JSON_PRETTY_PRINT;
use function mb_substr;
use function natcasesort;
use function ob_flush;
use function ob_get_contents;
use function ob_start;
use const PHP_EOL;
use function preg_match;
use function preg_replace;
use RuntimeException;
use function sprintf;
use function str_replace;
use function strnatcasecmp;
use function strtotime;
use function uasort;
use function uksort;
use function usort;

require_once (__DIR__ . '/../vendor/autoload.php');
require_once (__DIR__ . '/global-topic-hierarchy.php');

/**
 * @var array<string, array{
 *	title:string,
 *	date:string,
 *	topics:list<string>,
 *	duplicates:list<string>,
 *	replaces:list<string>,
 *	seealso:list<string>
 * }>
 */
$existing = array_filter(
	(array) json_decode(
		file_get_contents(__DIR__ . '/data/q-and-a.json'),
		true
	),
	/**
	 * @psalm-assert-if-true array $a
	 * @psalm-assert-if-true string $b
	 *
	 * @param mixed $a
	 * @param array-key $b
	 */
	static function ($a, $b) : bool {
		return
			is_array($a)
			&& is_string($b)
			&& isset(
				$a['title'],
				$a['date'],
				$a['topics'],
				$a['duplicates'],
				$a['replaces'],
				$a['seealso']
			)
			&& is_string($a['title'])
			&& is_string($a['date'])
			&& is_array($a['topics'])
			&& is_array($a['duplicates'])
			&& is_array($a['replaces'])
			&& is_array($a['seealso'])
			&& false !== strtotime($a['date'])
			&& $a['topics'] === array_values(array_filter(
				$a['topics'],
				'is_string'
			))
			&& $a['duplicates'] === array_values(array_filter(
				$a['duplicates'],
				'is_string'
			))
			&& $a['replaces'] === array_values(array_filter(
				$a['replaces'],
				'is_string'
			))
			&& $a['seealso'] === array_values(array_filter(
				$a['seealso'],
				'is_string'
			))
		;
	},
	ARRAY_FILTER_USE_BOTH
);

$api = new YouTubeApiWrapper();

$api->update();

$slugify = new Slugify();

$cache = $api->toLegacyCacheFormat();

/**
 * @var array{
 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	playlistItems:array<string, array{0:string, 1:string}>,
 *	videoTags:array<string, array{0:string, list<string>}>,
 *	stubPlaylists?:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	legacyAlts?:array<string, list<string>>,
 *	internalxref?:array<string, string>
 * }
 */
$injected_cache = json_decode(
	file_get_contents(__DIR__ . '/cache-injection.json'),
	true
);

$cache = inject_caches($cache, $injected_cache);

$global_topic_hierarchy = array_merge_recursive(
	$global_topic_hierarchy,
	$injected_global_topic_hierarchy
);

$externals_cache = process_externals(
	$cache,
	$global_topic_hierarchy,
	$not_a_livestream,
	$not_a_livestream_date_lookup,
	$slugify,
	false
);

$cache = inject_caches($cache, $externals_cache);

$playlists_filter =
	/**
	 * @psalm-assert-if-true string $maybe_value
	 * @psalm-assert-if-true string $maybe_key
	 *
	 * @param scalar|array|object|resource|null $maybe_value
	 * @param array-key $maybe_key
	 */
	static function ($maybe_value, $maybe_key) : bool {
		return is_string($maybe_value) && is_string($maybe_key);
	};

/** @var array<string, string> */
$playlists = array_map(
	static function (string $date) : string {
		return date('Y-m-d', strtotime($date));
	},
	array_filter(
		array_map(
			static function (string $filename) : string {
				return mb_substr($filename, 0, -3);
			},
			array_merge(
				array_filter(
					(array) json_decode(
						file_get_contents(
							__DIR__
							. '/playlists/coffeestainstudiosdevs/satisfactory.json'
						),
						true
					),
					$playlists_filter,
					ARRAY_FILTER_USE_BOTH
				),
				array_filter(
					(array) json_decode(
						file_get_contents(
							__DIR__
							. '/playlists/coffeestainstudiosdevs/satisfactory.injected.json'
						),
						true
					),
					$playlists_filter,
					ARRAY_FILTER_USE_BOTH
				)
			)
		),
		static function (string $maybe) : bool {
			return false !== strtotime($maybe);
		}
	)
);

/**
 * @param array<string, array{0:string, 1:string, 2:list<string>}> $playlists
 * @param array<string, string> $playlist_date_ref
 */
function determine_date_for_video(
	string $video_id,
	array $playlists,
	array $playlist_date_ref
) : string {
	/** @var false|string */
	$found = false;

	foreach (array_keys($playlist_date_ref) as $playlist_id) {
		if ( ! isset($playlists[$playlist_id])) {
			throw new RuntimeException(sprintf(
				'No data available for playlist %s',
				$playlist_id
			));
		} elseif (in_array($video_id, $playlists[$playlist_id][2], true)) {
			if (false !== $found) {
				throw new InvalidArgumentException(sprintf(
					'Video %s already found on %s',
					$video_id,
					$found
				));
			}

			$found = $playlist_id;
		}
	}

	if (false === $found) {
		throw new InvalidArgumentException(sprintf(
			'Video %s was not found in any playlist!',
			$video_id
		));
	}

	return $playlist_date_ref[$found];
}

/**
 * @psalm-type CACHE = array{
 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	playlistItems:array<string, array{0:string, 1:string}>,
 *	videoTags:array<string, array{0:string, list<string>}>,
 *	stubPlaylists:array<string, array{0:string, 1:string, 2:list<string>}>
 * }
 *
 * @param CACHE $cache
 * @param array<string, list<string>> $topics_hierarchy
 *
 * @return list<string>
 */
function determine_video_topics(
	string $video_id,
	array $cache,
	array $playlists,
	array $topics_hierarchy,
	Slugify $slugify
) : array {
	$topics = array_map(
		static function (
			string $topic_id
		) use (
			$cache,
			$topics_hierarchy,
			$slugify
		) : string {
			return topic_to_slug(
				$topic_id,
				$cache,
				$topics_hierarchy,
				$slugify
			)[0];
		},
		array_keys(array_filter(
			$cache['playlists'],
			/**
			 * @param array{0:string, 1:string, 2:list<string>} $maybe
			 */
			static function (
				array $maybe,
				string $topic_id
			) use (
				$video_id,
				$playlists
			) : bool {
				return
					! isset($playlists[$topic_id])
					&& in_array($video_id, $maybe[2], true);
			},
			ARRAY_FILTER_USE_BOTH
		))
	);

	natcasesort($topics);

	return array_values($topics);
}

$all_video_ids = array_keys($cache['playlistItems']);

$all_topics = array_reduce(
	array_filter(
		array_keys($cache['playlists']),
		static function (string $maybe) use ($playlists) : bool {
			return ! isset($playlists[$maybe]);
		}
	),
	/**
	 * @psalm-type OUT = array<string, string>
	 *
	 * @param OUT $out
	 *
	 * @return OUT
	 */
	static function (
		array $out,
		string $topic_id
	) use (
		$cache,
		$global_topic_hierarchy,
		$slugify
	) : array {
		$out[$topic_id] = topic_to_slug(
			$topic_id,
			$cache,
			$global_topic_hierarchy['satisfactory'],
			$slugify
		)[0];

		return $out;
	},
	[]
);

$questions = array_map(
	static function (array $data) : array {
		return [
			'title' => $data[1],
		];
	},
	array_filter(
		$cache['playlistItems'],
		static function (array $maybe) : bool {
			return (bool) preg_match('/^q&a:/i', $maybe[1]);
		}
	)
);

foreach ($questions as $video_id => $data) {
	$existing[$video_id] = $existing[$video_id] ?? [
		'title' => $data['title'],
		'date' => '',
		'topics' => [],
		'duplicates' => [],
		'replaces' => [],
		'seealso' => [],
	];

	$existing[$video_id]['title'] = $data['title'];
	$existing[$video_id]['date'] = determine_date_for_video(
		$video_id,
		$cache['playlists'],
		$playlists
	);
	$existing[$video_id]['topics'] = determine_video_topics(
		$video_id,
		$cache,
		$playlists,
		array_map(
			/**
			 * @return list<string>
			 */
			static function (array $data) : array {
				return array_values(array_filter($data, 'is_string'));
			},
			$global_topic_hierarchy['satisfactory']
		),
		$slugify
	);

	foreach (
		[
			'duplicates',
			'replaces',
			'seealso',
		] as $required
	) {
		$existing[$video_id][$required] = array_values(array_filter(
			$existing[$video_id][$required],
			/**
			 * @psalm-assert-if-true string $maybe_value
			 * @psalm-assert-if-true int $maybe_key
			 *
			 * @param scalar|array|object|resource|null $maybe_value
			 * @param array-key $maybe_key
			 */
			static function (
				$maybe_value,
				$maybe_key
			) use (
				$cache
			) : bool {
				return
					is_string($maybe_value)
					&& is_int($maybe_key)
					&& isset($cache['playlistItems'][$maybe_value])
				;
			},
			ARRAY_FILTER_USE_BOTH
		));
	}
}

/** @var array<string, list<string>> */
$duplicates = [];

/** @var array<string, list<string>> */
$seealsos = [];

foreach (array_keys($questions) as $video_id) {
	$duplicates[$video_id] = [$video_id];
	$seealsos[$video_id] = [$video_id];

	$duplicates[$video_id] = array_merge(
		$duplicates[$video_id],
		$cache['legacyAlts'][$video_id] ?? []
	);

	foreach ($existing[$video_id]['duplicates'] as $duplicate) {
		if ( ! in_array($duplicate, $duplicates[$video_id], true)) {
			$duplicates[$video_id][] = $duplicate;
		}
	}

	foreach ($existing[$video_id]['seealso'] as $seealso) {
		if ( ! in_array($seealso, $seealsos[$video_id], true)) {
			$seealsos[$video_id][] = $seealso;
		}
	}
}

$seealsos_checked = [];

foreach ($seealsos as $video_id => $video_ids) {
	$merged_see_also = array_merge([$video_id], $video_ids);

	$was = count($merged_see_also);
	$added_more = true;

	while ($added_more) {
		foreach ($video_ids as $other_video_id) {
			$merged_see_also = array_merge(
				$merged_see_also,
				$seealsos[$other_video_id] ?? []
			);
		}

		$merged_see_also = array_unique($merged_see_also);

		$is = count($merged_see_also);

		$added_more = $was !== $is;

		$was = $is;
	}

	foreach ($merged_see_also as $other_video_id) {
		$seealsos[$other_video_id] = $merged_see_also;
	}

	$seealsos_checked = array_merge($seealsos_checked, $merged_see_also);
}

foreach (array_keys($duplicates) as $video_id) {
	foreach ($duplicates[$video_id] as $duplicate) {
		if ( ! isset($existing[$duplicate])) {
			continue;
		}

		$existing[$duplicate]['duplicates'] = array_values(
			array_intersect($all_video_ids, array_filter(
				$duplicates[$video_id],
				static function (string $maybe) use ($duplicate) : bool {
					return $maybe !== $duplicate;
				}
			))
		);
	}
}

foreach (array_keys($seealsos) as $video_id) {
	foreach ($seealsos[$video_id] as $seealso) {
		if ( ! isset($existing[$seealso])) {
			continue;
		}

		$existing[$seealso]['seealso'] = $seealsos[$video_id];
	}
}

foreach ($cache['legacyAlts'] as $legacy_ids) {
	foreach ($legacy_ids as $video_id) {
		unset($existing[$video_id]);
	}
}

uasort(
	$existing,
	/**
	 * @psalm-type ROW = array{
	 *	date:string,
	 *	title:string
	 * }
	 *
	 * @param ROW $a
	 * @param ROW $b
	 */
	static function (array $a, array $b) : int {
		$maybe = strtotime($b['date']) <=> strtotime($a['date']);

		if (0 === $maybe) {
			$maybe = strnatcasecmp($a['title'], $b['title']);
		}

		return $maybe;
	});
usort(
	$all_video_ids,
	static function (string $a, string $b) use ($cache, $playlists) : int {
		$a_date = determine_date_for_video(
			$a,
			$cache['playlists'],
			$playlists
		);
		$b_date = determine_date_for_video(
			$b,
			$cache['playlists'],
			$playlists
		);

		$maybe = strtotime($b_date) <=> strtotime($a_date);

		if (0 === $maybe) {
			$maybe = strnatcasecmp(
				$cache['playlistItems'][$a][1],
				$cache['playlistItems'][$b][1]
			);
		}

		return $maybe;
	}
);

$by_topic = [];

foreach (array_keys($all_topics) as $topic_id) {
	$by_topic[$topic_id] = array_values(array_intersect(
		$all_video_ids,
		$cache['playlists'][$topic_id][2]
	));
}

foreach (array_keys($existing) as $lookup) {
	$existing[$lookup]['duplicates'] = array_values(array_filter(
		$existing[$lookup]['duplicates'],
		static function (string $maybe) use ($lookup, $existing) : bool {
			return ! in_array($maybe, $existing[$lookup]['replaces'], true);
		}
	));

	$existing[$lookup]['seealso'] = array_values(array_filter(
		$existing[$lookup]['seealso'],
		static function (string $maybe) use ($lookup, $existing) : bool {
			return
				! in_array(
					$maybe,
					$existing[$lookup]['duplicates'] ?? [],
					true
				)
				&& ! in_array(
					$maybe,
					$existing[$lookup]['replaces'] ?? [],
					true
				)
				&& $maybe !== $lookup
			;
		}
	));

	foreach (
		[
			'duplicates',
			'replaces',
			'seealso',
		] as $required
	) {
		natcasesort($existing[$lookup][$required]);

		$existing[$lookup][$required] = array_values(
			$existing[$lookup][$required]
		);
	}
}

$data = str_replace(PHP_EOL, "\n", json_encode($existing, JSON_PRETTY_PRINT));

file_put_contents(__DIR__ . '/data/q-and-a.json', $data);

$filter_no_references =
	/**
	 * @param array{duplicates:array, replaces:array, seealso:array} $maybe
	 */
	static function (array $maybe) : bool {
		return
			count($maybe['duplicates']) < 1
			&& count($maybe['replaces']) < 1
			&& count($maybe['seealso']) < 1
		;
	};

ob_start();

echo sprintf(
		'%s questions found out of %s clips',
		count($existing),
		count($cache['playlistItems'])
	),
	"\n",
	sprintf(
		'%s questions found with no other references',
		count(array_filter($existing, $filter_no_references))
	),
	"\n"
;

$grouped = [];

foreach ($existing as $data) {
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
			count(array_filter($data, $filter_no_references)),
			count($data)
		),
		"\n"
	;
}

$video_id_date_sort = static function (
	string $a,
	string $b
) use ($existing, $cache, $playlists) : int {
	return
		strtotime(
			($existing[$b] ?? ['date' => determine_date_for_video(
				$b,
				$cache['playlists'],
				$playlists
			)])['date']
		) <=> strtotime(
			($existing[$a] ?? ['date' => determine_date_for_video(
				$a,
				$cache['playlists'],
				$playlists
			)])['date']
		)
	;
};

$duplicates = array_map(
	/**
	 * @param list<string> $video_ids
	 *
	 * @return list<string>
	 */
	static function (array $video_ids) use ($video_id_date_sort) : array {
		usort($video_ids, $video_id_date_sort);

		return $video_ids;
	},
	array_filter(
		$duplicates,
		static function (array $a, string $b) : bool {
			return $a !== [$b];
		},
		ARRAY_FILTER_USE_BOTH
	)
);

uksort($duplicates, $video_id_date_sort);

$duplicates = array_filter(
	$duplicates,
	static function (array $a, string $b) : bool {
		return $a[0] === $b;
	},
	ARRAY_FILTER_USE_BOTH
);

echo "\n", '# prototype replacement for faq markdown file', "\n";

$faq = array_filter(
	$duplicates,
	static function (array $maybe) : bool {
		return count($maybe) >= 3;
	}
);

uksort($faq, static function (string $a, string $b) use ($existing) : int {
	return strnatcasecmp($existing[$a]['title'], $existing[$b]['title']);
});

echo "\n";

foreach ($faq as $video_id => $faq_duplicates) {
	$transcription = captions($video_id);
	$playlist_id = array_search(
		determine_date_for_video(
			$video_id,
			$cache['playlists'],
			$playlists
		),
		$playlists, true
	);

	if ( ! is_string($playlist_id)) {
		throw new RuntimeException(sprintf(
			'Could not find playlist id for %s',
			$video_id
		));
	}

	echo '## ',
		preg_replace('/\.md\)/', ')', str_replace(
			'./',
			'https://archive.satisfactory.video/',
			maybe_transcript_link_and_video_url(
				$video_id,
				(
					date(
						'F jS, Y',
						(int) strtotime(
							(
								$existing[$video_id] ?? [
									'date' => determine_date_for_video(
										$video_id,
										$cache['playlists'],
										$playlists
									),
								]
							)['date']
						)
					)
					. (
						isset($not_a_livestream[$playlist_id])
							? (
								' '
								. $not_a_livestream[$playlist_id]
								. ' '
							)
							: ' Livestream '
					)
					. $cache['playlistItems'][$video_id][1]
				)
			)
		)),
		"\n"
	;

	echo "\n", '### Asked previously:';

	foreach ($faq_duplicates as $other_video_id) {
		if ($other_video_id === $video_id) {
			continue;
		}

		$playlist_id = array_search(
			determine_date_for_video(
				$other_video_id,
				$cache['playlists'],
				$playlists
			),
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
						date(
							'F jS, Y',
							(int) strtotime(
								(
									$existing[$other_video_id] ?? [
										'date' => determine_date_for_video(
											$other_video_id,
											$cache['playlists'],
											$playlists
										),
									]
								)['date']
							)
						)
						. (
							isset($not_a_livestream[$playlist_id])
								? (
									' '
									. $not_a_livestream[$playlist_id]
									. ' '
								)
								: ' Livestream '
						)
						. $cache['playlistItems'][$other_video_id][1]
					)
				)
			))
		;
	}

	if (count($transcription) > 0) {
		echo "\n", '### Transcript', "\n";
		echo "\n", markdownify_transcription_lines(...$transcription), "\n";
	}

	echo "\n";
}

file_put_contents(
	__DIR__ . '/q-and-a.md',
	'# Progress' . "\n" . ob_get_contents()
);

ob_flush();

$data = str_replace(PHP_EOL, "\n", json_encode($by_topic, JSON_PRETTY_PRINT));

file_put_contents(__DIR__ . '/data/video-id-by-topic.json', $data);

$data = str_replace(PHP_EOL, "\n", json_encode($all_topics, JSON_PRETTY_PRINT));

file_put_contents(__DIR__ . '/data/all-topic-slugs.json', $data);
