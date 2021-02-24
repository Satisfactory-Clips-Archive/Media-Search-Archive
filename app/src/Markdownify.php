<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_search;
use function count;
use function preg_replace;
use function sprintf;
use function str_replace;

class Markdownify
{
	private Injected $injected;

	public function __construct(Injected $injected)
	{
		$this->injected = $injected;
	}

	public function content_if_video_has_other_parts(
		string $video_id,
		bool $include_self = false
	) {
		if ( ! has_other_part($video_id)) {
			return '';
		}

		$date = determine_date_for_video(
			$video_id,
			$this->injected->cache['playlists'],
			$this->injected->api->dated_playlists()
		);

		$playlist_id = array_search(
			$date,
			$this->injected->api->dated_playlists(), true
		);

		if (false === $playlist_id) {
			throw new RuntimeException(sprintf(
				'Could not determine dated playlist id for %s',
				$video_id
			));
		}

		$video_part_info = cached_part_continued()[$video_id];
		$video_other_parts = other_video_parts($video_id, $include_self);

		$out = "\n"
			. '<details>'
			. "\n"
			. '<summary>'
		;

		if (count($video_other_parts) > 2) {
			$out .= sprintf(
				'This video is part of a series of %s videos.',
				count($video_other_parts)
			);
		} elseif (null !== $video_part_info['previous']) {
			$out .= 'This video is a continuation of a previous video';
		} else {
			$out .= 'This video continues in another video';
		}

		$out .= '</summary>' . "\n\n";

		if (count($video_other_parts) > 2) {
			$video_other_parts = other_video_parts($video_id, false);
		}

		foreach ($video_other_parts as $other_video_id) {
			$out .= '* '
				. preg_replace('/\.md\)/', ')', str_replace(
					'./',
					'https://archive.satisfactory.video/',
					maybe_transcript_link_and_video_url(
						$other_video_id,
						(
							$this->injected->friendly_dated_playlist_name(
								$playlist_id
							)
							. ' '
							. $this->injected->cache['playlistItems'][$other_video_id][1]
						)
					)
				))
				. "\n"
			;
		}

		$out .= '</details>' . "\n";

		return $out;
	}
}
