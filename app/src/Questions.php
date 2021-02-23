<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use RuntimeException;

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
 *	suggested?:list<string>,
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
 *	suggested:list<string>,
 *	legacyalts:list<string>
 * }
 */
class Questions
{
	private Injected $injected;

	public function __construct(Injected $injected)
	{
		$this->injected = $injected;
	}

	/**
	 * @psalm-assert-if-true array $a
	 * @psalm-assert-if-true string $b
	 *
	 * @param mixed $a
	 * @param array-key $b
	 */
	private static function filter_cached_questions($a, $b) : bool
	{
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
	}

	/**
	 * @return array<string, DEFINITELY>
	 */
	public function existing() : array
	{
		/**
		 * @var array<string, MAYBE>
		*/
		$existing = array_filter(
			(array) json_decode(
				file_get_contents(__DIR__ . '/../data/q-and-a.json'),
				true
			),
			[$this, 'filter_cached_questions'],
			ARRAY_FILTER_USE_BOTH
		);

		$existing = array_map(
			/**
			 * @param MAYBE $data
			*
			* @return DEFINITELY
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
				 * @var DEFINITELY
				*/
				return $data;
			},
			$existing
		);

		return $existing;
	}

	/**
	 * @return array<string, DEFINITELY>
	 */
	public function append_new_questions() : array
	{
		[$cache] = prepare_injections($this->injected->api, $this->injected->slugify);
		$playlists = $this->injected->api->dated_playlists();

		$existing = self::existing();

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
			$existing[$video_id]['topics'] = $this->injected->determine_video_topic_slugs(
				$video_id
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

		/** @var array<string, DEFINITELY> */
		return $existing;
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

		$injected = $this->injected;

		$duplicates = array_map(
			/**
			 * @param list<string> $video_ids
			 *
			 * @return list<string>
			 */
			static function (array $video_ids) use ($injected) : array {
				$video_ids = array_unique($video_ids);

				usort($video_ids, [$injected->sorting, 'sort_video_ids_by_date']);

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

		uksort($duplicates, [$injected->sorting, 'sort_video_ids_by_date']);

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

			$existing[$video_id]['suggested'] = [];
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

		uksort($existing, [$this->injected->sorting, 'sort_video_ids_by_date']);

		/**
		 * @var array<string, MAYBE>
		 */
		return $existing;
	}
}
