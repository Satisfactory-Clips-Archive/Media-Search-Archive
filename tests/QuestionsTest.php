<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext;

class QuestionsTest extends TestCase
{
	/**
	 * @covers \SignpostMarv\VideoClipNotes\Questions::filter_video_ids
	 *
	 * @throws RecursionContext\InvalidArgumentException but not really, phpstorm is overthinking things
	 * @throws ExpectationFailedException if assertions fail
	 */
	public function test_filter_video_ids_matches_typescript() : void
	{
		ob_start();
		$api = new YouTubeApiWrapper();

		$slugify = new Slugify();

		$skipping = SkippingTranscriptions::i();

		$injected = new Injected($api, $slugify, $skipping);

		$questions = new Questions($injected);
		ob_end_clean();

		/** @var array<string, string> */
		$kvp = json_decode(
			file_get_contents(__DIR__ . '/fixtures/title-kvp.json'),
			true
		);

		/** @var array<value-of<Questions::REGEX_TYPES>, list<string>> */
		$ts = json_decode(
			file_get_contents(
				__DIR__ . '/fixtures/title-pattern-check.ts.json'
			),
			true
		);

		$video_ids = array_keys($kvp);

		$php = array_reduce(
			Questions::REGEX_TYPES,
			/**
			 * @psalm-type RES = array<string, list<string>>
			 *
			 * @param RES $result
			 *
			 * @return RES
			 */
			static function (
				array $result,
				string $category
			) use ($questions, $video_ids) : array {
				/** @var value-of<Questions::REGEX_TYPES> */
				$category = $category;

				$result[$category] = $questions->filter_video_ids(
					$video_ids,
					$category,
				);

				return $result;
			},
			[]
		);

		$this->assertSame($ts, $php);
	}
}
