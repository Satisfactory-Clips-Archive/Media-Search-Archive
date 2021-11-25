<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

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
		$skipping = array_unique($this->video_ids);

		usort($skipping, [$sorting, 'sort_video_ids_by_date']);

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
