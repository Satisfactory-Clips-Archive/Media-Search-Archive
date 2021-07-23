<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use Cocur\Slugify\RuleProvider\RuleProviderInterface;
use Cocur\Slugify\Slugify as Base;

class Slugify extends Base
{
	public function __construct(
		array $options = [],
		RuleProviderInterface $provider = null
	) {
		$this->rules = [
			'S.A.M.' => 'sam',
			's.a.m.' => 'sam',
			'S.A.M' => 'sam',
			's.a.m' => 'sam',
			'm.A.M.' => 'mam',
			'm.a.m.' => 'mam',
			'm.A.M' => 'mam',
			'm.a.m' => 'mam',
			'mk++' => 'mk-plus-plus',
			'Mk++' => 'mk-plus-plus',
			'Hannah\'s' => 'hannahs',
			'hannah\'s' => 'hannahs',
			'can\'t' => 'cant',
		];

		parent::__construct($options, $provider);
	}
}
