<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes\CaptionsSource;

use InvalidArgumentException;

final class PreferredCaptionsSource extends AbstractCaptionsSource
{
	public function __construct(
		private readonly AbstractCaptionsSource $fallback,
		private readonly AbstractCaptionsSource $preferred
	) {}

	public function remove_cached_file(string $filename): void
	{
		$this->fallback->remove_cached_file($filename);
		$this->preferred->remove_cached_file($filename);
	}

	public function add_from_string(string $filename, string $contents): void
	{
		$this->preferred->add_from_string($filename, $contents);
		$this->fallback->remove_cached_file($filename);
	}

	public function get_content(string $filename): string
	{
		if ( ! $this->preferred->exists($filename)) {
			if ( ! $this->fallback->exists($filename)) {
				throw new InvalidArgumentException(sprintf(
					'Filename not found in preferred or fallback sources: %s',
					$filename
				));
			}

			$content = $this->fallback->get_content($filename);
			$this->preferred->add_from_string($filename, $content);
		}

		return $this->preferred->get_content($filename);
	}

	public function exists(string $filename): bool
	{
		return $this->preferred->exists($filename) || $this->fallback->exists($filename);
	}
}
