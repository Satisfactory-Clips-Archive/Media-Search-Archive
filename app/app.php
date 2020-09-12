<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\TwitchClipNotes;

use function array_diff;
use function array_filter;
use function array_keys;
use const FILE_APPEND;
use function file_get_contents;
use function file_put_contents;
use Google_Client;
use Google_Service_YouTube;
use function http_build_query;
use function implode;
use function in_array;
use function is_file;
use function ksort;
use function preg_match_all;

require_once(__DIR__ . '/vendor/autoload.php');

$client = new Google_Client();
$client->setApplicationName('Twitch Clip Notes');
$client->setScopes([
	'https://www.googleapis.com/auth/youtube.readonly',
]);

$client->setAuthConfig(__DIR__ . '/google-auth.json');
$client->setAccessType('offline');

$http = $client->authorize();

$service = new Google_Service_YouTube($client);

$other_playlists_on_channel = [];

$playlists = [
	'PLbjDnnBIxiEoaZoarNpiJyV5QJ_pYIRyW' => __DIR__ . '/../coffeestainstudiosdevs/satisfactory/2020-09-01.md',
	'PLbjDnnBIxiEpupaMEI10RkF5iaX8X89fF' => __DIR__ . '/../coffeestainstudiosdevs/satisfactory/2020-09-08.md',
];

/** @var array<string, array<string, string>> */
$videos = [];

/** @var array<string, list<string>> */
$already_in_markdown = [];

/** @var list<string> */
$autocategorise = [];

$fetch_videos = static function (array $args, string $playlist_id, array &$videos) use (
	$service,
	&$fetch_videos
) : void {
	$args['playlistId'] = $playlist_id;

	$response = $service->playlistItems->listPlaylistItems(
		implode(',', [
			'id',
			'snippet',
			'contentDetails',
		]),
		$args
	);

	foreach ($response->items as $video) {
		$videos[$playlist_id][
			$video->snippet->resourceId->videoId
		] = $video->snippet->title;
	}

	if (isset($response->nextPageToken)) {
		$args['pageToken'] = $response->nextPageToken;

		$fetch_videos($args, $playlist_id, $videos);
	}
};

foreach ($playlists as $playlist_id => $markdown_path) {
	$videos[$playlist_id] = [];

	$fetch_videos(
		[
			'maxResults' => 50,
		],
		$playlist_id,
		$videos
	);

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

$fetch_all_playlists = static function (array $args) use (
	&$other_playlists_on_channel,
	$service,
	$fetch_videos,
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

			$fetch_videos(
				['maxResults' => 50],
				$playlist->id,
				$other_playlists_on_channel[$playlist->id][1]
			);

			$other_playlists_on_channel[$playlist->id][1] = array_keys(
				$other_playlists_on_channel[$playlist->id][1][$playlist->id]
			);
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
