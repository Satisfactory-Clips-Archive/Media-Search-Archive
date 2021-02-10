<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\TwitchClipNotes;

use function array_diff;
use function array_filter;
use const ARRAY_FILTER_USE_KEY;
use function array_keys;
use function array_map;
use function array_merge;
use function array_pop;
use function array_reduce;
use function array_unique;
use function array_values;
use function ceil;
use function chr;
use function count;
use function date;
use function dirname;
use function end;
use function explode;
use function fclose;
use function fgetcsv;
use const FILE_APPEND;
use function file_get_contents;
use function file_put_contents;
use function floor;
use function fopen;
use function glob;
use function http_build_query;
use function implode;
use function in_array;
use InvalidArgumentException;
use function is_file;
use function is_int;
use function iterator_to_array;
use function json_decode;
use function mb_strlen;
use function mb_strpos;
use function mb_substr;
use function pathinfo;
use const PATHINFO_FILENAME;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function rawurlencode;
use SimpleXMLElement;
use function sort;
use function sprintf;
use function str_pad;
use const STR_PAD_LEFT;
use function str_repeat;
use function str_replace;
use function strnatcasecmp;
use function strtotime;
use function uasort;
use function usort;

function video_url_from_id(string $video_id, bool $short = false) : string
{
	/** @var array<string, string>|null */
	static $overrides = null;

	if (null === $overrides) {
		$overrides = json_decode(
			file_get_contents(
				__DIR__
				. '/../playlists/coffeestainstudiosdevs/satisfactory.url-overrides.json'
			),
			true
		);
	}

	if (isset($overrides[$video_id])) {
		return $overrides[$video_id];
	}

	if (preg_match('/^yt-.{11},\d+(?:\.\d+)(?:,\d+(?:\.\d+))/', $video_id)) {
		$parts = explode(',', $video_id);
		[$video_id, $start] = $parts;

		$start = '' === trim($start) ? null : (float) $start;
		$end = isset($parts[2]) ? (float) $parts[2] : null;

		return embed_link($video_id, $start, $end);
	}

	if (0 === mb_strpos($video_id, 'tc-')) {
		return sprintf(
			'https://clips.twitch.tv/%s',
			rawurlencode(mb_substr($video_id, 3))
		);
	} elseif ($short) {
		return sprintf('https://youtu.be/%s', rawurlencode($video_id));
	}

	return
		'https://www.youtube.com/watch?' .
		http_build_query([
			'v' => $video_id,
		]);
}

function transcription_filename(string $video_id) : string
{
	if (preg_match('/^yt-.{11},\d+(?:\.\d+)(?:,\d+(?:\.\d+))/', $video_id)) {
		return
			__DIR__
			. '/../../coffeestainstudiosdevs/satisfactory/transcriptions/'
			. $video_id
			. '.md';
	}

	if (11 !== mb_strlen($video_id) && preg_match('/^(tc|is)\-/', $video_id)) {
		return
			__DIR__
			. '/../../coffeestainstudiosdevs/satisfactory/transcriptions/'
			. $video_id
			. '.md';
	}

	return
		__DIR__
		. '/../../coffeestainstudiosdevs/satisfactory/transcriptions/yt-'
		. $video_id
		. '.md';
}

function maybe_transcript_link_and_video_url(
	string $video_id,
	string $title,
	int $repeat_directory_up = 0
) : string {
	$url = video_url_from_id($video_id);
	$initial_segment = $title;

	$directory_up =
		(1 <= $repeat_directory_up)
			? str_repeat('../', $repeat_directory_up)
			: './';

	if (preg_match('/^yt-.{11},\d+(?:\.\d+)(?:,\d+(?:\.\d+))/', $video_id)) {
		if (
			is_file(
				__DIR__
				. '/../../coffeestainstudiosdevs/satisfactory/transcriptions/'
				. $video_id
				. '.md'
			)
		) {
			$initial_segment = (
				'['
				. $title
				. ']('
				. $directory_up
				. 'transcriptions/'
				. $video_id
				. '.md)'
			);
		}

		return $initial_segment . ' ' . $url;
	}

	if (11 !== mb_strlen($video_id) && preg_match('/^(tc|is)\-/', $video_id)) {
		if (is_file(transcription_filename($video_id))) {
			$initial_segment = (
				'['
				. $title
				. ']('
				. $directory_up
				. 'transcriptions/'
				. $video_id
				. '.md)'
			);
		}
	} else {
		if (is_file(transcription_filename($video_id))) {
			$initial_segment = (
				'['
				. $title
				. ']('
				. $directory_up
				. 'transcriptions/yt-'
				. $video_id
				. '.md)'
			);
		}
	}

	return $initial_segment . ' ' . $url;
}

