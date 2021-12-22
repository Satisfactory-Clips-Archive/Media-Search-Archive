<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use const JSON_PRETTY_PRINT;

function dump_video_title_kvp() : void
{
	$api = new YouTubeApiWrapper();

	$kvp = array_map(
		static function (array $data) : string {
			return $data[0];
		},
		$api->fetch_all_videos_in_playlists()
	);

	file_put_contents(
		__DIR__ . '/../../tests/fixtures/title-kvp.json',
		json_encode($kvp, JSON_PRETTY_PRINT)
	);
}
