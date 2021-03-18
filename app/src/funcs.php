<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_combine;
use function array_diff;
use function array_filter;
use const ARRAY_FILTER_USE_BOTH;
use const ARRAY_FILTER_USE_KEY;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_merge_recursive;
use function array_pop;
use function array_reduce;
use function array_unique;
use function array_unshift;
use function array_values;
use function ceil;
use function chr;
use function count;
use function current;
use function date;
use function dirname;
use function end;
use ErrorException;
use function explode;
use function fclose;
use function fgetcsv;
use const FILE_APPEND;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function floor;
use function fopen;
use function glob;
use function http_build_query;
use function implode;
use function in_array;
use InvalidArgumentException;
use function is_array;
use function is_file;
use function is_int;
use function is_string;
use function iterator_to_array;
use function json_decode;
use function key;
use function ksort;
use function mb_strlen;
use function mb_strpos;
use function mb_substr;
use function next;
use function parse_str;
use function parse_url;
use function pathinfo;
use const PATHINFO_FILENAME;
use const PHP_URL_QUERY;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function rawurlencode;
use RuntimeException;
use function set_error_handler;
use SimpleXMLElement;
use function sort;
use function sprintf;
use function str_pad;
use const STR_PAD_LEFT;
use function str_repeat;
use function str_replace;
use function strnatcasecmp;
use function strtotime;
use Throwable;
use function trim;
use function uasort;
use function uksort;
use function usort;

set_error_handler(static function (
	int $_errno,
	string $errstr,
	string $errfile,
	int $errline,
	array $_errcontext
) : bool {
	throw new ErrorException(sprintf(
		'%s:%s %s',
		$errfile,
		$errline,
		$errstr
	));
});

