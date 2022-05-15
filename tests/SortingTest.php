<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext;

/**
 * @psalm-import-type CACHE from Sorting
 */
class SortingTest extends TestCase
{
	/**
	 * @return list<array{
	 *	0:CACHE|null,
	 *	1:string,
	 *	2:string,
	 *	3:int
	 * }>
	 */
	public function data_sort_video_ids_alphabetically() : array
	{
		/**
		 * @var list<array{
		 *	0:CACHE|null,
		 *	1:string,
		 *	2:string,
		 *	3:int
		 * }>
		 */
		return [
			[
				[
					'playlists' => [],
					'playlistItems' => [
						'a' => ['', 'Q&A: foo (Part 0)'],
						'b' => ['', 'Q&A: foo (Part 0)'],
					],
					'videoTags' => ['' => []],
					'stubPlaylists' => ['' => []],
					'legacyAlts' => ['' => []],
				],
				'a',
				'b',
				0,
			],
			[
				[
					'playlists' => [],
					'playlistItems' => [
						'a' => ['', 'Q&A: foo (Part 0)'],
						'b' => ['', 'Q&A: foo (Part 0)'],
					],
					'videoTags' => ['' => []],
					'stubPlaylists' => ['' => []],
					'legacyAlts' => ['' => []],
				],
				'a',
				'a',
				0,
			],
			[
				[
					'playlists' => [],
					'playlistItems' => [
						'a' => ['', 'foo'],
						'b' => ['', 'foo'],
					],
					'videoTags' => ['' => []],
					'stubPlaylists' => ['' => []],
					'legacyAlts' => ['' => []],
				],
				'a',
				'a',
				0,
			],
			[
				[
					'playlists' => [],
					'playlistItems' => [
						'a' => ['', 'foo'],
						'b' => ['', 'foo'],
					],
					'videoTags' => ['' => []],
					'stubPlaylists' => ['' => []],
					'legacyAlts' => ['' => []],
				],
				'b',
				'a',
				1,
			],
			[
				[
					'playlists' => [],
					'playlistItems' => [
						'a' => ['', 'foo'],
						'b' => ['', 'foo'],
					],
					'videoTags' => ['' => []],
					'stubPlaylists' => ['' => []],
					'legacyAlts' => ['' => []],
				],
				'a',
				'b',
				-1,
			],
			[
				[
					'playlists' => [],
					'playlistItems' => [
						'a' => ['', 'Q&A: foo'],
						'b' => ['', 'Q&A: foo'],
					],
					'videoTags' => ['' => []],
					'stubPlaylists' => ['' => []],
					'legacyAlts' => ['' => []],
				],
				'a',
				'b',
				-1,
			],
		];
	}

	/**
	 * @param CACHE|null $cache
	 *
	 * @dataProvider data_sort_video_ids_alphabetically
	 *
	 * @covers \SignpostMarv\VideoClipNotes\Sorting::__construct
	 * @covers \SignpostMarv\VideoClipNotes\Sorting::sort_video_ids_alphabetically
	 *
	 * @throws RecursionContext\InvalidArgumentException but not really, phpstorm is overthinking things
	 * @throws ExpectationFailedException if assertions fail
	 */
	public function test_sort_video_ids_alphabetically(
		? array $cache,
		string $a,
		string $b,
		int $expected
	) : void {
		$sorting = new Sorting($cache);

		$this->assertSame($expected, $sorting->sort_video_ids_alphabetically(
			$a,
			$b
		));
	}
	/**
	 * @return list<array{
	 *	0:CACHE|null,
	 *	1:array<string, string>,
	 *	2:string,
	 *	3:string,
	 *	4:int
	 * }>
	 */
	public function data_sort_video_ids_by_date() : array
	{
		/**
		 * @var list<array{
		 *	0:CACHE|null,
		 *	1:array<string, string>,
		 *	2:string,
		 *	3:string,
		 *	4:int
		 * }>
		 */
		return [
			[
				[
					'playlists' => [
						'c' => [
							'',
							'1970-01-01',
							[
								'a',
							],
						],
						'd' => [
							'',
							'1970-01-02',
							[
								'b',
							],
						],
					],
					'playlistItems' => [
						'a' => ['', 'Q&A: foo'],
						'b' => ['', 'Q&A: foo'],
					],
					'videoTags' => ['' => []],
					'stubPlaylists' => ['' => []],
					'legacyAlts' => ['' => []],
				],
				[
					'c' => '1970-01-01',
					'd' => '1970-01-02',
				],
				'a',
				'b',
				1,
			],
			[
				[
					'playlists' => [
						'c' => [
							'',
							'1970-01-01',
							[
								'a',
							],
						],
						'd' => [
							'',
							'1970-01-01',
							[
								'b',
							],
						],
					],
					'playlistItems' => [
						'a' => ['', 'Q&A: foo'],
						'b' => ['', 'Q&A: foo'],
					],
					'videoTags' => ['' => []],
					'stubPlaylists' => ['' => []],
					'legacyAlts' => ['' => []],
				],
				[
					'c' => '1970-01-01',
					'd' => '1970-01-01',
				],
				'a',
				'b',
				0,
			],
		];
	}

