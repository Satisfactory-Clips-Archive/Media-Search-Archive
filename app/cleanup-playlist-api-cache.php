<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

require_once(__DIR__ . '/../vendor/autoload.php');

(new YouTubeApiWrapper())->clear_cache();
