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
			'#SaveTheWillows' => 'save-the-willows',
			'#savethewillows' => 'save-the-willows',
			'Northern Forest World Update Q&A with Hannah' => 'northern-forest-world-update-q-and-a-with-hannah',
			'northern forest world update q&a with hannah' => 'northern-forest-world-update-q-and-a-with-hannah',
			'Update 5 Quiz: Underrated/Overrated' => 'underrated-or-overrated-quiz',
			'Update 5 Loot' => 'loot',
			'Update 5 Art Giveaway' => 'art-giveaway',
			'Update 5 Challenge Run' => 'challenge-run',
			'Update 5 Final Countdown' => 'its-the-final-countdown',
			'Foundation Stencils' => 'stencils',
			'Snutty Mays & Juice Velvet Present: The Customizer™' => 'snutty-mays-and-juice-velvet-present-the-customizer',
		];

		parent::__construct($options, $provider);
	}
}
