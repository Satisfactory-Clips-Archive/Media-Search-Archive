<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use QueryPath\DOMQuery;

require_once(__DIR__ . '/../vendor/autoload.php');

$api = new YouTubeApiWrapper();

/** @var array<string, int> $output */
$output = [];

$playlists = $api->fetch_all_playlists();

foreach (array_keys($playlists) as $playlist_id) {
	$page = file_get_contents(__DIR__ . '/playlists-html-cache/' . $playlist_id . '.html');

	$scripts = html5qp($page, 'script');

	/** @var list<string> */
	$nodes = [];

	foreach ($scripts as $node) {
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

	if (1 !== count($nodes)) {
		throw new \UnexpectedValueException(sprintf(
			'Could not find data for %s',
			$playlist_id
		));
	}

	if ( ! preg_match('/ytInitialData = (.+);(var|const|let)/', $nodes[0], $matches)) {
		if (
			! preg_match('/ytInitialData = (.+);$/', $nodes[0], $matches)
			&& null === json_decode($matches[1])
		) {
			throw new \UnexpectedValueException(sprintf(
				'Could not extract data for %s',
				$playlist_id
			));
		}
	}

	$raw = json_decode($matches[1], true, JSON_THROW_ON_ERROR);

	if (
		!isset(
			$raw['header']['playlistHeaderRenderer']['playlistId'],
			$raw['header']['playlistHeaderRenderer']['byline']
		)
	) {
		throw new \UnexpectedValueException(sprintf(
			'Required properties were not present in json for %s',
			$playlist_id
		));
	}

	if ($playlist_id !== $raw['header']['playlistHeaderRenderer']['playlistId']) {
		throw new \UnexpectedValueException(sprintf(
			'Unexpected playlist metadata found, expecting %s found %s',
			$playlist_id,
			$raw['header']['playlistHeaderRenderer']['playlistId']
		));
	}

	$bylines = array_map(
		/**
		 * @param array{playlistBylineRenderer: array{text: array{runs: list<array{text: string}>}}} $runs
		 *
		 * @return list<string>
		 */
		static function (array $runs): array {
			return array_map(
				static function (array $run): string {
					return $run['text'];
				},
				$runs['playlistBylineRenderer']['text']['runs']
			);
		},
		array_values(array_filter(
			(array) $raw['header']['playlistHeaderRenderer']['byline'],
			static function (mixed $maybe): bool {
				return
					isset($maybe['playlistBylineRenderer']['text']['runs'])
					&& is_array($maybe['playlistBylineRenderer']['text']['runs'])
					&& array_is_list($maybe['playlistBylineRenderer']['text']['runs'])
					&& count($maybe['playlistBylineRenderer']['text']['runs']) === count(
						array_filter(
							$maybe['playlistBylineRenderer']['text']['runs'],
							static function (mixed $maybe) {
								return isset($maybe['text']) && is_string($maybe['text']);
							}
						)
					) && preg_match('/^(?:Updated|Last updated) /', $maybe['playlistBylineRenderer']['text']['runs'][0]['text']);
			}
		))
	);

	if (0 === count($bylines)) {
		throw new \UnexpectedValueException(sprintf(
			'No byline matches found for %s',
			$playlist_id
		));
	}
	if (1 !== count($bylines)) {
		throw new \UnexpectedValueException(sprintf(
			'Too many byline matches found for %s',
			$playlist_id
		));
	}

	$byline_last_updated = implode(' ', array_map('trim', $bylines[0]));

	if ('Updated today' === $byline_last_updated) {
		$output[$playlist_id] = strtotime('today');
	} else if ('Updated yesterday' === $byline_last_updated) {
		$output[$playlist_id] = strtotime('yesterday');
	} elseif (preg_match('/^(?:Updated|Last updated) (\d+ days ago)$/', $byline_last_updated, $matches)) {
		$output[$playlist_id] = strtotime($matches[1]);
	} elseif (preg_match('/^Last updated on (\d{1,2}) ([A-z]{3,4}) (\d{4,})$/', $byline_last_updated, $matches)) {
		$output[$playlist_id] = strtotime(sprintf('%2$s %1$s %3$s', $matches[1], $matches[2], $matches[3]));
	} else {
		throw new \UnexpectedValueException(sprintf(
			'Cannot parse a value of %s for %s',
			$byline_last_updated,
			$playlist_id
		));
	}
}

$output = array_map(static function (int $timestamp) : string {
	return date('c', $timestamp);
}, $output);

$today = new \DateTimeImmutable('tomorrow');
$last_tuesday = new \DateTimeImmutable('last tuesday');

$number_of_days = $today->diff($last_tuesday)->days;

$recently_updated = array_map(
	static function (\DateTimeImmutable $date) : string {
		return $date->format('c');
	},
	array_filter(
		array_map(static function (string $date) : \DateTimeImmutable {
			return new \DateTimeImmutable($date);
		}, $output),
		static function (\DateTimeImmutable $date) use ($today, $number_of_days) : bool {
			return $date->diff($today)->days <= $number_of_days;
		}
	)
);

//foreach ($recently_updated as $k => $v) {
//	$recently_updated[$k] = [$playlists[$k], $v];
//}

file_put_contents(__DIR__ . '/playlists-last-updated.json', json_encode_pretty($output));
file_put_contents(__DIR__ . '/playlists-recently-updated.json', json_encode_pretty($recently_updated));
