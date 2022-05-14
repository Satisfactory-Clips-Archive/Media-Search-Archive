<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

class TopicNesting
{
	/** @readonly */
	public string $topic_id;

	/** @readonly */
	public string $topic_name;

	public int $clips = 0;

	public int $hdepth_for_templates = 1;

	/** @var list<string> */
	public array $children = [];

	public int $left = -1;

	public int $right = -1;

	public int $level = -1;

	/** @var list<string> */
	public array $videos = [];

	public function __construct(string $topic_id, string $topic_name)
	{
		$this->topic_id = $topic_id;
		$this->topic_name = $topic_name;
	}
}