function video_url_from_id(string $video_id, bool $short = false) : string
{
	/** @var array<string, string>|null */
	static $overrides = null;

	if (null === $overrides) {
		/** @var array<string, string> */
		$overrides = array_filter(
			(array) json_decode(
			file_get_contents(
				__DIR__
				. '/../playlists/coffeestainstudiosdevs/satisfactory.url-overrides.json'
			),
			true
			),
			[new Filtering(), 'kvp_string_string'],
			ARRAY_FILTER_USE_BOTH
		);
	}

	if (isset($overrides[$video_id])) {
		return $overrides[$video_id];
	}

	if (preg_match('/^yt-.{11}(?:,(?:\d+(?:\.\d+)?)?){2}/', $video_id)) {
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
	if (preg_match('/^yt-.{11}(?:,(?:\d+(?:\.\d+)?)?){2}/', $video_id)) {
		return
			__DIR__
			. '/../../video-clip-notes/coffeestainstudiosdevs/satisfactory/transcriptions/'
			. $video_id
			. '.md';
	}

	if (11 !== mb_strlen($video_id) && preg_match('/^(tc|is)\-/', $video_id)) {
		return
			__DIR__
			. '/../../video-clip-notes/coffeestainstudiosdevs/satisfactory/transcriptions/'
			. $video_id
			. '.md';
	}

	return
		__DIR__
		. '/../../video-clip-notes/coffeestainstudiosdevs/satisfactory/transcriptions/yt-'
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

	if (preg_match('/^yt-.{11}(?:,(?:\d+(?:\.\d+)?)?){2}/', $video_id)) {
		if (
			is_file(
				__DIR__
				. '/../../video-clip-notes/coffeestainstudiosdevs/satisfactory/transcriptions/'
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

		return $initial_segment . ' [' . $url . '](' . $url . ')';
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

	return $initial_segment . ' [' . $url . '](' . $url . ')';
}

function vendor_prefixed_video_id(string $video_id) : string
{
	if (
		(
			11 !== mb_strlen($video_id)
			&& preg_match('/^(tc|is|ts)\-/', $video_id)
		)
		|| preg_match('/^yt-.{11}(?:(?:,(?:\d+(?:\.\d+)?)?){2})?$/', $video_id)
	) {
		return $video_id;
	}

	return 'yt-' . $video_id;
}

/**
 * @psalm-type CACHE = array{
 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	playlistItems:array<string, array{0:string, 1:string}>,
 *	videoTags:array<string, array{0:string, list<string>}>,
 *	stubPlaylists?:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	legacyAlts?:array<string, list<string>>,
 *	internalxref?:array<string, string>
 * }
 *
 * @param CACHE $cache
 * @param CACHE ...$caches
 *
 * @return array{
 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	playlistItems:array<string, array{0:string, 1:string}>,
 *	videoTags:array<string, array{0:string, list<string>}>,
 *	stubPlaylists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	legacyAlts:array<string, list<string>>,
 *	internalxref:array<string, string>
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

	/**
	 * @var array{
	 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
	 *	playlistItems:array<string, array{0:string, 1:string}>,
	 *	videoTags:array<string, array{0:string, list<string>}>,
	 *	stubPlaylists:array<string, array{0:string, 1:string, 2:list<string>}>,
	 *	legacyAlts:array<string, list<string>>,
	 *	internalxref:array<string, string>
	 * }
	 */
	return $cache;
}

/**
 * @return array{
 *	0:array{
 *		playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *		playlistItems:array<string, array{0:string, 1:string}>,
 *		videoTags:array<string, array{0:string, list<string>}>,
 *		stubPlaylists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *		legacyAlts:array<string, list<string>>,
 *		internalxref:array<string, string>
 *	},
 *	1:array{satisfactory:array<string, list<int|string>>},
 *	2:array<string, string>,
 *	3:array<string, string>
 * }
 */
function prepare_injections(YouTubeApiWrapper $api, Slugify $slugify) : array
{
	/**
	 * @var array{
	 *	0:array{
	 *		playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
	 *		playlistItems:array<string, array{0:string, 1:string}>,
	 *		videoTags:array<string, array{0:string, list<string>}>,
	 *		stubPlaylists:array<string, array{0:string, 1:string, 2:list<string>}>,
	 *		legacyAlts:array<string, list<string>>,
	 *		internalxref:array<string, string>
	 *	},
	 *	1:array{satisfactory:array<string, list<int|string>>},
	 *	2:array<string, string>,
	 *	3:array<string, string>
	 * }|null
	 */
	static $out = null;

	if (null === $out) {
		require (__DIR__ . '/../global-topic-hierarchy.php');

		$api->update();
		$cache = $api->toLegacyCacheFormat();

		$injected_cache = [
			'playlists' => [],
			'playlistItems' => [],
			'videoTags' => [],
			'legacyAlts' => [],
			'stubPlaylists' => [],
		];

		foreach (get_additional_externals() as $date => $data) {
			[$dated_playlist_id, $playlist_name] = determine_playlist_id(
				$date,
				$cache,
				$not_a_livestream,
				$not_a_livestream_date_lookup
			);

			if ( ! isset($injected_cache['playlists'][$dated_playlist_id])) {
				$injected_cache['playlists'][$dated_playlist_id] = [
					'',
					$playlist_name,
					[],
				];
			}

			foreach ($data as $video_id => $video) {
				$injected_cache['playlists'][$dated_playlist_id][2][] = $video_id;

				$injected_cache['playlistItems'][$video_id] = [
					'',
					$video['title'],
				];

				$injected_cache['videoTags'][$video_id] = [
					'',
					$video['tags'] ?? [],
				];

				foreach ($video['topics'] as $topic) {
					[$topic_id, $topic_name] = determine_playlist_id(
						$topic,
						$cache,
						$not_a_livestream,
						$not_a_livestream_date_lookup
					);

					if ( ! isset($injected_cache['playlists'][$topic_id])) {
						$injected_cache['playlists'][$topic_id] = [
							'',
							$topic_name,
							[],
						];
					}

					$injected_cache['playlists'][$topic_id][2][] = $video_id;
				}

				foreach (($video['legacyof'] ?? []) as $legacyof) {
					if ( ! isset($injected_cache['legacyAlts'][$legacyof])) {
						$injected_cache['legacyAlts'][$legacyof] = [];
					}

					$injected_cache['legacyAlts'][$legacyof][] = $video_id;
				}
			}
		}

		/** @var array{satisfactory:array<string, list<int|string>>} */
		$global_topic_hierarchy = array_merge_recursive(
			$global_topic_hierarchy,
			$injected_global_topic_hierarchy
		);

		foreach ($global_topic_hierarchy['satisfactory'] as $parents) {
			foreach ($parents as $topic) {
				if ( ! is_string($topic)) {
					continue;
				}

				[$topic_id, $topic_name] = determine_playlist_id(
					$topic,
					$cache,
					$not_a_livestream,
					$not_a_livestream_date_lookup
				);

				$injected_cache['stubPlaylists'][$topic_id] = [
					'',
					$topic_name,
					[],
				];
			}
		}

		foreach (
			array_keys(
				$injected_global_topic_hierarchy['satisfactory']
			) as $topic
		) {
			[$topic_id, $topic_name] = determine_playlist_id(
				$topic,
				$cache,
				$not_a_livestream,
				$not_a_livestream_date_lookup
			);

			$injected_cache['stubPlaylists'][$topic_id] = [
				'',
				$topic_name,
				[],
			];
		}

		$cache = inject_caches($cache, $injected_cache);

		$externals_cache = process_externals(
			$cache,
			$global_topic_hierarchy,
			$not_a_livestream,
			$not_a_livestream_date_lookup,
			$slugify,
			false
		);

		$cache = inject_caches($cache, $externals_cache);

		/**
		 * @var array{
		 *	0:array{
		 *		playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
		 *		playlistItems:array<string, array{0:string, 1:string}>,
		 *		videoTags:array<string, array{0:string, list<string>}>,
		 *		stubPlaylists:array<string, array{0:string, 1:string, 2:list<string>}>,
		 *		legacyAlts:array<string, list<string>>,
		 *		internalxref:array<string, string>
		 *	},
		 *	1:array{satisfactory:array<string, list<int|string>>},
		 *	2:array<string, string>,
		 *	3:array<string, string>
		 * }
		 */
		$out = [
			$cache,
			$global_topic_hierarchy,
			$not_a_livestream,
			$not_a_livestream_date_lookup,
		];
	}

	return $out;
}

/**
 * @psalm-type CACHE = array{
 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	playlistItems:array<string, array{0:string, 1:string}>,
 *	videoTags:array<string, array{0:string, list<string>}>,
 *	stubPlaylists:array<string, array{0:string, 1:string, 2:list<string>}>
 * }
 *
 * @param CACHE $cache
 * @param array<string, list<int|string>> $topics_hierarchy
 *
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
 * @psalm-type CACHE = array{
 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	playlistItems:array<string, array{0:string, 1:string}>,
 *	videoTags:array<string, array{0:string, list<string>}>,
 *	stubPlaylists?:array<string, array{0:string, 1:string, 2:list<string>}>
 * }
 *
 * @param CACHE $main
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
 * @psalm-type CACHE = array{
 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	playlistItems:array<string, array{0:string, 1:string}>,
 *	videoTags:array<string, array{0:string, list<string>}>,
 *	stubPlaylists?:array<string, array{0:string, 1:string, 2:list<string>}>
 * }
 *
 * @param CACHE $main
 * @param array<string, string> $not_a_livestream
 * @param array<string, string> $not_a_livestream_date_lookup
 *
 * @return array{0:string, 1:string}
 */
function determine_playlist_id(
	string $playlist_name,
	array $main,
	array $not_a_livestream,
	array $not_a_livestream_date_lookup
) : array {
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

		$friendly = date('F jS, Y', $unix) . ' ' . $suffix;

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

	return [$maybe_playlist_id, $friendly];
}

/**
 * @psalm-type DATA = array<string, array{
 *	children: list<string>,
 *	videos?: list<string>,
 *	left: positive-int,
 *	right: positive-int,
 *	level: int
 * }>
 *
 * @param DATA $data
 * @param array{
 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	playlistItems:array<string, array{0:string, 1:string}>,
 *	videoTags:array<string, array{0:string, list<string>}>,
 *	stubPlaylists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	legacyAlts:array<string, list<string>>,
 *	internalxref:array<string, string>
 * } $cache
 * @param array<string, list<int|string>> $topics_hierarchy
 *
 * @return array{0:int, 1:DATA}
 */
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
				/** @var int */
				$a_int = $topics_hierarchy[$a][0];

				/** @var int */
				$b_int = $topics_hierarchy[$b][0];

				return $a_int - $b_int;
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
		$children = array_values(array_filter(
			$children,
			static function (string $maybe) use ($data, $target) : bool {
				return $data[$maybe]['level'] === $data[$target]['level'] + 1;
			}
		));
	}

	return $children;
}

/**
 * @param array{
 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	playlistItems:array<string, array{0:string, 1:string}>,
 *	videoTags:array<string, array{0:string, list<string>}>,
 *	stubPlaylists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	legacyAlts:array<string, list<string>>,
 *	internalxref:array<string, string>
 * } $cache
 */
function determine_topic_name(string $topic, array $cache) : string
{
	return ($cache['playlists'][$topic] ?? $cache['stubPlaylists'][$topic])[1];
}

/**
 * @param array<string, array{
 *	children: list<string>,
 *	videos?: list<string>,
 *	left: positive-int,
 *	right: positive-int,
 *	level: int
 * }> $nested
 * @param array{
 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	playlistItems:array<string, array{0:string, 1:string}>,
 *	videoTags:array<string, array{0:string, list<string>}>,
 *	stubPlaylists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	legacyAlts:array<string, list<string>>,
 *	internalxref:array<string, string>
 * } $cache
 * @param array<string, list<int|string>> $topic_hierarchy
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
	string $_playlist_id,
	array $nested,
	array $cache,
	array $topic_hierarchy,
	string $video_id,
	string ...$video_ids
) : array {
	$video_ids[] = $video_id;

	$filtered = $nested;

	foreach (array_keys($filtered) as $topic_id) {
		if ( ! isset($filtered[$topic_id])) {
			throw new RuntimeException(sprintf(
				'Could not find %s in argument 2 passed to %s()!',
				$topic_id,
				__FUNCTION__
			));
		}

		$filtered[$topic_id]['videos'] = array_values(array_filter(
			($cache['playlists'][$topic_id][2] ?? []),
			static function (string $video_id) use ($video_ids) : bool {
				return in_array($video_id, $video_ids, true);
			}
		));
	}

	$too_few = array_filter($filtered, static function (array $maybe) : bool {
		return count($maybe['videos'] ?? []) < 3 && $maybe['level'] > 0;
	});

	foreach ($too_few as $topic_id => $data) {
		$parents = nesting_parents($topic_id, $nested);
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
					$data['videos'] ?? []
				)
			)
		);
	}

	$filtered = array_filter($filtered, static function (array $data) : bool {
		return count($data['videos'] ?? []) > 0;
	});

	foreach (array_keys($filtered) as $topic_id) {
		foreach (nesting_parents($topic_id, $nested) as $parent_id) {
			if ( ! isset($filtered[$parent_id])) {
				$filtered[$parent_id] = $nested[$parent_id];
				$filtered[$parent_id]['videos'] = [];
			}
		}
	}

	/**
	 * @var array<string, array{
	 *	children: list<string>,
	 *	videos: list<string>,
	 *	left: positive-int,
	 *	right: positive-int,
	 *	level: int
	 * }>
	 */
	$filtered = array_map(
		static function (array $data) use ($filtered, $cache) : array {
			$data['children'] = array_filter(
				$data['children'],
				static function (string $maybe) use ($filtered) : bool {
					return isset($filtered[$maybe]);
				}
			);

			$data['videos'] = $data['videos'] ?? [];

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
			return $a['left'] <=> $b['left'];
		}
	);

	/**
	 * @var array<string, array{
	 *	children: list<string>,
	 *	videos: list<string>,
	 *	left: positive-int,
	 *	right: positive-int,
	 *	level: int
	 * }>
	 */
	return $filtered;
}

