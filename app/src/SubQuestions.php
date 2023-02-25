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

	public function update(): void
	{
		$out = json_decode(
			file_get_contents(__DIR__ . '/../data/q-and-a.sub.json'),
			true,
			512,
			JSON_THROW_ON_ERROR
		);

		foreach ($this->injected->all_video_ids() as $video_id)
		{
			if ($this->string_is_probably_question((string) $this->injected->determine_video_title($video_id))) {
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
