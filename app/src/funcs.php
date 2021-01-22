<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\TwitchClipNotes;

function video_url_from_id(string $video_id, bool $short = false) : string
{
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
	if (0 === mb_strpos($video_id, 'tc-')) {
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

	if (0 === mb_strpos($video_id, 'tc-')) {
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
	if (0 === mb_strpos($video_id, 'tc-')) {
		return $video_id;
	}

	return 'yt-' . $video_id;
}
