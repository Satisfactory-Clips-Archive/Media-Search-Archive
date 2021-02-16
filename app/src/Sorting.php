<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function strnatcasecmp;

/**
 * @psalm-type CACHE = array{
 *	playlists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	playlistItems:array<string, array{0:string, 1:string}>,
 *	videoTags:array<string, array{0:string, list<string>}>,
 *	stubPlaylists:array<string, array{0:string, 1:string, 2:list<string>}>,
 *	legacyAlts:array<string, list<string>>,
 *	internalxref:array<string, string>
 * }
 */
class Sorting
{
	const regex_typed_clip = '/^(?|([^:]+)\s*:([^\(]+)\([Pp]art\s+(\d+)\)|([^:]+)\s*:\s*(.+))\s*$/';

	/**
	 * @var CACHE
	 */
	public array $cache = [
		'playlists' => [],
		'playlistItems' => [],
		'videoTags' => [],
		'stubPlaylists' => [],
		'legacyAlts' => [],
		'internalxref' => [],
	];

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
			return strnatcasecmp(
				$this->cache['playlistItems'][$a][1],
				$this->cache['playlistItems'][$b][1]
			);
		}

		return $maybe;
	}
}
