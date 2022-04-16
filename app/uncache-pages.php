<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function count;
use function in_array;

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
		$captions_cache =
			__DIR__
			. '/captions-cache/'
			. vendor_prefixed_video_id($video_id)
			. '.json';

		if (is_file($captions_cache)) {
			unlink($captions_cache);
		}

		$card_cache =
			__DIR__
			. '/cards-cache/'
			. vendor_prefixed_video_id($video_id)
			. '.json';

		if (is_file($card_cache)) {
			unlink($card_cache);
		}
	}

	$skipping_cache = __DIR__ . '/data/skipping-cards.json';

	file_put_contents(
		$skipping_cache,
		json_encode_pretty(array_values(array_filter(
			json_decode(file_get_contents($skipping_cache)),
			static function (string $maybe) use ($videos) : bool {
				return ! in_array($maybe, $videos, true);
			}
		)))
	);
}
