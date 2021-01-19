<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\TwitchClipNotes;

use Cocur\Slugify\RuleProvider\RuleProviderInterface;
use Cocur\Slugify\Slugify as Base;

class Slugify extends Base
{
	public function __construct(
		array $options = [],
		RuleProviderInterface $provider = null
	) {
		$this->rules = [
			'ruleset' => [
				'S.A.M.' => 'sam',
				's.a.m.' => 'sam',
				'S.A.M' => 'sam',
				's.a.m' => 'sam',
			],
		];

		parent::__construct($options, $provider);
	}
}
