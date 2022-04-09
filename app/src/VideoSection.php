<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use function count;
use function is_array;
use const STR_PAD_LEFT;

class VideoSection
{
	/**
	 * @readonly
	 */
	public string $title;

	/**
	 * @readonly
	 */
	public string $link;

	/**
	 * @readonly
	 */
	public string $start;

	/**
	 * @readonly
	 */
	public ?string $end;

	/**
	 * @readonly
	 */
	public bool $is_all_section;

	/**
	 * @var list<array|VideoSection>
	 *
	 * @readonly
	 */
	public array $subsections = [];

	/**
	 * @param VideoSection|array{
	 *	link:string,
	 *	started_formatted:string,
	 *	has_captions:bool,
	 *	title:string,
	 *	start:numeric,
	 *	end?:numeric
	 * } ...$subsections
	 */
	public function __construct(
		string $title,
		string $link,
		string $start,
		?string $end,
		array|VideoSection ...$subsections
	) {
		$this->title = $title;
		$this->is_all_section = count($subsections) === array_filter(
			$subsections,
			'is_object'
		);
		$this->subsections = $subsections;
		$this->link = $link;
		$this->start = $start;
		$this->end = $end;
	}

	public function jsonSerialize() : array
	{
		$this->is_all_section = count($this->subsections) === array_filter(
			$this->subsections,
			'is_object'
		);

		/** @var numeric-string */
		$start = ($this->start ?: '0.0');

		$decimals = mb_strlen(explode('.', $start)[1] ?? '');

		$start_hours = str_pad((string) floor(((float) $start) / 3600), 2, '0', STR_PAD_LEFT);
		$start_minutes = str_pad((string) floor((float) bcdiv(bcmod($start, '3600', $decimals) ?? '0', '60', $decimals)), 2, '0', STR_PAD_LEFT);
		$start_seconds = str_pad((string) floor((float) bcmod($start, '60', $decimals)), 2, '0', STR_PAD_LEFT);

		return [
			'is_section' => true,
			'is_all_section' => $this->is_all_section,
			'title' => $this->title,
			'link' => $this->link,
			'start' => $this->start,
			'started_formatted' => sprintf(
				'%s:%s:%s',
				$start_hours,
				$start_minutes,
				$start_seconds
			),
			'end' => $this->end,
			'subsections' => array_map(
				static function (array|VideoSection $subsection) : array {
					if (is_array($subsection)) {
						$subsection['is_section'] = false;

						return $subsection;
					}

					return $subsection->jsonSerialize();
				},
				$this->subsections
			),
		];
	}
}
