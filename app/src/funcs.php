<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\TwitchClipNotes;

use function file_get_contents;
use InvalidArgumentException;
use function json_decode;

function video_url_from_id(string $video_id, bool $short = false) : string
{
	/** @var null|array<string, string> */
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

	if (0 === mb_strpos($video_id, 'tc-')) {
		return sprintf(
			'https://clips.twitch.tv/%s',
			rawurlencode(mb_substr($video_id, 3))
		);
	} elseif ($short) {
		return sprintf('https://youtu.be/%s', rawurlencode($video_id));
	}

	return (
		'https://www.youtube.com/watch?' .
		http_build_query([
			'v' => $video_id,
		])
	);
}

function transcription_filename(string $video_id) : string
{
	if (11 !== mb_strlen($video_id) && preg_match('/^(tc|is)\-/', $video_id)) {
		return (
			__DIR__
			. '/../../coffeestainstudiosdevs/satisfactory/transcriptions/'
			. $video_id
			. '.md'
		);
	}

	return (
		__DIR__
		. '/../../coffeestainstudiosdevs/satisfactory/transcriptions/yt-'
		. $video_id
		. '.md'
	);
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
	if (11 !== mb_strlen($video_id) && preg_match('/^(tc|is)\-/', $video_id)) {
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
				$cache['videoTags'][$video_id] = $video_id;
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
		throw new InvalidArgumentException(
			'Topic not in cache!'
		);
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

	if (\preg_match('/^\d{4,}\-\d{2}\-\d{2}$/', $playlist_name)) {
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
			. $suffix
		;

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
