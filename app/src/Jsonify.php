<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use RuntimeException;

class Jsonify
{
	const link_part_regex = '/^(.+) \[[^\]]+\]\(([^\)]+)\)$/';

	const transcript_part_regex = '/^\[([^\]]+)\]\(\.([^\)]+)\.md\)$/';

	private Injected $injected;

	private Questions $questions;

	public function __construct(
		Injected $injected,
		Questions $questions = null
	) {
		$this->injected = $injected;
		$this->questions = $questions ?? new Questions($injected);
	}

	/**
	 * @return array<empty, empty>|array{
	 *	0:string,
	 *	1:array{0:string, 1:string, 2:string}
	 * }
	 */
	public function content_if_video_has_other_parts(
		string $video_id,
		bool $include_self = false
	) : array {
		if ( ! has_other_part($video_id)) {
			return [];
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

		/** @var array{0:string, 1:array{0:string, 1:string, 2:string}} */
		$out = ['', ['', '', '']];

		if (count($video_other_parts) > 2) {
			$out[0] = sprintf(
				'This video is part of a series of %s videos.',
				count($video_other_parts)
			);
		} elseif (null !== $video_part_info['previous']) {
			$out[0] = 'This video is a continuation of a previous video';
		} else {
			$out[0] = 'This video continues in another video';
		}

		if (count($video_other_parts) > 2) {
			$video_other_parts = other_video_parts($video_id, false);
		}

		foreach ($video_other_parts as $other_video_id) {
			throw new RuntimeException('Time to actually implement this bit!');
			/*
			$link = maybe_transcript_link_and_video_url(
				$other_video_id,
				(
					$this->injected->friendly_dated_playlist_name(
						$playlist_id
					)
					. ' '
					. $this->injected->cache['playlistItems'][$other_video_id][1]
				)
			);

			if ( ! preg_match(self::link_part_regex, $link, $link_parts)) {
				throw new RuntimeException('Could not determine link parts!');
			}

			$out[1][0] = $link_parts[1];
			$out[1][2] = $link_parts[2];

			if (
				preg_match(
					self::transcript_part_regex,
					$link_parts[1],
					$link_parts
				)
			) {
				$out[1][0] = $link_parts[1];
				$out[1][1] = $link_parts[2];
			}
			*/
		}

		return $out;
	}

	public function description_if_video_has_duplicates(
		string $video_id,
		Questions $questions = null
	) : string {
		$questions = $questions ?? $this->questions;

		$faq_duplicates = $questions->process()[1][$video_id] ?? [];

		if ([] === $faq_duplicates) {
			return '';
		}

		$injected = $questions->injected;

		uasort(
			$faq_duplicates,
			[$injected->sorting, 'sort_video_ids_by_date']
		);

		$faq_duplicate_dates = [];

		$faq_duplicates_for_date_checking = array_diff(
			$faq_duplicates,
			[
				$video_id,
			]
		);

		foreach ($faq_duplicates_for_date_checking as $other_video_id) {
			$faq_duplicate_video_date = determine_date_for_video(
				$other_video_id,
				$injected->cache['playlists'],
				$injected->api->dated_playlists()
			);

			if (
				! in_array($faq_duplicate_video_date, $faq_duplicate_dates, true)
			) {
				$faq_duplicate_dates[] = $faq_duplicate_video_date;
			}
		}

		return sprintf(
				'This question may have been asked previously at least %s other %s',
				count($faq_duplicates_for_date_checking),
				count($faq_duplicates_for_date_checking) > 1 ? 'times' : 'time'
			)
			. sprintf(
				', as recently as %s%s',
				date('F Y', strtotime(current($faq_duplicate_dates))),
				(
					count($faq_duplicate_dates) > 1
						? (
							' and as early as '
							. date('F Y.', strtotime(end($faq_duplicate_dates)))
						)
						: '.'
				)
			);
	}
}
