<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function count;

require_once(__DIR__ . '/../vendor/autoload.php');

array_shift($argv);

/** @var list<string> */
$videos = array_values(array_filter(
	array_map(
		__NAMESPACE__ . '\vendor_prefixed_video_id',
		$argv
	),
	static function (string $maybe) : bool {
		return (bool) preg_match('/^yt-/', $maybe);
	}
));

if (count($videos)) {
	foreach ($videos as $video_id) {
		$video_id = preg_replace(
			'/^yt-([^,]+).*/',
			'$1',
			vendor_prefixed_video_id($video_id)
		);

		$descriptions_cache =
			__DIR__
			. '/app/data/api-cache/video-descriptions/'
			. $video_id
			. '.json';

		if (is_file($descriptions_cache)) {
			unlink($descriptions_cache);
		}
	}
}
