<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

require_once(__DIR__ . '/../vendor/autoload.php');

$data = json_decode(file_get_contents(__DIR__ . '/data/yt-shorts.json'), true);

$api = new YouTubeApiWrapper();

$shorts_video_ids = [];

if (!isset($data['pending'])) {
	$data['pending'] = [];
}

foreach ($data['videos'] as $long_form_video_id => $shorts_ids) {
	$shorts_video_ids = array_merge($shorts_video_ids, $shorts_ids);
}

foreach ($data['playlists'] as $playlist_id) {
	if (!isset($data['pending'][$playlist_id])) {
		$data['pending'][$playlist_id] = [];
	}

	foreach ($api->listPlaylistItems(['playlistId' => $playlist_id]) as $video_id) {
		if (!in_array($video_id, $shorts_video_ids, true)) {
			if (!in_array($video_id, $data['pending'][$playlist_id], true)) {
				$data['pending'][$playlist_id][] = $video_id;
			}
		}
	}

	$data['pending'][$playlist_id] = array_values(array_filter(
		$data['pending'][$playlist_id],
		static function (string $maybe) use($shorts_video_ids): bool {
			return !in_array($maybe, $shorts_video_ids, true);
		}
	));

	if (!count($data['pending'][$playlist_id])) {
		unset($data['pending'][$playlist_id]);
	}
}

file_put_contents(__DIR__ . '/data/yt-shorts.json', json_encode_pretty($data) . "\n");

if (count($data['pending'])) {
	throw new \RuntimeException('Shorts sources not mapped for some videos!');
}
