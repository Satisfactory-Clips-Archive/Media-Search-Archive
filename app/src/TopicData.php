<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use UnexpectedValueException;

class TopicData
{
	/**
	 * @var array<string, list<int|string>>
	 *
	 * @readonly
	 */
	public array $injected = [];

	protected function __construct()
	{
		/** @var array<string, list<int|string>> */
		$injected_global_topic_hierarchy = array_map(
			/**
			 * @param mixed[] $route_to_topic
			 *
			 * @return list<int|string>
			 */
			static function (array $route_to_topic) : array {
				/** @var list<int|string> */
				$filtered = array_values(array_filter(
					$route_to_topic,
					/**
					 * @param array-key $maybe_key
					 * @param mixed $maybe_value
					 *
					 * @psalm-assert-if-true int $maybe_key
					 * @psalm-assert-if-true int|string $maybe_value
					 */
					static function($maybe_value, $maybe_key) : bool {
						return (
							is_int($maybe_key)
							&& (
								is_string($maybe_value)
								|| (
									is_int($maybe_value)
									&& 0 === $maybe_key
								)
							)
						);
					},
					ARRAY_FILTER_USE_BOTH
				));

				if (count($filtered) !== count($route_to_topic)) {
					throw new UnexpectedValueException(
						'Unsupported value found in injected topics!'
					);
				}

				return $filtered;
			},
			array_filter(
				(array) json_decode(
					file_get_contents(
						__DIR__ . '/../topic-data/injected.json'
					),
					true
				),
				/**
				 * @param array-key $maybe_key
				 * @param mixed $maybe_value
				 *
				 * @psalm-assert-if-true string $maybe_key
				 * @psalm-assert-if-true array $maybe_value
				 */
				static function ($maybe_value, $maybe_key) : bool {
					return (
						is_string($maybe_key)
						&& is_array($maybe_value)
					);
				},
				ARRAY_FILTER_USE_BOTH
			)
		);

		$this->injected = $injected_global_topic_hierarchy;
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
