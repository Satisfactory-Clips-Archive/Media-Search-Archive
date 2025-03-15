<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes\CaptionsSource;

use DateTimeImmutable;
use InvalidArgumentException;
use PharData;
use SignpostMarv\VideoClipNotes\Injected;
use function SignpostMarv\VideoClipNotes\determine_date_for_video;

final class DynamicDatedTarballCaptionsSource extends AbstractCaptionsSource
{
	const HARDCODED_DATES = [
		'4_cYnq746zk.xml' => '2020-09-04',
	];

	/**
	 * @var array<string, AbstractTarballCaptionsSource>
	 */
	private array $tarballs = [];

	public function __construct(
		private readonly Injected $injected,
		private readonly FilesystemCaptionsSource $fallback
	) {}

	private function captions_data(DateTimeImmutable $date) : AbstractTarballCaptionsSource
	{
		$date_string = $date->format('Y-m-d');

		if ( ! isset($this->tarballs[$date_string])) {
			$this->tarballs[$date_string] = new class ($date_string) extends AbstractTarballCaptionsSource
			{
				public function __construct(private readonly string $date)
				{
				}

				protected function captions_data(): PharData
				{
					if ( ! isset($this->data)) {
						$this->data = new PharData(
							__DIR__ . '/../../captions-dated-cache/captions.' . $this->date . '.tar',
							(
								PharData::CURRENT_AS_PATHNAME
								| PharData::SKIP_DOTS
								| PharData::UNIX_PATHS
							)
						);
					}

					return $this->data;
				}
			};
		}

		return $this->tarballs[$date_string];
	}

	private function filename_to_datetimeimmutable(string $filename) : DateTimeImmutable
	{
		if (isset(self::HARDCODED_DATES[$filename])) {
			return new DateTimeImmutable(self::HARDCODED_DATES[$filename]);
		}

		if ( ! preg_match('/^(?:yt-)?(.{11})&?(?:(?:(?:,(?:\d+(?:\.\d+)?)?){1,2})|,?hl-.+)?\.(?:xml|html|json)$/', $filename, $matches)) {
			if (preg_match('/^((?:tc|is)-.+)\.html$/', $filename, $matches)) {
				return new DateTimeImmutable(
					determine_date_for_video($matches[1], $this->injected->cache['playlists'], $this->injected->playlists_date_ref)
				);
			}

			throw new InvalidArgumentException(sprintf('Unsupported filename supplied: %s', $filename));
		}

		return new DateTimeImmutable(
			determine_date_for_video($matches[1], $this->injected->cache['playlists'], $this->injected->playlists_date_ref)
		);
	}

	public function remove_cached_file(string $filename): void
	{
		$date = $this->filename_to_datetimeimmutable($filename);

		$this->captions_data($date)->remove_cached_file($filename);
	}

	public function add_from_string(string $filename, string $contents): void
	{
		$date = $this->filename_to_datetimeimmutable($filename);

		$captions_data = $this->captions_data($date);

		$captions_data->add_from_string($filename, $contents);
	}

	public function get_content(string $filename): string
	{
		$date = $this->filename_to_datetimeimmutable($filename);

		return $this->captions_data($date)->get_content($filename);
	}

	public function exists(string $filename): bool
	{
		$date = $this->filename_to_datetimeimmutable($filename);

		$result = $this->captions_data($date)->exists($filename);

		if (!$result && $this->fallback->exists($filename)) {
			$this->add_from_string($filename, $this->fallback->get_content($filename));
			$result = $this->captions_data($date)->exists($filename);
		}

		if ($result && $this->fallback->exists($filename)) {
			$this->fallback->remove_cached_file($filename);
		}

		return $result;
	}
}
