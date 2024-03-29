<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

/**
 * @psalm-type MAYBE = array{
 *	title:string,
 *	date:string,
 *	topics?:list<string>,
 *	duplicates?:list<string>,
 *	replaces?:list<string>,
 *	replacedby?:string,
 *	duplicatedby?:string,
 *	seealso?:list<string>,
 *	seealso_video_cards?:list<string>,
 *	seealso_topic_cards?:list<string>,
 *	seealso_card_urls?:list<array{0:string, 1:string, 2:string}>,
 *	seealso_card_channels?:list<array{0:string, 1:string}>,
 *	incoming_video_cards?:list<string>,
 *	legacyalts?:list<string>
 * }
 * @psalm-type DEFINITELY = array{
 *	title:string,
 *	date:string,
 *	topics:list<string>,
 *	duplicates:list<string>,
 *	replaces:list<string>,
 *	replacedby?:string,
 *	duplicatedby?:string,
 *	seealso:list<string>,
 *	seealso_video_cards?:list<string>,
 *	seealso_topic_cards?:list<string>,
 *	seealso_card_urls?:list<array{0:string, 1:string, 2:string}>,
 *	seealso_card_channels?:list<array{0:string, 1:string}>,
 *	incoming_video_cards?:list<string>,
 *	legacyalts:list<string>
 * }
 *
 * @psalm-type REGEXES = array{
 *	qanda: list<string>,
 *	talk: list<string>,
 *	community_fyi: list<string>,
 *	state_of_dev: list<string>,
 *	community_highlights: list<string>,
 *	trolling: list<string>,
 *	jace_art: list<string>,
 *	random: list<string>,
 *	terrible_jokes: list<string>
 * }
 */
abstract class AbstractQuestions
{
	public const REGEX_TYPES = [
		'qanda',
		'talk',
		'community_fyi',
		'state_of_dev',
		'community_highlights',
		'trolling',
		'jace_art',
		'random',
		'terrible_jokes',
	];

	/**
	 * @var REGEXES
	 */
	public readonly array $title_pattern_check;

	public readonly Sorting $sorting;

	public function __construct(Sorting $sorting)
	{
		$this->sorting = $sorting;

		/**
		 * @var REGEXES
		 */
		$title_pattern_check = json_decode(
			file_get_contents(__DIR__ . '/../title-pattern-check.json'),
			true,
			3,
			JSON_THROW_ON_ERROR
		);

		$title_pattern_check = array_map(
		/**
		 * @param list<string> $strings
		 *
		 * @return list<string>
		 */
			static function (array $strings) : array {
				return array_map(
					static function (string $str) : string {
						return sprintf('/%s/', $str);
					},
					$strings
				);
			},
			$title_pattern_check
		);

		$this->title_pattern_check = $title_pattern_check;
	}

	public function string_is_probably_question(string $maybe) : bool
	{
		$result = false;

		foreach ($this->title_pattern_check['qanda'] as $regex) {
			if (preg_match($regex, $maybe)) {
				$result = true;
				break;
			}
		}

		if ($result) {
			foreach ($this->title_pattern_check as $str => $regexes) {
				if ('qanda' === $str) {
					continue;
				}

				foreach ($regexes as $regex) {
					if (preg_match($regex, $maybe)) {
						return false;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * @param array<string, DEFINITELY> $existing
	 * @param array<string, list<string>> $legacy_alts
	 *
	 * @return array<string, DEFINITELY>
	 */
	public function process_legacyalts(
		array $existing,
		array $legacy_alts
	) : array {
		foreach (array_keys($existing) as $video_id) {
			$existing[$video_id]['legacyalts'] = $legacy_alts[$video_id] ?? [];
		}

		foreach ($legacy_alts as $legacy_ids) {
			foreach ($legacy_ids as $video_id) {
				unset($existing[$video_id]);
			}
		}

		return $existing;
	}

	/**
	 * @param array<string, DEFINITELY> $existing
	 *
	 * @return array{
	 *	0:array<string, DEFINITELY>,
	 *	1:array<string, list<string>>
	 * }
	 */
	public function process_duplicates(array $existing) : array
	{
		/** @var array<string, list<string>> */
		$duplicates = [];

		foreach (array_keys($existing) as $video_id) {
			$duplicates[$video_id] = array_merge(
				[$video_id],
				$existing[$video_id]['duplicates']
			);

			if (isset($existing[$video_id]['duplicatedby'])) {
				unset($existing[$video_id]['duplicatedby']);
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

		$duplicates = array_map(
		/**
		 * @param list<string> $video_ids
		 *
		 * @return list<string>
		 */
			function (array $video_ids) : array {
				$video_ids = array_unique($video_ids);

				usort($video_ids, [$this->sorting, 'sort_video_ids_by_date']);

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

		uksort($duplicates, [$this->sorting, 'sort_video_ids_by_date']);

		$duplicates = array_filter(
			$duplicates,
			static function (array $a, string $b) : bool {
				return $a[0] === $b;
			},
			ARRAY_FILTER_USE_BOTH
		);

		return [$existing, $duplicates];
	}

	/**
	 * @param array<string, DEFINITELY> $existing
	 *
	 * @return array{
	 *	0:array<string, DEFINITELY>,
	 *	1:array<string, list<string>>
	 * }
	 */
	public function process_seealsos(array $existing) : array
	{
		/** @var array<string, list<string>> */
		$seealsos = [];

		foreach (array_keys($existing) as $video_id) {
			$seealsos[$video_id] = [$video_id];

			foreach ($existing[$video_id]['seealso'] as $seealso) {
				if ( ! in_array($seealso, $seealsos[$video_id], true)) {
					$seealsos[$video_id][] = $seealso;
				}
			}
		}

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
		}

		/**
		 * @var array{
		 *	0:array<string, DEFINITELY>,
		 *	1:array<string, list<string>>
		 * }
		 */
		return [$existing, $seealsos];
	}

	/**
	 * @param array<string, DEFINITELY> $existing
	 * @param array{
	 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
	 *	playlistItems:array<string, array{0:string, 1:string}>,
	 *	videoTags:array<string, array{0:string, list<string>}>,
	 *	stubPlaylists:array<string, array{0:string, 1:string, 2:list<string>}>
	 * } $cache
	 *
	 * @return array<string, MAYBE>
	 */
	public function finalise(array $existing, array $cache) : array
	{
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
				] as $required
			) {
				natcasesort($existing[$lookup][$required]);
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

			foreach (
				[
					'duplicates',
					'replaces',
					'seealso',
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

		uksort($existing, [$this->sorting, 'sort_video_ids_by_date']);

		/**
		 * @var array<string, MAYBE>
		 */
		return $existing;
	}
}
