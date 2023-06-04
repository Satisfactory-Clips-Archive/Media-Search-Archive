<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use PHPUnit\Framework\TestCase;

class TalkTest extends TestCase
{
	/**
	 * @covers \SignpostMarv\VideoClipNotes\Questions::filter_video_ids
	 */
	public function test_talk() : void
	{
		ob_start();
		$api = new YouTubeApiWrapper();

		$slugify = new Slugify();

		$skipping = SkippingTranscriptions::i();

		$injected = new Injected($api, $slugify, $skipping);

		$questions = new Questions($injected);
		ob_end_clean();

		$this->assertSame(
			['yt-6X4jqMUtCwI,961.5606,1021.9209000000001'],
			$questions->filter_video_ids(
				['yt-6X4jqMUtCwI,961.5606,1021.9209000000001'],
				'talk'
			)
		);
	}
}