function vendor_prefixed_video_id(string $video_id) : string
{
	if (
		(
			11 !== mb_strlen($video_id)
			&& preg_match('/^(tc|is|ts)\-/', $video_id)
		)
		|| preg_match('/^yt-.{11}$/', $video_id)
	) {
		return $video_id;
	}

	return 'yt-' . $video_id;
}

/**
 * @psalm-type CACHE = array{
 *	playlists:array<string, array{0:string, 1:string, 1:list<string>}>,
 *	playlistItems:array<string, array{0:string, 1:string}>,
 *	videoTags:array<string, array{0:string, list<string>}>,
 *	stubPlaylists?:array<string, array{0:string, 1:string, 1:list<string>}>
 * }
 *
 * @param CACHE $cache
 * @param CACHE ...$caches
 *
 * @return array{
 *	playlists:array<string, array{0:string, 1:string, 1:list<string>}>,
 *	playlistItems:array<string, array{0:string, 1:string}>,
 *	videoTags:array<string, array{0:string, list<string>}>,
 *	stubPlaylists:array<string, array{0:string, 1:string, 1:list<string>}>
 * }
 */
function inject_caches(array $cache, array ...$caches) : array
{
	if ( ! isset($cache['stubPlaylists'])) {
		$cache['stubPlaylists'] = [];
	}

	if ( ! isset($cache['legacyAlts'])) {
		$cache['legacyAlts'] = [];
	}

	if ( ! isset($cache['internalxref'])) {
		$cache['internalxref'] = [];
	}

	foreach ($caches as $inject) {
		foreach ($inject['playlists'] as $playlist_id => $playlist_data) {
			if ( ! isset($cache['playlists'][$playlist_id])) {
				$cache['playlists'][$playlist_id] = $playlist_data;
			} else {
				$cache['playlists'][$playlist_id][2] = array_unique(
					array_merge(
						$cache['playlists'][$playlist_id][2],
						$playlist_data[2]
					)
				);
			}
		}

		foreach ($inject['playlistItems'] as $video_id => $video_data) {
			if ( ! isset($cache['playlistItems'][$video_id])) {
				$cache['playlistItems'][$video_id] = $video_data;
			}
		}

		foreach ($inject['videoTags'] as $video_id => $video_data) {
			if ( ! isset($cache['videoTags'][$video_id])) {
				$cache['videoTags'][$video_id] = $video_data;
			} else {
				$cache['videoTags'][$video_id][1] = array_unique(
					array_merge(
						$cache['videoTags'][$video_id][1],
						$video_data[1]
					)
				);
			}
		}

		if (isset($inject['stubPlaylists'])) {
			foreach ($inject['stubPlaylists'] as $playlist_id => $playlist_data) {
				if ( ! isset($cache['stubPlaylists'][$playlist_id])) {
					$cache['stubPlaylists'][$playlist_id] = $playlist_data;
				} else {
					$cache['stubPlaylists'][$playlist_id][2] = array_unique(
						array_merge(
							$cache['stubPlaylists'][$playlist_id][2],
							$playlist_data[2]
						)
					);
				}
			}
		}

		if (isset($inject['legacyAlts'])) {
			foreach ($inject['legacyAlts'] as $video_id => $legacy_ids) {
				if ( ! isset($cache['legacyAlts'][$video_id])) {
					$cache['legacyAlts'][$video_id] = [];
				}

				$cache['legacyAlts'][$video_id] = array_unique(array_merge(
					$cache['legacyAlts'][$video_id],
					$legacy_ids
				));

				sort($cache['legacyAlts'][$video_id]);
			}
		}

		if (isset($inject['internalxref'])) {
			foreach ($inject['internalxref'] as $playlist_id => $video_id) {
				if (isset($cache['internalxref'][$playlist_id])) {
					throw new RuntimeException(sprintf(
						'Playlist cross-reference for internal clip data already specified! (%s)',
						$playlist_id
					));
				}

				$cache['internalxref'][$playlist_id] = $video_id;
			}
		}
	}

	return $cache;
}

/**
 * @return array{0:string, 1:list<string>}
 */
