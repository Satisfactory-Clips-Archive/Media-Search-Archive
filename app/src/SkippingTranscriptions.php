<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function count;
use function in_array;
use const JSON_PRETTY_PRINT;

class SkippingTranscriptions
{
	/** @var list<string> */
	public array $video_ids = [];

	protected function __construct()
	{
		/** @var list<string> */
		$previously_skipped = json_decode(
			file_get_contents(__DIR__ . '/../skipping-transcriptions.json'),
			true
		);

		$this->video_ids = $previously_skipped;
	}

	public function sync(Sorting $sorting) : void
	{
		$skipping = array_unique(array_map(
			__NAMESPACE__ . '\vendor_prefixed_video_id',
			$this->video_ids
		));

		usort($skipping, [$sorting, 'sort_video_ids_by_date']);

		$might_not_actually_be_skipped = array_filter(
			$skipping,
			static function (string $maybe) : bool {
				return (bool) preg_match('/^yt-[^,]+,/', $maybe);
			}
		);

		$might_not_actually_be_skipped = array_combine(
			$might_not_actually_be_skipped,
			array_map(
				static function (string $id) : string {
					return preg_replace('/^yt-([^,]+)(?:,.*)$/', '$1', $id);
				},
				$might_not_actually_be_skipped
			)
		);

		$might_also_not_actually_be_skipped = array_map(
			__NAMESPACE__ . '\vendor_prefixed_video_id',
			$might_not_actually_be_skipped
		);

		$probably_not_actually_skipped = [];

		foreach (array_keys($might_not_actually_be_skipped) as $maybe) {
			if (
				! in_array(
					$might_not_actually_be_skipped[$maybe],
					$skipping,
					true
				)
				&& ! in_array(
					$might_also_not_actually_be_skipped[$maybe],
					$skipping,
					true
				)
			) {
				if ( ! isset($probably_not_actually_skipped[$might_not_actually_be_skipped[$maybe]])) {
					$probably_not_actually_skipped[$might_not_actually_be_skipped[$maybe]] = [];
				}

				$probably_not_actually_skipped[$might_not_actually_be_skipped[$maybe]][] = $maybe;
			}
		}

		if (count($probably_not_actually_skipped)) {
			$faux_skipping = new self();
			$faux_skipping->video_ids = [];

			$not_actually_skipped = [];

			foreach (array_keys($probably_not_actually_skipped) as $maybe) {
				if (count(raw_captions($maybe, $faux_skipping))) {
					$not_actually_skipped[] = $maybe;
				}
			}

			$skipping = array_values(array_diff(
				$skipping,
				...array_values($probably_not_actually_skipped)
			));
		}

		$this->video_ids = $skipping;

		file_put_contents(
			(
				__DIR__
				. '/../skipping-transcriptions.json'
			),
			json_encode(
				$skipping,
				JSON_PRETTY_PRINT
			)
		);
	}

	public static function i() : self
	{
		/** @var self|null */
		static $instance = null;

		if (null === $instance) {
			$instance = new self();
		}

		return $instance;
	}
}
