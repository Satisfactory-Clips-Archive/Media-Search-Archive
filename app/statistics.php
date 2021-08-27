<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function count;

require_once(__DIR__ . '/../vendor/autoload.php');

$api = new YouTubeApiWrapper();

$contents = $api->fetch_all_videos_in_playlists();

$probably_has_standard_description = array_filter(
	$contents,
	static function (array $maybe) : bool {
		return '🏭' !== $maybe[2];
	}
);

$topic_slugs = json_decode(
	file_get_contents(
		__DIR__
		. '/topics-satisfactory.json'
	),
	true
);

$topic_slugs_check = array_map(
	static function (string $slug) : string {
		return
			__DIR__
			. '/../Media-Archive-Metadata/src/permalinked/topics/'
			. $slug
			. '.js';
	},
	array_combine(array_keys($topic_slugs), array_keys($topic_slugs))
);

$has_structured_data = array_filter(
	$topic_slugs_check,
	'is_file'
);

$has_no_structured_data = array_keys(array_filter(
	$topic_slugs_check,
	static function (string $maybe) : bool {
		return ! is_file($maybe);
	}
));

natsort($has_no_structured_data);

file_put_contents(
	(__DIR__ . '/data/topic-has-no-structured-data.json'),
	json_encode_pretty(array_values($has_no_structured_data))
);

$skipping_transcriptions = count(json_decode(file_get_contents(
	__DIR__
	. '/skipping-transcriptions.json'
)));

$has_structured_transcription = array_filter(
	array_map(
		static function (string $video_id) : string {
			return
				__DIR__
				. '/../Media-Archive-Metadata/transcriptions/'
				. vendor_prefixed_video_id($video_id)
				. '.json';
		},
		array_keys($contents)
	),
	'is_file'
);

/**
 * @var list<array{
 *	title:string,
 *	progress?:numeric-string,
 *	percentage?:numeric-string,
 *	count?:int
 * }>
 */
$statistics = [
	[
		'title' => 'Total Clips',
		'count' => count($contents),
	],
	[
		'title' => 'Total Topics',
		'count' => count($topic_slugs),
	],
	[
		'title' => 'Number of days worth of source streams & videos indexed',
		'count' => count($api->dated_playlists()),
	],
	[
		'title' => 'Clips probably having standard description',
		'progress' => ($progress = bcdiv(
			(string) count($probably_has_standard_description),
			(string) count($contents),
			6
		)),
		'percentage' => bcmul(
			'100',
			$progress,
			2
		),
	],
	[
		'title' => 'Clips with any transcription',
		'progress' => ($progress = bcdiv(
			(string) (count($contents) - $skipping_transcriptions),
			(string) count($contents),
			6
		)),
		'percentage' => bcmul(
			'100',
			$progress,
			2
		),
	],
	[
		'title' => 'Clips with structured transcription',
		'progress' => ($progress = bcdiv(
			(string) count($has_structured_transcription),
			(string) count($contents),
			6
		)),
		'percentage' => bcmul(
			'100',
			$progress,
			2
		),
	],
	[
		'title' => 'Topics with structured data',
		'progress' => ($progress = bcdiv(
			(string) count($has_structured_data),
			(string) count($topic_slugs),
			6
		)),
		'percentage' => bcmul(
			'100',
			$progress,
			2
		),
	],
];

file_put_contents(
	__DIR__ . '/../11ty/data/statistics.json',
	json_encode_pretty(
		$statistics
	)
);
