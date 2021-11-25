<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use UnexpectedValueException;

class TopicData
{
	const NOT_A_LIVESTREAM = [
		'PLbjDnnBIxiEqbUTvxOt7tlshFbT-skT_2' => 'Satisfactory Update 5 Patch Notes vid commentary',
		'PLbjDnnBIxiEpWeDmJ93Uxdxsp1ScQdfEZ' => 'Teasers',
		'PLbjDnnBIxiEpmVEhuMrGff6ES5V34y2wW' => 'Teasers',
		'PLbjDnnBIxiEoEYUiAzSSmePa-9JIADFrO' => 'Teasers',
		'2017-08-01' => 'Tutorial',
		'2017-11-17' => 'Introduction',
		'2018-03-09' => 'Q&A',
		'2018-06-22' => 'Q&A',
		'2018-07-04' => 'Video',
		'2018-07-19' => 'Dev Blog',
		'2018-08-01' => 'Q&A',
		'2018-08-15' => 'Video',
		'2018-09-12' => 'Alpha Info',
		'2018-09-19' => 'Video',
		'2018-09-26' => 'Video',
		'2018-10-03' => 'Alpha Info',
		'2018-11-08' => 'Dev Blog',
		'2018-11-23' => 'Dev Blog',
		'2018-12-12' => 'Q&A',
		'2018-12-25' => 'Video',
		'2019-01-19' => 'Video',
		'2019-03-07' => 'Q&A',
		'2019-03-15' => 'Q&A',
		'2019-04-17' => 'Video',
		'2019-04-26' => 'Milo Tutorial',
		'2019-05-14' => 'Patch Notes',
		'2019-05-24' => 'Video',
		'2019-06-07' => 'Video',
		'2019-07-02' => 'Patch Notes',
		'2019-07-06' => 'Video',
		'2019-08-30' => 'Video',
		'2019-09-13' => 'Video',
		'2019-09-25' => 'Patch Notes',
		'2019-10-24' => 'Video',
		'2019-11-05' => 'Q&A',
		'2019-12-02' => 'Patch Notes',
		'2019-12-13' => 'Video',
		'2019-12-19' => 'Video',
		'2020-01-20' => 'Video',
		'2020-01-24' => 'Video',
		'2020-02-20' => 'Video',
		'2020-03-12' => 'Patch Notes',
		'2020-04-02' => 'Q&A',
		'2020-04-10' => 'Video',
		'2020-04-30' => 'Dev Vlog',
		'2020-05-15' => 'Q&A',
		'2020-05-22' => 'Video',
		'2020-05-29' => 'Video',
		'2020-06-12' => 'Video',
		'2020-07-03' => 'Video',
		'2020-07-23' => 'Video',
		'2020-07-30' => 'Mod Highlight',
		'2020-09-09' => 'Video',
		'2020-10-01' => 'Q&A',
		'2020-10-27' => 'Patch Notes',
		'2020-11-05' => 'Dev Vlog',
		'2020-11-12' => 'Video',
		'2020-11-16' => 'Embracer Group Video',
		'2020-11-27' => 'Video',
		'2020-12-04' => 'Video',
		'2020-12-11' => 'Teasers',
		'2020-12-17' => 'Q&A',
		'2021-01-15' => 'Video',
		'2021-02-05' => 'Video',
		'2021-02-19' => 'Video',
		'2021-02-26' => 'Videos',
		'2021-03-12' => 'Video',
		'2021-03-26' => 'Video',
		'2021-01-22' => 'Instagram AMA',
		'PLbjDnnBIxiEqJudZvNZcnhrq0tQG_JSBY' => 'Satisfactory Update 4 Patch Notes vid commentary',
		'2021-04-23' => 'Video',
		'2021-10-26' => 'Update 5 Launch Stream and Patch Notes Video',
	];

	/**
	 * @var array<string, list<int|string>>
	 *
	 * @readonly
	 */
	public array $injected = [];

	/**
	 * @var array<string, string>
	 *
	 * @readonly
	 */
	public array $not_a_livestream = [];

	/**
	 * @var array<string, string>
	 *
	 * @readonly
	 */
	public array $not_a_livestream_date_lookup = [];

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
		$this->not_a_livestream = array_merge(
			self::NOT_A_LIVESTREAM,
			array_reduce(
				array_filter(
					glob(__DIR__ . '/../data/*/yt-*.json'),
					static function (string $maybe) : bool {
						$maybe_date = basename(dirname($maybe));

						return
							is_file($maybe)
							&& preg_match('/^\d{4,}\-\d{2}\-\d{2}$/', $maybe_date)
							&& ! isset(self::NOT_A_LIVESTREAM[$maybe_date]);
					}
				),
				/**
				 * @psalm-type T = array<string, string>
				 *
				 * @param T $result
				 *
				 * @return T
				 */
				static function (array $result, string $file) : array {
					$date = basename(dirname($file));

					$result[$date] = 'Video';

					/** @var object{title:string} */
					$data = json_decode(file_get_contents($file));

					$title = $data->title;

					if (
						preg_match(
							'/^(Dev [BV]log)\:/i',
							$title,
							$matches
						)
					) {
						$result[$date] = $matches[1];
					} elseif (preg_match('/\bstream\b/i', $title)) {
						$result[$date] = 'Livestream';
					}

					return $result;
				},
				[]
			)
		);

		/** @var array<string, string> */
		$not_a_livestream_date_lookup = [
			'2021-03-17' => 'PLbjDnnBIxiEqJudZvNZcnhrq0tQG_JSBY',
			'2021-01-15' => 'PLbjDnnBIxiEpWeDmJ93Uxdxsp1ScQdfEZ',
			'2020-12-11' => 'PLbjDnnBIxiEpmVEhuMrGff6ES5V34y2wW',
			'2020-09-04' => 'PLbjDnnBIxiEoEYUiAzSSmePa-9JIADFrO',
		];

		foreach (array_keys($this->not_a_livestream) as $maybe_date) {
			if (preg_match('/^\d{4,}\-\d{2}\-\d{2}$/', $maybe_date)) {
				$not_a_livestream_date_lookup[$maybe_date] = $maybe_date;
			}
		}

		$this->not_a_livestream_date_lookup = $not_a_livestream_date_lookup;
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
