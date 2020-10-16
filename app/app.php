<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\TwitchClipNotes;

use function array_diff;
use function array_filter;
use function array_keys;
use function basename;
use Benlipp\SrtParser\Parser;
use function count;
use function date;
use const FILE_APPEND;
use function file_get_contents;
use function file_put_contents;
use Google_Client;
use Google_Service_YouTube;
use GuzzleHttp\Exception\ClientException;
use function http_build_query;
use function implode;
use function in_array;
use function is_file;
use function ksort;
use function mb_substr;
use function preg_match_all;
use function rawurlencode;
use function sha1_file;
use function sprintf;
use function strtotime;

$transcriptions = in_array('--transcriptions', $argv, true);
$clear_nopes = in_array('--clear-nopes', $argv, true);

require_once(__DIR__ . '/vendor/autoload.php');

$client = new Google_Client();
$client->setApplicationName('Twitch Clip Notes');
$client->setScopes([
	'https://www.googleapis.com/auth/youtube.readonly',
	'https://www.googleapis.com/auth/youtube.force-ssl',
]);

$client->setAuthConfig(__DIR__ . '/google-auth.json');
$client->setAccessType('offline');

$http = $client->authorize();

$service = new Google_Service_YouTube($client);

$other_playlists_on_channel = [];

$playlists = [
	'PLbjDnnBIxiEoaZoarNpiJyV5QJ_pYIRyW' => __DIR__ . '/../coffeestainstudiosdevs/satisfactory/2020-09-01.md',
	'PLbjDnnBIxiEpupaMEI10RkF5iaX8X89fF' => __DIR__ . '/../coffeestainstudiosdevs/satisfactory/2020-09-08.md',
	'PLbjDnnBIxiEpYksFx1ybkbcrRmNylKVCO' => __DIR__ . '/../coffeestainstudiosdevs/satisfactory/2020-09-15.md',
	'PLbjDnnBIxiErSNk3fWuh3ghlsE-l9lAvi' => __DIR__ . '/../coffeestainstudiosdevs/satisfactory/2020-09-22.md',
	'PLbjDnnBIxiEoPGFyx8wiNv4pEg4BAUD5g' => __DIR__ . '/../coffeestainstudiosdevs/satisfactory/2020-09-29.md',
	'PLbjDnnBIxiEry9tVPDAYqaNR7TEIw0lrs' => __DIR__ . '/../coffeestainstudiosdevs/satisfactory/2020-10-06.md',
	'PLbjDnnBIxiEq7lNu6NUJUNoxX_juk7mbg' => __DIR__ . '/../coffeestainstudiosdevs/satisfactory/2020-10-13.md',
];

/** @var array<string, array<string, string>> */
$videos = [];

/** @var array<string, list<string>> */
$video_tags = [];

$exclude_from_absent_tag_check = [
	'4_cYnq746zk', // official merch announcement video
];

/** @var array<string, list<string>> */
$already_in_markdown = [];

/** @var list<string> */
$autocategorise = [];

/** @var list<string> */
$already_in_faq = [];

$cache = json_decode(
	file_get_contents(__DIR__ . '/cache.json') ?: '[]',
	true
);

foreach (($cache['videoTags'] ?? []) as $video_id => $data) {
	[$etag, $tags] = $data;

	$video_tags[$video_id] = $tags;
}

$update_cache = function () use (&$cache) : void {
	file_put_contents(
		__DIR__ . '/cache.json',
		json_encode($cache, JSON_PRETTY_PRINT)
	);
};

$object_cache_captions = [];
$object_cache_videos = [];