function topic_to_slug(
	string $topic_id,
	array $cache,
	array $topics_hierarchy,
	Slugify $slugify
) : array {
	if (
		! isset($cache['playlists'][$topic_id])
		&& ! isset($cache['stubPlaylists'][$topic_id])
	) {
		throw new InvalidArgumentException(sprintf(
			'Topic not in cache! (%s)',
			$topic_id
		));
	} elseif (isset($cache['playlists'][$topic_id])) {
		$topic_data = $cache['playlists'][$topic_id];
	} else {
		$topic_data = $cache['stubPlaylists'][$topic_id];
	}

	[, $topic_title] = $topic_data;

	$slug = $topics_hierarchy[$topic_id] ?? [];

	if (($slug[0] ?? '') !== $topic_title) {
		$slug[] = $topic_title;
	}

	$slug = array_values(array_filter(array_filter($slug, 'is_string')));

	$slugged = array_map(
		[$slugify, 'slugify'],
		$slug
	);

	return [implode('/', $slugged), $slug];
}

/**
 * @param array{
 *	playlists:array<
 *		string,
 *		array{
 *			0:string,
 *			1:string,
 *			2:list<string>
 *		}
 *	} $main
 */
function try_find_main_playlist(
	string $playlist_name,
	array $main
) : ? string {
	foreach ($main['playlists'] as $playlist_id => $data) {
		if ($playlist_name === $data[1]) {
			return $playlist_id;
		}
	}

	return null;
}

/**
 * @return array{0:string, 1:string}
 */
function determine_playlist_id(
	string $playlist_name,
	array $cache,
	array $main,
	array $global_topic_hierarchy,
	array $not_a_livestream,
	array $not_a_livestream_date_lookup
) : array {
	/** @var string|null */
	$maybe_playlist_id = null;
	$friendly = $playlist_name;

	if (preg_match('/^\d{4,}\-\d{2}\-\d{2}$/', $playlist_name)) {
		$unix = strtotime($playlist_name);

		if (false === $unix) {
			throw new RuntimeException(
				'Invalid date found!'
			);
		}

		$suffix = 'Livestream';

		if (isset($not_a_livestream_date_lookup[$playlist_name])) {
			$suffix = $not_a_livestream[
				$not_a_livestream_date_lookup[$playlist_name]
			];
		}

		$friendly =
			date('F jS, Y', $unix)
			. ' '
			. $suffix;

		$maybe_playlist_id = try_find_main_playlist($friendly, $main);

		if (null === $maybe_playlist_id) {
			$maybe_playlist_id = $playlist_name;
		}
	} else {
		$maybe_playlist_id = try_find_main_playlist($playlist_name, $main);

		if (null === $maybe_playlist_id) {
			$maybe_playlist_id = $playlist_name;
		}

		$friendly = $playlist_name;
	}

	if (null === $maybe_playlist_id) {
		throw new RuntimeException(
			'Could not find playlist id!'
		);
	}

	return [$maybe_playlist_id, $friendly];
}

function adjust_nesting(
	array $data,
	string $current,
	int $current_left,
	array $topics_hierarchy,
	array $cache,
	int $level = 0
) : array {
	$data[$current]['left'] = $current_left;
	$data[$current]['level'] = $level;

	++$current_left;

	$all_have_custom_sort = count(
		array_filter(
			$data[$current]['children'],
			static function (string $maybe) use ($topics_hierarchy) : bool {
				return is_int($topics_hierarchy[$maybe][0]);
			}
		)
	) === count($data[$current]['children']);

	if (count($data[$current]['children']) > 0 && $all_have_custom_sort) {
		usort(
			$data[$current]['children'],
			static function (
				string $a,
				string $b
			) use ($topics_hierarchy) : int {
				return $topics_hierarchy[$a][0] - $topics_hierarchy[$b][0];
			}
		);
	} else {
		usort(
			$data[$current]['children'],
			static function (
				string $a,
				string $b
			) use ($cache, $data) : int {
				$maybe_a = count($data[$a]['children'] ?? []) > 0;
				$maybe_b = count($data[$b]['children'] ?? []) > 0;

				if ( ! $maybe_a && $maybe_b) {
					return -1;
				} elseif ($maybe_a && ! $maybe_b) {
					return 1;
				}

				return strnatcasecmp(
					determine_topic_name($a, $cache),
					determine_topic_name($b, $cache)
				);
			}
		);
	}

	foreach ($data[$current]['children'] as $child) {
		[$current_left, $data] = adjust_nesting(
			$data,
			$child,
			$current_left,
			$topics_hierarchy,
			$cache,
			$level + 1
		);
	}

	$data[$current]['right'] = $current_left + 1;

	return [$current_left, $data];
}

