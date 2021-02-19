<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_diff;
use function array_filter;
use const ARRAY_FILTER_USE_BOTH;
use function array_intersect;
use function array_keys;
use function array_map;
use function array_merge;
use function array_reduce;
use function array_search;
use function array_unique;
use function array_values;
use function count;
use function current;
use function date;
use function end;
use const FILE_APPEND;
use function file_get_contents;
use function file_put_contents;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use const JSON_PRETTY_PRINT;
use function mb_substr;
use function natcasesort;
use function ob_flush;
use function ob_get_clean;
use function ob_get_contents;
use function ob_start;
use const PHP_EOL;
use function preg_match;
use function preg_replace;
use RuntimeException;
use function sprintf;
use function str_replace;
use function strtotime;
use function uasort;
use function uksort;
use function usort;

require_once (__DIR__ . '/../vendor/autoload.php');
require_once (__DIR__ . '/global-topic-hierarchy.php');

$filtering = new Filtering();

/**
 * @var array<string, array{
 *	title:string,
 *	date:string,
 *	topics?:list<string>,
 *	duplicates?:list<string>,
 *	replaces?:list<string>,
 *	replacedby?:string,
 *	duplicatedby?:string,
 *	seealso?:list<string>,
 *	suggested?:list<string>,
 *	legacyalts?:list<string>
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
				$a['date']
			)
			&& is_string($a['title'])
			&& is_string($a['date'])
			&& false !== strtotime($a['date'])
			&& (
				! isset($a['topics'])
				|| $a['topics'] === array_values(array_filter(
						(array) $a['topics'],
					'is_string'
				))
			)
			&& (
				! isset($a['duplicates'])
				|| $a['duplicates'] === array_values(array_filter(
						(array) $a['duplicates'],
					'is_string'
				))
			)
			&& (
				! isset($a['replaces'])
				|| $a['replaces'] === array_values(array_filter(
					(array) $a['replaces'],
					'is_string'
				))
			)
			&& (
				! isset($a['seealso'])
				|| $a['seealso'] === array_values(array_filter(
					(array) $a['seealso'],
					'is_string'
				))
			)
			&& (
				! isset($a['suggested'])
				|| $a['suggested'] === array_values(array_filter(
					(array) $a['suggested'],
					'is_string'
				))
			)
			&& ( ! isset($a['replacedby']) || is_string($a['replacedby']))
			&& ( ! isset($a['duplicatedby']) || is_string($a['duplicatedby']))
			&& (
				! isset($a['legacyalts'])
				|| $a['legacyalts'] === array_values(array_filter(
					(array) $a['legacyalts'],
					'is_string'
				))
			)
		;
	},
	ARRAY_FILTER_USE_BOTH
);

$existing = array_map(
	/**
	 * @param array{
	 *	title:string,
	 *	date:string,
	 *	topics?:list<string>,
	 *	duplicates?:list<string>,
	 *	replaces?:list<string>,
	 *	replacedby?:string,
	 *	duplicatedby?:string,
	 *	seealso?:list<string>,
	 *	suggested?:list<string>,
	 *	legacyalts?:list<string>
	 * } $data
	 *
	 * @return array{
	 *	title:string,
	 *	date:string,
	 *	topics:list<string>,
	 *	duplicates:list<string>,
	 *	replaces:list<string>,
	 *	replacedby?:string,
	 *	duplicatedby?:string,
	 *	seealso:list<string>,
	 *	suggested:list<string>,
	 *	legacyalts:list<string>
	 * }
	 */
	static function (array $data) : array {
		foreach (
			[
				'topics',
				'duplicates',
				'replaces',
				'seealso',
				'suggested',
				'legacyalts',
			] as $required
		) {
			$data[$required] = $data[$required] ?? [];
		}

		/**
		 * @var array{
		 *	title:string,
		 *	date:string,
		 *	topics:list<string>,
		 *	duplicates:list<string>,
		 *	replaces:list<string>,
		 *	replacedby?:string,
		 *	duplicatedby?:string,
		 *	seealso:list<string>,
		 *	suggested:list<string>,
		 *	legacyalts:list<string>
		 * }
		 */
		return $data;
	},
	$existing
);

