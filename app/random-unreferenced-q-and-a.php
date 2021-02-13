<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function array_filter;
use function array_rand;
use function count;
use function file_get_contents;
use function json_decode;

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

$questions = array_filter($questions, static function (array $maybe) : bool {
	return
		count($maybe['duplicates']) < 1
		&& count($maybe['replaces']) < 1
		&& count($maybe['seealso']) < 1
		&& ! isset($maybe['replacedby'])
	;
});

echo array_rand($questions);
