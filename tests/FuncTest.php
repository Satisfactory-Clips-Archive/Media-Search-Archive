<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use PHPUnit\Framework\TestCase;

class FuncTest extends TestCase
{
	/**
	 * @return list<array{0:string, 1:string|null}>
	 */
	public function data_maybe_video_override() : array
	{
		return [
			['', null],
			[
				'is-2492243863408545767',
				'https://satisfactory.fandom.com/wiki/File:January_22nd%2C_2021_Instagram_AMA_-_story_is_fully_written.mp4',
			],
			[
				'yt-Y7G72e0LLBg,71.42,114.46',
				'https://youtube.com/clip/UgkxL9vaIrt0MxCytSleaR7vgs63AITDM5ih',
			],
		];
	}

	/**
	 * @dataProvider data_maybe_video_override
	 *
	 * @covers \SignpostMarv\VideoClipNotes\maybe_video_override
	 * @covers \SignpostMarv\VideoClipNotes\Filtering::kvp_string_string
	 */
	public function test_maybe_video_override(
		string $video_id,
		? string $expected
	) : void {
		$this->assertSame($expected, maybe_video_override($video_id));
	}

	/**
	 * @return list<array{
	 *	0:string,
	 *	1:bool,
	 *	2:string
	 * }>
	 */
	public function data_video_url_from_id() : array
	{
		return [
			[
				'is-2492243863408545767',
				false,
				'https://satisfactory.fandom.com/wiki/File:January_22nd%2C_2021_Instagram_AMA_-_story_is_fully_written.mp4',
			],
			[
				'is-2492243863408545767',
				true,
				'https://satisfactory.fandom.com/wiki/File:January_22nd%2C_2021_Instagram_AMA_-_story_is_fully_written.mp4',
			],
			[
				'yt-rePLsjw-eEY,114.51440000000001,226.29273333333333',
				false,
				'https://youtube.com/embed/rePLsjw-eEY?autoplay=1&start=114&end=227',
			],
			[
				'yt-rePLsjw-eEY,114.51440000000001,226.29273333333333',
				true,
				'https://youtube.com/embed/rePLsjw-eEY?autoplay=1&start=114&end=227',
			],
			[
				'yt-0123456789a,0,',
				false,
				'https://youtube.com/embed/0123456789a?autoplay=1&start=0&end=0',
			],
			[
				'yt-0123456789a,0,',
				true,
				'https://youtube.com/embed/0123456789a?autoplay=1&start=0&end=0',
			],
			[
				'yt-0123456789a,,10',
				false,
				'https://youtube.com/embed/0123456789a?autoplay=1&start=0&end=10',
			],
			[
				'yt-0123456789a,,10',
				true,
				'https://youtube.com/embed/0123456789a?autoplay=1&start=0&end=10',
			],
			[
				'yt-0123456789a',
				false,
				'https://www.youtube.com/watch?v=yt-0123456789a',
			],
			[
				'yt-0123456789a',
				true,
				'https://youtu.be/yt-0123456789a',
			],
			[
				'ts-0',
				false,
				'https://twitch.tv/videos/0',
			],
			[
				'ts-0',
				true,
				'https://twitch.tv/videos/0',
			],
		];
	}

	/**
	 * @dataProvider data_video_url_from_id
	 *
	 * @covers \SignpostMarv\VideoClipNotes\video_url_from_id
	 * @covers \SignpostMarv\VideoClipNotes\maybe_video_override
	 * @covers \SignpostMarv\VideoClipNotes\embed_link
	 * @covers \SignpostMarv\VideoClipNotes\vendor_prefixed_video_id
	 * @covers \SignpostMarv\VideoClipNotes\Filtering::kvp_string_string
	 */
	public function test_video_url_from_id(
		string $video_id,
		bool $short,
		string $expected
	) : void {
		$this->assertSame($expected, video_url_from_id($video_id, $short));
	}
}
