<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function count;
use function is_string;

/**
 * @psalm-import-type MAYBE from Questions
 */
class Filtering
{
	/**
	 * @param MAYBE $maybe
	 */
	public function QuestionDataNoReferences(array $maybe) : bool
	{
		return
			count($maybe['duplicates'] ?? []) < 1
			&& count($maybe['replaces'] ?? []) < 1
			&& count($maybe['seealso'] ?? []) < 1
			&& ! isset($maybe['replacedby'])
			&& ! isset($maybe['duplicatedby'])
		;
	}

	/**
	 * @psalm-assert-if-true string $maybe_value
	 * @psalm-assert-if-true string $maybe_key
	 *
	 * @param scalar|array|object|resource|null $maybe_value
	 * @param array-key $maybe_key
	 */
	public function kvp_string_string($maybe_value, $maybe_key) : bool
	{
		return is_string($maybe_value) && is_string($maybe_key);
	}
}
