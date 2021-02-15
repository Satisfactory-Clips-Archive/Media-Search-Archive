<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function count;

class Filtering
{
	/**
	 * @param array{
	 *	title:string,
	 *	date:string,
	 *	topics:list<string>,
	 *	duplicates?:list<string>,
	 *	replaces?:list<string>,
	 *	replacedby?:string,
	 *	duplicatedby?:string,
	 *	seealso?:list<string>,
	 *	suggested?:list<string>
	 * } $maybe
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
}
