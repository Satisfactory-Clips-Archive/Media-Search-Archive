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
use function array_reduce;
use function array_values;
use function count;
use function date;
use function floor;
use function in_array;
use InvalidArgumentException;
use function natcasesort;
use function preg_match;
use function sprintf;
use function str_pad;
use const STR_PAD_LEFT;
use function strtotime;
use function usort;

class Injected
{
	public readonly YouTubeApiWrapper $api;

	public readonly Slugify $slugify;

	public readonly Sorting $sorting;

	public readonly SkippingTranscriptions $skipping;

	/**
	 * @var array{
	 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
	 *	playlistItems:array<string, array{0:string, 1:string}>,
	 *	videoTags:array<string, array{0:string, list<string>}>,
	 *	stubPlaylists:array<string, array{0:string, 1:string, 2:list<string>}>,
	 *	legacyAlts:array<string, list<string>>
	 * }
	 */
	public array $cache = [];

	public array $from_a_livestream = [
		...TopicData::VIDEO_IS_FROM_A_LIVESTREAM,
	];

	/**
	 * @var array<string, list<int|string>>
	 */
	public readonly array $topics_hierarchy;

	/**
	 * @var array<string, string>
	 */
	public readonly array $not_a_livestream;

	/**
	 * @var array<string, string>
	 */
	public readonly array $not_a_livestream_date_lookup;

	/**
	 * @var array<string, string>
	 */
	public array $playlists_date_ref;

	/**
	 * @var array{playlists:non-empty-list<string>, non-empty-array<string, non-empty-list<string>>}
	 */
	public array $yt_shorts;

	public function __construct(
		YouTubeApiWrapper $api,
		Slugify $slugify,
		SkippingTranscriptions $skipping
	) {
		$this->api = $api;
		$this->slugify = $slugify;
		$this->skipping = $skipping;
		$this->playlists_date_ref = $api->dated_playlists();

		echo "\n", 'preparing injections', "\n";
		$prepared = prepare_injections(
			$this->api,
			$this->slugify,
			$this->skipping,
			$this
		);
		echo 'injections prepared', "\n";

		[
			$this->cache,
			,
			$this->not_a_livestream,
			$this->not_a_livestream_date_lookup,
		] = $prepared;

		$this->topics_hierarchy = $prepared[1];

		$this->playlists_date_ref = $api->dated_playlists();

		$this->sorting = new Sorting($this->cache);
		$this->sorting->playlists_date_ref = $this->playlists_date_ref;

		/**
		 * @var array{playlists:non-empty-list<string>, non-empty-array<string, non-empty-list<string>>}
		 */
		$this->yt_shorts = json_decode(file_get_contents(__DIR__ . '/../../Media-Search-Archive-Data/data/yt-shorts.json'), true);
	}

	/**
	 * @return array<string, string> topic id as key, topic slug as value
	 */
	public function all_topics() : array
	{
		/** @var array<string, string>|null */
		static $out = null;

		if (null === $out) {
			$playlists = $this->api->dated_playlists();

			$out = array_reduce(
				array_filter(
					array_keys($this->cache['playlists']),
					static function (string $maybe) use ($playlists) : bool {
						return ! isset($playlists[$maybe]);
					}
				),
				[$this, 'reduce_all_topics'],
				[]
			);
		}

		return $out;
	}

