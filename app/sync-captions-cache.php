<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use PharData;
use SignpostMarv\VideoClipNotes\CaptionsSource\DynamicDatedTarballCaptionsSource;
use SignpostMarv\VideoClipNotes\CaptionsSource\FilesystemCaptionsSource;
use SignpostMarv\VideoClipNotes\CaptionsSource\MonolithicTarballCaptionsSource;

require_once(__DIR__ . '/../vendor/autoload.php');

$api = new YouTubeApiWrapper();
echo 'YouTube API Wrapper instantiated', "\n";

$slugify = new Slugify();

$skipping = SkippingTranscriptions::i();
echo 'SkippingTranscriptions instantiated', "\n";

$injected = new Injected($api, $slugify, $skipping);
echo 'Injected instantiated', "\n";

$fallback = new FilesystemCaptionsSource();
$new_source = new DynamicDatedTarballCaptionsSource($injected, $fallback);

$old_source = new PharData(
	MonolithicTarballCaptionsSource::TAR_FILEPATH,
	(
		PharData::CURRENT_AS_PATHNAME
		| PharData::SKIP_DOTS
		| PharData::UNIX_PATHS
	)
);

$total_files = count($old_source);

$offset = 0;

$skip = [
	'3JiUE8sBbCY.html',
	'7CxTjOw1uys.html',
	'XGnGc9zsiWI.html',
	'aDb37nwZVeI.html',
	'eYXpQc1ORN8.html',
	'e_fp1kroZTY.html',
	'ts-952893339.html',
	'ts-953014105.html',
];

foreach ($old_source as $filename) {
	++$offset;

	$basename = basename($filename);

	if (in_array($basename, $skip, true)) {
		continue;
	}

	if ( ! $new_source->exists($basename)) {
		$new_source->add_from_string(
			$basename,
			file_get_contents($filename)
		);
	}

	echo "\r", (($offset / $total_files) * 100), '%';
}

echo "\n", 'checking untarballed', "\n";

$files = array_map('basename', array_merge(
	glob(__DIR__ . '/captions/*.html'),
	glob(__DIR__ . '/captions/*.xml'),
));

$skipping_untarballed = [
	'lYRlELsuNak.html',
	'lYRlELsuNak,hl-en-gbandkind-asrandlang-en.xml',
];

$files = array_filter($files, static function (string $maybe) use ($skipping_untarballed): bool {
	return !in_array($maybe, $skipping_untarballed, true);
});

$offset = 0;
$total_files = count($files);

foreach ($files as $filename) {
	++$offset;
	if ($fallback->exists($filename) && !$new_source->exists($filename)) {
		$new_source->add_from_string($filename, file_get_contents(__DIR__ . '/captions/' . $filename));
		$fallback->remove_cached_file($filename);
	}
	echo "\r", (($offset / $total_files) * 100), '% ', $offset, ' of ', $total_files;
}