$fetch_videos = static function (
	array $args,
	string $playlist_id,
	array &$videos,
	array &$video_tags
) use (
	$http,
	$playlists,
	$service,
	&$cache,
	$update_cache,
	&$object_cache_captions,
	$transcriptions,
	&$fetch_videos
) : void {
	$args['playlistId'] = $playlist_id;
	$cache['playlists'] = $cache['playlists'] ?? [];
	$cache['playlistItems'] = $cache['playlistItems'] ?? [];
	$cache['captions'] = $cache['captions'] ?? [];
	$cache['videoTags'] = $cache['videoTags'] ?? [];

	$response = $service->playlistItems->listPlaylistItems(
		implode(',', [
			'id',
			'snippet',
			'contentDetails',
		]),
		$args
	);

	foreach ($response->items as $video) {
		$video_id = $video->snippet->resourceId->videoId;

		if (
			! isset($cache['playlistItems'][$video_id])
			|| $cache['playlistItems'][$video_id][0] !== $video->etag
		) {
			$tag_response = $service->videos->listVideos(
				'snippet',
				[
					'id' => $video_id,
				]
			);

			if (
				! isset($cache['videoTags'][$video_id])
				|| $cache['videoTags'][$video_id][0] !== $tag_response->etag
			) {
				if (isset($tag_response->items[0]->snippet->tags)) {
					$cache['videoTags'][$video_id] = [
						$tag_response->etag,
						$tag_response->items[0]->snippet->tags,
					];
				} else {
					$cache['videoTags'][$video_id] = [
						$tag_response->etag,
						[],
					];
				}

				$update_cache();
			}

			$cache['playlistItems'][$video_id] = [
				$video->etag,
				$video->snippet->title,
			];

			$update_cache();
		}

		$videos[$playlist_id][$video_id] = $cache['playlistItems'][$video_id][1];
	}

	if (isset($response->nextPageToken)) {
		$args['pageToken'] = $response->nextPageToken;

		$fetch_videos($args, $playlist_id, $videos, $video_tags);
	}
};

$cache['playlists'] = $cache['playlists'] ?? [];

foreach ($playlists as $playlist_id => $markdown_path) {
	$videos[$playlist_id] = [];

	$response = $service->playlists->listPlaylists(
		'id,snippet',
		[
			'maxResults' => 1,
			'id' => $playlist_id,
		]
	);

	if (
		! isset($cache['playlists'][$playlist_id])
		|| $cache['playlists'][$playlist_id][0] !== $response->etag
	) {
		$fetch_videos(
			[
				'maxResults' => 50,
			],
			$playlist_id,
			$videos,
			$video_tags
		);
		$cache['playlists'][$playlist_id] = [
			$response->etag,
			$response->items[0]->snippet->title,
			array_keys($videos[$playlist_id]),
		];

		$update_cache();
	} else {
		foreach ($cache['playlists'][$playlist_id][2] as $video_id) {
			$videos[$playlist_id][$video_id] = $cache['playlistItems'][$video_id][1];
		}
	}

	if ( ! is_file($markdown_path)) {
		file_put_contents($markdown_path, "\n");

		$autocategorise[] = $playlist_id;
	}

	$contents = file_get_contents($markdown_path);

	preg_match_all(
		'/https:\/\/www\.youtube\.com\/watch\?v=([^\n\s\*]+)/',
		$contents,
		$matches
	);

	$already_in_markdown[$playlist_id] = $matches[1];
}

preg_match_all(
	'/https:\/\/www\.youtube\.com\/watch\?v=([^\n\s\*]+)/',
	file_get_contents(
		__DIR__ .
		'/../coffeestainstudiosdevs/satisfactory/FAQ.md'
	),
	$matches
);

$already_in_faq = $matches[1];

$fetch_all_playlists = static function (array $args) use (
	&$other_playlists_on_channel,
	&$video_tags,
	$service,
	$fetch_videos,
	&$cache,
	$update_cache,
	&$videos,
	$playlists
) : void {
	$response = $service->playlists->listPlaylists(
		'id,snippet',
		$args
	);

	foreach ($response->items as $playlist) {
		if ( ! isset($playlists[$playlist->id])) {
			$other_playlists_on_channel[$playlist->id] = [
				$playlist->snippet->title,
				[],
			];

			$cache_response = $service->playlists->listPlaylists(
				'id,snippet',
				[
					'maxResults' => 1,
					'id' => $playlist->id,
				]
			);

			if (
				! isset($cache['playlists'][$playlist->id])
				|| $cache['playlists'][$playlist->id][0] !== $cache_response->etag
			) {
				$fetch_videos(
					['maxResults' => 50],
					$playlist->id,
					$other_playlists_on_channel[$playlist->id][1],
					$video_tags
				);

				$cache['playlists'][$playlist->id] = [
					$cache_response->etag,
					$playlist->snippet->title,
					array_keys($other_playlists_on_channel[$playlist->id][1][$playlist->id]),
				];

				$update_cache();

				$other_playlists_on_channel[$playlist->id][1] = array_keys(
					$other_playlists_on_channel[$playlist->id][1][$playlist->id]
				);
			} else {
				foreach ($cache['playlists'][$playlist->id][2] as $video_id) {
					$videos[$playlist->id][$video_id] = $cache['playlistItems'][$video_id][1];
					$other_playlists_on_channel[$playlist->id][1][] = $video_id;
				}
			}
		}
	}

	if (isset($response->nextPageToken)) {
		$args['pageToken'] = $response->nextPageToken;

		$fetch_all_playlists($args);
	}
};

