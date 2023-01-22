<?php
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

require_once(__DIR__ . '/../vendor/autoload.php');


use QueryPath\DOMQuery;
use UnexpectedValueException;

$api = new YouTubeApiWrapper();
echo 'YouTube API Wrapper instantiated', "\n";

$slugify = new Slugify();

$skipping = SkippingTranscriptions::i();
echo 'SkippingTranscriptions instantiated', "\n";

$injected = new Injected($api, $slugify, $skipping);

$youtube_video_ids = array_reduce(
	array_filter(
		array_map(
			__NAMESPACE__ . '\vendor_prefixed_video_id',
			$injected->all_video_ids()
		),
		static function (string $video_id) : bool {
			return str_starts_with($video_id, 'yt-');
		}
	),
	/**
	 * @param list<string> $was
	 *
	 * @return list<string>
	 */
	static function (array $was, string $is) : array {
		$is = preg_replace('/,.+/', '', $is);

		if (!in_array($is, $was, true)) {
			$was[] = $is;
		}

		return $was;
	},
	[],
);

$missing_category = array_filter(
	(array) json_decode(file_get_contents(__DIR__ . '/data/youtube-video-subcategories--missing.json')),
	'is_string'
);

$i = 0;

$skipped = 0;

$unsupported_categories = [];

$expected_categories = [
	'2RN5zaBq7MU' => 'UCZnTi4am-kZN8geitjpQV-Q',
	'PLXS5oQZyBY' => 'UC4D6ypDjcIOt7imDp0E9VQA',
	'5zq8YEaPejI' => 'UC4D6ypDjcIOt7imDp0E9VQA',
	'_NRHMCiS5uM' => 'UC4D6ypDjcIOt7imDp0E9VQA',
	'8l8-iRJpxuE' => 'UC4D6ypDjcIOt7imDp0E9VQA',
	'2WRhQ9QNyfI' => 'UCaIQQvS5S-dLgf8XbX1Prkg',
	'Zo2ybvs7keI' => 'UCBy9WI7NEaEDbKnNfjrmpMw',
];

$start = microtime(true);

$supported_category = array_filter(
	(array) json_decode(file_get_contents(__DIR__ . '/data/youtube-video-subcategories--supported.json')),
'is_string'
);

$flush_stat_data = static function () use (& $unsupported_categories, & $missing_category, & $supported_category) : void {
	file_put_contents(__DIR__ . '/data/youtube-video-subcategories--unsupported.json', json_encode_pretty($unsupported_categories));
	file_put_contents(__DIR__ . '/data/youtube-video-subcategories--missing.json', json_encode_pretty($missing_category));
	file_put_contents(__DIR__ . '/data/youtube-video-subcategories--supported.json', json_encode_pretty($supported_category));
};

$emit_progress = static function () use (& $i, $youtube_video_ids, $start) : void {
	$current = microtime(true);

	$length = $current - $start;

	++$i;

	$avg = $length / $i;

	$remaining_seconds_total = (count($youtube_video_ids) - $i) * $avg;

	$remaining_hours = floor($remaining_seconds_total / 3600);
	$remaining_minutes = floor(($remaining_seconds_total - ($remaining_hours * 3600)) / 60);
	$remaining_seconds = ((int) $remaining_seconds_total) % 60;

	$remaining = sprintf(
		'%s:%s:%s',
		$remaining_hours,
		str_pad((string) $remaining_minutes, 2, '0', STR_PAD_LEFT),
		str_pad((string) $remaining_seconds, 2, '0', STR_PAD_LEFT)
	);

	echo "\r", sprintf('%s of %s videos checked, %s estimated remaining', $i, count($youtube_video_ids), $remaining);
};

