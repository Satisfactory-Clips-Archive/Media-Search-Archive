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
use const JSON_THROW_ON_ERROR;
use function natcasesort;
use function preg_match;
use RuntimeException;
use function sprintf;
use function strtotime;
use function uksort;
use UnexpectedValueException;
use function usort;

/**
 * @psalm-import-type MAYBE from AbstractQuestions
 * @psalm-import-type DEFINITELY from AbstractQuestions
 * @psalm-import-type -type REGEXES from AbstractQuestions
 */
class Questions extends AbstractQuestions
{

	public readonly Injected $injected;

	public function __construct(Injected $injected)
	{
		$this->injected = $injected;

		parent::__construct($injected->sorting);
	}

	/**
	 * @param list<string> $video_ids
	 * @param value-of<self::REGEX_TYPES> $type
	 *
	 * @return list<string>
	 */
	public function filter_video_ids(array $video_ids, string $type) : array
	{
		return array_values(array_filter(
			$video_ids,
			function (string $maybe) use ($type) : bool {
				$result = false;

				foreach ($this->title_pattern_check[$type] as $regex) {
					if (
						preg_match(
							$regex,
							$this->injected->determine_video_title(
								$maybe
							) ?? ''
						)
					) {
						$result = true;
						break;
					}
				}

				if ('qanda' === $type && $result) {
					foreach (
						array_keys($this->title_pattern_check) as $other_str
					) {
						if ('qanda' === $other_str) {
							continue;
						} elseif (
							1 === count(
								$this->filter_video_ids([$maybe], $other_str)
							)
						) {
							return false;
						}
					}
				}

				return $result;
			}
		));
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
			$this->injected->skipping,
			$this->injected
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
				function (array $maybe) : bool {
					return $this->string_is_probably_question($maybe[1]);
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
