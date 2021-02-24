<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_filter;
use const ARRAY_FILTER_USE_BOTH;
use function array_keys;
use function array_reduce;
use function array_values;
use function date;
use function in_array;
use InvalidArgumentException;
use function natcasesort;
use function sprintf;
use function strtotime;
use function usort;

class Injected
{
	/**
	 * @readonly
	 */
	public YouTubeApiWrapper $api;

	/**
	 * @readonly
	 */
	public Slugify $slugify;

	/**
	 * @readonly
	 */
	public Sorting $sorting;

	/**
	 * @readonly
	 *
	 * @var array{
	 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
	 *	playlistItems:array<string, array{0:string, 1:string}>,
	 *	videoTags:array<string, array{0:string, list<string>}>,
	 *	stubPlaylists:array<string, array{0:string, 1:string, 2:list<string>}>,
	 *	legacyAlts:array<string, list<string>>,
	 *	internalxref:array<string, string>
	 * }
	 */
	public array $cache;

	/**
	 * @readonly
	 *
	 * @var array<string, list<int|string>>
	 */
	public array $topics_hierarchy;

	/**
	 * @var array<string, string>
	 */
	private array $not_a_livestream;

	/**
	 * @var array<string, string>
	 */
	private array $playlists_date_ref;

	public function __construct(YouTubeApiWrapper $api, Slugify $slugify)
	{
		$this->api = $api;
		$this->slugify = $slugify;

		$prepared = prepare_injections($this->api, $this->slugify);

		[
			$this->cache,
			,
			$this->not_a_livestream,
		] = $prepared;

		$this->topics_hierarchy = $prepared[1]['satisfactory'];
		$this->playlists_date_ref = $api->dated_playlists();

		$this->sorting = new Sorting($this->cache);
		$this->sorting->playlists_date_ref = $this->playlists_date_ref;
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

			$cache[$video_id] = array_keys(array_filter(
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

	public function all_video_ids() : array
	{
		$all_video_ids = array_keys($this->cache['playlistItems']);
		usort($all_video_ids, [$this->sorting, 'sort_video_ids_by_date']);

		return $all_video_ids;
	}

	public function friendly_dated_playlist_name(
		string $playlist_id,
		string $default_label = 'Livestream'
	) : string {
		if ( ! isset($this->playlists_date_ref[$playlist_id])) {
			throw new InvalidArgumentException(sprintf(
				'Argument 1 passed to %s() was not found in the playlists!',
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
