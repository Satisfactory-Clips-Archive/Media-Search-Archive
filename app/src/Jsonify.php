<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_diff;
use function array_keys;
use function array_search;
use function array_values;
use function count;
use function current;
use function date;
use function end;
use function in_array;
use function is_string;
use RuntimeException;
use function sprintf;
use function str_replace;
use function strtotime;
use function uasort;
use UnexpectedValueException;

class Jsonify
{
	private Injected $injected;

	private Questions $questions;

	/**
	 * @var array<string, string>
	 */
	private readonly array $topics;

	public function __construct(
		Injected $injected,
		Questions $questions = null
	) {
		$this->injected = $injected;
		$this->questions = $questions ?? new Questions($injected);
		$this->topics = $injected->all_topics();
	}

	/**
	 * @return false|array{
	 *	0:string,
	 *	1:list<array{0:string, 1:string|false, 2:string}>
	 * }
	 */
	public function content_if_video_has_other_parts(
		string $video_id,
		bool $include_self = false
	) {
		if ( ! has_other_part($video_id)) {
			return false;
		}

		$date = determine_date_for_video(
			$video_id,
			$this->injected->cache['playlists'],
			$this->injected->api->dated_playlists()
		);

		$playlist_id = array_search(
			$date,
			$this->injected->api->dated_playlists(), true
		);

		if (false === $playlist_id) {
			throw new RuntimeException(sprintf(
				'Could not determine dated playlist id for %s',
				$video_id
			));
		}

		$video_part_info = cached_part_continued()[$video_id];
		$video_other_parts = other_video_parts($video_id, $include_self);

		/**
		 * @var array{
		 *	0:string,
		 *	1:list<array{0:string, 1:string|false, 2:string}>
		 * }
		 */
		$out = ['', []];

		if (count($video_other_parts) > 2) {
			$out[0] = sprintf(
				'This video is part of a series of %s videos.',
				count($video_other_parts)
			);
		} elseif (null !== $video_part_info['previous']) {
			$out[0] = 'This video is a continuation of a previous video';
		} else {
			$out[0] = 'This video continues in another video';
		}

		if (count($video_other_parts) > 2) {
			$video_other_parts = other_video_parts($video_id, false);
		}

		$out[1] = $this->content_from_other_video_parts(
			$video_other_parts
		);

		return $out;
	}

	/**
	 * @return false|array{0:string, 1:list<array{0:string, 1:false|string, 2:string}>}
	 */
	public function content_if_video_has_duplicates(
		string $video_id,
		Questions $questions = null
	) {
		$questions = $questions ?? $this->questions;

		$faq_duplicates = $questions->process()[1][$video_id] ?? [];

		if ([] === $faq_duplicates) {
			return false;
		}

		$injected = $questions->injected;

		uasort(
			$faq_duplicates,
			[$injected->sorting, 'sort_video_ids_by_date']
		);

		$faq_duplicate_dates = [];

		$faq_duplicates_for_date_checking = array_values(array_diff(
			$faq_duplicates,
			[
				$video_id,
			]
		));

		foreach ($faq_duplicates_for_date_checking as $other_video_id) {
			$faq_duplicate_video_date = determine_date_for_video(
				$other_video_id,
				$injected->cache['playlists'],
				$injected->api->dated_playlists()
			);

			if (
				! in_array($faq_duplicate_video_date, $faq_duplicate_dates, true)
			) {
				$faq_duplicate_dates[] = $faq_duplicate_video_date;
			}
		}

		return [
			(
				sprintf(
					'This question may have been asked previously at least %s other %s',
					count($faq_duplicates_for_date_checking),
					(
						count($faq_duplicates_for_date_checking) > 1
							? 'times'
							: 'time'
					)
				)
				. sprintf(
					', as recently as %s%s',
					date('F Y', strtotime(current($faq_duplicate_dates))),
					(
						count($faq_duplicate_dates) > 1
							? (
								' and as early as '
								. date(
									'F Y.',
									strtotime(end($faq_duplicate_dates))
								)
							)
							: '.'
					)
				)
			),
			$this->content_from_other_video_parts(
				$faq_duplicates_for_date_checking
			),
		];
	}

