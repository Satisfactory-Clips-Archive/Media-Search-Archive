<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_chunk;
use function array_combine;
use function array_diff;
use function array_filter;
use const ARRAY_FILTER_USE_BOTH;
use const ARRAY_FILTER_USE_KEY;
use function array_intersect;
use function array_keys;
use function array_map;
use function array_merge;
use function array_reduce;
use function array_reverse;
use function array_unique;
use function asort;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use Google_Client;
use Google_Service_YouTube;
use Google_Service_YouTube_Playlist;
use Google_Service_YouTube_PlaylistItem;
use Google_Service_YouTube_PlaylistItemListResponse;
use Google_Service_YouTube_PlaylistListResponse;
use Google_Service_YouTube_ResourceId;
use Google_Service_YouTube_VideoListResponse;
use Google_Service_YouTube_VideoSnippet;
use function implode;
use function in_array;
use function is_file;
use function json_decode;
use function json_encode;
use const JSON_PRETTY_PRINT;
use function natcasesort;
use function realpath;
use RuntimeException;
use function sort;
use function sprintf;

class YouTubeApiWrapper
{
	/**
	 * @readonly
	 */
	private Google_Client $client;

	/**
	 * @readonly
	 */
	private Google_Service_YouTube $service;

	/** @var array<string, string>|null */
	private $playlists = null;

	/**
	 * @var array<string, list<string>>|null
	 */
	private $fetch_playlist_items = null;

	public function __construct()
	{
		$client = new Google_Client();
		$client->setApplicationName('Twitch Clip Notes');
		$client->setScopes([
			'https://www.googleapis.com/auth/youtube.readonly',
			'https://www.googleapis.com/auth/youtube.force-ssl',
		]);

		$client->setAuthConfig(__DIR__ . '/../google-auth.json');
		$client->setAccessType('offline');

		$this->client = $client;

		$this->service = new Google_Service_YouTube($client);
	}

	public function update() : void
	{
		$this->fetch_all_playlists();
		$this->fetch_playlist_items();
		$this->fetch_all_videos_in_playlists();
		$this->sort_playist_items();
	}

	public function toLegacyCacheFormat() : array
	{
		$out = [
			'playlists' => [],
			'playlistItems' => [],
			'videoTags' => [],
			'captions' => [],
		];

		$videos = $this->fetch_all_videos_in_playlists();
		$videos_by_playist = $this->fetch_playlist_items();

		foreach ($this->fetch_all_playlists() as $playlist_id => $topic) {
			$out['playlists'][$playlist_id] = [
				'',
				$topic,
				$videos_by_playist[$playlist_id] ?? [],
			];
		}

		foreach ($videos as $video_id => $data) {
			[$title, $tags] = $data;

			$out['playlistItems'][$video_id] = ['', $title];
			$out['videoTags'][$video_id] = ['', $tags];
		}

		return $out;
	}

	/**
	 * @return array<string, string>
	 */
	public function fetch_all_playlists() : array
	{
		if (null === $this->playlists) {
			$cache_file = (__DIR__ . '/../data/api-cache/playlists.json');

			if (is_file($cache_file)) {
				/** @var array<string, string> */
				$to_sort = array_filter(
					json_decode(file_get_contents($cache_file), true),
					/**
					 * @param mixed $a
					 * @param mixed $b
					 */
					static function ($a, $b) : bool {
						return is_string($a) && is_string($b);
					},
					ARRAY_FILTER_USE_BOTH
				);
			} else {
				$to_sort = $this->listPlaylists([
					'channelId' => 'UCJamaIaFLyef0HjZ2LBEz1A',
					'maxResults' => 50,
				]);
			}

			asort($to_sort);

			/** @var array<string, string> */
			$playlists = [];

			foreach (array_keys($this->dated_playlists()) as $playlist_id) {
				if (isset($to_sort[$playlist_id])) {
					$playlists[$playlist_id] = $to_sort[$playlist_id];
				}
			}

			$playlists = array_merge(
				$playlists,
				array_diff($to_sort, $playlists)
			);

			/** @var array<string, string> */
			$playlists = array_filter(
				$playlists,
				static function (string $a, string $b) : bool {
					return $a !== $b;
				},
				ARRAY_FILTER_USE_BOTH
			);

			$this->playlists = $playlists;

			file_put_contents(
				(__DIR__ . '/../data/api-cache/playlists.json'),
				json_encode($this->playlists, JSON_PRETTY_PRINT)
			);
		}

		return $this->playlists;
	}

