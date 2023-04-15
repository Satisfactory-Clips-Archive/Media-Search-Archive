<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes\CaptionsSource;

use UnexpectedValueException;

class ProxiedCaptionsSource extends AbstractCaptionsSource
{
	/**
	 * @var list<AbstractCaptionsSource>
	 */
	private readonly array $sources;

	public function __construct(AbstractCaptionsSource ...$sources)
	{
		$this->sources = $sources;
	}


	public function remove_cached_file(string $filename): void
	{
		foreach ($this->sources as $source) {
			$source->remove_cached_file($filename);
		}
	}

	public function add_from_string(string $filename, string $contents): void
	{
		foreach ($this->sources as $source) {
			$source->add_from_string($filename, $contents);
		}
	}

	public function get_content(string $filename): string
	{
		foreach ($this->sources as $source) {
			if ($source->exists($filename)) {
				return $source->get_content($filename);
			}
		}

		throw new UnexpectedValueException(sprintf('No source found with %s', $filename));
	}

	public function exists(string $filename): bool
	{
		foreach ($this->sources as $source) {
			if ($source->exists($filename)) {
				return true;
			}
		}

		return false;
	}
}