	public function description_if_video_has_duplicates(
		string $video_id,
		Questions $questions = null
	) : string {
		$questions = $questions ?? $this->questions;

		$faq_duplicates = $questions->process()[1][$video_id] ?? [];

		if ([] === $faq_duplicates) {
			return '';
		}

		$injected = $questions->injected;

		uasort(
			$faq_duplicates,
			[$injected->sorting, 'sort_video_ids_by_date']
		);

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
				$injected->cache['playlists'],
				$injected->api->dated_playlists()
			);

			if (
				! in_array($faq_duplicate_video_date, $faq_duplicate_dates, true)
			) {
				$faq_duplicate_dates[] = $faq_duplicate_video_date;
			}
		}

		return sprintf(
				'This question may have been asked previously at least %s other %s',
				count($faq_duplicates_for_date_checking),
				count($faq_duplicates_for_date_checking) > 1 ? 'times' : 'time'
			)
			. sprintf(
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
			);
	}

	/**
	 * @return false|array{
	 *	0:string,
	 *	1:list<array{0:string, 1:false|string, 2:string}|array{0:string, 1:string}>
	 * }
	 */
	public function content_if_video_has_seealsos(
		string $video_id,
		Questions $questions = null
	) {
		$questions = $questions ?? $this->questions;

		$process = $questions->process()[0];

		$video_ids = array_keys($process);

		if ( ! isset($process[$video_id])) {
			$process[$video_id] = $this->seealso_cards($video_id);
		}

		$filter = static function (string $maybe) use ($video_ids) : bool {
			return in_array($maybe, $video_ids, true);
		};

		$faq_duplicates = array_unique(array_merge(
			$process[$video_id]['seealso'] ?? [],
			array_filter(
				$process[$video_id]['seealso_video_cards'] ?? [],
				$filter
			),
			array_filter(
				$process[$video_id]['incoming_video_cards'] ?? [],
				$filter
			)
		));

		$seealso_topics = array_filter(
			$process[$video_id]['seealso_topic_cards'] ?? [],
			function (string $maybe) : bool {
				return isset($this->topics[$maybe]);
			}
		);

		$topics_content = array_combine(
			$seealso_topics,
			array_map(
				/**
				 * @return array{0:string, 1:string}
				 */
				function (string $topic) : array {
					return [
						determine_topic_name(
							$topic,
							$this->injected->cache
						),
						'/topics/' . $this->topics[$topic],
					];
				},
				$seealso_topics
			)
		);

		$injected = $questions->injected;

		uasort(
			$faq_duplicates,
			[$injected->sorting, 'sort_video_ids_by_date']
		);

		$faq_duplicate_dates = [];

		$faq_duplicates_for_date_checking = array_values(array_diff(
			$faq_duplicates,
			[
				$video_id,
			]
		));

		foreach ($faq_duplicates_for_date_checking as $other_video_id) {
			$faq_duplicate_video_date = determine_date_for_video(
				$other_video_id,
				$injected->cache['playlists'],
				$injected->api->dated_playlists()
			);

			if (
				! in_array($faq_duplicate_video_date, $faq_duplicate_dates, true)
			) {
				$faq_duplicate_dates[] = $faq_duplicate_video_date;
			}
		}

		$opening_line_parts = [];

		if (count($faq_duplicates_for_date_checking)) {
			$plural = 'videos';
			$singular = 'video';

			$all_twitter_threads = count(
				$faq_duplicates_for_date_checking
			) === count(array_filter(
				$faq_duplicates_for_date_checking,
				static function (string $maybe) : bool {
					return (bool) preg_match('/^tt-\d+(?:,\d+)*$/', $maybe);
				}
			));

			foreach ($faq_duplicates_for_date_checking as $maybe) {
				if (preg_match('/^tt-\d+$/', $maybe)) {
					$plural = 'videos or tweets';
					$singular = 'tweet';

					if ($all_twitter_threads) {
						$plural = 'tweets';
						$singular = 'tweet';
					}
				}
				if (preg_match('/^tt-\d+(?:,\d+)+$/', $maybe)) {
					$plural = 'videos or twitter threads';
					$singular = 'twitter thread';

					if ($all_twitter_threads) {
						$plural = 'twitter threads';
						$singular = 'twitter thread';
					}

					break;
				}
			}

			$opening_line_parts[] = sprintf(
				'%s related %s',
				(
					count($faq_duplicates_for_date_checking) > 1
						? count($faq_duplicates_for_date_checking)
						: 'a'
				),
				(
					count($faq_duplicates_for_date_checking) > 1
						? $plural
						: $singular
				)
			);
		}

		if (count($topics_content)) {
			$opening_line_parts[] = sprintf(
				'%s related %s',
				(
					count($topics_content) > 1
						? count($topics_content)
						: 'a'
				),
				(
					count($topics_content) > 1
						? 'topics'
						: 'topic'
				)
			);
		}

		if (
			isset($process[$video_id]['seealso_card_urls'])
			&& count($process[$video_id]['seealso_card_urls'])
		) {
			$opening_line_parts[] = sprintf(
				'%s related %s',
				(
					count($process[$video_id]['seealso_card_urls']) > 1
						? count($process[$video_id]['seealso_card_urls'])
						: 'a'
				),
				(
					count($process[$video_id]['seealso_card_urls']) > 1
						? 'links'
						: 'link'
				)
			);
		}

		if (
			isset($process[$video_id]['seealso_card_channels'])
			&& count($process[$video_id]['seealso_card_channels'])
		) {
			$opening_line_parts[] = sprintf(
				'%s related %s',
				(
					count($process[$video_id]['seealso_card_channels']) > 1
						? count($process[$video_id]['seealso_card_channels'])
						: 'a'
				),
				(
					count($process[$video_id]['seealso_card_channels']) > 1
						? 'channels'
						: 'channel'
				)
			);
		}

		if (count($opening_line_parts) < 1) {
			return false;
		}

		if (count($opening_line_parts) > 1) {
			$last_opening_part = array_pop($opening_line_parts);

			$opening_line_parts[] = 'and ' . $last_opening_part;
		}

		$opening_line =
			'This question has '
			. implode(', ', $opening_line_parts);

		$content = $this->content_from_other_video_parts($faq_duplicates_for_date_checking);

		foreach ($topics_content as $row) {
			$content[] = $row;
		}

		foreach (($process[$video_id]['seealso_card_urls'] ?? []) as $row) {
			$content[] = ['url', $row[0], $row[1], $row[2]];
		}

		foreach (($process[$video_id]['seealso_card_channels'] ?? []) as $row) {
			$content[] = ['channel', $row[0], $row[1]];
		}

		return [
			$opening_line,
			$content,
		];
	}

	/**
	 * @return false|array{0:string, 1:string|false, 2:string}
	 */
	public function content_if_video_is_a_duplicate(string $video_id)
	{
		return $this->content_if_video_is_thinged(
			$video_id,
			'duplicatedby'
		);
	}

	/**
	 * @return false|array{0:string, 1:string|false, 2:string}
	 */
	public function content_if_video_is_replaced(string $video_id)
	{
		return $this->content_if_video_is_thinged(
			$video_id,
			'replacedby'
		);
	}

	private function seealso_cards(string $video_id) : array
	{
		$cards = null;

		if ( ! isset($cards)) {
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
		}

		/** @var list<string> */
		$see_also_card_videos = [];

		/** @var list<string> */
		$see_also_card_playlists = [];

		/** @var list<array{0:string, 1:string, 2:string}> */
		$see_also_card_urls = [];

		/** @var list<array{0:string, 1:string}> */
		$see_also_card_channels = [];

		if (isset($cards[$video_id]) && count($cards[$video_id])) {
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
		}

		return [
			'seealso_video_cards' => $see_also_card_videos,
			'seealso_topic_cards' => $see_also_card_playlists,
			'seealso_card_urls' => $see_also_card_urls,
			'seealso_card_channels' => $see_also_card_channels,
		];
	}

	/**
	 * @param 'duplicatedby'|'replacedby' $thinged
	 *
	 * @return false|array{0:string, 1:string|false, 2:string}
	 */
	private function content_if_video_is_thinged(
		string $video_id,
		string $thinged
	) {
		[$existing] = $this->questions->process();

		/** @var string|null */
		$found = $existing[$video_id][$thinged] ?? null;

		if (null === $found) {
			return false;
		}

		/** @var string|null */
		$playlist_id = null;

		foreach (
			array_keys(
				$this->injected->api->dated_playlists()
			) as $maybe_playlist_id
		) {
			if (
				in_array(
					$found,
					$this->injected->cache['playlists'][$maybe_playlist_id][2],
					true
				)
			) {
				$playlist_id = $maybe_playlist_id;
				break;
			}
		}

		if ( ! is_string($playlist_id)) {
			foreach (get_externals() as $date => $dated) {
				foreach ($dated as $external) {
					if (
						vendor_prefixed_video_id($external[0])
						=== vendor_prefixed_video_id(
							preg_replace('/,.+$/', '', $found)
						)
					) {
						$playlist_id = $date;

						break 2;
					}
				}
			}

			if ( ! is_string($playlist_id)) {
				if (false !== strpos($found, ',')) {
					[$found] = explode(',', $found);

					if (in_array($found, TopicData::VIDEO_IS_FROM_A_LIVESTREAM, true)) {
						$found = preg_replace('/^yt-/', '', $found);

						foreach (
							array_keys(
								$this->injected->api->dated_playlists()
							) as $maybe_playlist_id
						) {
							if (
								in_array(
									$found,
									$this->injected->cache['playlists'][$maybe_playlist_id][2],
									true
								)
							) {
								$playlist_id = $maybe_playlist_id;
								break;
							}
						}
					}
				}

				if (!is_string($playlist_id)) {
				throw new RuntimeException(sprintf(
					'Could not find playlist id for %s',
					$found
				));
				}
			}
		}

		return
			maybe_transcript_link_and_video_url_data(
				$found,
				str_replace(
					'Q&A Q&A:',
					'Q&A:',
					$this->injected->friendly_dated_playlist_name($playlist_id)
					. ' '
					. $this->injected->determine_video_title(
						$found
					)
				)
			)
		;
	}

	/**
	 * @param list<string> $video_other_parts
	 *
	 * @return list<array{0:string, 1:false|string, 2:string}>
	 */
	private function content_from_other_video_parts(
		array $video_other_parts
	) : array {
		$out = [];

		$twitter_threads = twitter_threads();

		$externals = get_externals();

		foreach ($video_other_parts as $other_video_id) {
			if (
				preg_match('/^tt-\d+(?:,\d+)*$/', $other_video_id)
				&& isset($twitter_threads[$other_video_id])
			) {
				$out[] = [
					$twitter_threads[$other_video_id]['title'],
					$other_video_id,
					sprintf(
						'/transcriptions/%s',
						$other_video_id
					),
				];

				continue;
			}

			/** @var string|null */
			$playlist_id = null;

			foreach (
				array_keys(
					$this->injected->api->dated_playlists()
				) as $maybe_playlist_id
			) {
				if (
					in_array(
						$other_video_id,
						$this->injected->cache['playlists'][$maybe_playlist_id][2],
						true
					)
				) {
					$playlist_id = $maybe_playlist_id;
					break;
				}
			}

			if ( ! is_string($playlist_id)) {
				foreach ($externals as $date => $dated) {
					foreach ($dated as $external) {
						if (
							vendor_prefixed_video_id($external[0])
							=== vendor_prefixed_video_id(
								preg_replace('/,.+$/', '', $other_video_id)
							)
						) {
							$playlist_id = $date;

							break 2;
						}
					}
				}

				if ( ! is_string($playlist_id)) {
					throw new RuntimeException(sprintf(
						'Could not find playlist id for %s',
						$other_video_id
					));
				}

				$out[] = maybe_transcript_link_and_video_url_data(
					$other_video_id,
					(
						$this->injected->friendly_dated_playlist_name(
							$playlist_id
						)
						. ': '
						. $this->injected->determine_video_title(
							$other_video_id
						)
					)
				);

				continue;
			}

			$out[] = maybe_transcript_link_and_video_url_data(
				$other_video_id,
				str_replace(
					'Q&A Q&A:',
					'Q&A:',
					$this->injected->friendly_dated_playlist_name(
						$playlist_id
					)
					. ' '
					. $this->injected->determine_video_title($other_video_id)
				)
			);
		}

		return $out;
	}
}