$fetch_all_playlists([
	'channelId' => 'UCJamaIaFLyef0HjZ2LBEz1A',
	'maxResults' => 50,
]);

$videos_to_add = [];

foreach ($already_in_markdown as $playlist_id => $videos_in_markdown) {
	$videos_to_add[$playlist_id] = array_diff(array_keys($videos[$playlist_id]), $videos_in_markdown);
}

$videos_to_add = array_filter($videos_to_add, 'count');

foreach ($videos_to_add as $playlist_id => $video_ids) {
	$content_arrays = [
		'Related answer clips' => [],
		'Single video clips' => [],
	];

	foreach ($video_ids as $video_id) {
		$found = false;

		foreach ($other_playlists_on_channel as $playlist_data) {
			[$title, $other_playlist_video_ids] = $playlist_data;

			if (in_array($video_id, $other_playlist_video_ids, true)) {
				$found = true;

				if ( ! isset($content_arrays['Related answer clips'][$title])) {
					$content_arrays['Related answer clips'][$title] = [];
				}
				$content_arrays['Related answer clips'][$title][] = $video_id;
			}
		}

		if ( ! $found) {
			$content_arrays['Single video clips'][] = $video_id;
		}
	}

	ksort($content_arrays['Related answer clips']);

	file_put_contents(
		$playlists[$playlist_id],
		"\n" . '# Related answer clips' . "\n",
		FILE_APPEND
	);

	foreach ($content_arrays['Related answer clips'] as $title => $video_ids) {
		file_put_contents(
			$playlists[$playlist_id],
			"\n" . '## ' . $title . "\n",
			FILE_APPEND
		);

		foreach ($video_ids as $video_id) {
			file_put_contents(
				$playlists[$playlist_id],
				(
					'* ' .
					$videos[$playlist_id][$video_id] .
					' https://www.youtube.com/watch?' .
					http_build_query([
						'v' => $video_id,
					]) .
					"\n"
				),
				FILE_APPEND
			);
		}
	}

	file_put_contents(
		$playlists[$playlist_id],
		"\n" . '# Single video clips' . "\n",
		FILE_APPEND
	);

	foreach ($content_arrays['Single video clips'] as $video_id) {
		file_put_contents(
			$playlists[$playlist_id],
			(
				'* ' .
				$videos[$playlist_id][$video_id] .
				' https://www.youtube.com/watch?' .
				http_build_query([
					'v' => $video_id,
				]) .
				"\n"
			),
			FILE_APPEND
		);
	}
}

/** @var array<string, list<array{0:string, 1:string}>> */
$absent_from_faq = [];

foreach ($already_in_faq as $id) {
	if (in_array($id, $exclude_from_absent_tag_check, true)) {
		continue;
	}

	if (
		! isset($video_tags[$id]) ||
		! in_array('faq', $video_tags[$id], true)
	) {
		echo
			'Missing FAQ tag: ',
			' https://www.youtube.com/watch?',
			http_build_query([
				'v' => $id,
			]),
			"\n";
	}
}

foreach ($video_tags as $id => $tags) {
	if (
		in_array('faq', $tags, true)
		&& ! in_array($id, $already_in_faq, true)
	) {
		foreach ($videos as $playlist_id => $video_ids) {
			if (isset($video_ids[$id]) && isset($playlists[$playlist_id])) {
				$date = mb_substr(basename($playlists[$playlist_id]), 0, -3);

				if ( ! isset($absent_from_faq[$date])) {
					$absent_from_faq[$date] = [];
				}

				$absent_from_faq[$date][] = [$playlist_id, $id];
			}
		}
	}
}