/**
 * @param array{
 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	playlistItems:array<string, array{0:string, 1:string}>,
 *	videoTags:array<string, array{0:string, list<string>}>,
 *	stubPlaylists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	legacyAlts:array<string, list<string>>,
 *	internalxref:array<string, string>
 * } $cache
 *
 * @return list<string>
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
			/**
			 * @param list<string> $out
			 *
			 * @return list<string>
			 */
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

	return array_values($video_ids);
}

/**
 * @psalm-type ITEM = array{
 *	text:string|list<string|array{text:string, about?:string}>,
 *	startTime:string,
 *	endTime:string,
 *	speaker?:list<string>,
 *	followsOnFromPrevious?:bool,
 *	webvtt?: array{
 *		position?:positive-int,
 *		line?:int,
 *		size?:positive-int,
 *		align?:'start'|'middle'|'end'
 *	}
 * }
 *
 * @param array<string, string> $playlist_topic_strings_reverse_lookup
 *
 * @return list<string>
 */
function captions(
	string $video_id,
	array $playlist_topic_strings_reverse_lookup
) : array {
	if (
		! preg_match(
			'/^https:\/\/youtu\.be\//',
			video_url_from_id($video_id, true)
		)
	) {
		return [];
	}

	$maybe = raw_captions($video_id);

	if ( ! isset($maybe[1])) {
		return [];
	}

	if (array_key_exists(0, $maybe) && null === $maybe[0]) {
		/**
		 * @var list<ITEM>
		 */
		$lines = $maybe[1];

		/** @var string|null */
		$last_speaker = null;

		return array_reduce(
			$lines,
			/**
			 * @param list<string> $result
			 * @param ITEM $line
			 *
			 * @return list<string>
			 */
			static function (
				array $result,
				array $line
			) use (
				&$last_speaker,
				$playlist_topic_strings_reverse_lookup
			) : array {
				$out = array_map(
					/**
					 * @param string|array{text:string, about?:string} $chunk
					 */
					static function (
						$chunk
					) use (
						$playlist_topic_strings_reverse_lookup
					) : string {
						$chunk_text =
							is_array($chunk)
								? $chunk['text']
								: $chunk;

						$out = preg_replace(
							'/\s+/',
							' ',
							str_replace("\n", ' ', $chunk_text)
						);

						if (
							is_array($chunk)
							&& isset($chunk['about'])
							&& preg_match('/^\/topics\//', $chunk['about'])
							&& isset(
								$playlist_topic_strings_reverse_lookup[
									mb_substr($chunk['about'], 8, -1)
								]
							)
						) {
							$out = sprintf(
								'[%s](%s.md)',
								$out,
								mb_substr($chunk['about'], 0, -1)
							);
						}

						return $out;
					},
					(array) $line['text']
				);

				$out = implode('', $out);

				if (isset($line['speaker'])) {
					$current_speaker = implode(', ', $line['speaker']);

					if ($last_speaker !== $current_speaker) {
						$last_speaker = $current_speaker;

						$out = $current_speaker . ': ' . $out;

						$result[] = $out;
					} elseif (
						$line['followsOnFromPrevious'] ?? false
					) {
						$result[] = array_pop($result) . ' ' . $out;
					} else {
						$result[] = $out;
					}
				} elseif (
					$line['followsOnFromPrevious'] ?? false
				) {
					$result[] = array_pop($result) . ' ' . $out;
				}

				return $result;
			},
			[]
		);
	}

	/** @var list<SimpleXMLElement> */
	$xml_lines = $maybe[1];

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
 * @psalm-type JSON = list<array{
 *	text:string|list<string|array{text:string, about?:string}>,
 *	startTime:string,
 *	endTime:string,
 *	speaker?:list<string>,
 *	followsOnFromPrevious?:bool,
 *	webvtt?: array{
 *		position?:positive-int,
 *		line?:int,
 *		size?:positive-int,
 *		align?:'start'|'middle'|'end'
 *	}
 * }>
 *
 * @return array<empty, empty>|array{0:SimpleXMLElement, 1:list<SimpleXMLElement>}|array{0:null, JSON}
 */
function raw_captions(string $video_id) : array
{
	/** @var Slugify|null */
	static $slugify = null;

	if (null === $slugify) {
		$slugify = new Slugify();
	}

	$video_id = preg_replace('/^yt-(.{11})/', '$1', $video_id);

	$json_source =
		__DIR__
		. '/../../Media-Archive-Metadata/transcriptions/'
		. vendor_prefixed_video_id($video_id)
		. '.json';

	if (is_file($json_source)) {
		/**
		 * @var array{
		 *	language: 'en'|'en-US'|'en-GB',
		 *	about:string,
		 *	text: list<array{
		 *		text:string|list<string|array{text:string, about?:string}>,
		 *		startTime?:string,
		 *		endTime?:string,
		 *		speaker?:list<string>,
		 *		followsOnFromPrevious?:bool,
		 *		webvtt?: array{
		 *			position?:positive-int,
		 *			line?:int,
		 *			size?:positive-int,
		 *			align?:'start'|'middle'|'end'
		 *		}
		 *	}>
		 * }
		 */
		$transcript = json_decode(
			file_get_contents($json_source),
			true
		);

		$transcript['text'] = array_filter(
			$transcript['text'],
			static function (array $maybe) : bool {
				return
					isset($maybe['startTime'], $maybe['endTime'])
					&& preg_match('/^PT\d+(?:\.\d+)?S$/', $maybe['startTime'])
					&& preg_match('/^PT\d+(?:\.\d+)?S$/', $maybe['endTime'])
				;
			}
		);

		$transcript['text'] = array_map(
			static function (array $line) {
				$text = $line['text'];

				if (
					is_array($text)
					&& count($text) === count(array_filter($text, 'is_string'))
				) {
					$text = implode('', $text);
				}

				$line['text'] = $text;

				return $line;
			},
			$transcript['text']
		);

		/**
		 * @var array{
		 *	language: 'en'|'en-US'|'en-GB',
		 *	about:string,
		 *	text: list<array{
		 *		text:string|list<string|array{text:string, about?:string}>,
		 *		startTime:string,
		 *		endTime:string,
		 *		speaker?:list<string>,
		 *		followsOnFromPrevious?:bool,
		 *		webvtt?: array{
		 *			position?:positive-int,
		 *			line?:int,
		 *			size?:positive-int,
		 *			align?:'start'|'middle'|'end'
		 *		}
		 *	}>
		 * }
		 */
		$transcript = $transcript;

		usort(
			$transcript['text'],
			/**
			 * @psalm-type VALUE = array{
			 *		text:string|list<string|array{text:string, about?:string}>,
			 *		startTime:string,
			 *		endTime:string,
			 *		speaker?:list<string>,
			 *		followsOnFromPrevious?:bool,
			 *		webvtt?: array{
			 *			position?:positive-int,
			 *			line?:int,
			 *			size?:positive-int,
			 *			align?:'start'|'middle'|'end'
			 *		}
			 *	}
			 *
			 * @param VALUE $a
			 * @param VALUE $b
			 */
			static function (array $a, array $b) : int {
				$a_time = (float) (mb_substr($a['startTime'], 2, -1));
				$b_time = (float) (mb_substr($b['startTime'], 2, -1));

				return $a_time <=> $b_time;
			}
		);

		return [null, $transcript['text']];
	}

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

	/** @var array<string, string> */
	$url_matches = array_combine(
		array_map(
			static function (string $remap) use ($slugify) : string {
				parse_str(
					parse_url(
						str_replace('\u0026', '&', $remap),
						PHP_URL_QUERY
					),
					$query
				);

				ksort($query);

				return $slugify->slugify(http_build_query(array_filter(
					$query,
					static function (string $maybe) : bool {
						return in_array(
							$maybe,
							[
								'hl',
								'kind',
								'lang',
							],
							true
						);
					},
					ARRAY_FILTER_USE_KEY
				)));
			},
			$matches[0]
		),
		array_map(
			static function (string $remap) : string {
				return str_replace('\u0026', '&', $remap);
			},
			$matches[0]
		)
	);

	uksort($url_matches, static function (string $a, string $b) : int {
		$maybe_a = preg_match('/kind-asr$/', $a);
		$maybe_b = preg_match('/kind-asr$/', $b);

		$maybe = $maybe_b <=> $maybe_a;

		if (0 !== $maybe) {
			return $maybe;
		}

		$maybe_a = preg_match('/lang-en/', $a);
		$maybe_b = preg_match('/lang-en/', $b);

		return $maybe_b <=> $maybe_a;
	});

	/** @var string|null */
	$tt = null;

	$tt_cache = (
		__DIR__
		. '/../captions/'
		. $video_id
		. '.xml'
	);

	while ('' !== $tt) {
		if (null === key($url_matches)) {
			break;
		}

		$tt_cache = (
			__DIR__
			. '/../captions/'
			. $video_id
			. ','
			. key($url_matches)
			. '.xml'
		);

		if ( ! is_file($tt_cache)) {
			$tt = file_get_contents((string) current($url_matches));

			file_put_contents($tt_cache, $tt);
		} else {
			$tt = file_get_contents($tt_cache);
		}

		if ('' !== $tt) {
			break;
		}

		next($url_matches);

		if (null === key($url_matches)) {
			end($url_matches);

			break;
		}
	}

	$fallback_tt_cache = __DIR__ . '/../captions/' . $video_id . '.xml';

	if ('' === $tt && is_file($fallback_tt_cache)) {
		$tt_cache = $fallback_tt_cache;

		$tt = file_get_contents($tt_cache);
	}

	/** @var list<SimpleXMLElement> */
	$lines = [];

	try {
		$xml = new SimpleXMLElement((string) $tt);
	} catch (Throwable $e) {
		if ('' === (string) $tt) {
			throw new RuntimeException(
				sprintf(
					'transcription %s for %s was blank!',
					$tt_cache,
					$video_id
				),
				0,
				$e
			);
		}

		throw new RuntimeException(
			$tt_cache . ': ' . $e->getMessage(),
			0,
			$e
		);
	}

	foreach ($xml->children() as $line) {
		if (null === $line) {
			continue;
		}

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
 *			0:numeric-string|'',
 *			1:numeric-string|'',
 *			2:string
 *		}>,
 *		2:array{
 *			title:string,
 *			skip:list<bool>,
 *			topics:array<int, list<string>>
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
		 *			0:numeric-string|'',
		 *			1:numeric-string|'',
		 *			2:string
		 *		}>,
		 *		2:array{
		 *			title:string,
		 *			skip:list<bool>,
		 *			topics:array<int, list<string>>
		 *		}
		 *	}
		 * > $out
		 *
		 * @return array<
		 *	string,
		 *	array{
		 *		0:string,
		 *		1:list<array{
		 *			0:numeric-string|'',
		 *			1:numeric-string|'',
		 *			2:string
		 *		}>,
		 *		2:array{
		 *			title:string,
		 *			skip:list<bool>,
		 *			topics:array<int, list<string>>
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

			$dated_csv = get_dated_csv($date, $video_id);

			$out[$date] = $dated_csv;

			return $out;
		},
		[]
	);
}