	/**
	 * @return array<string, list<string>>
	 */
	public function fetch_playlist_items() : array
	{
		if (null === $this->fetch_playlist_items) {
			$this->fetch_playlist_items = [];

			foreach (
				array_keys($this->fetch_all_playlists()) as $playlist_id
			) {
				$cache_file = (
					__DIR__
					. '/../data/api-cache/playlists/'
					. $playlist_id
					. '.json'
				);

				if (
					is_file($cache_file)
					&& (
						realpath(
							dirname($cache_file)
						) !== realpath(
							__DIR__
							. '/../data/api-cache/playlists'
						)
					)
				) {
					throw new RuntimeException(sprintf(
						'Invalid playlist id found! (%s)',
						$playlist_id
					));
				} elseif (
					is_file($cache_file)
				) {
					$playlist = array_values(array_filter(
						(array) json_decode(
							file_get_contents($cache_file),
							true
						),
						'is_string'
					));
				} else {
					$playlist = $this->listPlaylistItems(
						[
							'playlistId' => $playlist_id,
						]
					);
				}

				/** @var list<string> */
				$playlist = array_values(array_unique($playlist));

				sort($playlist);

				file_put_contents(
					$cache_file,
					json_encode($playlist, JSON_PRETTY_PRINT)
				);

				$this->fetch_playlist_items[$playlist_id] = $playlist;
			}
		}

		return $this->fetch_playlist_items;
	}

	/**
	 * @return array<string, array{0:string, 1:list<string>}>
	 */
	public function fetch_all_videos_in_playlists() : array
	{
		/** @var array<string, array{0:string, 1:list<string>}>|null */
		static $out = null;

		if (null === $out) {
			$reduced = array_values(array_reduce(
				$this->fetch_playlist_items(),
				/**
				 * @param list<string> $out
				 * @param list<string> $playlist_items
				 *
				 * @return list<string>
				 */
				static function (array $out, array $playlist_items) : array {
					return array_merge(
						$out,
						array_diff($playlist_items, $out)
					);
				},
				[]
			));

			$filtered = array_filter(
				$reduced,
				static function (string $video_id) : bool {
					$cache_file = (
						__DIR__
						. '/../data/api-cache/videos/'
						. $video_id
						. '.json'
					);

					if (
						realpath(
							dirname($cache_file)
						) !== realpath(
							__DIR__
							. '/../data/api-cache/videos'
						)
					) {
						throw new RuntimeException(sprintf(
							'Invalid video id found! (%s)',
							$video_id
						));
					}

					return ! is_file($cache_file);
				}
			);

			$out = [];

			foreach (array_chunk($filtered, 50) as $video_ids) {
				$chunk = $this->listVideos(
					[
						'id' => implode(',', $video_ids),
					]
				);

				foreach ($chunk as $video_id => $data) {
					$cache_file = (
						__DIR__
						. '/../data/api-cache/videos/'
						. $video_id
						. '.json'
					);

					if (
						realpath(
							dirname($cache_file)
						) !== realpath(
							__DIR__
							. '/../data/api-cache/videos'
						)
					) {
						throw new RuntimeException(sprintf(
							'Invalid video id found! (%s)',
							$video_id
						));
					}

					file_put_contents(
						$cache_file,
						json_encode($data, JSON_PRETTY_PRINT)
					);
				}
			}

			foreach ($reduced as $video_id) {
				$cache_file = (
					__DIR__
					. '/../data/api-cache/videos/'
					. $video_id
					. '.json'
				);

				if ( ! is_file($cache_file)) {
					throw new RuntimeException(sprintf(
						'No cache data for %s',
						$video_id
					));
				}

				$data = json_decode(
					file_get_contents($cache_file),
					true
				);

				if (
					! is_array($data)
					|| ! isset($data[0], $data[1])
					|| 2 !== count($data)
					|| ! is_string($data[0])
					|| ! is_array($data[1])
				) {
					throw new RuntimeException(sprintf(
						'Unsupported cache data found for %s',
						$video_id
					));
				}

				$out[$video_id] = [
					$data[0],
					array_values(array_filter($data[1], 'is_string')),
				];
			}
		}

		return $out;
	}

	/**
	 * @param array<string, array{
	 *	children: list<string>,
	 *	left: positive-int,
	 *	right: positive-int,
	 *	level: int
	 * }> $nested
	 */
	public function sort_playlists_by_nested_data(array $nested) : void
	{
		$not_nested = $this->fetch_all_playlists();

		$not_sorted = array_diff(array_keys($not_nested), array_keys($nested));

		$this->playlists = array_merge(
			array_filter(
				$not_nested,
				static function (
					string $playlist_id
				) use (
					$not_sorted
				) : bool {
					return in_array($playlist_id, $not_sorted, true);
				},
				ARRAY_FILTER_USE_KEY
			),
			array_combine(array_keys($nested), array_map(
				static function (string $topic_id) use ($not_nested) : string {
					return $not_nested[$topic_id] ?? $topic_id;
				},
				array_keys($nested)
			))
		);

		file_put_contents(
			(__DIR__ . '/../data/api-cache/playlists.json'),
			json_encode($this->playlists, JSON_PRETTY_PRINT)
		);
	}