$slugify = new Slugify();

[$cache, $global_topic_hierarchy] = prepare_injections(
	new YouTubeApiWrapper(),
	new Slugify()
);

$playlists = dated_playlists();

$sorting = new Sorting($cache);
$sorting->playlists_date_ref = $playlists;

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
		'suggested' => [],
		'duplicatedby' => [],
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
			'suggested',
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

foreach (array_keys($existing) as $video_id) {
	$duplicates[$video_id] = [$video_id];
	$seealsos[$video_id] = [$video_id];

	$duplicates[$video_id] = array_merge(
		$duplicates[$video_id],
		$existing[$video_id]['duplicates']
	);

	$existing[$video_id]['legacyalts'] = $cache['legacyAlts'][$video_id] ?? [];

	foreach ($existing[$video_id]['seealso'] as $seealso) {
		if ( ! in_array($seealso, $seealsos[$video_id], true)) {
			$seealsos[$video_id][] = $seealso;
		}
	}

	$existing[$video_id]['suggested'] = [];

	if (isset($existing[$video_id]['duplicatedby'])) {
		unset($existing[$video_id]['duplicatedby']);
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

foreach ($cache['legacyAlts'] as $legacy_ids) {
	foreach ($legacy_ids as $video_id) {
		unset($existing[$video_id]);
	}
}

foreach (array_keys($duplicates) as $video_id) {
	foreach ($duplicates[$video_id] as $duplicate) {
		if (
			$video_id === $duplicate
			|| ! isset($existing[$duplicate])
		) {
			continue;
		}

		/** @var string|null */
		$existing_duplicatedby = $existing[$duplicate]['duplicatedby'] ?? null;

		if (
			null !== $existing_duplicatedby
			&& $video_id !== $existing_duplicatedby
		) {
			throw new RuntimeException(sprintf(
				'Video already has duplicate set! (on %s, trying to set %s, found %s)',
				$duplicate,
				$video_id,
				$existing_duplicatedby
			));
		}

		$existing[$duplicate]['duplicatedby'] = $video_id;
	}
}

foreach (array_keys($seealsos) as $video_id) {
	foreach ($seealsos[$video_id] as $seealso) {
		if ( ! isset($existing[$seealso])) {
			continue;
		}

		$existing[$seealso]['suggested'] = array_filter(
			$seealsos[$video_id],
			static function (string $maybe) use ($seealso, $existing) : bool {
				if (
					(
						isset($existing[$seealso]['duplicatedby'])
						&& $maybe === $existing[$seealso]['duplicatedby']
					)
					|| (
						isset($existing[$seealso]['replacedby'])
						&& $maybe === $existing[$seealso]['replacedby']
					)
				) {
					return false;
				}

				return
					$maybe !== $seealso
					&& ! in_array(
						$maybe,
						$existing[$seealso]['seealso'] ?? [],
						true
					);
			}
		);
	}
}

uksort($existing, [$sorting, 'sort_video_ids_by_date']);
usort($all_video_ids, [$sorting, 'sort_video_ids_by_date']);

$by_topic = [];

foreach (array_keys($all_topics) as $topic_id) {
	$by_topic[$topic_id] = array_values(array_intersect(
		$all_video_ids,
		$cache['playlists'][$topic_id][2]
	));
}

/** @var array<string, string> */
$replacements_not_in_existing = [];

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
			'suggested',
		] as $required
	) {
		natcasesort($existing[$lookup][$required]);

		$existing[$lookup][$required] = array_values(
			$existing[$lookup][$required]
		);
	}

	$replacements_tmp = array_filter(
		[$existing[$lookup]['replacedby'] ?? ''],
		static function (string $maybe) use ($existing, $cache) : bool {
			return
				'' !== $maybe
				&& ! isset($existing[$maybe])
				&& isset($cache['playlistItems'][$maybe])
			;
		}
	);

	if (count($replacements_tmp) < 1) {
		if (isset($replacements_not_in_existing[$lookup])) {
			unset($replacements_not_in_existing[$lookup]);
		}
	} else {
		$replacements_not_in_existing[$lookup] = (string) current(
			$replacements_tmp
		);
	}

	if (isset($existing[$lookup]['replacedby'])) {
		unset($existing[$lookup]['replacedby']);
	}
}

