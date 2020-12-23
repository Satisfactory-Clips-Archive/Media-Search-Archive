<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\TwitchClipNotes;

use function array_values;
use function basename;
use function file_get_contents;
use function file_put_contents;
use function http_build_query;
use function is_file;
use function preg_match;
use function preg_quote;
use function preg_replace_callback;
use SimpleXMLElement;

/**
 * @return list<string>
 */
function captions(string $video_id) : array
{
	$html_cache = __DIR__ . '/captions/' . $video_id . '.html';

	if ( ! is_file($html_cache)) {
		$page = file_get_contents(
			'https://youtube.com/watch?' .
			http_build_query([
				'v' => $video_id,
			])
		);

		file_put_contents($html_cache, $page);
	} else {
		$page = file_get_contents($html_cache);
	}

	$urls = preg_match_all(
		(
			'/https:\/\/www\.youtube\.com\/api\/timedtext\?v=' .
			preg_quote($video_id, '/') .
			'[^"]+/'
		),
		$page,
		$matches
	);

	if ( ! $urls) {
		return [];
	}

	$tt_cache = __DIR__ . '/captions/' . $video_id . '.xml';

	if ( ! is_file($tt_cache)) {
		$tt = file_get_contents(str_replace('\u0026', '&', $matches[0][1]));

		file_put_contents($tt_cache, $tt);
	} else {
		$tt = file_get_contents($tt_cache);
	}

	/** @var list<string> */
	$lines = [];

	$xml = new SimpleXMLElement($tt);

	foreach ($xml->children() as $line) {
		$lines[] = preg_replace_callback(
			'/&#(\d+);/',
			static function (array $match) : string {
				return chr((int) $match[1]);
			},
			(string) $line
		);
	}

	return $lines;
}
