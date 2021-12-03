<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_filter;
use const ARRAY_FILTER_USE_BOTH;
use function array_keys;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function count;
use function current;
use function file_get_contents;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function natcasesort;
use function preg_match;
use RuntimeException;
use function sprintf;
use function strtotime;
use function uksort;
use UnexpectedValueException;
use function usort;

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
 */
class Questions
{
	public const REGEX_IS_QUESTION = '/^(.+\ )?q&a:/i';

	/**
	 * @readonly
	 */
	public Injected $injected;

	public function __construct(Injected $injected)
	{
		$this->injected = $injected;
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

		/**
		 * @var array<string, list<array{
		 *	0:string,
		 *	1:int,
		 *	2:'video'|'playlist'|'url'|'channel',
		 *	3:string
		 * }>>
		 */
		$cards = json_decode(
			file_get_contents(__DIR__ . '/../data/info-cards.json'),
			true
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
						'legacyalts',
						'seealso_video_cards',
						'seealso_topic_cards',
						'incoming_video_cards',
						'seealso_card_urls',
						'seealso_card_channels',
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

		$existing_keys = array_keys($existing);

		foreach ($existing_keys as $video_id) {
			unset(
				$existing[$video_id]['seealso_video_cards'],
				$existing[$video_id]['seealso_topic_cards'],
				$existing[$video_id]['seealso_card_urls'],
				$existing[$video_id]['seealso_card_channels'],
				$existing[$video_id]['incoming_video_cards']
			);

			if (isset($cards[$video_id]) && count($cards[$video_id])) {
				/** @var list<string> */
				$see_also_card_videos = [];

				/** @var list<string> */
				$see_also_card_playlists = [];

				/** @var list<array{0:string, 1:string, 2:string}> */
				$see_also_card_urls = [];

				/** @var list<array{0:string, 1:string}> */
				$see_also_card_channels = [];

				foreach ($cards[$video_id] as $card) {
					if ('playlist' === $card[2]) {
						$see_also_card_playlists[] = $card[3];
					} elseif ('video' === $card[2]) {
						$see_also_card_videos[] = $card[3];
					} elseif ('url' === $card[2]) {
						$maybe_entry = (array) json_decode($card[3], true, 2);

						if (
							3 !== count($maybe_entry)
							|| ! isset($maybe_entry[0], $maybe_entry[1], $maybe_entry[2])
							|| ! is_string($maybe_entry[0])
							|| ! is_string($maybe_entry[1])
							|| ! is_string($maybe_entry[2])
						) {
							throw new UnexpectedValueException(sprintf(
								'Unsupported URL card found on %s',
								$video_id
							));
						}

						$see_also_card_urls[] = $maybe_entry;
					} elseif ('channel' === $card[2]) {
						$maybe_entry = (array) json_decode($card[3], true, 2);

						if (
							2 !== count($maybe_entry)
							|| ! isset($maybe_entry[0], $maybe_entry[1])
							|| ! is_string($maybe_entry[0])
							|| ! is_string($maybe_entry[1])
						) {
							throw new UnexpectedValueException(sprintf(
								'Unsupported channel card found on %s',
								$video_id
							));
						}

						$see_also_card_channels[] = $maybe_entry;
					} else {
						throw new UnexpectedValueException(sprintf(
							'Unsupported card found on %s',
							$video_id
						));
					}
				}

				if (count($see_also_card_videos)) {
					$existing[$video_id]['seealso_video_cards'] = $see_also_card_videos;
				}
				if (count($see_also_card_playlists)) {
					$existing[$video_id]['seealso_topic_cards'] = $see_also_card_playlists;
				}
				if (count($see_also_card_urls)) {
					$existing[$video_id]['seealso_card_urls'] = $see_also_card_urls;
				}
				if (count($see_also_card_channels)) {
					$existing[$video_id]['seealso_card_channels'] = $see_also_card_channels;
				}
			}
		}

		foreach ($existing_keys as $video_id) {
			if (isset($existing[$video_id]['seealso_video_cards'])) {
				foreach ($existing[$video_id]['seealso_video_cards'] as $other_video_id) {
					if ( ! isset($existing[$other_video_id])) {
						continue;
					}

					if ( ! isset($existing[$other_video_id]['incoming_video_cards'])) {
						$existing[$other_video_id]['incoming_video_cards'] = [];
					}

					$existing[$other_video_id]['incoming_video_cards'][] = $video_id;
				}
			}
		}

		return $existing;
	}

	/**
	 * @return array<string, DEFINITELY>
	 */
	public function append_new_questions() : array
	{
		[$cache] = prepare_injections(
			$this->injected->api,
			$this->injected->slugify,
			$this->injected->skipping
		);
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
					return (bool) preg_match(self::REGEX_IS_QUESTION, $maybe[1]);
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
					function (
						$maybe_value,
						$maybe_key
					) use (
						$cache
					) : bool {
						return
							is_string($maybe_value)
							&& is_int($maybe_key)
							&& (
								isset($cache['playlistItems'][$maybe_value])
								|| in_array(
									$maybe_value,
									$this->twitter_thread_ids(),
									true
								)
							)
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

		uksort($existing, [$this->injected->sorting, 'sort_video_ids_by_date']);

		/**
		 * @var array<string, MAYBE>
		 */
		return $existing;
	}

	/**
	 * @return array{
	 *	0:array<string, array{
	 *		title:string,
	 *		date:string,
	 *		topics?:list<string>,
	 *		duplicates?:list<string>,
	 *		replaces?:list<string>,
	 *		replacedby?:string,
	 *		duplicatedby?:string,
	 *		seealso?:list<string>,
	 *		seealso_video_cards?:list<string>,
	 *		seealso_topic_cards?:list<string>,
	 *		seealso_card_urls?:list<array{0:string, 1:string, 2:string}>,
	 *		seealso_card_channels?:list<array{0:string, 1:string}>,
	 *		incoming_video_cards?:list<string>,
	 *		legacyalts?:list<string>
	 *	}>,
	 *	1:array<string, list<string>>,
	 *	2:array<string, list<string>>
	 * }
	 */
	public function process() : array
	{
		/**
		 * @var array{
		 *	0:array<string, array{
		 *		title:string,
		 *		date:string,
		 *		topics?:list<string>,
		 *		duplicates?:list<string>,
		 *		replaces?:list<string>,
		 *		replacedby?:string,
		 *		duplicatedby?:string,
		 *		seealso?:list<string>,
		 *		seealso_video_cards?:list<string>,
		 *		seealso_topic_cards?:list<string>,
		 *		seealso_card_urls?:list<array{0:string, 1:string, 2:string}>,
		 *		seealso_card_channels?:list<array{0:string, 1:string}>,
		 *		incoming_video_cards?:list<string>,
		 *		legacyalts?:list<string>
		 *	}>,
		 *	1:array<string, list<string>>,
		 *	2:array<string, list<string>>
		 * }|null
		 */
		static $cache = null;

		if (null === $cache) {
			$existing = $this->append_new_questions();
			$existing = $this->process_legacyalts(
				$existing,
				$this->injected->cache['legacyAlts']
			);
			[$existing, $duplicates] = $this->process_duplicates($existing);
			[$existing, $seealsos] = $this->process_seealsos($existing);
			$existing = $this->finalise($existing, $this->injected->cache);

			$cache = [
				$existing,
				$duplicates,
				$seealsos,
			];
		}

		/**
		 * @var array{
		 *	0:array<string, array{
		 *		title:string,
		 *		date:string,
		 *		topics?:list<string>,
		 *		duplicates?:list<string>,
		 *		replaces?:list<string>,
		 *		replacedby?:string,
		 *		duplicatedby?:string,
		 *		seealso?:list<string>,
		 *		seealso_video_cards?:list<string>,
		 *		seealso_topic_cards?:list<string>,
		 *		seealso_card_urls?:list<array{0:string, 1:string, 2:string}>,
		 *		seealso_card_channels?:list<array{0:string, 1:string}>,
		 *		incoming_video_cards?:list<string>,
		 *		legacyalts?:list<string>
		 *	}>,
		 *	1:array<string, list<string>>,
		 *	2:array<string, list<string>>
		 * }
		 */
		return $cache;
	}

	/**
	 * @psalm-type DUPLICATES = array<string, list<string>>
	 *
	 * @param DUPLICATES $duplicates
	 *
	 * @return DUPLICATES
	 */
	public function faq_threshold(
		array $duplicates,
		int $threshold = 3
	) : array {
		return array_filter(
			$duplicates,
			static function (array $maybe) use ($threshold) : bool {
				return count($maybe) >= $threshold;
			}
		);
	}

	/**
	 * @return list<string>
	 */
	public function twitter_thread_ids() : array
	{
		/** @var list<string>|null */
		static $ids = null;

		if (null === $ids) {
			/**
			 * @var list<array{
			 *	tweet_ids: list<string>
			 * }>
			 */
			$data = json_decode(
				file_get_contents(__DIR__ . '/../data/tweets.json'),
				true
			);

			$ids = array_map(
				static function (array $row) : string {
					return sprintf('tt-%s', implode(',', $row['tweet_ids']));
				},
				$data
			);
		}

		return $ids;
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
}