/**
 * @return array<string, array<string, array{
 *	title:string,
 *	tags:list<string>,
 *	topics:list<string>,
 *	legacyof:list<string>
 * }>>
 */
function get_additional_externals() : array
{
	$inject_externals = array_filter(
		glob(__DIR__ . '/../data/externals/*.json'),
		static function (string $path) : bool {
			$info = pathinfo($path, PATHINFO_FILENAME);

			$unix = strtotime($info);

			return false !== $unix && date('Y-m-d', $unix) === $info;
		}
	);

	return array_combine(
		array_map(
			static function (string $path) : string {
				return pathinfo($path, PATHINFO_FILENAME);
			},
			$inject_externals
		),
		array_map(
			/**
			 * @return array<string, array{
			 *	title:string,
			 *	tags:list<string>,
			 *	topics:list<string>,
			 *	legacyof:list<string>
			 * }>
			 */
			static function (string $path) : array {
				/**
				 * @var array<string, array{
				 *	title:string,
				 *	tags:list<string>,
				 *	topics:list<string>,
				 *	legacyof:list<string>
				 * }>
				 */
				return (array) json_decode(file_get_contents($path), true);
			},
			$inject_externals
		)
	);
}

/**
 * @return array{
 *	0:string,
 *	1:list<array{
 *		0:numeric-string|'',
 *		1:numeric-string|'',
 *		2:string
 *	}>,
 *	2:array{
 *		title:string,
 *		skip:list<bool>,
 *		topics:array<int, list<string>>
 *	}
 * }
 */
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

	/** @var list<false|array{0:numeric-string|'', 1:numeric-string|'', 2:string}> */
	$csv = [];

	while (false !== ($line = fgetcsv($fp, 0, ',', '"', '"'))) {
		$csv[] = $line;
	}

	fclose($fp);

	/** @var list<array{0:numeric-string|'', 1:numeric-string|'', 2:string}> */
	$csv = array_values(array_filter($csv, 'is_array'));

	usort(
		$csv,
		/**
		 * @psalm-type IN = array{
		 *	0:numeric-string|'',
		 *	1:numeric-string|'',
		 *	2:string
		 * }
		 *
		 * @param IN $a_in
		 * @param IN $b_in
		 */
		static function (array $a_in, array $b_in) : int {
			[$a] = $a_in;
			[$b] = $b_in;

			$a = '' === $a ? '0' : $a;
			$b = '' === $b ? '0' : $b;

			return ((float) $a) <=> ((float) $b);
		}
	);

	if ( ! $require_json) {
		/**
		 * @var array{
		 *	0:string,
		 *	1:list<array{
		 *		0:numeric-string|'',
		 *		1:numeric-string|'',
		 *		2:string
		 *	}>,
		 *	2:array{
		 *		title:string,
		 *		skip:list<bool>,
		 *		topics:array<int, list<string>>
		 *	}
		 * }
		 */
		return [
			$video_id,
			$csv,
			[],
		];
	}

	/**
	 * @var array{
	 *	title:string,
	 *	skip:list<bool>,
	 *	topics:array<int, list<string>>
	 * }
	 */
	$video_data = json_decode(
		file_get_contents(
			dirname($path)
			. '/'
			. $video_id
			. '.json'
		),
		true
	);

	/**
	 * @var array{
	 *	0:string,
	 *	1:list<array{
	 *		0:numeric-string|'',
	 *		1:numeric-string|'',
	 *		2:string
	 *	}>,
	 *	2:array{
	 *		title:string,
	 *		skip:list<bool>,
	 *		topics:array<int, list<string>>
	 *	}
	 * }
	 */
	return [
		$video_id,
		$csv,
		$video_data,
	];
}