/**
 * @param array<string, array{
 *	children: list<string>,
 *	left: positive-int,
 *	right: positive-int,
 *	level: int
 * }> $data
 *
 * @return list<string>
 */
function nesting_parents(
	string $target,
	array $data
) : array {
	if ( ! isset($data[$target])) {
		throw new InvalidArgumentException(
			'Target not found on data!'
		);
	}

	$left = $data[$target]['left'];
	$right = $data[$target]['right'];

	$parents = array_keys(array_filter(
		$data,
		/**
		 * @param array{
		 *	children: list<string>,
		 *	left: positive-int,
		 *	right: positive-int,
		 *	level: int
		 * } $maybe
		 */
		static function (array $maybe) use ($left, $right) : bool {
			return $maybe['left'] <= $left && $maybe['right'] >= $right;
		}
	));

	return $parents;
}

/**
 * @param array<string, array{
 *	children: list<string>,
 *	left: positive-int,
 *	right: positive-int,
 *	level: int
 * }> $data
 *
 * @return list<string>
 */
function nesting_children(
	string $target,
	array $data,
	bool $all_the_way_down = true
) : array {
	if ( ! isset($data[$target])) {
		throw new InvalidArgumentException(
			'Target not found on data!'
		);
	}

	$left = $data[$target]['left'];
	$right = $data[$target]['right'];

	$children = array_keys(array_filter(
		$data,
		/**
		 * @param array{
		 *	children: list<string>,
		 *	left: positive-int,
		 *	right: positive-int,
		 *	level: int
		 * } $maybe
		 */
		static function (array $maybe) use ($left, $right) : bool {
			return $maybe['left'] > $left && $maybe['right'] <= $right;
		}
	));

	if ( ! $all_the_way_down) {
		$children = array_filter(
			$children,
			static function (string $maybe) use ($data, $target) : bool {
				return
					$data[$maybe]['level'] === $data[$target]['level'] + 1;
			}
		);
	}

	return $children;
}

function determine_topic_name(string $topic, array $cache) : string
{
	return ($cache['playlists'][$topic] ?? $cache['stubPlaylists'][$topic])[1];
}

/**
 * @param array{
 *	children: list<string>,
 *	left: positive-int,
 *	right: positive-int,
 *	level: int
 * } $nested
 *
 * @return array<string, array{
 *	children: list<string>,
 *	videos: list<string>,
 *	left: positive-int,
 *	right: positive-int,
 *	level: int
 * }>
 */
function filter_nested(
	string $playlist_id,
	array $nested,
	array $cache,
	array $topic_hierarchy,
	string $video_id,
	string ...$video_ids
) : array {
	$video_ids[] = $video_id;

	$filtered = $nested;

	foreach (array_keys($filtered) as $topic_id) {
		$filtered[$topic_id]['videos'] = array_values(array_filter(
			($cache['playlists'][$topic_id] ?? [2 => []])[2],
			static function (string $video_id) use ($video_ids) : bool {
				return in_array($video_id, $video_ids, true);
			}
		));
	}

	$too_few = array_filter($filtered, static function (array $maybe) : bool {
		return count($maybe['videos']) < 3 && $maybe['level'] > 0;
	});

	foreach ($too_few as $topic_id => $data) {
		$parents = nesting_parents($topic_id, $nested);
		$previous = $topic_id;
		array_pop($parents);
		$checking = end($parents);

		if ( ! $checking) {
			continue;
		}

		$filtered[$topic_id]['videos'] = [];

		$filtered[$checking]['videos'] = array_values(
			array_unique(
				array_merge(
					$filtered[$checking]['videos'] ?? [],
					$data['videos']
				)
			)
		);
	}

	$filtered = array_filter(
		$filtered,
		static function (array $data) : bool {
			return count($data['videos']) > 0;
		}
	);

	foreach (array_keys($filtered) as $topic_id) {
		foreach (nesting_parents($topic_id, $nested) as $parent_id) {
			if ( ! isset($filtered[$parent_id])) {
				$filtered[$parent_id] = $nested[$parent_id];
				$filtered[$parent_id]['videos'] = [];
			}
		}
	}

	$filtered = array_map(
		static function (array $data) use ($filtered, $cache) : array {
			$data['children'] = array_filter(
				$data['children'],
				static function (string $maybe) use ($filtered) : bool {
					return isset($filtered[$maybe]);
				}
			);

			usort(
				$data['videos'],
				static function (string $a, string $b) use ($cache) : int {
					return strnatcasecmp(
						$cache['playlistItems'][$a][1],
						$cache['playlistItems'][$b][1]
					);
				}
			);

			return $data;
		},
		$filtered
	);

	$roots = array_keys(array_filter(
		$filtered,
		static function (array $maybe) : bool {
			return 0 === $maybe['level'];
		}
	));

	$current_left = 0;

	usort(
		$roots,
		static function (
			string $a,
			string $b
		) use ($cache) : int {
			return strnatcasecmp(
				determine_topic_name($a, $cache),
				determine_topic_name($b, $cache)
			);
		}
	);

	foreach ($roots as $topic_id) {
		[$current_left, $filtered] = adjust_nesting(
			$filtered,
			$topic_id,
			$current_left,
			$topic_hierarchy,
			$cache
		);
	}

	uasort(
		$filtered,
		static function (array $a, array $b) : int {
			return $a['left'] - $b['left'];
		}
	);

	return $filtered;
}

