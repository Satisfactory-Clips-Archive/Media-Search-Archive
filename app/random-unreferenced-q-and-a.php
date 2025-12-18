<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_filter;
use function array_keys;
use function array_rand;
use function file_get_contents;
use function implode;
use function json_decode;

require_once(__DIR__ . '/../vendor/autoload.php');

$filtering = new Filtering();

$date = $argv[1] ?? null;

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
	file_get_contents(__DIR__ . '/../Media-Search-Archive-Data/data/q-and-a.json'),
	true
);

if (isset($date)) {
	$questions = array_filter(
		$questions,
		static function (array $maybe) use ($date) : bool {
			return $maybe['date'] === $date;
		}
	);
}

$questions = array_filter(
	$questions,
	[$filtering, 'QuestionDataNoReferences']
);

if ('--show-all' === ($argv[2] ?? null)) {
	echo "\n", implode("\n", array_keys($questions)), "\n";

	return;
}

echo array_rand($questions);