/**
 * @param array{
 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	playlistItems:array<string, array{0:string, 1:string}>,
 *	videoTags:array<string, array{0:string, list<string>}>,
 *	stubPlaylists?:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	legacyAlts?:array<string, list<string>>,
 *	internalxref?:array<string, string>
 * } $cache
 * @param array{
 *	satisfactory: array<string, list<int|string>>
 * } $global_topic_hierarchy
 * @param array<string, string> $not_a_livestream
 * @param array<string, string> $not_a_livestream_date_lookup
 *
 * @return array{
 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
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
			. '/../../video-clip-notes/coffeestainstudiosdevs/satisfactory/'
			. $date .
			'.md'
		);

		if ($write_files) {
			if (
				file_exists(
					__DIR__
					. '/../data/externals/'
					. $date
					. '.json'
				)
			) {
				[, $playlist_friendly_name] = determine_playlist_id(
					$date,
					$cache,
					$not_a_livestream,
					$not_a_livestream_date_lookup
				);

				if (preg_match('/^title: ".+"\n$/', $lines_to_write[0][1])) {
					$lines_to_write[0][1] = preg_replace(
						'/"\n$/',
						sprintf(' & %s"' . "\n", $playlist_friendly_name),
						$lines_to_write[0][1]
					);
				}

				/** @var list<string> */
				$video_ids = array_keys((array) json_decode(
					file_get_contents(
						__DIR__
						. '/../data/externals/'
						. $date
						. '.json'
					),
					true
				));

				usort(
					$video_ids,
					static function (string $a, string $b) use ($cache) : int {
						return strnatcasecmp(
							$cache['playlistItems'][$a][1],
							$cache['playlistItems'][$b][1]
						);
					}
				);

				$lines_to_write[0][] = "\n";

				$lines_to_write[0][] = sprintf(
					'# %s' . "\n",
					$playlist_friendly_name
				);

				foreach ($video_ids as $video_id) {
					$lines_to_write[0][] = sprintf(
						'* %s' . "\n",
						maybe_transcript_link_and_video_url(
							$video_id,
							$cache['playlistItems'][$video_id][1]
						)
					);
				}
			}

			[$processed_lines, $files_with_lines_to_write] = $lines_to_write;

			file_put_contents($filename, '');

			foreach ($processed_lines as $line) {
				file_put_contents($filename, $line, FILE_APPEND);
			}

			foreach ($files_with_lines_to_write as $other_file => $lines) {
				file_put_contents($other_file, implode('', $lines));
			}
		}
	}

	return $inject;
}

