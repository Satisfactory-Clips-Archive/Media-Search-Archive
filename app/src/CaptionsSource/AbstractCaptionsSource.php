<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes\CaptionsSource;

abstract class AbstractCaptionsSource
{
	abstract public function remove_cached_file(string $filename) : void;

	abstract public function add_from_string(string $filename, string $contents) : void;

	abstract public function get_content(string $filename) : string;

	abstract public function exists(string $filename) : bool;
}
