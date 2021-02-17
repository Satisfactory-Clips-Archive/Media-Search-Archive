<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_filter;
use const ARRAY_FILTER_USE_KEY;
use function array_keys;
use function array_map;
use function array_values;
use function dirname;
use function file_get_contents;
use function glob;
use function in_array;
use function is_string;
use function json_decode;
use function pathinfo;
use const PATHINFO_FILENAME;
use function realpath;
use RuntimeException;

$playlists = array_keys(array_filter(
	(array) json_decode(
		(string) file_get_contents(
			__DIR__
			. '/playlists/coffeestainstudiosdevs/satisfactory.json'
		)
	),
	'is_string',
	ARRAY_FILTER_USE_KEY
));

$directory = realpath(__DIR__ . '/data/api-cache/playlists/');

if ( ! is_string($directory)) {
	throw new RuntimeException('Could not find playlists api cache!');
}

$to_delete = array_values(array_filter(
	glob(__DIR__ . '/data/api-cache/playlists/*.json'),
	static function (string $maybe) use ($playlists, $directory) : bool {
		$maybe_directory = realpath(dirname($maybe));

		return
			is_string($maybe_directory)
			&& $maybe_directory === $directory
			&& ! in_array(
				pathinfo($maybe, PATHINFO_FILENAME),
				$playlists,
				true
			)
		;
	}
));

array_map('unlink', $to_delete);
