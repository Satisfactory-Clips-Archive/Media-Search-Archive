<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_diff;
use function array_search;
use function array_values;
use function count;
use function current;
use function date;
use function end;
use function in_array;
use function is_string;
use RuntimeException;
use function sprintf;
use function strtotime;
use function uasort;

class Markdownify
{
	private Injected $injected;

	private Questions $questions;

	public function __construct(
		Injected $injected,
		Questions $questions = null
	) {
		$this->injected = $injected;
		$this->questions = $questions ?? new Questions($injected);
	}

	public function content_if_video_has_other_parts(
		string $video_id,
		bool $include_self = false
	) : string {
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

		$out .= $this->content_from_other_video_parts(
			$playlist_id,
			$video_other_parts
		);

		$out .= '</details>' . "\n";

		return $out;
	}

	public function content_if_video_has_duplicates(
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

		$faq_duplicates_for_date_checking = array_values(array_diff(
			$faq_duplicates,
			[
				$video_id,
			]
		));

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

		$out = "\n"
			. '<details>'
			. "\n"
			. '<summary>'
			. sprintf(
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
			) .
			'</summary>' .
			"\n"
		;

		$out .= "\n";
		$out .= $this->content_from_other_video_parts(
			null,
			$faq_duplicates_for_date_checking,
			$questions
		);

		$out .= '</details>' . "\n";

		return $out;
	}

	public function content_if_video_has_seealsos(
		string $video_id,
		Questions $questions = null
	) : string {
		$questions = $questions ?? $this->questions;

		$see_alsos = $questions->process()[2][$video_id] ?? [];

		if ([] === $see_alsos) {
			return '';
		}

		$injected = $questions->injected;

		uasort(
			$see_alsos,
			[$injected->sorting, 'sort_video_ids_by_date']
		);

		$out = "\n"
			. '<details>'
			. "\n"
			. '<summary>'
			. sprintf(
				'There %2$s %1$u other %3$s that may be related to this one',
				count($see_alsos),
				count($see_alsos) > 1 ? 'are' : 'is',
				count($see_alsos) > 1 ? 'videos' : 'video'
			)
			. '</summary>'
			. "\n"
		;

		$out .= "\n";
		$out .= $this->content_from_other_video_parts(
			null,
			$see_alsos,
			$questions
		);

		$out .= '</details>' . "\n";

		return $out;
	}

	public function content_if_video_is_a_duplicate(string $video_id) : string
	{
		return $this->content_if_video_is_thinged(
			$video_id,
			'duplicatedby',
			'duplicated'
		);
	}

	public function content_if_video_is_replaced(string $video_id) : string
	{
		return $this->content_if_video_is_thinged(
			$video_id,
			'replacedby',
			'replaced'
		);
	}

	/**
	 * @param 'duplicatedby'|'replacedby' $thinged
	 */
	private function content_if_video_is_thinged(
		string $video_id,
		string $thinged,
		string $friendly
	) : string {
		[$existing] = $this->questions->process();

		/** @var string|null */
		$found = $existing[$video_id][$thinged] ?? null;

		if (null === $found) {
			return '';
		}
		$found_date = determine_date_for_video(
			$found,
			$this->injected->cache['playlists'],
			$this->injected->api->dated_playlists()
		);
		$playlist_id = array_search(
			$found_date,
			$this->injected->api->dated_playlists(), true
		);

		if ( ! is_string($playlist_id)) {
			throw new RuntimeException(sprintf(
				'Could not find playlist id for %s',
				$video_id
			));
		}

		return sprintf(
			(
				"\n" .
				'This question was possibly %s with a more recent answer: %s'
				. "\n"
			),
			$friendly,
			maybe_transcript_link_and_video_url(
				$found,
				(
					$this->injected->friendly_dated_playlist_name($playlist_id)
					. ' '
					. $this->injected->cache['playlistItems'][$found][1]
				)
			)
		);
	}

	/**
	 * @param list<string> $video_other_parts
	 */
	private function content_from_other_video_parts(
		? string $playlist_id,
		array $video_other_parts,
		Questions $questions = null
	) : string {
		$injected =
			null === $questions
				? $this->injected
				: $questions->injected;
		$reset_playlist_id = null === $playlist_id;
		$out = '';

		foreach ($video_other_parts as $other_video_id) {
			if (null === $playlist_id) {
				$other_video_date = determine_date_for_video(
					$other_video_id,
					$injected->cache['playlists'],
					$injected->api->dated_playlists()
				);
				$playlist_id = array_search(
					$other_video_date,
					$injected->api->dated_playlists(), true
				);

				if ( ! is_string($playlist_id)) {
					throw new RuntimeException(sprintf(
						'Could not find playlist id for %s',
						$other_video_id
					));
				}
			}

			$out .= '* '
				. maybe_transcript_link_and_video_url(
					$other_video_id,
					(
						$this->injected->friendly_dated_playlist_name(
							$playlist_id
						)
						. ' '
						. $this->injected->cache['playlistItems'][$other_video_id][1]
					)
				)
				. "\n"
			;

			if ($reset_playlist_id) {
				$playlist_id = null;
			}
		}

		return $out;
	}
}
