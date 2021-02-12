<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_filter;
use function file_get_contents;
use function implode;
use function json_decode;
use function mb_strlen;
use function natcasesort;
use function preg_match;
use function rawurlencode;
use function sprintf;

require_once (__DIR__ . '/vendor/autoload.php');

/** @var list<string> */
$data = json_decode(
	file_get_contents(__DIR__ . '/skipping-transcriptions.json'),
	true
);

$data = array_filter(
	$data,
	static function (string $maybe) : bool {
		return ! preg_match('/^yt-.+,/', $maybe);
	}
);

natcasesort($data);

$lines = [];

foreach ($data as $video_id) {
	if (11 === mb_strlen($video_id)) {
		$lines[] = (
			'* [ ] '
			. sprintf(
				'https://studio.youtube.com/video/%s/translations',
				rawurlencode($video_id)
			)
			. ' '
			. sprintf('https://youtu.be/%s', rawurlencode($video_id))
		);
	} else {
		$lines[] = '* [ ] ' . video_url_from_id($video_id);
	}
}

echo "\n", implode("\n", $lines), "\n";