foreach ($existing as $video_id => $data) {
	foreach (
		[
			'duplicates',
			'replaces',
			'seealso',
			'suggested',
		] as $required
	) {
		$data[$required] = array_filter(
			$data[$required],
			static function (string $maybe) use ($video_id) : bool {
				return $video_id !== $maybe;
			}
		);

		$data[$required] = array_values(array_unique($data[$required]));

		natcasesort($data[$required]);

		$existing[$video_id][$required] = $data[$required];
	}

	foreach ($data['replaces'] as $other_video_id) {
		if (isset($existing[$other_video_id])) {
			$existing[$other_video_id]['replacedby'] = $video_id;
		}
	}

	$existing[$video_id]['duplicates'] = $data['duplicates'] = array_filter(
		$data['duplicates'],
		static function (string $maybe) use ($data) : bool {
			return ! in_array($maybe, $data['legacyalts'] ?? [], true);
		}
	);

	$existing[$video_id]['seealso'] = $data['seealso'] = array_values(
		array_filter(
			$data['seealso'],
			static function (string $maybe) use ($video_id) : bool {
				return ! in_array($maybe, other_video_parts($video_id), true);
			}
		)
	);

	$existing[$video_id]['suggested'] = $data['suggested'] = array_values(
		array_filter(
			$data['suggested'],
			static function (string $maybe) use ($video_id) : bool {
				return ! in_array($maybe, other_video_parts($video_id), true);
			}
		)
	);

	foreach (
		[
			'duplicates',
			'replaces',
			'seealso',
			'suggested',
			'legacyalts',
		] as $required
	) {
		if ([] === $data[$required]) {
			unset($existing[$video_id][$required]);
		}
	}
}

foreach ($replacements_not_in_existing as $video_id => $replacement) {
	if (isset($existing[$video_id])) {
		$existing[$video_id]['replacedby'] = $replacement;
	}
}

/**
 * @var array<string, array{
 *	title:string,
 *	date:string,
 *	topics:list<string>,
 *	duplicates?:list<string>,
 *	replaces?:list<string>,
 *	replacedby?:string,
 *	duplicatedby?:string,
 *	seealso?:list<string>,
 *	suggested?:list<string>
 * }>
 */
$existing = $existing;

$data = str_replace(PHP_EOL, "\n", json_encode($existing, JSON_PRETTY_PRINT));

file_put_contents(__DIR__ . '/data/q-and-a.json', $data);

$filtered = array_filter(
	$existing,
	static function (array $data) : bool {
		return
			! in_array('trolling', $data['topics'], true)
			&& ! in_array('off-topic', $data['topics'], true);
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

$duplicates = array_map(
	/**
	 * @param list<string> $video_ids
	 *
	 * @return list<string>
	 */
	static function (array $video_ids) use ($sorting) : array {
		$video_ids = array_unique($video_ids);

		usort($video_ids, [$sorting, 'sort_video_ids_by_date']);

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

uksort($duplicates, [$sorting, 'sort_video_ids_by_date']);

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

uksort($faq, [$sorting, 'sort_video_ids_by_date']);

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
					date(
						'F jS, Y',
						(int) strtotime(
								$faq_date
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
			,
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
				(
					''
					. $cache['playlistItems'][$video_id][1]
				)
			)
		)),
		"\n"
	;

	if (has_other_part($video_id)) {
		$video_part_info = cached_part_continued()[$video_id];
		$video_other_parts = other_video_parts($video_id);

		echo
			"\n",
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
			echo
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
									$existing[$other_video_id]['date']
										?? determine_date_for_video(
												$other_video_id,
												$cache['playlists'],
												$playlists
										)
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

	uasort($faq_duplicates, [$sorting, 'sort_video_ids_by_date']);

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
								$existing[$other_video_id]['date']
									?? determine_date_for_video(
											$other_video_id,
											$cache['playlists'],
											$playlists
									)
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

$data = str_replace(PHP_EOL, "\n", json_encode($all_topics, JSON_PRETTY_PRINT));

file_put_contents(__DIR__ . '/data/all-topic-slugs.json', $data);