if (count($absent_from_faq) > 0) {
	file_put_contents(
		(
			__DIR__ .
			'/../coffeestainstudiosdevs/satisfactory/FAQ.md'
		),
		(
			"\n" .
			'# Pending categorisation in FAQ' .
			"\n" .
			"\n"
		),
		FILE_APPEND
	);

	ksort($absent_from_faq);

	foreach ($absent_from_faq as $date => $video_ids) {
		file_put_contents(
			(
				__DIR__ .
				'/../coffeestainstudiosdevs/satisfactory/FAQ.md'
			),
			(
				"\n" .
				'## ' .
				date('F jS, Y', (int) strtotime($date)) .
				"\n"
			),
			FILE_APPEND
		);

		foreach ($video_ids as $data) {
			[$playlist_id, $video_id] = $data;

			file_put_contents(
				(
					__DIR__ .
					'/../coffeestainstudiosdevs/satisfactory/FAQ.md'
				),
				(
					'* ' .
					$videos[$playlist_id][$video_id] .
					' https://www.youtube.com/watch?' .
					http_build_query([
						'v' => $video_id,
					]) .
					"\n"
				),
				FILE_APPEND
			);
		}
	}
}

if ($transcriptions) {
	$checked = 0;

	foreach(array_keys($playlists) as $playlist_id) {
		if ( ! isset($videos[$playlist_id])) {
			echo 'skipping: ', $playlist_id, "\n";
			continue;
		}

		foreach(array_keys($videos[$playlist_id]) as $video_id) {

			$subtitles_file = __DIR__ . '/captions/' . $video_id . '.srt';

			if (
				$clear_nopes
				&& is_file($subtitles_file)
				&& '76272dc4faf660733711f58c736830d27159fb55' === sha1_file(
					$subtitles_file
				)
			) {
				unlink($subtitles_file);

				echo 'cleared: ', $subtitles_file, "\n";
			}

			if ( ! is_file($subtitles_file)) {
				if ( ! isset($object_cache_captions[$video_id])) {
					$captions = $service->captions->listCaptions($video_id, 'snippet');
					$object_cache_captions[$video_id] = $captions;
				} else {
					$captions = $object_cache_captions[$video_id];
				}

				if (count($captions->items) <= 0) {
					echo
						sprintf(
							'Subtitles needed: https://studio.youtube.com/video/%s/translations',
							rawurlencode($video_id)
						),
						"\n";
				}

				if (
					count($captions->items) > 0
					&& ($etag = $captions->items[0]->etag)
					&& (
						! isset($cache['captions'][$video_id])
						|| $cache['captions'][$video_id] !== $etag
					)
				) {
					try {
						$captions = $http->request(
							'GET',
							sprintf(
								'/youtube/v3/captions/%s',
								rawurlencode($captions->items[0]->id)
							),
							[
								'query' => [
									'tfmt' => 'srt',
								],
							]
						);

						file_put_contents(
							$subtitles_file,
							$captions->getBody()->getContents()
						);
					} catch (ClientException $e) {
						echo
							'Could not download subtitles for ' .
							(
								'https://www.youtube.com/watch?' .
								http_build_query([
									'v' => $video_id,
								])
							),
							"\n",
							$e->getMessage(),
							"\n";
					}
					$cache['captions'][$video_id] = $etag;
					$update_cache();
				} else {
					file_put_contents($subtitles_file, 'nope');
				}
			}

			if (
				is_file($subtitles_file)
				&& '76272dc4faf660733711f58c736830d27159fb55' !== sha1_file(
					$subtitles_file
				)
			) {
				$parser = new Parser();

				$parser->loadFile($subtitles_file);

				$transcriptions_file = (
					__DIR__ .
					'/../coffeestainstudiosdevs/satisfactory/transcriptions/yt-' .
					$video_id .
					'.md'
				);

				$date = mb_substr(basename($playlists[$playlist_id]), 0, -3);

				file_put_contents(
					$transcriptions_file,
					(
						'# [' . date('F jS, Y', (int) strtotime($date)) .
						' livestream](../' . $date . '.md)' .
						"\n" .
						'## ' . $videos[$playlist_id][$video_id] .
						"\n" .
						(
							'https://www.youtube.com/watch?' .
							http_build_query([
								'v' => $video_id,
							])
						) .
						"\n"
					)
				);

				foreach ($parser->parse() as $caption_line) {
					file_put_contents(
						$transcriptions_file,
						(
							'> ' . $caption_line->text .
							"\n" .
							'> ' .
							"\n"
						),
						FILE_APPEND
					);
				}
			}

			++$checked;
		}
	}

	echo
		sprintf(
			'%s subtitles checked of %s videos cached',
			$checked,
			count($cache['playlistItems'])
		),
		"\n";
}
