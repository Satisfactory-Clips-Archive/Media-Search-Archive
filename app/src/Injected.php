<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

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
	 * @var array{
	 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
	 *	playlistItems:array<string, array{0:string, 1:string}>,
	 *	videoTags:array<string, array{0:string, list<string>}>,
	 *	stubPlaylists:array<string, array{0:string, 1:string, 2:list<string>}>,
	 *	legacyAlts:array<string, list<string>>,
	 *	internalxref:array<string, string>
	 * }
	 */
	private array $cache;

	/**
	 * @var array<string, list<int|string>>
	 */
	private array $topics_hierarchy;

	public function __construct(YouTubeApiWrapper $api, Slugify $slugify)
	{
		$this->api = $api;
		$this->slugify = $slugify;

		$prepared = prepare_injections($this->api, $this->slugify);

		[$this->cache] = $prepared;

		$this->topics_hierarchy = $prepared[1]['satisfactory'];

		$this->sorting = new Sorting($this->cache);
		$this->sorting->playlists_date_ref = $api->dated_playlists();
	}

	/**
	 * @return array<string, string> topic id as key, topic slug as value
	 */
	public function all_topics() : array
	{
		/** @var null|array<string, string> */
		static $out = null;

		if (null === $out) {
			$cache = $this->cache;
			$playlists = $this->api->dated_playlists();
			$topics_hierarchy = $this->topics_hierarchy;
			$slugify = $this->slugify;

			$out = array_reduce(
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
					$topics_hierarchy,
					$slugify
				) : array {
					$out[$topic_id] = topic_to_slug(
						$topic_id,
						$cache,
						$topics_hierarchy,
						$slugify
					)[0];

					return $out;
				},
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
}