/**
 * @psalm-type CACHE = array{
 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	playlistItems:array<string, array{0:string, 1:string}>,
 *	videoTags:array<string, array{0:string, list<string>}>,
 *	stubPlaylists?:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	legacyAlts?:array<string, list<string>>,
 *	internalxref?:array<string, string>
 * }
 *
 * @param CACHE $inject
 * @param array{
 *	0:string,
 *	1:list<array{
 *		0:numeric-string|'',
 *		1:numeric-string|'',
 *		2:string
 *	}>,
 *	2:array{
 *		title:string,
 *		skip:list<bool>,
 *		topics:array<int, list<string>>
 *	}
 * } $externals_data
 * @param CACHE $cache
 * @param array{
 *	satisfactory: array<string, list<int|string>>
 * } $global_topic_hierarchy
 * @param array<string, string> $not_a_livestream
 * @param array<string, string> $not_a_livestream_date_lookup
 *
 * @return array{
 *	0:CACHE,
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
			),
		]);
	}

	/**
	 * @var list<array{
	 *	0:numeric-string|'',
	 *	1:numeric-string|'',
	 *	2:string,
	 *	3?:bool
	 * }>
	 */
	$captions_with_start_time = [];

	if (
		array_key_exists(0, $captions)
		&& array_key_exists(1, $captions)
		&& null === $captions[0]
	) {
		/**
		 * @var array{0:null, 1:list<array{
		 *	text:string|list<string|array{text:string, about?:string}>,
		 *	startTime:string,
		 *	endTime:string,
		 *	speaker?:list<string>,
		 *	followsOnFromPrevious?:bool,
		 *	webvtt?: array{
		 *		position?:positive-int,
		 *		line?:int,
		 *		size?:positive-int,
		 *		align?:'start'|'middle'|'end'
		 *	}
		 * }>}
		 */
		$captions = $captions;

		$captions_with_start_time = array_map(
			/**
			 * @return array{
			 *	0:numeric-string,
			 *	1:numeric-string,
			 *	2:string,
			 *	3:bool
			 * }
			 */
			static function (array $caption_line) : array {
				if (is_array($caption_line['text'])) {
					throw new RuntimeException(
						'JSON transcription not yet supported here!'
					);
				}

				/**
				 * @var array{
				 *	0:numeric-string,
				 *	1:numeric-string,
				 *	2:string,
				 *	3:bool
				 * }
				 */
				return [
					mb_substr($caption_line['startTime'], 2, -1),
					mb_substr($caption_line['endTime'], 2, -1),
					$caption_line['text'],
					$caption_line['followsOnFromPrevious'] ?? false,
				];
			},
			$captions[1]
		);
	} else {
		/** @var list<SimpleXmlElement> */
		$lines = ($captions[1] ?? []);

		foreach ($lines as $caption_line) {
			$attrs = iterator_to_array(
				$caption_line->attributes()
			);

			/** @var array{0:numeric-string, 1:numeric-string, 2:string} */
			$captions_with_start_time_row = [
				(string) $attrs['start'],
				(string) $attrs['dur'],
				(string) preg_replace_callback(
					'/&#(\d+);/',
					static function (array $match) : string {
						return chr((int) $match[1]);
					},
					(string) $caption_line
				),
			];

			$captions_with_start_time[] = $captions_with_start_time_row;
		}
	}

	$csv_captions = array_map(
		/**
		 * @param array{
		 *	0:numeric-string|'',
		 *	1:numeric-string|'',
		 *	2:string
		 * } $csv_line
		 *
		 * @return array{
		 *	0:numeric-string|'',
		 *	1:numeric-string|'',
		 *	2:string,
		 *	3:string
		 * }
		 */
		static function (array $csv_line) use ($captions_with_start_time) : array {
			$csv_line_captions = trim(implode("\n", array_reduce(
				array_filter(
					$captions_with_start_time,
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
					),
					/**
					 * @param non-empty-list<string> $out
					 * @param array{2:string, 3?:bool} $caption_line
					 *
					 * @return non-empty-list<string>
					 */
					static function (array $out, array $caption_line) : array {
						$follows_on_from_previous = $caption_line[3] ?? false;

						if ($follows_on_from_previous) {
							$out[] = preg_replace(
								'/\s+/',
								' ',
								array_pop($out) . ' ' . $caption_line[2]
							);
						} else {
							$out[] = $caption_line[2];
						}

						return $out;
					},
					['']
				)
			));

			$csv_line[3] = $csv_line_captions;

			return $csv_line;
		},
		array_filter(
			$externals_csv,
			static function (int $k) use ($data) : bool {
				return
					isset($data['skip'][$k])
						? ( ! $data['skip'][$k])
						: (false !== ($data['topics'][$k] ?? false));
			},
			ARRAY_FILTER_USE_KEY
		)
	);

	if ($do_injection) {
		$inject['playlists'][$date] = ['', $data['title'], array_map(
			static function (int $i) use ($externals_csv, $video_id) : string {
				[$start, $end] = $externals_csv[$i];

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
			'start' => floor((float) ($start ?: '0')),
			'end' => $end,
		];

		if ('' === $embed_data['end']) {
			unset($embed_data['end']);
		} else {
			$embed_data['end'] = ceil((float) $embed_data['end']);
		}

		$start = (float) ($start ?: '0.0');

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
						$cache,
						$not_a_livestream,
						$not_a_livestream_date_lookup
					);

					if ( ! isset($inject['playlists'][$playlist_id])) {
						$inject['playlists'][$playlist_id] = ['', $topic, []];
					}

					$inject['playlists'][$playlist_id][2][] = $clip_id;
				}
			}

			if ($write_files && '' !== trim($csv_captions[$i][3])) {
				$files_out[
					__DIR__
					. '/../../video-clip-notes/coffeestainstudiosdevs/satisfactory/transcriptions/'
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
									$cache,
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
					"\n",
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
									$cache,
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
					"\n",
				];
			}
		}

		if ($write_files) {
			$out[] = sprintf(
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

function timestamp_link(string $video_id, float $start) : string
{
	$video_id = vendor_prefixed_video_id($video_id);
	$vendorless_video_id = preg_replace('/,.*$/', '', mb_substr($video_id, 3));

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

function embed_link(string $video_id, ? float $start, ? float $end) : string
{
	$video_id = vendor_prefixed_video_id($video_id);
	$vendorless_video_id = preg_replace('/,.*$/', '', mb_substr($video_id, 3));

	if (preg_match('/^yt-/', $video_id)) {
		$start = floor($start ?: 0);
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
			'https://youtube.com/embed/%s?%s',
			$vendorless_video_id,
			$embed
		);
	}

	return timestamp_link($video_id, $start ?? 0.0);
}

function markdownify_transcription_lines(
	string $line,
	string ...$lines
) : string {
	/** @var string */
	static $transcription_blank_lines_regex = '/(>\n>\n)+/';

	array_unshift($lines, $line);

	$transcription_text = implode('', array_map(
		static function (string $caption_line) : string {
			return trim('> ' . $caption_line) . "\n" . '>' . "\n";
		},
		$lines
	));

	while (preg_match($transcription_blank_lines_regex, $transcription_text)) {
		$transcription_text = preg_replace(
			$transcription_blank_lines_regex,
			'>' . "\n",
			$transcription_text
		);
	}

	return preg_replace('/>\n$/', '', $transcription_text);
}

/**
 * @param array<string, array{0:string, 1:string, 2:list<string>}> $playlists
 * @param array<string, string> $playlist_date_ref
 */
function determine_date_for_video(
	string $video_id,
	array $playlists,
	array $playlist_date_ref,
	bool $ignore_lack_of_data = false
) : string {
	/** @var false|string */
	$found = false;

	foreach (array_keys($playlist_date_ref) as $playlist_id) {
		if ( ! $ignore_lack_of_data && ! isset($playlists[$playlist_id])) {
			throw new RuntimeException(sprintf(
				'No data available for playlist %s',
				$playlist_id
			));
		} elseif (
			isset($playlists[$playlist_id])
			&& in_array($video_id, $playlists[$playlist_id][2], true)
		) {
			if (false !== $found) {
				throw new InvalidArgumentException(sprintf(
					'Video %s already found on %s',
					$video_id,
					$found
				));
			}

			$found = $playlist_id;
		}
	}

	if (false === $found) {
		throw new InvalidArgumentException(sprintf(
			'Video %s was not found in any playlist!',
			$video_id
		));
	}

	return $playlist_date_ref[$found];
}

/**
 * @return array<string, array{
 *	title:string,
 *	date:string,
 *	previous:string|null,
 *	next:string|null
 * }>
 */
function cached_part_continued() : array
{
	/**
	 * @var null|array<string, array{
	 *	title:string,
	 *	date:string,
	 *	previous:string|null,
	 *	next:string|null
	 * }>
	 */
	static $part_continued = null;

	if (null === $part_continued) {
		/**
		 * @var array<string, array{
		 *	title:string,
		 *	date:string,
		 *	previous:string|null,
		 *	next:string|null
		 * }>
		 */
		$part_continued = json_decode(
			file_get_contents(__DIR__ . '/../data/part-continued.json'),
			true
		);
	}

	return $part_continued;
}

function has_other_part(string $video_id) : bool
{
	$part_continued = cached_part_continued();

	return
		isset($part_continued[$video_id])
		&& (
			null !== ($part_continued[$video_id]['previous'] ?? null)
			|| null !== ($part_continued[$video_id]['next'] ?? null)
		);
}

/**
 * @return list<string>
 */
function other_video_parts(string $video_id, bool $include_self = true) : array
{
	$out = [];

	if (has_other_part($video_id)) {
		$part_continued = cached_part_continued();

		$checked = [$video_id];

		$checking = $part_continued[$video_id];

		while (null !== $checking['previous']) {
			if (in_array($checking['previous'], $checked, true)) {
				throw new RuntimeException('Infinite loop detected!');
			}

			$checked[] = $checking['previous'];

			$checking = $part_continued[$checking['previous']];
		}

		$out[] = end($checked);

		while (null !== $checking['next']) {
			if (in_array($checking['next'], $out, true)) {
				throw new RuntimeException('Infinite loop detected!');
			}

			$out[] = $checking['next'];

			$checking = $part_continued[$checking['next']];
		}
	}

	if ( ! $include_self) {
		$out = array_values(array_diff($out, [$video_id]));
	}

	return $out;
}
