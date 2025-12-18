<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use BadFunctionCallException;
use function bcadd;
use function count;
use function dirname;
use function explode;
use function glob;
use function max;
use function mb_strlen;
use function preg_match;
use function sprintf;
use UnexpectedValueException;

require_once(__DIR__ . '/../vendor/autoload.php');

if (3 !== count($argv)) {
	throw new BadFunctionCallException(sprintf(
		'Expected 2 arguments, %s given!',
		count($argv)
	));
}

[, $video_id, $offset] = $argv;

if ( ! is_numeric($offset)) {
	throw new UnexpectedValueException(sprintf(
		'Argument 2 passed to %s expected to be numeric!',
		__FILE__
	));
} elseif ( ! preg_match('/^yt-[^,]{11}$/', $video_id)) {
	throw new UnexpectedValueException(sprintf(
		'Argument 1 passed to %s expected to be of the format yt-xxxxxxxxxxx',
		__FILE__
	));
}

$glob = glob(__DIR__ . '/../Media-Search-Archive-Data/data/dated/*/' . $video_id . '.csv');

if (1 !== count($glob)) {
	throw new UnexpectedValueException(sprintf(
		'Could not find CSV, %s results found!',
		count($glob)
	));
}

[$csv_filepath] = $glob;

$date = basename(dirname($csv_filepath));

[, $csv] = get_dated_csv($date, $video_id, false);

/** @var array<string, string> */
$replace_old_with_new = [];

$offset_decimals = mb_strlen(explode('.', $offset)[1] ?? '');

file_put_contents($csv_filepath, '');

$fp = fopen($csv_filepath, 'wb');

foreach ($csv as $row) {
	[$start, $end, $title] = $row;

	$row_video_id_old = sprintf('%s,%s,%s', $video_id, $start, $end);

	$changed = false;

	if ('' !== $start) {
		/** @var numeric-string */
		$start = $start;

		$decimals = max(
			$offset_decimals,
			mb_strlen(explode('.', $start)[1] ?? '')
		);

		$start = bcadd($start, $offset, $decimals);

		$changed = true;
	}

	if ('' !== $end) {
		/** @var numeric-string */
		$end = $end;

		$decimals = max(
			$offset_decimals,
			mb_strlen(explode('.', $end)[1] ?? '')
		);

		$end = bcadd($end, $offset, $decimals);

		$changed = true;
	}

	$row_video_id_new = sprintf(
		'%s,%s,%s',
		$video_id,
		$start,
		$end
	);

	if ($changed) {
		$replace_old_with_new[$row_video_id_old] = $row_video_id_new;
	}

	fputcsv($fp, [$start, $end, $title]);
}

fclose($fp);

if (count($replace_old_with_new)) {
	foreach (
		[
			__DIR__ . '/skipping-transcriptions.json',
			__DIR__ . '/../Media-Search-Archive-Data/data/skipping-cards.json',
		] as $skipping_filepath
	) {
		echo $skipping_filepath, "\n";
		echo 'reading into memory', "\n";

		/** @var list<string> */
		$skipping = array_filter(
			(array) json_decode(file_get_contents($skipping_filepath)),
			'is_string'
		);

		$fresh = [];

		echo 'modifying', "\n";

		$changed = false;

		foreach ($skipping as $maybe) {
			if (isset($replace_old_with_new[$maybe])) {
				$fresh[] = $replace_old_with_new[$maybe];

				$changed = true;
			} else {
				$fresh[] = $maybe;
			}
		}

		if ($changed) {
			echo 'writing to file', "\n";

			file_put_contents($skipping_filepath, json_encode_pretty($fresh));
		} else {
			echo 'no changes needed', "\n";
		}
	}

	/**
	 * @var array<string, array{
	 *	title:string,
	 *	date:string,
	 *	topics:list<string>,
	 *	seealso?:list<string>,
	 *	duplicates?:list<string>,
	 *	replaces?:list<string>,
	 *	duplicatedby?:string,
	 *	replacedby?:string
	 * }>
	 */
	$qanda = json_decode(
		file_get_contents(__DIR__ . '/../Media-Search-Archive-Data/data/q-and-a.json'),
		true
	);

	/**
	 * @var array<string, array{
	 *	title:string,
	 *	date:string,
	 *	topics:list<string>,
	 *	seealso?:list<string>,
	 *	duplicates?:list<string>,
	 *	replaces?:list<string>,
	 *	duplicatedby?:string,
	 *	replacedby?:string
	 * }>
	 */
	$fresh = [];

	foreach ($qanda as $k => $v) {
		if (isset($replace_old_with_new[$k])) {
			$k = $replace_old_with_new[$k];
		}

		if (
			isset(
				$v['duplicatedby'],
				$replace_old_with_new[$v['duplicatedby']]
			)
		) {
			$v['duplicatedby'] = $replace_old_with_new[$v['duplicatedby']];
		}

		if (
			isset(
				$v['replacedby'],
				$replace_old_with_new[$v['replacedby']]
			)
		) {
			$v['replacedby'] = $replace_old_with_new[$v['replacedby']];
		}

		foreach (['seealso', 'duplicates', 'replaces'] as $maybe) {
			if (isset($v[$maybe])) {
				/** @var list<string> */
				$values = $v[$maybe];

				$v[$maybe] = array_map(
					static function (
						string $maybe_replace
					) use (
						$replace_old_with_new
					) : string {
						if (isset($replace_old_with_new[$maybe_replace])) {
							return $replace_old_with_new[$maybe_replace];
						}

						return $maybe_replace;
					},
					$values
				);
			}
		}

		$fresh[$k] = $v;
	}

	file_put_contents(__DIR__ . '/../Media-Search-Archive-Data/data/q-and-a.json', json_encode_pretty(
		$fresh
	));
}

echo 'done', "\n";
