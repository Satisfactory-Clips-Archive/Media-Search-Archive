<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function count;
use function ob_get_contents;
use function ob_start;
use function sprintf;
use function preg_replace;

class Markdownify
{
	public function content_if_video_has_other_parts(string $video_id)
	{
		if ( ! has_other_part($video_id)) {
			return '';
		}

		$video_part_info = cached_part_continued()[$video_id];
		$video_other_parts = other_video_parts($video_id);

		ob_start();

		echo "\n",
			'<details>',
			"\n",
			'<summary>';

		if (count($video_other_parts) > 2) {
			echo sprintf(
				'This video is part of a series of %s videos.',
				count($video_other_parts)
			);
		} elseif (null !== $video_part_info['previous']) {
			echo 'This video is a continuation of a previous video';
		} else {
			echo 'This video continues in another video';
		}

		echo '</summary>', "\n\n";

		if (count($video_other_parts) > 2) {
			$video_other_parts = other_video_parts($video_id, false);
		}

		foreach ($video_other_parts as $other_video_id) {
			echo '* ',
				preg_replace('/\.md\)/', ')', str_replace(
					'./',
					'https://archive.satisfactory.video/',
					maybe_transcript_link_and_video_url(
						$other_video_id,
						(
							$injected->friendly_dated_playlist_name(
								$playlist_id
							)
							. ' '
							. $cache['playlistItems'][$other_video_id][1]
						)
					)
				)),
				"\n"
			;
		}

		return ob_get_contents();
	}
}
