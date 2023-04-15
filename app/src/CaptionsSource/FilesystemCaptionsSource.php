<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes\CaptionsSource;

final class FilesystemCaptionsSource extends AbstractCaptionsSource
{
	const DIR = __DIR__ . '/../../captions/';

	public function remove_cached_file(string $filename): void
	{
		$filepath = self::DIR . $filename;

		if (is_file($filepath)) {
			unlink($filepath);
		}
	}

	public function add_from_string(string $filename, string $contents): void
	{
		file_put_contents(self::DIR . $filename, $contents);
	}

	public function get_content(string $filename): string
	{
		return file_get_contents(self::DIR . $filename);
	}

	public function exists(string $filename): bool
	{
		return is_file(self::DIR . $filename);
	}
}
