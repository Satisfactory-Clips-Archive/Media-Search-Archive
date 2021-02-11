<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use Google_Client;
use Google_Service_YouTube;
use Google_Service_YouTube_Playlist;
use Google_Service_YouTube_PlaylistItem;
use Google_Service_YouTube_PlaylistItemListResponse;
use Google_Service_YouTube_PlaylistListResponse;
use Google_Service_YouTube_PlaylistSnippet;
use Google_Service_YouTube_ResourceId;
use Google_Service_YouTube_VideoListResponse;
use Google_Service_YouTube_VideoSnippet;
use RuntimeException;

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

	public function fetch_all_playlists() : array
	{
		/** @var array<string, string>|null */
		static $playlists = null;

		if (null === $playlists) {
			$cache_file = (__DIR__ . '/../data/api-cache/playlists.json');

			if (
				is_file($cache_file)
				&& ((time() - filemtime($cache_file)) < 86400)
			) {
				$to_sort = json_decode(file_get_contents($cache_file), true);
			} else {
				$to_sort = $this->listPlaylists([
					'channelId' => 'UCJamaIaFLyef0HjZ2LBEz1A',
					'maxResults' => 50,
				]);
			}

			asort($to_sort);

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

			file_put_contents(
				(__DIR__ . '/../data/api-cache/playlists.json'),
				json_encode($playlists, JSON_PRETTY_PRINT)
			);
		}

		return $playlists;
	}

	public function fetch_playlist_items() : array
	{
		/** @var array<string, list<string>> */
		static $out = null;

		if (null == $out) {
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
					$playlist = array_filter(
						(array) json_decode(
							file_get_contents($cache_file),
							true
						),
						'is_string'
					);
				} else {
					$playlist = $this->listPlaylistItems(
						[
							'playlistId' => $playlist_id,
						]
					);
				}

				$playlist = array_unique($playlist);

				sort($playlist);

				file_put_contents(
					$cache_file,
					json_encode($playlist, JSON_PRETTY_PRINT)
				);

				$out[$playlist_id] = $playlist;
			}
		}

		return $out;
	}

	public function fetch_all_videos_in_playlists() : array
	{
		/** @var array<string, array{0:string, 1:list<string>}>|null */
		static $out = null;

		if (null === $out) {
			$reduced = array_reduce(
				$this->fetch_playlist_items(),
				static function (array $out, array $playlist_items) : array {
					return array_merge(
						$out,
						array_diff($playlist_items, $out)
					);
				},
				[]
			);

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

				$out[$video_id] = json_decode(
					file_get_contents($cache_file),
					true
				);
			}
		}

		return $out;
	}

	/**
	 * @param array<string, array{0:string}>
	 *
	 * @return array<string, array{0:string}>
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

			$video_id = $video_snippet_resourceId->videoId;

			$out[] = $video_id;
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
}
