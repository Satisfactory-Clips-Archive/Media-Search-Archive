<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_diff;
use function array_filter;
use const ARRAY_FILTER_USE_KEY;
use function array_keys;
use function array_map;
use function array_merge;
use function array_reduce;
use function array_reverse;
use function array_slice;
use function array_values;
use function asort;
use function count;
use function file_get_contents;
use function in_array;
use function is_string;
use function json_decode;
use function mb_strtolower;
use function natcasesort;
use function preg_match;
use function preg_quote;
use function sprintf;

require_once(__DIR__ . '/../vendor/autoload.php');

$skipping = SkippingTranscriptions::i();

/**
 * @var array<string, array{
 *	title:string,
 *	date:string,
 *	topics:list<string>,
 *	duplicates:list<string>,
 *	replaces:list<string>,
 *	seealso:list<string>
 * }>
 */
$questions = json_decode(
	file_get_contents(__DIR__ . '/data/q-and-a.json'),
	true
);

/**
 * @var array<string, list<string>>
 */
$videos_by_topic = json_decode(
	file_get_contents(__DIR__ . '/data/video-id-by-topic.json'),
	true
);

/** @var array<string, string> */
$topic_slugs = json_decode(
	file_get_contents(__DIR__ . '/data/all-topic-slugs.json'),
	true
);

$boolean_all_topics_mode = in_array('--all', $argv, true);

if ($boolean_all_topics_mode) {
	$argv = array_values(array_filter(
		$argv,
		static function (string $maybe) : bool {
			return '--all' !== $maybe;
		}
	));
}

$lookup = array_slice($argv, 1)[0] ?? null;

$api = new YouTubeApiWrapper();

$api->update();

$slugify = new Slugify();

$injected = new Injected($api, $slugify, $skipping);

[
	$cache,
] = prepare_injections($api, $slugify, $skipping, $injected);

if ( ! is_string($lookup)) {
	echo 'no video id specified!'
	;

	exit(1);
} elseif ( ! isset($questions[$lookup])) {
	echo 'the specified video id was not identified as a question!'
	;

	exit(1);
}

$other_topics = array_keys(array_filter(
	$topic_slugs,
	static function (string $maybe) use ($lookup, $questions) : bool {
		return in_array($maybe, $questions[$lookup]['topics'], true);
	}
));

$other_videos = [];

foreach ($other_topics as $topic_id) {
	foreach ($videos_by_topic[$topic_id] as $video_id) {
		if ($video_id !== $lookup) {
			if ( ! isset($other_videos[$video_id])) {
				$other_videos[$video_id] = 0;
			}

			++$other_videos[$video_id];
		}
	}
}

$topics_for_lookup = array_keys(array_filter(
	$videos_by_topic,
	/**
	 * @param list<string> $maybe
	 */
	static function (array $maybe) use ($lookup) : bool {
		return in_array($lookup, $maybe, true);
	}
));

natcasesort($topics_for_lookup);

if ($boolean_all_topics_mode) {
	$other_videos = array_filter(
		$other_videos,
		static function (string $maybe) use ($topics_for_lookup, $videos_by_topic) : bool {
			$topics_for_maybe = array_keys(array_filter(
				$videos_by_topic,
				/**
				 * @param list<string> $video_ids
				 */
				static function (array $video_ids) use ($maybe) : bool {
					return in_array($maybe, $video_ids, true);
				}
			));

			natcasesort($topics_for_maybe);

			return $topics_for_maybe === $topics_for_lookup;
		},
		ARRAY_FILTER_USE_KEY
	);
}

$legacy_alts = array_reduce(
	$cache['legacyAlts'],
	/**
	 * @param list<string> $out
	 * @param list<string> $alts
	 *
	 * @return list<string>
	 */
	static function (array $out, array $alts) : array {
		return array_merge($out, array_diff($alts, $out));
	},
	[]
);

$other_videos = array_filter(
	$other_videos,
	static function (string $maybe) use ($legacy_alts) : bool {
		return ! in_array($maybe, $legacy_alts, true);
	},
	ARRAY_FILTER_USE_KEY
);

asort($other_videos);
$other_videos = array_reverse($other_videos, true);

if (count($other_videos) < 1) {
	echo 'no other videos identified on related topics!',
		"\n";

	exit(1);
}

$filter = array_slice($argv, 2);

if (count($filter) > 0) {
	$filter = array_map(
		static function (string $word) : string {
			return '/\b' . preg_quote(mb_strtolower($word), '/') . '\b/i';
		},
		$filter
	);

	$other_videos = array_filter(
		$other_videos,
		static function (string $maybe) use ($cache, $filter) : bool {
			foreach ($filter as $regex) {
				if (preg_match($regex, $cache['playlistItems'][$maybe][1])) {
					return true;
				}
			}

			return false;
		},
		ARRAY_FILTER_USE_KEY
	);
}

echo sprintf('%s other videos identified!', count($other_videos)), "\n";

foreach ($other_videos as $video_id => $topic_matches) {
	echo sprintf(
			'%s %s (%s topics in common)',
			$video_id,
			$cache['playlistItems'][$video_id][1],
			$topic_matches
		),
		"\n"
	;
}
