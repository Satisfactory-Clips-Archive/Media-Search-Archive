<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use const ARRAY_FILTER_USE_BOTH;
use function count;
use function in_array;
use function mb_strtolower;
use function preg_match;
use function strnatcasecmp;
use function strtotime;
use function trim;

/**
 * @psalm-type CACHE = array{
 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	playlistItems:array<string, array{0:string, 1:string}>,
 *	videoTags:array<string, array{0:string, list<string>}>,
 *	stubPlaylists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	legacyAlts:array<string, list<string>>
 * }
 */
class Sorting
{
	public const regex_typed_clip = '/^(?|([^:]+)\s*:([^\(]+)\([Pp]art\s+(\d+)\)|([^:]+)\s*:\s*(.+))\s*$/';

	/**
	 * @var CACHE
	 */
	public array $cache = [
		'playlists' => [],
		'playlistItems' => [],
		'videoTags' => [],
		'stubPlaylists' => [],
		'legacyAlts' => [],
	];

	/**
	 * @var array<string, string>
	 */
	public array $playlists_date_ref = [];

	/**
	 * @param CACHE|null $cache
	 */
	public function __construct(array $cache = null)
	{
		if (null !== $cache) {
			$this->cache = $cache;
		}
	}

	public function sort_video_ids_alphabetically(string $a, string $b) : int
	{
		$a_title =
			$this->cache['playlistItems'][$a][1]
		;
		$b_title =
			$this->cache['playlistItems'][$b][1]
		;

		if (
			preg_match(self::regex_typed_clip, $a_title, $a_matches)
		) {
			$a_title = mb_strtolower(trim($a_matches[2]));
		}

		if (
			preg_match(self::regex_typed_clip, $b_title, $b_matches)
		) {
			$b_title = mb_strtolower(trim($b_matches[2]));
		}

		if (
			$a_title === $b_title
			&& 4 === count($a_matches)
			&& 4 === count($b_matches)
		) {
			return (int) $a_matches[3] <=> (int) $b_matches[3];
		}

		$maybe = strnatcasecmp($a_title, $b_title);

		if (0 === $maybe) {
			$maybe = strnatcasecmp(
				$this->cache['playlistItems'][$a][1],
				$this->cache['playlistItems'][$b][1]
			);
		}

		if (0 === $maybe) {
			return strnatcasecmp($a, $b);
		}

		return $maybe;
	}

	public function sort_video_ids_by_date(string $a, string $b) : int
	{
		$a_date = determine_date_for_video(
			$a,
			$this->cache['playlists'],
			$this->playlists_date_ref
		);
		$b_date = determine_date_for_video(
			$b,
			$this->cache['playlists'],
			$this->playlists_date_ref
		);

		$maybe = strtotime($b_date) <=> strtotime($a_date);

		if (0 === $maybe) {
			$a_id = array_search($a_date, $this->playlists_date_ref, true);
			$b_id = array_search($b_date, $this->playlists_date_ref, true);

			if (
				$a_id === $b_id
				&& isset($this->cache['playlists'][$a_id])
				&& false === array_search(
					$a,
					$this->cache['playlists'][$a_id][2],
					true
				)
				&& false === array_search(
					$b,
					$this->cache['playlists'][$b_id][2],
					true
				)
			) {
				$maybe_other = array_filter(
					$this->playlists_date_ref,
					function (
						string $value,
						string $key
					) use (
						$a,
						$b,
						$a_date
					) : bool {
						return
							$value === $a_date
							&& $key !== $a_date
							&& in_array(
								$a,
								$this->cache['playlists'][$key][2],
								true
							)
							&& in_array(
								$b,
								$this->cache['playlists'][$key][2],
								true
							);
					},
					ARRAY_FILTER_USE_BOTH
				);

				if (1 === count($maybe_other)) {
					$a_id = $b_id = key($maybe_other);
				}
			}

			if (
				$a_id === $b_id
				&& preg_match('/,\d+/', $a)
				&& preg_match('/,\d+/', $b)
			) {
				[, $a_start] = explode(',', $a);
				[, $b_start] = explode(',', $b);

				return ((float) $a_start) <=> ((float) $b_start);
			} elseif (
				$a_id === $b_id
				&& isset($this->cache['playlists'][$a_id])
			) {
				return
					array_search(
						$a,
						$this->cache['playlists'][$a_id][2],
						true
					) <=> array_search(
						$b,
						$this->cache['playlists'][$b_id][2],
						true
					);
			}

			return $this->sort_video_ids_alphabetically($a, $b);
		}

		return $maybe;
	}

	/**
	 * @psalm-type IN = array{
	 *	children: list<string>,
	 *	videos?: list<string>,
	 *	left: positive-int,
	 *	right: positive-int,
	 *	level: int
	 * }
	 *
	 * @param IN $a
	 * @param IN $b
	 */
	public function sort_by_nleft(array $a, array $b) : int
	{
		return $a['left'] - $b['left'];
	}
}
