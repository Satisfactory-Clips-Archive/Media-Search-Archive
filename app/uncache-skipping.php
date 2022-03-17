<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

require_once(__DIR__ . '/../vendor/autoload.php');

$ids = array_map(
	__NAMESPACE__ . '\vendor_prefixed_video_id',
	SkippingTranscriptions::i()->video_ids
);

$ids += array_map(
	__NAMESPACE__ . '\vendor_prefixed_video_id',
	array_values(array_filter(
		(array) json_decode(
			file_get_contents(__DIR__ . '/data/skipping-cards.json')
		),
		'is_string'
	))
);

$argv += array_unique($ids);

require_once(__DIR__ . '/uncache-pages.php');

file_put_contents(__DIR__ . '/data/skipping-cards.json', '[]');
file_put_contents(__DIR__ . '/skipping-transcriptions.json', '[]');
