<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use JsonException;
use stdClass;
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
use function array_unshift;
use function array_values;
use function asort;
use function count;
use function date;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function glob;
use Google_Client;
use Google_Service_YouTube;
use Google_Service_YouTube_PlaylistItem;
use Google_Service_YouTube_PlaylistItemStatus;
use Google_Service_YouTube_Resource_PlaylistItems;
use Google_Service_YouTube_Resource_Playlists;
use Google_Service_YouTube_Resource_Videos;
use Google_Service_YouTube_ResourceId;
use Google_Service_YouTube_VideoSnippet;
use function implode;
use function in_array;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;
use const JSON_PRETTY_PRINT;
use function mb_strpos;
use function mb_substr;
use function pathinfo;
use const PATHINFO_FILENAME;
use function preg_match;
use function preg_replace;
use function realpath;
use RuntimeException;
use function sprintf;
use function strtotime;
use function time;
use function unlink;
use UnexpectedValueException;

class YouTubeApiWrapper
{
	private readonly Google_Service_YouTube $service;

	/** @var array<string, string>|null */
	private $playlists = null;

	/**
	 * @var array<string, list<string>>|null
	 */
	private ?array $fetch_playlist_items = null;

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

		$this->service = new Google_Service_YouTube($client);
	}

	public function update() : void
	{
		$this->fetch_all_playlists();
		$this->fetch_playlist_items();
		$this->fetch_all_videos_in_playlists();
		$this->sort_playist_items();
	}

	/**
	 * @return array{
	 *	playlists: array<string, array{0:'', 1:string, 2:list<string>}>,
	 *	playlistItems: array<string, array{0:'', 1:string}>,
	 *	videoTags: array<string, array{0:'', 1:list<string>}>,
	 *	captions: array<empty, empty>
	 * }
	 */
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
	 *
	 * @throws JsonException if json_encode fails
	 */
	public function fetch_all_playlists() : array
	{
		if (null === $this->playlists) {
			$cache_file = (__DIR__ . '/../data/api-cache/playlists.json');
			$cache_file_objects = __DIR__ . '/../data/api-cache/playlists-unmapped.json';
			$cache_file_objects_was = __DIR__ . '/../data/api-cache/playlists-unmapped--.json';

			/** @var non-empty-list<string> $yt_shorts */
			$yt_shorts = json_decode(file_get_contents(__DIR__ . '/../data/yt-shorts.json'))->playlists;

			$was_as_objects = null;

			if (is_file($cache_file)) {
				/** @var array<string, string> */
				$to_sort = array_filter(
					(array) json_decode(file_get_contents($cache_file), true),
					/**
					 * @param mixed $a
					 * @param mixed $b
					 */
					static function ($a, $b) : bool {
						return is_string($a) && is_string($b);
					},
					ARRAY_FILTER_USE_BOTH
				);

				$as_objects = json_decode(file_get_contents($cache_file_objects));
			} else {
				$as_objects = $this->listPlaylists([
					'channelId' => 'UCJamaIaFLyef0HjZ2LBEz1A',
					'maxResults' => 50,
				]);

				if (is_file($cache_file_objects)) {
					file_put_contents($cache_file_objects_was, file_get_contents($cache_file_objects));
					$was_as_objects = json_decode(file_get_contents($cache_file_objects));
				}

				file_put_contents($cache_file_objects, json_encode(
					$as_objects,
					JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
				));

				$to_sort = [];

				foreach ($as_objects as $playlist_id => $playlist_data) {
					if ('private' === $playlist_data->status->privacyStatus) {
						continue;
					}

					$to_sort[$playlist_id] = $playlist_data->snippet->title;
				}
			}

			if ($was_as_objects) {
				$has_differences = [];
				foreach ($was_as_objects as $playlist_id => $playlist_data) {
					if (
						property_exists($as_objects, $playlist_id)
						&& $as_objects->{$playlist_id}->etag !== $playlist_data->etag
					) {
						$has_differences[] = $playlist_id;
					}
				}

				if (count($has_differences) > 0) {
					throw new UnexpectedValueException(sprintf(
						'Difference in playlist data found on %s',
						implode("\n", $has_differences)
					));
				}
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

			$playlists = array_filter(
				$playlists,
				static function (string $a, string $b) use ($yt_shorts) : bool {
					if (in_array($b, $yt_shorts, true)) {
						return false;
					}

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
	 * @return array<string, array{0:string, 1:list<string>, 2:string}>
	 */
	public function fetch_all_videos_in_playlists() : array
	{
		/** @var array<string, array{0:string, 1:list<string>, 2:string}>|null */
		static $out = null;

		if (null === $out) {
			$reduced = array_reduce(
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
			);

			$yt_shorts = array_reduce(
				json_decode(file_get_contents(__DIR__ . '/../data/yt-shorts.json'), true)['videos'],
				static function (array $was, array $is): array {
					return array_merge($was, $is);
				},
				[]
			);

			$filtered = array_filter(
				$reduced,
				static function (string $video_id) use ($yt_shorts) : bool {
					$cache_file = (
						__DIR__
						. '/../data/api-cache/videos/'
						. $video_id
						. '.json'
					);
					$description_cache_file = (
						__DIR__
						. '/../data/api-cache/video-descriptions/'
						. $video_id
						. '.json'
					);

					if (in_array($video_id, $yt_shorts, true)) {
						return false;
					}

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

					return
						! is_file($cache_file)
						|| ! is_file($description_cache_file)
					;
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
					$description_cache_file = (
						__DIR__
						. '/../data/api-cache/video-descriptions/'
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

					[$title, $tags, $description] = $data;

					file_put_contents(
						$cache_file,
						json_encode([$title, $tags], JSON_PRETTY_PRINT)
					);

					file_put_contents(
						$description_cache_file,
						json_encode($description, JSON_PRETTY_PRINT)
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
				$description_cache_file = (
					__DIR__
					. '/../data/api-cache/video-descriptions/'
					. $video_id
					. '.json'
				);

				if ( ! is_file($cache_file)) {
					throw new RuntimeException(sprintf(
						'No cache data for %s',
						$video_id
					));
				} elseif ( ! is_file($description_cache_file)) {
					throw new RuntimeException(sprintf(
						'No cache data for %s',
						$video_id
					));
				}

				/** @var scalar|array|object|null */
				$data = json_decode(
					file_get_contents($cache_file),
					true
				);

				/** @var scalar|array|object|null */
				$description = json_decode(
					file_get_contents($description_cache_file),
					true
				);

				if (
					! is_array($data)
					|| ! isset($data[0], $data[1], $description)
					|| 2 !== count($data)
					|| ! is_string($data[0])
					|| ! is_array($data[1])
					|| ! is_string($description)
				) {
					throw new RuntimeException(sprintf(
						'Unsupported cache data found for %s',
						$video_id
					));
				}

				$out[$video_id] = [
					$data[0],
					array_values(array_filter($data[1], 'is_string')),
					$description,
				];
			}
		}

		return $out;
	}

	/**
	 * @return array<string, string>
	 */
	public function cache_all_video_descriptions_for_externals() : array
	{
		/** @var array<string, string>|null */
		static $out = null;

		if (null === $out) {
			$ids = [];

			foreach (get_externals() as $by_date) {
				foreach ($by_date as $external) {
					$ids[] = vendor_prefixed_video_id($external[0]);
				}
			}

			$ids = array_filter($ids, static function (string $maybe) : bool {
				return (bool) preg_match('/^yt-/', $maybe);
			});

			$ids = array_map(
				static function (string $id) : string {
					return preg_replace('/^yt-/', '', $id);
				},
				$ids
			);

			$filtered = array_filter(
				$ids,
				static function (string $video_id) : bool {
					$description_cache_file = (
						__DIR__
						. '/../data/api-cache/video-descriptions/'
						. $video_id
						. '.json'
					);

					return ! is_file($description_cache_file);
				}
			);

			foreach (array_chunk($filtered, 50) as $video_ids) {
				$chunk = $this->listVideos(
					[
						'id' => implode(',', $video_ids),
					]
				);

				foreach ($chunk as $video_id => $data) {
					$description_cache_file = (
						__DIR__
						. '/../data/api-cache/video-descriptions/'
						. $video_id
						. '.json'
					);

					[, , $description] = $data;

					file_put_contents(
						$description_cache_file,
						json_encode($description, JSON_PRETTY_PRINT)
					);
				}
			}

			$out = [];

			foreach ($ids as $video_id) {
				$description_cache_file = (
					__DIR__
					. '/../data/api-cache/video-descriptions/'
					. $video_id
					. '.json'
				);

				if (is_file($description_cache_file)) {
					$out[
						vendor_prefixed_video_id($video_id)
					] = (string) json_decode(
						file_get_contents($description_cache_file),
						true
					);
				}
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
	 * @return array<string, string>
	 */
	public function dated_playlists() : array
	{
		/** @var array<string, string>|null */
		static $cache = null;

		if (null === $cache) {
			$playlists_filter =
				[new Filtering(), 'kvp_string_string'];

			/** @var array<string, string> */
			$dated_playlists = array_filter(
						array_merge(
							array_filter(
								(array) json_decode(
									file_get_contents(
										__DIR__
										. '/../playlists/youtube.json'
									),
									true
								),
								$playlists_filter,
								ARRAY_FILTER_USE_BOTH
							),
							array_filter(
								(array) json_decode(
									file_get_contents(
										__DIR__
										. '/../playlists/injected.json'
									),
									true
								),
								$playlists_filter,
								ARRAY_FILTER_USE_BOTH
							)
					),
					static function (string $maybe) : bool {
						return false !== strtotime($maybe);
					}
			);

			asort($dated_playlists);

			$cache = array_reverse($dated_playlists, true);
		}

		return $cache;
	}

	public function clear_cache() : void
	{
		$playlists = array_keys(array_filter(
			(array) json_decode(
				(string) file_get_contents(
					__DIR__
					. '/../playlists/youtube.json'
				)
			),
			'is_string',
			ARRAY_FILTER_USE_KEY
		));

		$directory = realpath(__DIR__ . '/../data/api-cache/playlists/');

		if ( ! is_string($directory)) {
			throw new RuntimeException('Could not find playlists api cache!');
		}
		if ( ! is_file(__DIR__ . '/../playlists-recently-updated.json')) {
			throw new RuntimeException('Could not find recently updated playlists cache!');
		}

		/** @var list<string> */
		$recently_updated = array_map(
			static function (string $playlist_id) : string {
				return $playlist_id . '.json';
			},
			array_keys(json_decode(
				file_get_contents(
					__DIR__ . '/../playlists-recently-updated.json'),
				true,
				JSON_THROW_ON_ERROR
			))
		);

		$to_delete = array_values(array_filter(
			glob(__DIR__ . '/../data/api-cache/playlists/*.json'),
			static function (string $maybe) use ($playlists, $directory) : bool {
				$maybe_directory = realpath(dirname($maybe));

				return
					is_string($maybe_directory)
					&& $maybe_directory === $directory
					&& ! in_array(
						pathinfo($maybe, PATHINFO_FILENAME),
						$playlists,
						true
					)
				;
			}
		));

		foreach ($to_delete as $file) {
			if (in_array(basename($file), $recently_updated, true)) {
				unlink($file);
			}
		}
	}

	/**
	 * @return array<string, false|array>
	 */
	public function getStatistics(
		string $lead_video_id,
		string ...$video_ids
	) : array {
		array_unshift($video_ids, $lead_video_id);

		$processed_video_ids = array_combine(
			$video_ids,
			array_map(
				static function (string $video_id) : string {
					$prefixed = vendor_prefixed_video_id($video_id);

					return (string) preg_replace('/,.+$/', '', $prefixed);
				},
				$video_ids
			)
		);

		$processed_video_ids = array_filter(
			$processed_video_ids,
			static function (string $maybe) : bool {
				return false === mb_strpos($maybe, ',');
			}
		);

		$lookup = [];

		foreach ($processed_video_ids as $video_id => $aliased) {
			if ( ! isset($lookup[$aliased])) {
				$lookup[$aliased] = [];
			}

			$lookup[$aliased][] = $video_id;
		}

		$cached = [];

		$dir = realpath(__DIR__ . '/../data/api-cache/statistics/');

		if ( ! is_string($dir)) {
			throw new RuntimeException(
				'Could not find video statistics cache directory!'
			);
		}

		$time = time();

		$to_fetch = [];

		foreach ($processed_video_ids as $aliased) {
			$cache_file = realpath($dir . '/' . $aliased . '.json');

			if (
				is_string($cache_file)
				&& is_file($cache_file)
				&& 0 === mb_strpos($cache_file, $dir)
				&& ($time - filemtime($cache_file)) < 86400
			) {
				/**
				 * @var array{
				 *	commentCount: 0|positive-int,
				 *	dislikeCount: 0|positive-int,
				 *	favoriteCount: 0|positive-int,
				 *	likeCount: 0|positive-int,
				 *	viewCount: 0|positive-int
				 * }
				 */
				$json_from_cache = json_decode(
					file_get_contents($cache_file),
					true
				);

				$cached[vendor_prefixed_video_id($aliased)] = $json_from_cache;
			} elseif (
				! in_array($aliased, $to_fetch, true)
				&& (bool) preg_match(
					'/^yt-[^,]{11}$/',
					vendor_prefixed_video_id($aliased)
				)
			) {
				$to_fetch[] = $aliased;
			}
		}

		$chunks = array_chunk(
			$to_fetch,
			50
		);

		foreach ($chunks as $chunk_video_ids) {
			$chunk = $this->listStatistics(
				[
					'id' => implode(',', array_map(
						static function (string $video_id) : string {
							return mb_substr($video_id, 3);
						},
						$chunk_video_ids
					)),
				]
			);

			foreach ($chunk as $video_id => $data) {
				$cache_file = (
					$dir
					. '/'
					. vendor_prefixed_video_id($video_id)
					. '.json'
				);
				file_put_contents(
					$cache_file,
					json_encode($data, JSON_PRETTY_PRINT)
				);

				foreach ($lookup['yt-' . $video_id] as $requested) {
					$cached[$requested] = $data;
				}
			}
		}

		return array_combine(
			array_map(
				static function (string $video_id) : string {
					return vendor_prefixed_video_id($video_id);
				},
				array_keys($cached)
			),
			$cached
		);
	}

	private function service_playlists() : Google_Service_YouTube_Resource_Playlists
	{
		/** @var Google_Service_YouTube_Resource_Playlists */
		return $this->service->playlists;
	}

	private function service_playlistItems() : Google_Service_YouTube_Resource_PlaylistItems
	{
		/** @var Google_Service_YouTube_Resource_PlaylistItems */
		return $this->service->playlistItems;
	}

	private function service_videos() : Google_Service_YouTube_Resource_Videos
	{
		/** @var Google_Service_YouTube_Resource_Videos */
		return $this->service->videos;
	}

	/**
	 * @psalm-type API_RESULT = object{
	 *	kind: 'youtube#playlist',
	 *	etag: string,
	 *	id: string,
	 *	snippet: object{
	 *		publishedAt: string,
	 *		channelId: string,
	 *		title: string,
	 *		description: string,
	 *		thumbnails: object<string, object{
	 *			url: string,
	 *			width: 0|positive-int,
	 *			height: 0|positive-int
	 *		}>,
	 *		channelTitle: string,
	 *		defaultLanguage: string,
	 *		localized: object{
	 *			title: string,
	 *			description: string
	 *		}
	 *	},
	 *	status: object{
	 *		privacyStatus: string
	 *	}
	 * }
	 *
	 * @param array<string, string> $out
	 *
	 * @return object<string, API_RESULT>
	 */
	private function listPlaylists(
		array $args = [],
		object $out = null
	) : object {
		if (null === $out) {
			$out = new stdClass();
		}

		/**
		 * @var object{
		 *	nextPageToken?:string,
		 *	items:list<API_RESULT>
		 * }
		 */
		$response = $this->service_playlists()->listPlaylists(
			'id,snippet,status',
			$args
		);

		foreach ($response->items as $playlist) {
			$out->{$playlist->id} = $playlist;
		}

		if (isset($response->nextPageToken)) {
			$args['pageToken'] = $response->nextPageToken;

			$out = $this->listPlaylists($args, $out);
		}

		return $out;
	}

	/**
	 * @param array{playlistId:string, pageToken?:string} $args
	 * @param list<string> $out
	 *
	 * @return list<string>
	 */
	public function listPlaylistItems(
		array $args,
		array $out = []
	) : array {
		/**
		 * @var object{
		 *	nextPageToken?: string,
		 *	items: iterable<Google_Service_YouTube_PlaylistItem>
		 * }
		 */
		$response = $this->service_playlistItems()->listPlaylistItems(
			implode(',', [
				'id',
				'snippet',
				'status',
			]),
			$args
		);

		foreach ($response->items as $playlist_item) {
			/** @var Google_Service_YouTube_VideoSnippet */
			$video_snippet = $playlist_item->snippet;

			/** @var Google_Service_YouTube_ResourceId */
			$video_snippet_resourceId = $video_snippet->resourceId;

			/** @var Google_Service_YouTube_PlaylistItemStatus */
			$status = $playlist_item->status;

			if ('private' === $status->privacyStatus) {
				continue;
			}

			$out[] = $video_snippet_resourceId->videoId;
		}

		if (isset($response->nextPageToken)) {
			$args['pageToken'] = $response->nextPageToken;

			$out = $this->listPlaylistItems($args, $out);
		}

		return $out;
	}

	/**
	 * @param array{id:string, pageToken?:string} $args
	 * @param array<string, array{0:string, 1:list<string>}> $out
	 *
	 * @return array<string, array{0:string, 1:list<string>}>
	 */
	private function listVideos(array $args, array $out = []) : array
	{
		/**
		 * @var object{
		 *	pageToken?:string,
		 *	items: iterable<object{
		 *		id:string,
		 *		snippet:object{
		 *			title:string,
		 *			description:string,
		 *			tags:list<string>|null
		 *		}
		 *	}>
		 * }
		 */
		$response = $this->service_videos()->listVideos(
			'snippet',
			$args
		);

		foreach ($response->items as $item) {
			$out[$item->id] = [
				$item->snippet->title,
				$item->snippet->tags ?? [],
				$item->snippet->description,
			];
		}

		if (isset($response->nextPageToken)) {
			$args['pageToken'] = (string) $response->nextPageToken;

			$out = $this->listVideos($args, $out);
		}

		return $out;
	}

	/**
	 * @param array{id:string, pageToken?:string} $args
	 * @param array<string, array{
	 *	commentCount: 0|positive-int,
	 *	dislikeCount: 0|positive-int,
	 *	favoriteCount: 0|positive-int,
	 *	likeCount: 0|positive-int,
	 *	viewCount: 0|positive-int
	 * }> $out
	 *
	 * @return array<string, array{
	 *	commentCount: 0|positive-int,
	 *	dislikeCount: 0|positive-int,
	 *	favoriteCount: 0|positive-int,
	 *	likeCount: 0|positive-int,
	 *	viewCount: 0|positive-int
	 * }>
	 */
	private function listStatistics(
		array $args,
		array $out = []
	) : array {
		/**
		 * @var object{
		 *	pageToken?:string,
		 *	items: iterable<object{
		 *		id:string,
		 *		statistics: object{
		 *			commentCount: 0|positive-int,
		 *			dislikeCount: 0|positive-int,
		 *			favoriteCount: 0|positive-int,
		 *			likeCount: 0|positive-int,
		 *			viewCount: 0|positive-int
		 *		},
		 *		snippet:object{
		 *			title:string,
		 *			tags:list<string>|null
		 *		}
		 *	}>
		 * }
		 */
		$response = $this->service_videos()->listVideos(
			'statistics',
			$args
		);

		foreach ($response->items as $item) {
			$out[$item->id] = [
				'commentCount' => $item->statistics->commentCount,
				'dislikeCount' => $item->statistics->dislikeCount,
				'favoriteCount' => $item->statistics->favoriteCount,
				'likeCount' => $item->statistics->likeCount,
				'viewCount' => $item->statistics->viewCount,
			];
		}

		if (isset($response->nextPageToken)) {
			$args['pageToken'] = (string) $response->nextPageToken;

			$out = $this->listStatistics($args, $out);
		}

		return $out;
	}

	private function sort_playist_items() : void
	{
		$videos = array_map('current', $this->fetch_all_videos_in_playlists());

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
