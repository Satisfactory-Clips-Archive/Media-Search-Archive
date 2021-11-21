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

		remove_captions_cache_file($video_id . '.html');
	}
}
