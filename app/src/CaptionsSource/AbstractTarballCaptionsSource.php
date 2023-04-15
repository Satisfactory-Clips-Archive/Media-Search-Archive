<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes\CaptionsSource;

use PharData;

abstract class AbstractTarballCaptionsSource extends AbstractCaptionsSource
{
	protected ?PharData $data = null;

	abstract protected function captions_data() : PharData;

	public function remove_cached_file(string $filename): void
	{
		$captions_data = $this->captions_data();

		unset($captions_data[$filename]);
	}

	public function add_from_string(string $filename, string $contents): void
	{
		$captions_data = $this->captions_data();

		$captions_data->addFromString($filename, $contents);
	}

	public function get_content(string $filename): string
	{
		return file_get_contents($this->captions_data()[$filename]->getPathname());
	}

	public function exists(string $filename): bool
	{
		$captions_data = $this->captions_data();

		return isset($captions_data[$filename]);
	}
}