	/**
	 * @param array<string, string>
	 *
	 * @return array<string, string>
	 */
	private function listPlaylists(
		array $args = [],
		array $out = []
	) : array {
		/** @var Google_Service_YouTube_PlaylistListResponse */
		$response = $this->service->playlists->listPlaylists(
			'id,snippet',
			$args
		);

		/** @var list<Google_Service_YouTube_Playlist> */
		$response_items = $response->items;

		foreach ($response_items as $playlist) {
			$out[$playlist->id] = $playlist->snippet->title;
		}

		if (isset($response->nextPageToken)) {
			$args['pageToken'] = $response->nextPageToken;

			$out = $this->listPlaylists($args, $out);
		}

		return $out;
	}

	/**
	 * @param array{playlistId:string} $args
	 * @param list<string> $out
	 *
	 * @return list<string>
	 */
	private function listPlaylistItems(
		array $args = [],
		array $out = []
	) : array {
		/** @var Google_Service_YouTube_PlaylistItemListResponse */
		$response = $this->service->playlistItems->listPlaylistItems(
			implode(',', [
				'id',
				'snippet',
			]),
			$args
		);

		/** @var iterable<Google_Service_YouTube_PlaylistItem> */
		$response_items = $response->items;

		foreach ($response_items as $playlist_item) {
			/** @var Google_Service_YouTube_VideoSnippet */
			$video_snippet = $playlist_item->snippet;

			/** @var Google_Service_YouTube_ResourceId */
			$video_snippet_resourceId = $video_snippet->resourceId;

			$out[] = (string) $video_snippet_resourceId->videoId;
		}

		if (isset($response->nextPageToken)) {
			$args['pageToken'] = $response->nextPageToken;

			$out = $this->listPlaylistItems($args, $out);
		}

		return $out;
	}

	/**
	 * @return array<string, string>
	 */
	private function dated_playlists() : array
	{
		/** @var array<string, string> */
		$dated_playlists = array_merge(
			json_decode(
				file_get_contents(
					__DIR__
					. '/../playlists/coffeestainstudiosdevs/satisfactory.json'
				),
				true
			),
			json_decode(
				file_get_contents(
					__DIR__
					. '/../playlists/coffeestainstudiosdevs'
					. '/satisfactory.injected.json'
				),
				true
			)
		);

		asort($dated_playlists);

		return array_reverse($dated_playlists, true);
	}

	private function listVideos(array $args = [], array $out = []) : array
	{
		/** @var Google_Service_YouTube_VideoListResponse */
		$response = $this->service->videos->listVideos(
			'snippet',
			$args
		);

		/**
		 * @var iterable{object{
		 *	snippet:Google_Service_YouTube_VideoSnippet
		 * }}
		 */
		$response_items = $response->items;

		foreach ($response_items as $item) {
			$out[$item->id] = [
				$item->snippet->title,
				$item->snippet->tags ?? [],
			];
		}

		if (isset($response->nextPageToken)) {
			$args['pageToken'] = $response->nextPageToken;

			$out = $this->listVideos($args, $out);
		}

		return $out;
	}

	private function sort_playist_items() : void
	{
		$videos = array_map('current', $this->fetch_all_videos_in_playlists());

		natcasesort($videos);

		$videos = array_keys($videos);

		$this->fetch_playlist_items = array_map(
			/**
			 * @param list<string> $video_ids
			 *
			 * @return list<string>
			 */
			static function (array $video_ids) use ($videos) : array {
				return array_values(array_intersect($videos, $video_ids));
			},
			$this->fetch_playlist_items()
		);

		foreach ($this->fetch_playlist_items as $playlist_id => $playlist) {
			$cache_file = (
				__DIR__
				. '/../data/api-cache/playlists/'
				. $playlist_id
				. '.json'
			);

			if (
				is_file($cache_file)
				&& (
					realpath(
						dirname($cache_file)
					) !== realpath(
						__DIR__
						. '/../data/api-cache/playlists'
					)
				)
			) {
				throw new RuntimeException(sprintf(
					'Invalid playlist id found! (%s)',
					$playlist_id
				));
			}

			file_put_contents(
				$cache_file,
				json_encode($playlist, JSON_PRETTY_PRINT)
			);
		}
	}
}