	/**
	 * @param CACHE|null $cache
	 * @param array<string, string> $playlists_date_ref
	 *
	 * @dataProvider data_sort_video_ids_by_date
	 *
	 * @covers \SignpostMarv\VideoClipNotes\Sorting::__construct
	 * @covers \SignpostMarv\VideoClipNotes\Sorting::sort_video_ids_by_date
	 * @covers \SignpostMarv\VideoClipNotes\determine_date_for_video
	 *
	 * @throws RecursionContext\InvalidArgumentException but not really, phpstorm is overthinking things
	 * @throws ExpectationFailedException if assertions fail
	 */
	public function test_sort_video_ids_by_date(
		? array $cache,
		array $playlists_date_ref,
		string $a,
		string $b,
		int $expected
	) : void {
		$sorting = new Sorting($cache);
		$sorting->playlists_date_ref = $playlists_date_ref;

		$this->assertSame($expected, $sorting->sort_video_ids_by_date(
			$a,
			$b
		));
	}

	/**
	 * @psalm-type IN = array{
	 *	children: list<string>,
	 *	videos?: list<string>,
	 *	left: positive-int,
	 *	right: positive-int,
	 *	level: int
	 * }
	 *
	 * @return list<array{
	 *	0:CACHE|null,
	 *	1:array<string, string>,
	 *	2:IN,
	 *	3:IN,
	 *	4:int
	 * }>
	 */
	public function data_sort_by_nleft() : array
	{
		/**
		 * @var list<array{
		 *	0:CACHE|null,
		 *	1:array<string, string>,
		 *	2:IN,
		 *	3:IN,
		 *	4:int
		 * }>
		 */
		return [
			[
				null,
				[],
				[
					'children' => [],
					'left' => 0,
					'right' => 1,
					'level' => 0,
				],
				[
					'children' => [],
					'left' => 2,
					'right' => 3,
					'level' => 0,
				],
				-2,
			],
			[
				null,
				[],
				[
					'children' => [],
					'left' => 2,
					'right' => 3,
					'level' => 0,
				],
				[
					'children' => [],
					'left' => 0,
					'right' => 1,
					'level' => 0,
				],
				2,
			],
			[
				null,
				[],
				[
					'children' => [],
					'left' => 0,
					'right' => 1,
					'level' => 0,
				],
				[
					'children' => [],
					'left' => 0,
					'right' => 1,
					'level' => 0,
				],
				0,
			],
		];
	}

	/**
	 * @psalm-type IN = array{
	 *	children: list<string>,
	 *	videos?: list<string>,
	 *	left: positive-int,
	 *	right: positive-int,
	 *	level: int
	 * }
	 *
	 * @param CACHE|null $cache
	 * @param array<string, string> $playlists_date_ref
	 * @param IN $a
	 * @param IN $b
	 *
	 * @dataProvider data_sort_by_nleft
	 *
	 * @covers \SignpostMarv\VideoClipNotes\Sorting::__construct
	 * @covers \SignpostMarv\VideoClipNotes\Sorting::sort_by_nleft
	 *
	 * @throws RecursionContext\InvalidArgumentException but not really, phpstorm is overthinking things
	 * @throws ExpectationFailedException if assertions fail
	 */
	public function test_sort_by_nleft(
		? array $cache,
		array $playlists_date_ref,
		array $a,
		array $b,
		int $expected
	) : void {
		$sorting = new Sorting($cache);
		$sorting->playlists_date_ref = $playlists_date_ref;

		$this->assertSame($expected, $sorting->sort_by_nleft(
			$a,
			$b
		));
	}
}
