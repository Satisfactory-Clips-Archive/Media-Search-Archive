<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes\CaptionsSource;

use PharData;

final class MonolithicTarballCaptionsSource extends AbstractTarballCaptionsSource
{
	const TAR_FILEPATH = __DIR__ . '/../../captions.tar';

	protected function captions_data() : PharData
	{
		if ( ! isset($this->data)) {
			$this->data = new PharData(
				self::TAR_FILEPATH,
				(
					PharData::CURRENT_AS_PATHNAME
					| PharData::SKIP_DOTS
					| PharData::UNIX_PATHS
				)
			);
		}

		return $this->data;
	}
}
