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

$api = new YouTubeApiWrapper();
echo 'YouTube API Wrapper instantiated', "\n";

$slugify = new Slugify();

$skipping = SkippingTranscriptions::i();
echo 'SkippingTranscriptions instantiated', "\n";

$injected = new Injected($api, $slugify, $skipping);
echo 'Injected instantiated', "\n";

$captions_source = captions_source($injected);

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

		$captions_source->remove_cached_file($video_id . '.html');
		$captions_cache = captions_json_cache_filename($video_id);

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

	$skipping_cache = __DIR__ . '/../Media-Search-Archive-Data/data/skipping-cards.json';

	file_put_contents(
		$skipping_cache,
		json_encode_pretty(array_values(array_filter(
			json_decode(file_get_contents($skipping_cache)),
			static function (string $maybe) use ($videos) : bool {
				return ! in_array($maybe, $videos, true);
			}
		)))
	);

	$missing_subcategory = __DIR__ . '/../Media-Search-Archive-Data/data/youtube-video-subcategories--missing.json';

	file_put_contents(
		$missing_subcategory,
		json_encode_pretty(array_values(array_filter(
			json_decode(file_get_contents($missing_subcategory)),
			static function (string $maybe) use ($videos) : bool {
				return ! in_array($maybe, $videos, true);
			}
		)))
	);
}