foreach ($youtube_video_ids as $video_id) {
	if (in_array($video_id, $supported_category) || in_array($video_id, $missing_category, true)) {
		++$i;
		++$skipped;
		$emit_progress();
		continue;
	}

	$filename = mb_substr($video_id, 3) . '.html';

	$html = captions_get_content($filename);

	if ('' === $html) {
		throw new UnexpectedValueException(sprintf('%s does not have a page!', $video_id));
	}

	/** @var DOMQuery[] */
	$html5qp = html5qp($html, 'script');

	$nodes = [];

	foreach ($html5qp as $node) {
		/** @var DOMQuery|string */
		$node_text = $node->text();

		if ( ! is_string($node_text)) {
			throw new UnexpectedValueException(
				'Unsupported text value found!'
			);
		}

		$nodes[] = $node_text;
	}

	$nodes = array_values(array_filter(
		$nodes,
		static function (string $maybe) : bool {
			return (bool) preg_match('/ytInitialData =/', $maybe);
		}
	));

	if (count($nodes) > 1) {
		throw new UnexpectedValueException(sprintf('Too many matches on video %s', $video_id));
	} elseif (count($nodes) < 1) {
		$missing_category[] = $video_id;
		++$i;
		$emit_progress();
		continue;
	} else {
		if ( ! preg_match('/ytInitialData = (.+);(var|const|let)/', $nodes[0], $matches)) {
			if (
				! preg_match('/ytInitialData = (.+);$/', $nodes[0], $matches)
				&& null === json_decode($matches[1])
			) {
				throw new UnexpectedValueException(sprintf('Could not find json blob for %s', $video_id));
			}
		}

		/** @var array{0:string, 1:string, 2?:string} */
		$matches = $matches;

		/**
		 * @var scalar|array|resource|null|object{
		 *	contents?:object{
		 *		twoColumnWatchNextResults?:object{
		 *			results?:object{
		 *				results?: object{
		 *					contents?: array{object, object{
		 *						videoSecondaryInfoRenderer?: object {
		 *							metadataRowContainer?: object{
		 *								metadataRowContainerRenderer?: object{
		 *									rows?: list<object{
		 *										richMetadataRowRenderer?: object{
		 *     										contents?: list<object{
		 *												richMetadataRenderer?: object{
		 *													style?: string,
		 *													endpoint?: object{
		 *														browseEndpoint?: object{
		 *     														browseId?: string
		 *     													}
		 *													}
		 *												}
		 *     										}>
		 *     									}
		 *									}>
		 *								}
		 *							}
		 *						}
		 *					}}
		 *				}
		 *			}
		 *		}
		 *	}
		 * }
		 */
		$raw = json_decode($matches[1]);


		if (
			!is_object($raw)
			|| !isset(
				$raw->contents,
				$raw->contents->twoColumnWatchNextResults,
				$raw->contents->twoColumnWatchNextResults->results,
				$raw->contents->twoColumnWatchNextResults->results->results,
				$raw->contents->twoColumnWatchNextResults->results->results->contents
			)
			|| !is_array($raw->contents->twoColumnWatchNextResults->results->results->contents)
			|| !isset(
				$raw->contents->twoColumnWatchNextResults->results->results->contents[1],
				$raw->contents->twoColumnWatchNextResults->results->results->contents[1]->videoSecondaryInfoRenderer,
				$raw->contents->twoColumnWatchNextResults->results->results->contents[1]->videoSecondaryInfoRenderer->metadataRowContainer,
				$raw->contents->twoColumnWatchNextResults->results->results->contents[1]->videoSecondaryInfoRenderer->metadataRowContainer->metadataRowContainerRenderer,
				$raw->contents->twoColumnWatchNextResults->results->results->contents[1]->videoSecondaryInfoRenderer->metadataRowContainer->metadataRowContainerRenderer->rows
			)
		) {
			$missing_category[] = $video_id;
			++$i;
			$emit_progress();
			continue;
			/*
			throw new UnexpectedValueException(sprintf('Something wrong with json on %s', $video_id));
			*/
		}

		$rows = current(array_filter(
			$raw->contents->twoColumnWatchNextResults->results->results->contents[1]->videoSecondaryInfoRenderer->metadataRowContainer->metadataRowContainerRenderer->rows,
			static function ($maybe): bool {
				return (
					is_object($maybe)
					&& isset(
						$maybe->richMetadataRowRenderer,
						$maybe->richMetadataRowRenderer->contents,
					)
					&& is_array($maybe->richMetadataRowRenderer->contents)
					&& count(array_filter(
						$maybe->richMetadataRowRenderer->contents,
						static function ($innerMaybe) {
							return (
								is_object($innerMaybe)
								&& isset(
									$innerMaybe->richMetadataRenderer,
									$innerMaybe->richMetadataRenderer->style,
									$innerMaybe->richMetadataRenderer->endpoint,
									$innerMaybe->richMetadataRenderer->endpoint->browseEndpoint,
									$innerMaybe->richMetadataRenderer->endpoint->browseEndpoint->browseId
								)
								&& 'RICH_METADATA_RENDERER_STYLE_BOX_ART' === $innerMaybe->richMetadataRenderer->style
							);
						}
					))
				);
			}
		));

		if (!$rows) {
			$missing_category[] = $video_id;
			++$i;
			$emit_progress();
			continue;
		}

		$box_art = current(array_filter(
			$rows->richMetadataRowRenderer->contents,
			static function ($innerMaybe) {
				return (
					is_object($innerMaybe)
					&& isset(
						$innerMaybe->richMetadataRenderer,
						$innerMaybe->richMetadataRenderer->style,
						$innerMaybe->richMetadataRenderer->endpoint,
						$innerMaybe->richMetadataRenderer->endpoint->browseEndpoint,
						$innerMaybe->richMetadataRenderer->endpoint->browseEndpoint->browseId
					)
					&& 'RICH_METADATA_RENDERER_STYLE_BOX_ART' === $innerMaybe->richMetadataRenderer->style
				);
			}
		));

		if (!$box_art) {
			$missing_category[] = $video_id;
			++$i;
			$emit_progress();
			continue;
		}

		if (
			'UC3Cl9NapmjQWRE6IuxW0f-w' !== $box_art->richMetadataRenderer->endpoint->browseEndpoint->browseId
			&& !(
				!isset($expected_categories[$video_id])
				|| $expected_categories[$video_id] !== $box_art->richMetadataRenderer->endpoint->browseEndpoint->browseId
			)
		) {
			$unsupported_categories[$video_id] = $box_art->richMetadataRenderer->endpoint->browseEndpoint->browseId;
			++$i;
			$emit_progress();
			continue;
		}

		if (isset($expected_categories[$video_id], $unsupported_categories[$video_id])) {
			unset($unsupported_categories[$video_id]);
		}
	}

	$emit_progress();

	if (
		!in_array($video_id, $supported_category, true)
	) {
		$supported_category[] = $video_id;
	}
	if (isset($unsupported_categories[$video_id])) {
		unset($unsupported_categories[$video_id]);
	}

	if (0 === ($i % 10)) {
		$flush_stat_data();
	}
}

echo
	"\n",
	sprintf('%s of %s videos found with no sub-category set!', count($missing_category), count($youtube_video_ids)), "\n",
	sprintf('%s of %s videos found with unsupported sub-category set!', count($unsupported_categories), count($youtube_video_ids)), "\n";

$flush_stat_data();
