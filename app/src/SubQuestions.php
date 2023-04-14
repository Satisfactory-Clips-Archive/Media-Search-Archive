<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

class SubQuestions extends AbstractQuestions
{
	public readonly Injected $injected;

	public function __construct(Injected $injected)
	{
		$this->injected = $injected;

		parent::__construct($injected->sorting);
	}

	/**
	 * @param list<string> $ignore_these_video_ids
	 */
	public function obtain_sub_question_ids(array $ignore_these_video_ids): array
	{
		/** @var list<string> */
		$sub_question_ids = [];

		foreach ($this->injected->all_video_ids() as $video_id)
		{
			if ($this->string_is_probably_question((string) $this->injected->determine_video_title($video_id))) {
				continue;
			}

			if (in_array($video_id, $ignore_these_video_ids, true)) {
				continue;
			}

			$chapters = array_values(array_filter(
				array_map(
					static function (string $timestamped_line): string {
						return trim(preg_replace('/ https?:\/\/.+/', '', $timestamped_line));
					},
					array_filter(
						array_map(
							'trim',
							explode("\n", (string) $this->injected->determine_video_description($video_id))
						),
						static function (string $maybe) : bool {
							return '' !== $maybe && preg_match('/^(\d+:)+\d{2} .+/', $maybe);
						}
					)
				),
				function (string $maybe) : bool {
					return $this->string_is_probably_question($maybe);
				}
			));

			if (count($chapters) < 1) {
				continue;
			}

			foreach ($chapters as $chapter) {
				$chapter_parts = explode(' ', $chapter);
				$timestamp = array_shift($chapter_parts);

				$time_parts = array_reverse(explode(':', $timestamp));

				$seconds = 0;

				foreach ($time_parts as $offset => $time_part) {
					$seconds += ((int)$time_part * (60 ** $offset));
				}

				$sub_question_ids[] = $video_id . '#' . $timestamp;
			}
		}

		return $sub_question_ids;
	}

	public function update(): void
	{
		$out = json_decode(
			file_get_contents(__DIR__ . '/../data/q-and-a.sub.json'),
			true,
			512,
			JSON_THROW_ON_ERROR
		);

		$sub_question_ids = $this->obtain_sub_question_ids([]);

		foreach ($this->injected->all_video_ids() as $video_id)
		{
			$chapters = array_values(array_filter(
				array_map(
					static function (string $timestamped_line): string {
						return trim(preg_replace('/ https?:\/\/.+/', '', $timestamped_line));
					},
					array_filter(
						array_map(
							'trim',
							explode("\n", (string) $this->injected->determine_video_description($video_id))
						),
						static function (string $maybe) : bool {
							return '' !== $maybe && preg_match('/^(\d+:)+\d{2} .+/', $maybe);
						}
					)
				),
				function (string $maybe) : bool {
					return $this->string_is_probably_question($maybe);
				}
			));

			if (count($chapters) < 1) {
				continue;
			}

			$url = video_url_from_id($video_id, true);

			foreach ($chapters as $chapter) {
				$chapter_parts = explode(' ', $chapter);
				$timestamp = array_shift($chapter_parts);
				$title = implode(' ', $chapter_parts);

				$time_parts = array_reverse(explode(':', $timestamp));

				$seconds = 0;

				foreach ($time_parts as $offset => $time_part) {
					$seconds += ((int) $time_part * (60 ** $offset));
				}

				$chapter_id = $video_id . '#' . $timestamp;

				if ( ! in_array($chapter_id, $sub_question_ids, true)) {
					continue;
				}

				if (isset($out[$chapter_id])) {
					$stub = $out[$chapter_id];
				} else {
					$stub = [
						'id' => $chapter_id,
						'title' => $title,
						'timestamp' => $timestamp,
						'seconds' => $seconds,
						'url' => $url . '?t=' . urlencode((string) $seconds),
						'seealso' => [],
						'duplicates' => [],
						'replaces' => [],
					];
				}

				$stub['title'] = $title;
				$stub['seconds'] = $seconds;
				$stub['url'] = $url . '?t=' . urlencode((string) $seconds);

				$out[$chapter_id] = $stub;
			}
		}

		file_put_contents(
			__DIR__ . '/../data/q-and-a.sub.json',
			json_encode_pretty(array_map(
				static function (array $stub): array {
					foreach (['seealso', 'duplicates', 'replaces'] as $cross_reference) {
						if (isset($stub[$cross_reference]) && count($stub[$cross_reference]) < 1) {
							unset($stub[$cross_reference]);
						}
					}

					return $stub;
				},
				$out
			))
		);
	}
}
