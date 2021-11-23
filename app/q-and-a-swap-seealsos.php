<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function in_array;

require_once(__DIR__ . '/../vendor/autoload.php');

[, $replace_this, $with_this] = $argv;

/**
 * @var array<string, array{
 *	seealso?:list<string>
 * }>
 */
$data = json_decode(
	file_get_contents(
		__DIR__
		. '/data/q-and-a.json'
	),
	true
);

foreach (array_keys($data) as $video_id) {
	if (
		isset($data[$video_id]['seealso'])
		&& in_array($replace_this, $data[$video_id]['seealso'], true)
	) {
		$seealso = array_combine(
			$data[$video_id]['seealso'],
			$data[$video_id]['seealso']
		);

		$seealso[$replace_this] = $with_this;

		$data[$video_id]['seealso'] = array_values(array_unique($seealso));
	}
}

file_put_contents(
	__DIR__ . '/data/q-and-a.json',
	json_encode_pretty($data)
);