/**
 * @param array{legacyAlts:array<string, list<string>} $cache
 */
function filter_video_ids_for_legacy_alts(
	array $cache,
	string ...$video_ids
) : array {
	$has_legacy_alts = array_filter(
		$video_ids,
		static function (string $maybe) use ($cache) : bool {
			return isset($cache['legacyAlts'][$maybe]);
		}
	);

	if (count($has_legacy_alts)) {
		$legacy_alts = array_unique(array_reduce(
			$has_legacy_alts,
			static function (
				array $out,
				string $video_id
			) use ($cache) : array {
				return array_merge($out, $cache['legacyAlts'][$video_id]);
			},
			[]
		));

		$video_ids = array_diff($video_ids, $legacy_alts);
	}

	return $video_ids;
}

/**
 * @return list<string>
 */
function captions(string $video_id) : array
{
	if (
		! preg_match(
			'/^https:\/\/youtu\.be\//',
			video_url_from_id($video_id, true)
		)
	) {
		return [];
	}

	$maybe = raw_captions($video_id);

	if ([] === $maybe) {
		return [];
	}

	[, $xml_lines] = $maybe;

	$lines = [];

	foreach ($xml_lines as $line) {
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

/**
 * @return array<empty, empty>|array{0:SimpleXMLElement, 1:list<SimpleXMLElement>}
 */
function raw_captions(string $video_id) : array
{
	$video_id = preg_replace('/^yt-(.{11})/', '$1', $video_id);

	$html_cache = __DIR__ . '/../captions/' . $video_id . '.html';

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

	$tt_cache = __DIR__ . '/../captions/' . $video_id . '.xml';

	if ( ! is_file($tt_cache)) {
		$tt = file_get_contents(str_replace('\u0026', '&', $matches[0][1]));

		file_put_contents($tt_cache, $tt);
	} else {
		$tt = file_get_contents($tt_cache);
	}

	/** @var list<SimpleXMLElement> */
	$lines = [];

	$xml = new SimpleXMLElement($tt);

	foreach ($xml->children() as $line) {
		$lines[] = $line;
	}

	return [$xml, $lines];
}

/**
 * @return array<
 *	string,
 *	array{
 *		0:string,
 *		1:list<array{
 *			0:numeric-string|empty-string,
 *			1:numeric-string|empty-string,
 *			2:string
 *		}>,
 *		2:array{
 *			title:string,
 *			skip:list<bool>,
 *			topics:array<int, list<string>
 *		}
 *	}
 * >
 */
function get_externals() : array
{
	return array_reduce(
		array_filter(
			glob(__DIR__ . '/../data/*/*.csv'),
			static function (string $maybe) : bool {
				$dir = dirname($maybe);
				$info = pathinfo($maybe, PATHINFO_FILENAME);

				return
					preg_match('/^(?:yt)\-/', $info)
					&& is_file($dir . '/' . $info . '.json');
			}
		),
		/**
		 * @param array<
		 *	string,
		 *	array{
		 *		0:string,
		 *		1:list<array{
		 *			0:numeric-string|empty-string,
		 *			1:numeric-string|empty-string,
		 *			2:string
		 *		}>,
		 *		2:array{
		 *			title:string,
		 *			skip:list<bool>,
		 *			topics:array<int, list<string>
		 *		}
		 *	}
		 * > $out
		 *
		 * @return array<
		 *	string,
		 *	array{
		 *		0:string,
		 *		1:list<array{
		 *			0:numeric-string|empty-string,
		 *			1:numeric-string|empty-string,
		 *			2:string
		 *		}>,
		 *		2:array{
		 *			title:string,
		 *			skip:list<bool>,
		 *			topics:array<int, list<string>
		 *		}
		 *	}
		 * >
		 */
		static function (array $out, string $path) : array {
			$date = pathinfo(dirname($path), PATHINFO_FILENAME);
			$unix = strtotime($date);

			if (false === $unix) {
				throw new RuntimeException(sprintf(
					'Unsupported date found for: %s',
					$path
				));
			}

			$video_id = pathinfo($path, PATHINFO_FILENAME);

			$out[$date] = get_dated_csv($date, $video_id);

			return $out;
		},
		[]
	);
}

function get_dated_csv(
	string $date,
	string $video_id,
	bool $require_json = true
) : array {
	$path = __DIR__ . '/../data/' . $date . '/' . $video_id . '.csv';

	if ( ! is_file($path)) {
		throw new InvalidArgumentException(sprintf(
			'Date and video id not found! (%s, %s)',
			$date,
			$video_id
		));
	}

	$fp = fopen($path, 'rb');

	$csv = [];

	while (false !== ($line = fgetcsv($fp, 0, ',', '"', '"'))) {
		$csv[] = $line;
	}

	fclose($fp);

	$csv = array_filter($csv, 'is_array');

	usort($csv, static function(array $a, array $b) use ($video_id) : int {
		[$a] = $a;
		[$b] = $b;

		$a = '' === $a ? '0' : $a;
		$b = '' === $b ? '0' : $b;

		return ((float) $a) <=> ((float) $b);
	});

	if ( ! $require_json) {
		return [
			$video_id,
			$csv,
			[]
		];
	}

	return [
		$video_id,
		$csv,
		json_decode(
			file_get_contents(
				dirname($path)
				. '/'
				. $video_id
				. '.json'
			),
			true
		),
	];
}

/**
 * @return array{
 *	playlists:array<string, array{0:string, 1:string, 1:list<string>}>,
 *	playlistItems:array<string, array{0:string, 1:string}>,
 *	videoTags:array<string, array{0:string, list<string>}>
 * }
 */
function process_externals(
	array $cache,
	array $global_topic_hierarchy,
	array $not_a_livestream,
	array $not_a_livestream_date_lookup,
	Slugify $slugify,
	bool $write_files = true
) : array {
	$externals = get_externals();

	$inject = [
		'playlists' => [],
		'playlistItems' => [],
		'videoTags' => [],
	];

	foreach ($externals as $date => $externals_data) {
		[$inject, $lines_to_write] = process_dated_csv(
			$date,
			$inject,
			$externals_data,
			$cache,
			$global_topic_hierarchy,
			$not_a_livestream,
			$not_a_livestream_date_lookup,
			$slugify,
			$write_files
		);

		$filename = (
			__DIR__
			. '/../../coffeestainstudiosdevs/satisfactory/'
			. $date .
			'.md'
		);

		if ($write_files) {
			[$processed_lines, $files_with_lines_to_write] = $lines_to_write;

			file_put_contents($filename, '');

			foreach ($processed_lines as $line) {
				file_put_contents($filename, $line, FILE_APPEND);
			}

			foreach ($files_with_lines_to_write as $other_file => $lines) {
				file_put_contents($other_file, '');

				foreach ($lines as $line) {
					file_put_contents($other_file, $line, FILE_APPEND);
				}
			}
		}
	}

	return $inject;
}

/**
 * @param array{
 *	playlists:array<string, array{0:string, 1:string, 1:list<string>}>,
 *	playlistItems:array<string, array{0:string, 1:string}>,
 *	videoTags:array<string, array{0:string, list<string>}>
 * } $inject
 *
 * @return array{
 *	0:array{
 *		playlists:array<string, array{0:string, 1:string, 1:list<string>}>,
 *		playlistItems:array<string, array{0:string, 1:string}>,
 *		videoTags:array<string, array{0:string, list<string>}>
 *	},
 *	1:array{0:list<string>, 1:array<string, list<string>>}
 * }
 */
function process_dated_csv(
	string $date,
	array $inject,
	array $externals_data,
	array $cache,
	array $global_topic_hierarchy,
	array $not_a_livestream,
	array $not_a_livestream_date_lookup,
	Slugify $slugify,
	bool $write_files = true,
	bool $do_injection = true,
	bool $skip_header = false
) : array {
	/** @var list<string> */
	$out = [];

	/** @var array<string, list<string>> */
	$files_out = [];

		[$video_id, $externals_csv, $data] = $externals_data;

		$captions = raw_captions($video_id);

		$friendly_date = date('F jS, Y', (int) strtotime($date));

		if ($write_files && ! $skip_header) {
		$out = array_merge($out, [
			'---' . "\n",
			sprintf('title: "%s"', $data['title']) . "\n",
			sprintf('date: "%s"', $date) . "\n",
			'layout: livestream' . "\n",
			'---' . "\n",
			sprintf(
				'# %s %s' . "\n",
				$friendly_date,
				$data['title']
			)
		]);
		}

		$captions_with_start_time = [];

		foreach (($captions[1] ?? []) as $caption_line) {
			$attrs = iterator_to_array(
				$caption_line->attributes()
			);

			$captions_with_start_time[] = [
				(string) $attrs['start'],
				(string) $attrs['dur'],
				preg_replace_callback(
					'/&#(\d+);/',
					static function (array $match) : string {
						return chr((int) $match[1]);
					},
					(string) $caption_line
				),
			];
		}

		$csv_captions = array_map(
			static function (array $csv_line) use ($captions_with_start_time) : array {
				$csv_line_captions = implode("\n", array_map(
					static function (array $data) : string {
						return $data[2];
					},
					array_filter(
						$captions_with_start_time,
						/**
						 * @param array{0:numeric-string, 1:numeric-string, 2:string} $maybe
						 */
						static function (array $maybe) use ($csv_line) : bool {
							[$start, $end] = $csv_line;

							$start = (float) $start;

							$from = (float) $maybe[0];
							$to = $from + (float) $maybe[1];

							if ('' === $end) {
								return $from >= $start;
							}

							return $from >= $start && $to <= (float) $end;
						}
					)
				));

				$csv_line[] = $csv_line_captions;

				return $csv_line;
			},
			array_filter(
				$externals_csv,
				static function (int $k) use ($data) : bool {
					if ( ! isset($data['skip'])) {
						return false;
					}

					return ! $data['skip'][$k];
				},
				ARRAY_FILTER_USE_KEY
			)
		);

	if ($do_injection) {
		$inject['playlists'][$date] = ['', $data['title'], array_map(
			static function (int $i) use ($externals_csv, $video_id) : string {
				[$start, $end, $clip_title] = $externals_csv[$i];

				return sprintf(
					'%s,%s',
					$video_id,
					$start . ('' === $end ? '' : (',' . $end))
				);
			},
			array_keys($csv_captions)
		)];
	}

	foreach ($externals_csv as $i => $line) {
		[$start, $end, $clip_title] = $line;
		$clip_id = sprintf(
			'%s,%s',
			$video_id,
			$start . ('' === $end ? '' : (',' . $end))
		);

		$embed_data = [
			'autoplay' => 1,
			'start' => floor($start ?: '0'),
			'end' => $end,
		];

		if ('' === $embed_data['end']) {
			unset($embed_data['end']);
		} else {
			$embed_data['end'] = ceil($embed_data['end']);
		}

		$start = (float) ($start ?: '0.0');

		$embed = http_build_query($embed_data);

		$start_minutes = str_pad((string) floor($start / 60), 2, '0', STR_PAD_LEFT);
		$start_seconds = str_pad((string) ($start % 60), 2, '0', STR_PAD_LEFT);

		$clip_title_maybe = $clip_title;

		if (isset($csv_captions[$i])) {
			$basename = sprintf(
				'%s.md',
				$clip_id
			);

			if ($do_injection) {
				$inject['playlistItems'][$clip_id] = ['', $clip_title];
				$inject['videoTags'][$clip_id] = ['', []];
			}

			$clip_title_maybe = sprintf(
				'[%s](./transcriptions/%s)',
				$clip_title,
				$basename
			);

			if ($do_injection) {
				foreach (($data['topics'][$i] ?? []) as $topic) {
					[$playlist_id] = determine_playlist_id(
						$topic,
						[],
						$cache,
						$global_topic_hierarchy,
						$not_a_livestream,
						$not_a_livestream_date_lookup
					);

					if ( ! isset($inject['playlists'][$playlist_id])) {
						$inject['playlists'][$playlist_id] = ['', $topic, []];
					}

					$inject['playlists'][$playlist_id][2][] = $clip_id;
				}
			}

			if ($write_files) {
				$files_out[
					__DIR__
					. '/../../coffeestainstudiosdevs/satisfactory/transcriptions/'
					. $basename
				] = [
					'---' . "\n",
					sprintf(
						'title: "%s"' . "\n",
						$friendly_date,
						$clip_title
					),
					sprintf('date: "%s"', $date) . "\n",
					'layout: transcript' . "\n",
					'topics: ' . "\n",
					'    - "',
					implode('"' . "\n" . '    - "', array_map(
						static function (
							string $topic
						) use (
							$cache,
							$global_topic_hierarchy,
							$not_a_livestream,
							$not_a_livestream_date_lookup,
							$slugify
						) : string {
							return topic_to_slug(
								determine_playlist_id(
									$topic,
									[],
									$cache,
									$global_topic_hierarchy,
									$not_a_livestream,
									$not_a_livestream_date_lookup
								)[0],
								$cache,
								$global_topic_hierarchy['satisfactory'],
								$slugify
							)[0];
						},
						($data['topics'][$i] ?? [])
					)),
					'"' . "\n",
					'---' . "\n",
					sprintf(
						'# [%s %s](../%s.md)' . "\n",
						$friendly_date,
						$data['title'],
						$date
					),
					sprintf('## %s', $clip_title) . "\n",
					embed_link(
						$video_id,
						$start,
						'' === $end ? null : ((float) $end)
					),
					'### Topics' . "\n",
					implode("\n", array_map(
						static function (
							string $topic
						) use (
							$cache,
							$global_topic_hierarchy,
							$not_a_livestream,
							$not_a_livestream_date_lookup,
							$slugify
						) : string {
							[$slug, $parts] = topic_to_slug(
								determine_playlist_id(
									$topic,
									[],
									$cache,
									$global_topic_hierarchy,
									$not_a_livestream,
									$not_a_livestream_date_lookup
								)[0],
								$cache,
								$global_topic_hierarchy['satisfactory'],
								$slugify
							);

							return sprintf(
								'* [%s](../topics/%s.md)',
								implode(' > ', $parts),
								$slug
							);
						},
						($data['topics'][$i] ?? [])
					)),
					"\n\n",
					'### Transcript' . "\n\n",
					implode("\n", array_map(
						static function (string $line) : string {
							return sprintf('> %s', $line);
						},
						explode("\n", $csv_captions[$i][3])
					)),
					"\n"
				];
			}
		}

		if ($write_files) {
			$out[] =
				sprintf(
					'* [%s:%s](%s) %s' . "\n",
					$start_minutes,
					$start_seconds,
					timestamp_link($video_id, $start),
					$clip_title_maybe
			);
		}
	}

	return [$inject, [$out, $files_out]];
}

function timestamp_link(string $video_id, float $start) : string {
	$video_id = vendor_prefixed_video_id($video_id);
	$vendorless_video_id = mb_substr($video_id, 3);

	if (preg_match('/^yt-/', $video_id)) {
		return sprintf(
			'https://youtu.be/%s?t=%s',
			$vendorless_video_id,
			floor($start)
		);
	} elseif (preg_match('/^ts-/', $video_id)) {
		$hours = floor($start / 3600);
		$minutes = floor(($start - ($hours * 3600)) / 60);
		$seconds = floor($start % 60);

		$hours = str_pad((string) $hours, 2, '0', STR_PAD_LEFT);
		$minutes = str_pad((string) $minutes, 2, '0', STR_PAD_LEFT);
		$seconds = str_pad((string) $seconds, 2, '0', STR_PAD_LEFT);

		return sprintf(
			'https://twitch.tv/videos/%s?t=%sh%sm%ss',
			$vendorless_video_id,
			$hours,
			$minutes,
			$seconds
		);
	}

	throw new InvalidArgumentException(sprintf(
		'Unsupported video id specified! (%s)',
		$video_id
	));
}

function embed_link(string $video_id, ? float $start, ? float $end) : string {
	$video_id = vendor_prefixed_video_id($video_id);
	$vendorless_video_id = mb_substr($video_id, 3);

	if (preg_match('/^yt-/', $video_id)) {
		$start = floor($start ?: '0');
		$end = isset($end) ? ceil($end) : null;
		$embed_data = [
			'autoplay' => 1,
			'start' => $start,
		];

		if (isset($end)) {
			$embed_data['end'] = $end;
		}

		$embed = http_build_query($embed_data);

		return sprintf(
			'https://youtube.com/embed/%s?%s' . "\n",
			$vendorless_video_id,
			$embed
		);
	}

	return timestamp_link($video_id, $start);
}