	/**
	 * @return list<string>
	 */
	public function determine_video_topics(string $video_id) : array
	{
		/** @var array<string, list<string>> */
		static $cache = [];

		if ( ! isset($cache[$video_id])) {
			$playlists = $this->api->dated_playlists();

			$determined_topics = array_keys(array_filter(
				$this->cache['playlists'],
				/**
				 * @param array{2:list<string>} $maybe
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
			));

			if (
				1 === count($determined_topics)
				&& false !== strpos($video_id, ',')
				&& preg_match('/^\d{4,}-\d{2}-\d{2}$/', $determined_topics[0])
			) {
				$video_id_parts = explode(',', $video_id);
				$end = '';
				[$csv_video_id, $start] = $video_id_parts;

				if (isset($video_id_parts[2])) {
					$end = $video_id_parts[2];
				}

				$date = determine_date_for_video(
					$csv_video_id,
					$this->cache['playlists'],
					$this->playlists_date_ref
				);

				$csv = get_dated_csv($date, $csv_video_id);

				$maybe_match_found = array_filter(
					$csv[1],
					static function (array $maybe) use ($start, $end) : bool {
						return $maybe[0] === $start && $maybe[1] === $end;
					}
				);
				if (1 === count($maybe_match_found)) {
					$csv_offset = key($maybe_match_found);

					if (isset($csv[2]['topics'][$csv_offset]['skip'])) {
						$determined_topics = [];
					} else if (isset($csv[2]['topics'][$csv_offset]['from_video'])) {
						$determined_topics = [
							...$determined_topics,
							...$this->determine_video_topics(
								preg_replace('/^yt-/', '', $csv[2]['topics'][$csv_offset]['from_video'])
							),
						];
					}
				}
			}

			if (0 === count($determined_topics) && false !== strpos($video_id, ',')) {
				$video_id_parts = explode(',', $video_id);
				$end = '';
				[$csv_video_id, $start] = $video_id_parts;

				if (isset($video_id_parts[2])) {
					$end = $video_id_parts[2];
				}

				$date = determine_date_for_video(
					$csv_video_id,
					$this->cache['playlists'],
					$this->playlists_date_ref
				);

				$csv = get_dated_csv($date, $csv_video_id);

				$maybe_match_found = array_filter(
					$csv[1],
					static function (array $maybe) use ($start, $end) : bool {
						return $maybe[0] === $start && $maybe[1] === $end;
					}
				);

				if (1 === count($maybe_match_found)) {
					$csv_offset = key($maybe_match_found);
					if (isset($csv[2]['topics'][$csv_offset]['skip'])) {
						$determined_topics = [];
					} else if (isset($csv[2]['topics'][$csv_offset]['from_video'])) {
						$determined_topics = $this->determine_video_topics(
							preg_replace('/^yt-/', '', $csv[2]['topics'][$csv_offset]['from_video'])
						);
					} else {
					$determined_topics = array_map(
						function (string $topic_name): string {
							return determine_playlist_id(
								$topic_name,
								$this->cache,
								$this->not_a_livestream,
								$this->not_a_livestream_date_lookup
							)[0];
						},
						($csv[2]['topics'][$csv_offset] ?? []) ?: []
					);
					}
				}
			}

			$date = determine_date_for_video(
				$video_id,
				$this->cache['playlists'],
				$this->playlists_date_ref,
				true
			);

			$determined_topics = array_filter(
				$determined_topics,
				static function (string $maybe) use ($date) : bool {
					return $maybe !== $date;
				}
			);

			$cache[$video_id] = $determined_topics;
		}

		return $cache[$video_id];
	}

	public function determine_video_topic_slugs(string $video_id) : array
	{
		/** @var array<string, list<string>> */
		static $cache = [];

		if ( ! isset($cache[$video_id])) {
			$topics = [];

			foreach ($this->determine_video_topics($video_id) as $topic_id) {
				$topics[] = topic_to_slug(
					$topic_id,
					$this->cache,
					$this->topics_hierarchy,
					$this->slugify
				)[0];
			}

			natcasesort($topics);

			$cache[$video_id] = array_values($topics);
		}

		return $cache[$video_id];
	}

	public function determine_video_title(string $video_id, bool $throw_if_not_found = false) : ? string
	{
		if ( ! isset($this->cache['playlistItems'][$video_id])) {
			$video_id = preg_replace('/^yt-/', '', $video_id);
		}

		if (isset($this->cache['playlistItems'][$video_id])) {
			return $this->cache['playlistItems'][$video_id][1];
		} elseif (false !== strpos($video_id, ',')) {
			$video_id_parts = explode(',', $video_id);

			$end = '';
			[$id, $start] = $video_id_parts;

			$id = vendor_prefixed_video_id($id);

			if (isset($video_id_parts[2])) {
				$end = $video_id_parts[2];
			}

			$csv = get_dated_csv(
				determine_date_for_video(
					$id,
					$this->cache['playlists'],
					$this->playlists_date_ref
				),
				$id
			);

			foreach ($csv[1] as $maybe) {
				if ($maybe[0] === $start && ($maybe[1] ?? '') === $end) {
					return $maybe[2];
				}
			}
		}

		if ($throw_if_not_found) {
			throw new InvalidArgumentException(sprintf('No title found for %s', $video_id));
		}

		return null;
	}

	public function determine_video_description(
		string $video_id,
		bool $strip_originally_streamed = true
	) : ? string {
		$video_id = preg_replace('/^yt-([^,]+)/', '$1', $video_id);

		$maybe = (
			$this->api->fetch_all_videos_in_playlists()[$video_id][2] ?? null
		);

		if (null !== $maybe) {
			$maybe = trim(str_replace(
				'Reminder: This is an unofficial channel, support requests should be directed to https://questions.satisfactorygame.com/',
				'',
				$maybe
			));
			if ($strip_originally_streamed) {
			$maybe = trim(preg_replace(
				'/Clips for the [A-z]+ \d+(?:st|nd|rd|th), 20\d+ Livestream originally streamed on https:\/\/www\.twitch\.tv\/coffeestainstudiosdevs/',
				'',
				$maybe
			));
			}
		} else {
			$maybe = (
				$this->api->cache_all_video_descriptions_for_externals()
			)[vendor_prefixed_video_id($video_id)] ?? null;
		}

		return '🏭' === $maybe ? null : $maybe;
	}

	public function all_video_ids() : array
	{
		$all_video_ids = array_keys($this->cache['playlistItems']);
		$shorts_ids = array_values(array_reduce(
			array_map('array_values', $this->yt_shorts['videos']),
			static function (array $was, array $is): array {
				return array_merge($was, $is);
			},
			[]
		));

		$all_video_ids = array_filter($all_video_ids, static function (string $maybe) use ($shorts_ids) : bool {
			return !in_array($maybe, $shorts_ids, true);
		});

		usort($all_video_ids, [$this->sorting, 'sort_video_ids_by_date']);

		return $all_video_ids;
	}

	public function friendly_dated_playlist_name(
		string $playlist_id,
		string $default_label = 'Livestream'
	) : string {
		if (
			! isset($this->playlists_date_ref[$playlist_id])
			&& preg_match('/^\d{4,}-\d{2}-\d{2}$/', $playlist_id)
			&& false !== ($maybe_playlist_id = array_search($playlist_id, $this->playlists_date_ref))
		) {
			$playlist_id = $maybe_playlist_id;
		}

		if ( ! isset($this->playlists_date_ref[$playlist_id])) {
			throw new InvalidArgumentException(sprintf(
				'Argument 1 (%s) passed to %s() was not found in the playlists!',
				$playlist_id,
				__METHOD__
			));
		}

		return
			date(
				'F jS, Y',
				(int) strtotime($this->playlists_date_ref[$playlist_id])
			)
			. sprintf(' %s', (
				$this->not_a_livestream[$playlist_id]
					?? $default_label
			))
		;
	}

	public function format_play() : array
	{
		return array_map(
			/**
			 * @param array{
			 *	0:string,
			 *	1:string,
			 *	2:numeric-string,
			 *	3:numeric-string,
			 *	4:string
			 * } $data
			 */
			function (array $data) : array {
				[$id, $twitch_id, $start, $end, $date] = $data;
				$hours = floor($start / 3600);
				$minutes = floor(($start - ($hours * 3600)) / 60);
				$seconds = floor($start % 60);

				$hours = str_pad((string) $hours, 2, '0', STR_PAD_LEFT);
				$minutes = str_pad((string) $minutes, 2, '0', STR_PAD_LEFT);
				$seconds = str_pad((string) $seconds, 2, '0', STR_PAD_LEFT);

				return [
					'id' => $id,
					'title' => $this->cache['playlistItems'][$id][1],
					'topics' => array_map(
						function (string $topic_id) : array {
							return topic_to_slug(
								$topic_id,
								$this->cache,
								$this->topics_hierarchy,
								$this->slugify
							);
						},
						$this->determine_video_topics($id)
					),
					'date' => $date,
					'date_friendly' => determine_playlist_id(
						$date,
						$this->cache,
						$this->not_a_livestream,
						$this->not_a_livestream_date_lookup
					)[1],
					'has_transcriptions' => count(captions($id, [], $this->skipping, $this)) > 0,
					'embed_data' => [
						$twitch_id,
						$start,
						$end,
						sprintf('%sh:%sm:%ss', $hours, $minutes, $seconds),
					],
				];
			},
			array_map(
				/**
				 * @return array{
				 *	0:string,
				 *	1:string,
				 *	2:numeric-string,
				 *	3:numeric-string,
				 *	4:string
				 * }
				 */
				function (string $id) : array {
					preg_match(
						'/^ts\-(\d+),(\d+(?:\.\d+)?),(\d+(?:\.\d+)?)$/',
						$id,
						$matches
					);

					/**
					 * @var array{
					 *	0:string,
					 *	1:string,
					 *	2:numeric-string,
					 *	3:numeric-string,
					 *	4:string
					 * }
					 */
					return [
						$id,
						$matches[1],
						$matches[2],
						$matches[3],
						determine_date_for_video(
							$id,
							$this->cache['playlists'],
							$this->playlists_date_ref
						),
					];
				},
				array_values(array_filter(
					$this->all_video_ids(),
					static function (string $id) : bool {
						return (bool) preg_match('/^ts\-\d+,\d+(?:\.\d+)?,\d+(?:\.\d+)?$/', $id);
					}
				))
			)
		);
	}

	/**
	 * @psalm-type OUT = array<string, string>
	 *
	 * @param OUT $out
	 *
	 * @return OUT
	 */
	private function reduce_all_topics(array $out, string $topic_id) : array
	{
		$out[$topic_id] = topic_to_slug(
			$topic_id,
			$this->cache,
			$this->topics_hierarchy,
			$this->slugify
		)[0];

		return $out;
	}
}
