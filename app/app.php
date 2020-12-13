<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\TwitchClipNotes;

use function array_diff;
use function array_filter;
use function array_keys;
use function basename;
use Benlipp\SrtParser\Parser;
use function count;
use Cocur\Slugify\Slugify;
use function date;
use const FILE_APPEND;
use function file_get_contents;
use function file_put_contents;
use Google_Client;
use Google_Service_YouTube;
use GuzzleHttp\Exception\ClientException;
use function http_build_query;
use function implode;
use function in_array;
use function is_file;
use function ksort;
use function mb_substr;
use function preg_match_all;
use function rawurlencode;
use function sha1_file;
use function sprintf;
use function strnatcasecmp;
use function strtotime;

$transcriptions = in_array('--transcriptions', $argv, true);
$clear_nopes = in_array('--clear-nopes', $argv, true);
$unset_other_playlists = in_array('--unset-other-playlists', $argv, true);
$skip_fetch = in_array('--skip-fetch', $argv, true);

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/captions.php');

$client = new Google_Client();
$client->setApplicationName('Twitch Clip Notes');
$client->setScopes([
	'https://www.googleapis.com/auth/youtube.readonly',
	'https://www.googleapis.com/auth/youtube.force-ssl',
]);

$client->setAuthConfig(__DIR__ . '/google-auth.json');
$client->setAccessType('offline');

$http = $client->authorize();

$service = new Google_Service_YouTube($client);

$other_playlists_on_channel = [];

$playlist_metadata = [
	__DIR__ . '/playlists/coffeestainstudiosdevs/satisfactory.json' => __DIR__ . '/../coffeestainstudiosdevs/satisfactory/',
];

/** @var array<string, string> */
$playlists = [
];

foreach ($playlist_metadata as $metadata_path => $prepend_path) {
	$data = json_decode(file_get_contents($metadata_path), true);

	foreach ($data as $playlist_id => $markdown_path) {
		$playlists[$playlist_id] = $prepend_path . $markdown_path;
	}
}

$preloaded_faq = [
	'Satisfactory Update 4' => [
		'2020-07-28' => [
			'Q&A: update 4 will rethink power situation? https://clips.twitch.tv/ProudRockyInternTooSpicy',
		],
		'2020-08-11' => [
			'Q&A: Next Update? https://clips.twitch.tv/CrunchyMistyAsparagus4Head',
		],
		'2020-08-18' => [
			'Q&A: When is Update 4 pencilled for? https://clips.twitch.tv/RelievedTawdryEelDogFace',
			'Snutt Talk: There\'s also discussions about how we release Update 4 https://clips.twitch.tv/FaintToughRingYee',
			'Q&A: What are some of the priorities for the next update? https://clips.twitch.tv/SneakyLovelyCrabsAMPEnergyCherry',
			'Q&A: How often will there be updates to the game? https://clips.twitch.tv/CheerfulZanyWebVoteYea',
		],
		'2020-08-25' => [
			'[ETA for Update 4](2020-08-25.md#eta-for-update-4)',
			'[additional clips](2020-08-25.md#update-4-single-clip-videos)',
		],
	],
	'Tiers' => [
		'2020-07-28' => [
			'Jace Talk: Content & Tiers https://clips.twitch.tv/SwissFurryPlumPlanking',
		],
		'2020-08-18' => [
			'Q&A: Might we see additions to Tier 7 before the end of the year? https://clips.twitch.tv/DoubtfulNaiveCroquettePeoplesChamp',
			'Q&A: Tier 8 before 1.0? https://clips.twitch.tv/AgreeableTentativeBeeCurseLit',
			'Q&A: What\'s in Tier 8? (part 1) https://clips.twitch.tv/RelievedRelievedCroissantMingLee',
			'Q&A: What\'s in Tier 8? (part 2) https://clips.twitch.tv/AwkwardBloodyNightingaleShadyLulu',
		],
		'2020-09-08' => [
			'Q&A: What additions to Tier 7 might be coming & when ? https://www.youtube.com/watch?v=lGbJwWh5W_I',
		],
	],
	'Space Exploration' => [
		'2020-07-28' => [
			'Q&A: Signs & Planets https://clips.twitch.tv/ArtisticTrustworthyHamOSkomodo',
		],
	],
	'Aerial Travel' => [
		'2020-07-28' => [
			'Jace Talk: Flight & map size perception https://clips.twitch.tv/ElatedBlueNightingaleMau5',
		],
		'2020-08-11' => [
			'Q&A: Will Drones be added to the game for aerial travel? https://clips.twitch.tv/CredulousWimpyMosquitoResidentSleeper',
		],
		'2020-08-25' => [
			'Q&A: Implement some kind of hire spaceship thingy for better exploration & faster travelling ? https://clips.twitch.tv/TrappedFaintBulgogiBigBrother',
			'Q&A: How about a drone to fly around? https://clips.twitch.tv/SteamyViscousGoshawkDancingBaby',
		],
	],
	'Console Release' => [
		'2020-07-28' => [
			'Q&A: Satisfactory Console Release https://clips.twitch.tv/FragileNimbleEggnogDatSheffy',
		],
		'2020-08-18' => [
			'Q&A: Are there any plans to port the game to console? https://clips.twitch.tv/CogentRichJackalHeyGirl',
		],
	],
	'Dedicated Servers' => [
		'2020-09-28' => [
			'Q&A: Dedicated Server cost https://clips.twitch.tv/ConfidentLittleSnood4Head',
		],
		'2020-08-11' => [
			'Q&A: Are Dedicated Servers coming? https://clips.twitch.tv/BigDeadPhoneKappaWealth',
			'Q&A: What\'s the hold-up on Dedicated Servers? https://clips.twitch.tv/ShinyAthleticCrocodileKappaPride',
			'Jace Talk: Massive Bases, Multiplayer lag, and Dedicated Servers https://clips.twitch.tv/RealPrettiestKoalaBloodTrail',
			'Q&A: Dedicated Servers, start building a community around that? https://clips.twitch.tv/EagerPeacefulMonkeyDoubleRainbow',
		],
		'2020-08-25' => [
			'Q&A: Dedicated Servers update? https://clips.twitch.tv/AgitatedAltruisticAnacondaStinkyCheese',
			'Q&A: Will Dedicated Servers be available on Linux, or Windows? https://clips.twitch.tv/SeductiveInnocentFerretHeyGirl',
			'Q&A: Linux would be useful for Servers https://clips.twitch.tv/UglyAwkwardCiderSSSsss',
			'Q&A: Will the Server source code be available for Custom Mods, or with pre-compiled binaries? https://clips.twitch.tv/ShinyFunnyJellyfishSMOrc',
		],
	],
	'World Map' => [
		'2020-07-28' => [
			'Jace Talk: Flight & map size perception https://clips.twitch.tv/ElatedBlueNightingaleMau5',
		],
		'2020-08-11' => [
			'Q&A: Randomly Generated Maps: https://clips.twitch.tv/OilyBloodyMangoFutureMan',
			'Q&A: Do you plan to release a World Editor? https://clips.twitch.tv/AnnoyingImpartialGaurChefFrank',
		],
		'2020-08-18' => [
			'Q&A: Will there be any underwater resources? https://clips.twitch.tv/RelievedCleanBibimbapDancingBanana',
			'Q&A: Terraforming? https://clips.twitch.tv/AmericanSpineyWitchTinyFace',
			'Q&A: Any ice/snow biome plans? https://clips.twitch.tv/AlluringScrumptiousBaboonHeyGirl',
			'Q&A: Any different maps planned? https://clips.twitch.tv/PlausibleEnthusiasticGrassRedCoat',
			'Q&A: Will you be able to create your own map? https://clips.twitch.tv/ChillyRockyWalrusUnSane',
		],
		'2020-08-25' => [
			'[various clips](2020-08-25.md#world-map)',
		],
	],
	'Mass Building' => [
		'2020-07-08' => [
			'Snutt & Jace Talk: not adding mass building tools into the vanilla game https://clips.twitch.tv/NimbleAgitatedPeanutNotLikeThis',
		],
		'2020-07-21' => [
			'Q&A: Why no mass building? https://clips.twitch.tv/SoftBovineArmadilloNerfRedBlaster',
		],
		'2020-08-11' => [
			'Q&A: Any plans to make vertical building easier? https://clips.twitch.tv/ImpartialHardSageBigBrother',
		],
		'2020-08-18' => [
			'Q&A: Any plans for 1-click multi-building? https://clips.twitch.tv/CheerfulLightAsteriskGOWSkull',
		],
	],
	'Merch' => [
		'*Please note that Merch has since been launched https://www.youtube.com/watch?v=4_cYnq746zk*',
		'2020-07-28' => [
			'Q&A: Coffee Mug? https://clips.twitch.tv/SpunkyHyperWasabi4Head',
		],
		'2020-08-11' => [
			'[in-depth discussion over various clips](2020-08-11.md#merch)',
		],
		'2020-08-18' => [
			'Q&A: Is there a Merch Store? https://clips.twitch.tv/CleanCarefulMoonAMPEnergyCherry',
			'Q&A: When will have Merch? https://clips.twitch.tv/FunOriginalPistachioNerfRedBlaster',
		],
	],
];

/** @var array<string, array<string, string>> */
$videos = [];

/** @var array<string, list<string>> */
$video_tags = [];

$exclude_from_absent_tag_check = [
	'4_cYnq746zk', // official merch announcement video
];

/** @var array<string, list<string>> */
$already_in_markdown = [];

/** @var list<string> */
$autocategorise = [];

/** @var list<string> */
$already_in_faq = [];

$cache = json_decode(
	file_get_contents(__DIR__ . '/cache.json') ?: '[]',
	true
);

$update_cache = function () use (&$cache) : void {
	file_put_contents(
		__DIR__ . '/cache.json',
		json_encode($cache, JSON_PRETTY_PRINT)
	);
};

if ($unset_other_playlists && isset($cache['playlists'])) {
	foreach (array_keys($cache['playlists']) as $playlist_id) {
		if ( ! isset($playlists[$playlist_id])) {
			unset($cache['playlists'][$playlist_id]);
		}
	}

	$update_cache();
}

foreach (($cache['videoTags'] ?? []) as $video_id => $data) {
	[$etag, $tags] = $data;

	$video_tags[$video_id] = $tags;
}

foreach (($cache['playlists'] ?? []) as $playlist_id => $data) {
	if (isset($playlists[$playlist_id])) {
		continue;
	}

	[$etag, $title, $video_ids] = $data;

	$other_playlists_on_channel[$playlist_id] = [$title, $video_ids];
}


$object_cache_captions = [];
$object_cache_videos = [];

$fetch_videos = static function (
	array $args,
	string $playlist_id,
	array &$videos,
	array &$video_tags
) use (
	$http,
	$playlists,
	$service,
	&$cache,
	$update_cache,
	&$object_cache_captions,
	$transcriptions,
	$skip_fetch,
	&$fetch_videos
) : void {
	if ($skip_fetch) {
		return;
	}

	$args['playlistId'] = $playlist_id;
	$cache['playlists'] = $cache['playlists'] ?? [];
	$cache['playlistItems'] = $cache['playlistItems'] ?? [];
	$cache['captions'] = $cache['captions'] ?? [];
	$cache['videoTags'] = $cache['videoTags'] ?? [];

	$response = $service->playlistItems->listPlaylistItems(
		implode(',', [
			'id',
			'snippet',
			'contentDetails',
		]),
		$args
	);

	foreach ($response->items as $video) {
		$video_id = $video->snippet->resourceId->videoId;

		if (
			! isset($cache['playlistItems'][$video_id])
			|| $cache['playlistItems'][$video_id][0] !== $video->etag
		) {
			$tag_response = $service->videos->listVideos(
				'snippet',
				[
					'id' => $video_id,
				]
			);

			if (
				! isset($cache['videoTags'][$video_id])
				|| $cache['videoTags'][$video_id][0] !== $tag_response->etag
			) {
				if (isset($tag_response->items[0]->snippet->tags)) {
					$cache['videoTags'][$video_id] = [
						$tag_response->etag,
						$tag_response->items[0]->snippet->tags,
					];
				} else {
					$cache['videoTags'][$video_id] = [
						$tag_response->etag,
						[],
					];
				}

				$update_cache();
			}

			$cache['playlistItems'][$video_id] = [
				$video->etag,
				$video->snippet->title,
			];

			$update_cache();
		}

		$videos[$playlist_id][$video_id] = $cache['playlistItems'][$video_id][1];
	}

	if (isset($response->nextPageToken)) {
		$args['pageToken'] = $response->nextPageToken;

		$fetch_videos($args, $playlist_id, $videos, $video_tags);
	}
};

$cache['playlists'] = $cache['playlists'] ?? [];

foreach ($playlists as $playlist_id => $markdown_path) {
	if ($skip_fetch) {
		continue;
	}

	$videos[$playlist_id] = [];

	$response = $service->playlists->listPlaylists(
		'id,snippet',
		[
			'maxResults' => 1,
			'id' => $playlist_id,
		]
	);

	if (
		! isset($cache['playlists'][$playlist_id])
		|| $cache['playlists'][$playlist_id][0] !== $response->etag
	) {
		$fetch_videos(
			[
				'maxResults' => 50,
			],
			$playlist_id,
			$videos,
			$video_tags
		);
		$cache['playlists'][$playlist_id] = [
			$response->etag,
			$response->items[0]->snippet->title,
			array_keys($videos[$playlist_id]),
		];

		$update_cache();
	} else {
		foreach ($cache['playlists'][$playlist_id][2] as $video_id) {
			$videos[$playlist_id][$video_id] = $cache['playlistItems'][$video_id][1];
		}
	}

	if ( ! is_file($markdown_path)) {
		file_put_contents($markdown_path, "\n");

		$autocategorise[] = $playlist_id;
	}

	$contents = file_get_contents($markdown_path);

	preg_match_all(
		'/https:\/\/www\.youtube\.com\/watch\?v=([^\n\s\*]+)/',
		$contents,
		$matches
	);

	$already_in_markdown[$playlist_id] = $matches[1];
}

if (
	! is_file(
		__DIR__ .
		'/../coffeestainstudiosdevs/satisfactory/FAQ.md'
	)
) {
	file_put_contents(
		(
			__DIR__ .
			'/../coffeestainstudiosdevs/satisfactory/FAQ.md'
		),
		''
	);
}

preg_match_all(
	'/https:\/\/www\.youtube\.com\/watch\?v=([^\n\s\*]+)/',
	file_get_contents(
		__DIR__ .
		'/../coffeestainstudiosdevs/satisfactory/FAQ.md'
	),
	$matches
);

$already_in_faq = $matches[1];

$fetch_all_playlists = static function (array $args) use (
	&$other_playlists_on_channel,
	&$video_tags,
	$service,
	$fetch_videos,
	&$cache,
	$update_cache,
	&$videos,
	&$fetch_all_playlists,
	$skip_fetch,
	$playlists
) : void {
	if ($skip_fetch) {
		return;
	}

	$response = $service->playlists->listPlaylists(
		'id,snippet',
		$args
	);

	foreach ($response->items as $playlist) {
		if ( ! isset($playlists[$playlist->id])) {
			$other_playlists_on_channel[$playlist->id] = [
				$playlist->snippet->title,
				[],
			];

			$cache_response = $service->playlists->listPlaylists(
				'id,snippet',
				[
					'maxResults' => 1,
					'id' => $playlist->id,
				]
			);

			if (
				! isset($cache['playlists'][$playlist->id])
				|| $cache['playlists'][$playlist->id][0] !== $cache_response->etag
			) {
				$fetch_videos(
					['maxResults' => 50],
					$playlist->id,
					$other_playlists_on_channel[$playlist->id][1],
					$video_tags
				);

				$cache['playlists'][$playlist->id] = [
					$cache_response->etag,
					$playlist->snippet->title,
					array_keys($other_playlists_on_channel[$playlist->id][1][$playlist->id]),
				];

				$update_cache();

				$other_playlists_on_channel[$playlist->id][1] = array_keys(
					$other_playlists_on_channel[$playlist->id][1][$playlist->id]
				);
			} else {
				foreach ($cache['playlists'][$playlist->id][2] as $video_id) {
					$videos[$playlist->id][$video_id] = $cache['playlistItems'][$video_id][1];
					$other_playlists_on_channel[$playlist->id][1][] = $video_id;
				}
			}
		}
	}

	if (isset($response->nextPageToken)) {
		$args['pageToken'] = $response->nextPageToken;

		$fetch_all_playlists($args);
	}
};

$fetch_all_playlists([
	'channelId' => 'UCJamaIaFLyef0HjZ2LBEz1A',
	'maxResults' => 50,
]);

uksort($videos, static function (string $a, string $b) use ($cache) : int {
	return strnatcasecmp(
		$cache['playlists'][$a][1],
		$cache['playlists'][$b][1]
	);
});

$videos = array_map(
	static function (array $in) : array {
		uasort($in, static function(string $a, string $b) : int {
			return strnatcasecmp($a, $b);
		});

		return $in;
	},
	$videos
);

$videos_to_add = [];

foreach ($already_in_markdown as $playlist_id => $videos_in_markdown) {
	$videos_to_add[$playlist_id] = array_diff(array_keys($videos[$playlist_id]), $videos_in_markdown);
}

$videos_to_add = array_filter($videos_to_add, 'count');

foreach ($videos_to_add as $playlist_id => $video_ids) {
	$content_arrays = [
		'Related answer clips' => [],
		'Single video clips' => [],
	];

	file_put_contents(
		$playlists[$playlist_id],
		date(
			'F jS, Y',
			(int) strtotime(
				mb_substr(
					basename($playlists[$playlist_id]),
					0,
					-3
				)
			)
		) .
		' Livestream clips (non-exhaustive)' .
		"\n"
	);

	foreach ($video_ids as $video_id) {
		$found = false;

		foreach ($other_playlists_on_channel as $playlist_data) {
			[$title, $other_playlist_video_ids] = $playlist_data;

			if (in_array($video_id, $other_playlist_video_ids, true)) {
				$found = true;

				if ( ! isset($content_arrays['Related answer clips'][$title])) {
					$content_arrays['Related answer clips'][$title] = [];
				}
				$content_arrays['Related answer clips'][$title][] = $video_id;
			}
		}

		if ( ! $found) {
			$content_arrays['Single video clips'][] = $video_id;
		}
	}

	ksort($content_arrays['Related answer clips']);

	file_put_contents(
		$playlists[$playlist_id],
		"\n" . '# Related answer clips' . "\n",
		FILE_APPEND
	);

	foreach ($content_arrays['Related answer clips'] as $title => $video_ids) {
		file_put_contents(
			$playlists[$playlist_id],
			"\n" . '## ' . $title . "\n",
			FILE_APPEND
		);

		foreach ($video_ids as $video_id) {
			file_put_contents(
				$playlists[$playlist_id],
				(
					'* ' .
					$videos[$playlist_id][$video_id] .
					' https://www.youtube.com/watch?' .
					http_build_query([
						'v' => $video_id,
					]) .
					"\n"
				),
				FILE_APPEND
			);
		}
	}

	file_put_contents(
		$playlists[$playlist_id],
		"\n" . '# Single video clips' . "\n",
		FILE_APPEND
	);

	foreach ($content_arrays['Single video clips'] as $video_id) {
		file_put_contents(
			$playlists[$playlist_id],
			(
				'* ' .
				$videos[$playlist_id][$video_id] .
				' https://www.youtube.com/watch?' .
				http_build_query([
					'v' => $video_id,
				]) .
				"\n"
			),
			FILE_APPEND
		);
	}
}

/** @var array<string, list<array{0:string, 1:string}>> */
$absent_from_faq = [];

foreach ($already_in_faq as $id) {
	if (in_array($id, $exclude_from_absent_tag_check, true)) {
		continue;
	}

	if (
		! isset($video_tags[$id]) ||
		! in_array('faq', $video_tags[$id], true)
	) {
		echo
			'Missing FAQ tag: ',
			' https://www.youtube.com/watch?',
			http_build_query([
				'v' => $id,
			]),
			"\n";
	}
}

foreach ($video_tags as $id => $tags) {
	if (
		in_array('faq', $tags, true)
		&& ! in_array($id, $already_in_faq, true)
	) {
		foreach ($videos as $playlist_id => $video_ids) {
			if (isset($video_ids[$id]) && isset($playlists[$playlist_id])) {
				$date = mb_substr(basename($playlists[$playlist_id]), 0, -3);

				if ( ! isset($absent_from_faq[$date])) {
					$absent_from_faq[$date] = [];
				}

				$absent_from_faq[$date][] = [$playlist_id, $id];
			}
		}
	}
}

if ($transcriptions) {
	$checked = 0;

	foreach(array_keys($playlists) as $playlist_id) {
		if ( ! isset($videos[$playlist_id])) {
			echo 'skipping: ', $playlist_id, "\n";
			continue;
		}

		foreach(array_keys($videos[$playlist_id]) as $video_id) {
				$transcriptions_file = (
					__DIR__ .
					'/../coffeestainstudiosdevs/satisfactory/transcriptions/yt-' .
					$video_id .
					'.md'
				);

			if ( ! is_file($transcriptions_file)) {
				$caption_lines = captions($video_id);

				if (count($caption_lines) < 1) {
					echo 'skipping captions for ', $video_id, "\n";

					continue;
				}

				$date = mb_substr(basename($playlists[$playlist_id]), 0, -3);

				file_put_contents(
					$transcriptions_file,
					(
						'# [' . date('F jS, Y', (int) strtotime($date)) .
						' Livestream](../' . $date . '.md)' .
						"\n" .
						'## ' . $videos[$playlist_id][$video_id] .
						"\n" .
						(
							'https://www.youtube.com/watch?' .
							http_build_query([
								'v' => $video_id,
							])
						) .
						"\n"
					)
				);

				foreach ($caption_lines as $caption_line) {
					file_put_contents(
						$transcriptions_file,
						(
							'> ' . $caption_line .
							"\n" .
							'> ' .
							"\n"
						),
						FILE_APPEND
					);
				}
			}
		}
	}

	echo
		sprintf(
			'%s subtitles checked of %s videos cached',
			$checked,
			count($cache['playlistItems'])
		),
		"\n";
}

/** @var list<string> */
$faq_dates = [];
$faq_patch = [];

foreach ($preloaded_faq as $topic => $values) {
	$faq_patch[$topic] = [];

	foreach (array_keys($values) as $key) {
		if (is_string($key)) {
			$faq_dates[] = $key;
		}
	}
}

$faq_playlist_data = [];
$faq_playlist_data_dates = [];

foreach ($playlist_metadata as $metadata_path => $prepend_path) {
	/** @var array<string, string> */
	$faq_playlist_data = json_decode(file_get_contents($metadata_path), true);

	foreach ($faq_playlist_data as $playlist_id => $filename) {
		$faq_playlist_date = mb_substr($filename, 0, -3);
		$faq_dates[] = $faq_playlist_date;
		$faq_playlist_data_dates[$playlist_id] = $faq_playlist_date;
	}
}

$faq_dates = array_unique($faq_dates);

natsort($faq_dates);

$faq_filepath = __DIR__ . '/../coffeestainstudiosdevs/satisfactory/FAQ.md';

usleep(100);

file_put_contents($faq_filepath, '');

foreach ($cache['playlists'] as $cached_playlist_id => $cached_playlist_data) {
	if (isset($faq_playlist_data_dates[$cached_playlist_id])) {
		continue;
	}

	foreach ($cached_playlist_data[2] as $video_id) {
		if (
			isset($video_tags[$video_id])
			&& in_array('faq', $video_tags[$video_id])
		) {
			/** @var string|null */
			$faq_video_date = null;

			foreach (array_keys($faq_playlist_data) as $playlist_id) {
				if (
					isset(
						$cache['playlists'][$playlist_id],
						$faq_playlist_data_dates[$playlist_id]
					)
					&& in_array(
						$video_id,
						$cache['playlists'][$playlist_id][2],
						true
					)
				) {
					$faq_video_date = $faq_playlist_data_dates[$playlist_id];

					break;
				}
			}

			if (is_string($faq_video_date)) {
				if ( ! isset($faq_patch[$cached_playlist_data[1]])) {
					$faq_patch[$cached_playlist_data[1]] = [];
				}

				if (
					! isset(
						$faq_patch[$cached_playlist_data[1]][$faq_video_date]
					)
				) {
					$faq_patch[$cached_playlist_data[1]][$faq_video_date] = [];
				}

				$faq_patch[$cached_playlist_data[1]][$faq_video_date][] = (
					$cache['playlistItems'][$video_id][1] .
					' https://www.youtube.com/watch?' .
					http_build_query([
						'v' => $video_id,
					])
				);
			}
		}
	}
}

$faq_topics = array_unique(
	array_merge(
		array_keys($preloaded_faq),
		array_keys($faq_patch)
	)
);

natsort($faq_topics);

foreach ($faq_topics as $faq_topic) {
	file_put_contents(
		$faq_filepath,
		sprintf('# %s' . "\n\n", $faq_topic),
		FILE_APPEND
	);

	if (isset($preloaded_faq[$faq_topic])) {
		foreach ($preloaded_faq[$faq_topic] as $k => $v) {
			if (is_string($v)) {
				file_put_contents(
					$faq_filepath,
					$v . "\n",
					FILE_APPEND
				);
			}
		}
	}

	if (isset($faq_patch[$faq_topic])) {
		foreach ($faq_dates as $faq_date) {
			$lines = [];

			if (isset($preloaded_faq[$faq_topic][$faq_date])) {
				$lines = $preloaded_faq[$faq_topic][$faq_date];
			}

			if (
				isset(
					$faq_patch[$faq_topic],
					$faq_patch[$faq_topic][$faq_date]
				)
			) {
				$patch_lines = $faq_patch[$faq_topic][$faq_date];
				natsort($patch_lines);

				$lines = array_merge($lines, $patch_lines);
			}

			if (count($lines) > 0) {
				file_put_contents(
					$faq_filepath,
					sprintf(
						'## %s' . "\n",
						date('F jS, Y', (int) strtotime($faq_date))
					),
					FILE_APPEND
				);

				foreach ($lines as $line) {
					file_put_contents(
						$faq_filepath,
						sprintf('* %s' . "\n", $line),
						FILE_APPEND
					);
				}

				file_put_contents(
					$faq_filepath,
					"\n",
					FILE_APPEND
				);
			}
		}

		foreach ($faq_patch[$faq_topic] as $k => $v) {
			if (is_array($v)) {
				natsort($v);

			}
		}
	}

	file_put_contents(
		$faq_filepath,
		"\n",
		FILE_APPEND
	);
}

$index_prefill = [
	'satisfactory' => [
		'',
		'## July 2020',
		'* [July 8th, 2020](satisfactory/2020-07-08.md)',
		'* [July 21st, 2020](satisfactory/2020-07-21.md)',
		'* [July 28th, 2020](satisfactory/2020-07-28.md)',
		'',
		'## August 2020',
		'* [August 11th, 2020](satisfactory/2020-08-11.md)',
		'* [August 18th, 2020](satisfactory/2020-08-18.md)',
		'* [August 25th, 2020](satisfactory/2020-08-25.md)',
	],
];

$global_topic_append = [
	'satisfactory' => [
		'features/unplanned-features/mass-building.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Copy & Paste settings from Machine to Machine? https://clips.twitch.tv/SlickEsteemedTriangleVoteNay',
			'* Q&A: Drag-to-build Mode? https://clips.twitch.tv/UglyRacyCaribouYouWHY',
			'',
			'## Q&A: Blueprints would be awesome for end-game',
			'* Part 1: https://clips.twitch.tv/LuckyNastyLionDogFace',
			'* Part 2: https://clips.twitch.tv/FreezingCuriousHeronDatBoi',
			'* Part 3: https://clips.twitch.tv/CrunchyGlamorousQuailSwiftRage',
			'* Part 4: https://clips.twitch.tv/RacyHilariousMangoStinkyCheese',
			'',
			'# August 18th, 2020 Livestream',
			'* Q&A: Any plans for 1-click multi-building? https://clips.twitch.tv/CheerfulLightAsteriskGOWSkull',
			'',
			'# August 11th, 2020 Livestream',
			'* Q&A: Any plans to make vertical building easier? https://clips.twitch.tv/ImpartialHardSageBigBrother',
			'',
			'# July 21st, 2020 Livestream',
			'* Q&A: Why no mass building? https://clips.twitch.tv/SoftBovineArmadilloNerfRedBlaster',
			'',
			'# July 8th, 2020 Livestream',
			'* Snutt & Jace Talk: not adding mass building tools into the vanilla game https://clips.twitch.tv/NimbleAgitatedPeanutNotLikeThis',
		],
		'environment/creatures.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: New enemies / creatures? https://clips.twitch.tv/WonderfulNurturingYamYouWHY',
			'* Q&A: Will we have more monsters? https://clips.twitch.tv/GrotesqueDelightfulLyrebirdPrimeMe',
			'* Q&A: Please make the Walking Bean stop clipping https://clips.twitch.tv/WanderingGloriousWallabyPunchTrees',
			'',
			'# August 18th, 2020 Livestream',
			'* Q&A: Are we ever going to add taming mounts? https://clips.twitch.tv/BoldAgileSquidDoggo',
			'* Q&A: Will you be able to pet the doggo? https://clips.twitch.tv/DullHyperSpindlePanicVis',
			'',
			'# August 11th, 2020 Livestream',
			'* Q&A: Do you have Goats in Satisfactory? https://clips.twitch.tv/FurryTalentedCrowBleedPurple',
			'',
			'# July 28th, 2020 Livestream',
			'* Q&A: More Wildlife? https://clips.twitch.tv/DirtyHilariousPancakeWow',
			'',
			'# July 21st, 2020 Livestream',
			'* Q&A: Puppies, Train Fix https://clips.twitch.tv/ColdBraveShieldSMOrc',
		],
		'transportation/trains.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Train Signals https://clips.twitch.tv/OriginalAntsySmoothieStoneLightning',
			'* Q&A: Add Train tunnels to go through mountains? https://clips.twitch.tv/GleamingHyperBottleRickroll',
			'* Q&A: Will the Train always clip? https://clips.twitch.tv/ImpartialEnchantingCider4Head',
			'* Q&A: When I play multiplayer and the train and host doesn\'t update correctly, is this a known bug? https://clips.twitch.tv/LightAcceptableCheesePermaSmug',
			'',
			'# August 18th, 2020 Livestream',
			'* Q&A: When will we be able to paint our trains? https://clips.twitch.tv/BelovedBloodyStapleGingerPower',
			'* Q&A: Any thoughts on whether Trains will ever collide? https://clips.twitch.tv/SaltyJazzyPasta4Head',
			'',
			'# July 21st, 2020 Livestream',
			'* Q&A: Puppies, Train Fix https://clips.twitch.tv/ColdBraveShieldSMOrc',
		],
		'satisfactory-updates/satisfactory-update-4.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Will Gas be in Update 4? https://clips.twitch.tv/SpinelessSneakySalsifyNerfRedBlaster',
			'* Q&A: Will there be new items coming to the AWESOME Shop between now and Update 4? https://clips.twitch.tv/PerfectNurturingTrollRiPepperonis',
			'* Snutt Talk: Minor stuff before Update 4 https://clips.twitch.tv/FrozenEndearingCodEleGiggle',
			'* Q&A: Update 4, just a quality-of-life thing? https://clips.twitch.tv/GleamingCheerfulWatercressRaccAttack',
			'* Q&A: Please tell me Update 4 will use S.A.M. Ore https://clips.twitch.tv/ArtisticGlutenFreeSpindleDxAbomb',
			'* Q&A: When will the next patch even get released? https://clips.twitch.tv/BlitheKitschySnoodTwitchRaid',
			'* Q&A: Some new Machines in the next update? https://clips.twitch.tv/CourteousSmellyNewtTTours',
			'',
			'## ETA for Update 4?',
			'* Part 1: https://clips.twitch.tv/DeadPrettySaladMoreCowbell',
			'* Part 2: https://clips.twitch.tv/SavageBenevolentEndiveChocolateRain',
			'* Part 3: https://clips.twitch.tv/GoodSaltyPepperoniPunchTrees',
			'* Part 4: https://clips.twitch.tv/UnsightlyApatheticHornetKreygasm',
			'* Part 5: https://clips.twitch.tv/AmazingEagerGorillaHeyGuys',
			'### Quotes',
			'> Yeah, MK2 Pipes is also a possibility that might happen before Update 4 - unless they tie in with something in Update 4 that is part of that update so to speak- but if they\'re not then we\'re probably going to release MK2 pipes before that or something, I don\'t know.',
			'---',
			'> Update 4 might happen at the end of 2020, Update 4 might happen at the beginning of 2021 depending on how big it is, and we also don\'t know if Update 4 is going to be "save everything until Update 4 and release it then" or "release things as we progress" and what that would be.',
			'---',
			'> The game\'s not dead, there\'s still cool stuff coming.',
			'',
			'### Mid-stream reiteration',
			'* Part 1: https://clips.twitch.tv/TangentialHyperFlyBigBrother',
			'* Part 2: https://clips.twitch.tv/PlumpEntertainingSandstormYee',
			'* Part 3: https://clips.twitch.tv/EntertainingTentativeGaurSmoocherZ',
			'',
			'### Additional',
			'* Q&A: Update before release of Cyberpunk 2077? https://clips.twitch.tv/AttractiveFrailRaisinKAPOW',
			'* Q&A: What game will come out first, Satisfactory or Star Citizen? https://clips.twitch.tv/AdventurousUninterestedBasenji4Head',
			'',
			'## Q&A: Can we expect significant performance increase with Update 4?',
			'* Part 1: https://clips.twitch.tv/CarelessDepressedShingleHassanChop',
			'* Part 2: https://clips.twitch.tv/LuckyMushyShingleTBTacoRight',
			'* Part 3: https://clips.twitch.tv/SincereProductiveScallionLeeroyJenkins',
			'',
			'# August 18th, 2020 Livestream',
			'* Q&A: When is Update 4 pencilled for? https://clips.twitch.tv/RelievedTawdryEelDogFace',
			'* Snutt Talk: There\'s also discussions about how we release Update 4 https://clips.twitch.tv/FaintToughRingYee',
			'* Q&A: What are some of the priorities for the next update? https://clips.twitch.tv/SneakyLovelyCrabsAMPEnergyCherry',
			'* Q&A: How often will there be updates to the game? https://clips.twitch.tv/CheerfulZanyWebVoteYea',
			'',
			'# August 11th, 2020 Livestream',
			'* Q&A: Next Update? https://clips.twitch.tv/CrunchyMistyAsparagus4Head',
			'',
			'# July 28th, 2020 Livestream',
			'* Q&A: update 4 will rethink power situation? https://clips.twitch.tv/ProudRockyInternTooSpicy',
		],
		'features/power-management.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: What about a more complex power system with transformers and stuff? https://clips.twitch.tv/FrozenVivaciousLaptopGivePLZ',
			'* Q&A: AI in an Electricity Management System that can handle power surges when we\'re away from base? https://clips.twitch.tv/FancyPiercingLardOneHand',
			'* Q&A: Potential to get better management for power grids? https://clips.twitch.tv/SoftTangentialGaurJonCarnage',
			'* Q&A: When will you ad UI for the Steam Geyser Power Plant? https://clips.twitch.tv/WanderingBashfulGoatTBCheesePull',
			'',
			'## Q&A: Any chance we can have a power switch so we can shut down power generators?',
			'* Part 1: https://clips.twitch.tv/SmokyBreakableAyeayeEagleEye',
			'* Part 2: https://clips.twitch.tv/SassyLightSkirretOSsloth',
			'* Part 3: https://clips.twitch.tv/KawaiiOddGrasshopperMrDestructoid',
			'* Part 4: https://clips.twitch.tv/ElegantNaivePorpoiseTF2John',
			'',
			'# July 28th, 2020 Livestream',
			'* Q&A: update 4 will rethink power situation? https://clips.twitch.tv/ProudRockyInternTooSpicy',
		],
		'features/fluids.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Is the sink going to accept liquids in the future? https://clips.twitch.tv/ArtisticCoweringTortoiseRitzMitz',
			'',
			'# August 11th, 2020 Livestream',
			'* Q&A: Gas Tanks? https://clips.twitch.tv/FitAlertTurtleDogFace',
			'',
			'# July 28th, 2020 Livestream',
			'* Q&A: Gas Manufacturing https://clips.twitch.tv/ThirstyJoyousSparrowSoBayed',
		],
		'technology/unreal-engine.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Will we ever have proper multi-core support? https://clips.twitch.tv/VenomousProtectiveDonutTheTarFu',
			'',
			'## Q&A: Will Satisfactory be updated to Unreal Engine 5 / Snutt Talk: Experimental Builds',
			'* Part 1: https://clips.twitch.tv/TentativeHardPlumberYee',
			'* Part 2: https://clips.twitch.tv/SquareLovelyFriesBudBlast',
			'* Part 3: https://clips.twitch.tv/TemperedEnchantingOrangeTBCheesePull',
			'* Part 4: https://clips.twitch.tv/FrigidFragileCucumberOneHand',
			'',
			'## Autosave',
			'* Q&A: Better Autosave system? https://clips.twitch.tv/CarefulBashfulHyenaWOOP',
			'* Snutt Talk: If you think Autosave is annoying https://clips.twitch.tv/InventiveStylishGerbilWow',
			'* Q&A: Is it possible to have the Autosave not noticeable at all ? https://clips.twitch.tv/ThirstyTubularHamMikeHogu',
			'',
			'# August 18th, 2020 Livestream',
			'* Q&A: Is Satisfactory affected by Epic vs. Apple? https://clips.twitch.tv/FurryAwkwardStrawberryWoofer',
			'* Q&A: Custom game engine? https://clips.twitch.tv/ViscousFuriousPonyPhilosoraptor',
			'* Q&A: Any news about autosave freezes? https://clips.twitch.tv/CrispyCheerfulCrocodilePanicBasket',
			'* Q&A: Are you going to upgrade to UE5? https://clips.twitch.tv/GloriousTangentialSalmonPastaThat',
			'',
			'## Build Limit',
			'* Part 1: https://clips.twitch.tv/SplendidAffluentVampireNotLikeThis',
			'* Part 2: https://clips.twitch.tv/UnusualExquisiteKuduDendiFace',
			'* Part 3: https://clips.twitch.tv/SullenColdbloodedDiscEagleEye',
			'* Part 4: https://clips.twitch.tv/BlitheEnergeticEelPRChase',
			'* Part 5: https://clips.twitch.tv/GiantGeniusGooseCclamChamp',
			'* Part 6: https://clips.twitch.tv/BoxySmallAsparagusSmoocherZ',
			'',
			'# July 28th, 2020 Livestream',
			'* Q&A: Unreal Engine 5 https://clips.twitch.tv/PiliableZanyGrassFreakinStinkin',
		],
		'features/possible-features/game-modes.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Is there a Battle Royale Mode planned? https://clips.twitch.tv/SavorySlickWombatOSkomodo',
			'',
			'# August 18th, 2020 Livestream',
			'* Q&A: When is Creative Mode coming? https://clips.twitch.tv/MagnificentImpartialSmoothieMikeHogu',
			'* Q&A: Will there be a no combat/fight version? https://clips.twitch.tv/ScaryTangibleTeaMrDestructoid',
			'* Q&A: Will there be animals that attack the base? https://clips.twitch.tv/ProtectiveTubularCatJebaited',
			'',
			'# August 11th, 2020 Livestream',
			'* Q&A: Any plans for Difficulty Levels? https://clips.twitch.tv/GrotesqueDaintyRamenGivePLZ',
			'',
			'# July 28th, 2020 Livestream',
			'* Q&A: Will you be expanding on the survival aspect of the game? https://clips.twitch.tv/IntelligentBlatantOrangeBrokeBack',
		],
		'features/tiers.md' => [
			'# August 25th, 2020 Livestream',
			'## Tier 8',
			'* Q&A: When\'s Tier 8 coming? https://clips.twitch.tv/BlueMildLaptopHassaanChop',
			'',
			'## Tier 9 & 10',
			'*answers in these clips are impaired by the technical difficulties experienced by Snutt throughout the stream.*',
			'* Q&A: What is expected for Tier 9? https://clips.twitch.tv/FrigidWiseSnakeOSfrog',
			'* Q&A: Tier 10, when? https://clips.twitch.tv/ThoughtfulDepressedAlfalfaOSfrog',
			'',
			'# August 18th, 2020 Livestream',
			'* Q&A: Might we see additions to Tier 7 before the end of the year? https://clips.twitch.tv/DoubtfulNaiveCroquettePeoplesChamp',
			'* Q&A: Tier 8 before 1.0? https://clips.twitch.tv/AgreeableTentativeBeeCurseLit',
			'* Q&A: What\'s in Tier 8? (part 1) https://clips.twitch.tv/RelievedRelievedCroissantMingLee',
			'* Q&A: What\'s in Tier 8? (part 2) https://clips.twitch.tv/AwkwardBloodyNightingaleShadyLulu',
			'',
			'# July 28th, 2020 Livestream',
			'* Jace Talk: Content & Tiers https://clips.twitch.tv/SwissFurryPlumPlanking',
		],
		'features/planned-features/signs.md' => [
			'# July 28th, 2020 Livestream',
			'* Q&A: Signs & Planets https://clips.twitch.tv/ArtisticTrustworthyHamOSkomodo',
		],
		'features/unplanned-features/aerial-travel.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Implement some kind of hire spaceship thingy for better exploration & faster travelling ? https://clips.twitch.tv/TrappedFaintBulgogiBigBrother',
			'* Q&A: How about a drone to fly around? https://clips.twitch.tv/SteamyViscousGoshawkDancingBaby',
			'',
			'## Q&A: Add Planes as Vehicles and we can automate it to  carry our resources?',
			'* Part 1: https://clips.twitch.tv/AbstruseFrailKathyMrDestructoid',
			'* Part 2: https://clips.twitch.tv/SourManlyMochaBudStar',
			'* Part 3: https://clips.twitch.tv/PowerfulFriendlyKoalaANELE',
			'* Part 4: https://clips.twitch.tv/PoliteEnergeticGrouseHassaanChop',
			'',
			'# August 11th, 2020 Livestream',
			'* Q&A: Will Drones be added to the game for aerial travel? https://clips.twitch.tv/CredulousWimpyMosquitoResidentSleeper',
			'',
			'# July 28th, 2020 Livestream',
			'* Jace Talk: Flight & map size perception https://clips.twitch.tv/ElatedBlueNightingaleMau5',
		],
		'features/possible-features/console-release.md' => [
			'# August 18th, 2020 Livestream',
			'* Q&A: Are there any plans to port the game to console? https://clips.twitch.tv/CogentRichJackalHeyGirl',
			'',
			'# July 28th, 2020 Livestream',
			'* Q&A: Satisfactory Console Release https://clips.twitch.tv/FragileNimbleEggnogDatSheffy',
		],
		'features/planned-features/dedicated-servers.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Dedicated Servers update? https://clips.twitch.tv/AgitatedAltruisticAnacondaStinkyCheese',
			'* Q&A: Will Dedicated Servers be available on Linux, or Windows? https://clips.twitch.tv/SeductiveInnocentFerretHeyGirl',
			'* Q&A: Linux would be useful for Servers https://clips.twitch.tv/UglyAwkwardCiderSSSsss',
			'* Q&A: Will the Server source code be available for Custom Mods, or with pre-compiled binaries? https://clips.twitch.tv/ShinyFunnyJellyfishSMOrc',
			'',
			'# August 18th, 2020 Livestream',
			'* Q&A: Did I miss the status update of Dedicated Servers? https://clips.twitch.tv/ElatedWittyVelociraptorThunBeast',
			'',
			'# August 11th, 2020 Livestream',
			'* Q&A: Are Dedicated Servers coming? https://clips.twitch.tv/BigDeadPhoneKappaWealth',
			'* Q&A: What\'s the hold-up on Dedicated Servers? https://clips.twitch.tv/ShinyAthleticCrocodileKappaPride',
			'* Jace Talk: Massive Bases, Multiplayer lag, and Dedicated Servers https://clips.twitch.tv/RealPrettiestKoalaBloodTrail',
			'* Q&A: Dedicated Servers, start building a community around that? https://clips.twitch.tv/EagerPeacefulMonkeyDoubleRainbow',
			'',
			'# July 28th, 2020 Livestream',
			'* Q&A: Dedicated Server cost https://clips.twitch.tv/ConfidentLittleSnood4Head',
		],
		'features/fluids/pipes.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: A mark on pipes to show the meters ? https://clips.twitch.tv/AltruisticSuperBobaBudBlast',
			'',
			'# August 18th, 2020 Livestream',
			'* Q&A: Is there any way to prioritise power plant pipes? https://clips.twitch.tv/AnnoyingSavageParrotWoofer',
			'* Q&A: What convinced you to add pipes? https://clips.twitch.tv/BashfulFantasticPotDAESuppy',
			'',
			'# July 28th, 2020 Livestream',
			'* Q&A: Has Pipe Overflow been discussed? https://clips.twitch.tv/VainArtsyLeopardUncleNox',
		],
		'merch.md' => [
			'# August 18th, 2020 Livestream',
			'* Q&A: Is there a Merch Store? https://clips.twitch.tv/CleanCarefulMoonAMPEnergyCherry',
			'* Q&A: When will have Merch? https://clips.twitch.tv/FunOriginalPistachioNerfRedBlaster',
			'',
			'# August 11th, 2020 Livestream',
			'## Prototypes',
			'* Pioneer Helmet t-shirt (black): https://clips.twitch.tv/PunchyGloriousMoonPanicBasket',
			'* FICSIT employee hoodie (light grey) https://clips.twitch.tv/FaithfulFrigidFinchKappaPride',
			'* Fine Art by Jace Varlet https://clips.twitch.tv/CrispyAstuteBeeNerfRedBlaster',
			'',
			'## FICSIT Cup',
			'* Jace Talk: Launch & FICSIT Cup https://clips.twitch.tv/AmazingOriginalMeerkatArgieB8',
			'* Jace Talk: FICSIT Cup https://clips.twitch.tv/InquisitiveCooperativeMallardWholeWheat',
			'* Q&A: FICSIT Cup material? https://clips.twitch.tv/SarcasticWildBeanRitzMitz',
			'',
			'## Q&A',
			'* Q&A: gravity-defying FICSIT-branded coffee https://clips.twitch.tv/TalentedIntelligentGazelleFunRun',
			'* Q&A: Lizard Doggo Plushies https://clips.twitch.tv/TolerantPunchyNewtJKanStyle',
			'* Q&A: Doggo Toys? https://clips.twitch.tv/FlirtyScarySushiYouWHY',
			'* Q&A: FICSIT employee t-shirt? https://clips.twitch.tv/SuspiciousAlluringDolphinThunBeast',
			'* Q&A: How much will the Merch cost? https://clips.twitch.tv/SmallSullenTomatoTheThing',
			'* Q&A: How much will the Merch cost? (part 2) https://clips.twitch.tv/EnticingPricklyWitchM4xHeh',
			'* Q&A: Figurine? https://clips.twitch.tv/ShortKathishAardvarkUnSane',
			'* Q&A: zip-up hoodie? https://clips.twitch.tv/SpoopyCrowdedOctopusTBTacoLeft',
			'* Q&A: FICSIT Masks/Helmets https://clips.twitch.tv/ClearColdbloodedCakeVoHiYo',
			'* Q&A: remote-control Factory Cart https://clips.twitch.tv/MoistSmellyReubenDoubleRainbow',
			'* Q&A: t-shirt material? https://clips.twitch.tv/ComfortableAltruisticHerringDansGame',
			'',
			'## Jace Talk',
			'* Jace Talk: Additional Merch, Launch & later Merch https://clips.twitch.tv/EndearingBraveSeahorseBloodTrail',
			'* Jace Merch Talk: US vs. EU Merch Warehousing https://clips.twitch.tv/ColdStormySalsifyArgieB8',
			'',
			'# July 28th, 2020 Livestream',
			'* Q&A: Coffee Mug? https://clips.twitch.tv/SpunkyHyperWasabi4Head',
			'',
			'# July 21st, 2020 Livestream',
			'* Q&A: How\'s the Merch Store coming along? https://clips.twitch.tv/OilySillySproutNotLikeThis',
		],
		'features/power-management/green-energy.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Why are there no Solar Panels ? https://clips.twitch.tv/CleverPluckyOctopusRedCoat',
			'* Q&A: Put in Solar & Wind Power to make it ultra limited? https://clips.twitch.tv/DeliciousStylishOctopusTBTacoRight',
			'* Q&A: What about wind turbines? https://clips.twitch.tv/TriangularColdShingleSquadGoals',
			'',
			'# August 11th, 2020 Livestream',
			'* Q&A: Green Energy? https://clips.twitch.tv/BloodyIcyDragonflyStoneLightning',
		],
		'mods/mod-vs-features.md' => [
			'# August 18th, 2020 Livestream',
			'## Mods vs. Features',
			'* Part 1: https://clips.twitch.tv/ShakingCredulousGalagoCopyThis',
			'* Part 2: https://clips.twitch.tv/OriginalDifficultTeaKevinTurtle',
			'* Part 3: https://clips.twitch.tv/CorrectAlertEggplantPJSalt',
			'* Part 4: https://clips.twitch.tv/ShakingNastyJaguarGrammarKing',
			'',
			'# August 11th, 2020 Livestream',
			'## Mods vs. Features',
			'* Part 1 https://clips.twitch.tv/ElegantKindPrariedogGrammarKing',
			'* Part 2 https://clips.twitch.tv/NimbleFurryDumplingsBudBlast',
		],
		'environment/world-map.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Underwater biome when? https://clips.twitch.tv/HonorableCautiousDonutStoneLightning',
			'* Q&A: Terraforming? https://clips.twitch.tv/CourageousTardyLarkShazBotstix',
			'* Q&A: Will you guys be hiding more stuff throughout the world for the Story Mode? https://clips.twitch.tv/VastAlertBadgerTF2John',
			'* Q&A: Why can\'t we explode some stones in the map? https://clips.twitch.tv/HeartlessAntsyMelonCharlieBitMe',
			'* Q&A: Like a new map for Satisfactory? https://clips.twitch.tv/ArtisticAthleticCroissantRalpherZ',
			'* Q&A: How about procedural maps? https://clips.twitch.tv/ProtectiveWonderfulFrogVoteYea',
			'* Q&A: Found a big pink flower thing in a cave, is that just some cool scenery or is it a WIP ? https://clips.twitch.tv/VibrantExpensiveRaisinStinkyCheese',
			'* Q&A: Will there be a rocket to leave the planet? https://clips.twitch.tv/BusyPowerfulWombatSoonerLater',
			'',
			'## Q&A: Plans for a Map Editor?',
			'* Part 1: https://clips.twitch.tv/ApatheticExpensiveDiscPeoplesChamp',
			'* Part 2: https://clips.twitch.tv/WiseToughOstrichYouWHY',
			'',
			'## Snutt Talk: Map Builders',
			'* Part 1: https://clips.twitch.tv/TsundereProudKiwiRaccAttack',
			'* Part 2: https://clips.twitch.tv/RichResourcefulSwanRlyTho',
			'',
			'# August 18th, 2020 Livestream',
			'* Q&A: Will there be any underwater resources? https://clips.twitch.tv/RelievedCleanBibimbapDancingBanana',
			'* Q&A: Terraforming? https://clips.twitch.tv/AmericanSpineyWitchTinyFace',
			'* Q&A: Any ice/snow biome plans? https://clips.twitch.tv/AlluringScrumptiousBaboonHeyGirl',
			'* Q&A: Any different maps planned? https://clips.twitch.tv/PlausibleEnthusiasticGrassRedCoat',
			'* Q&A: Will you be able to create your own map? https://clips.twitch.tv/ChillyRockyWalrusUnSane',
			'',
			'# August 11th, 2020 Livestream',
			'* Q&A: Randomly Generated Maps: https://clips.twitch.tv/OilyBloodyMangoFutureMan',
			'* Q&A: Do you plan to release a World Editor? https://clips.twitch.tv/AnnoyingImpartialGaurChefFrank',
		],
		'features/possible-features/weather.md' => [
			'# August 11th, 2020 Livestream',
			'* Q&A: What about Weather systems? https://clips.twitch.tv/SilkyFurryCheetahMVGame',
		],
		'story-lore.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Will there be more narrative? https://clips.twitch.tv/DarlingPoisedPotCopyThis',
			'* Q&A: Is the Story a mode, or can I play with my actual save game? https://clips.twitch.tv/GeniusInventiveMomPRChase',
			'',
			'# August 11th, 2020 Livestream',
			'* Q&A: Story / End-game? https://clips.twitch.tv/AmorphousVictoriousTrayPartyTime',
		],
		'features/pioneer.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: She!? Not me !? https://clips.twitch.tv/InexpensiveChillyWheelItsBoshyTime',
			'* Q&A: Let me personalise my character? https://clips.twitch.tv/CharmingRespectfulFlyFUNgineer',
			'',
			'## Q&A: Please consider adding a third-person view?',
			'* Part 1: https://clips.twitch.tv/PeacefulInventiveDogWOOP',
			'* Part 2: https://clips.twitch.tv/FrailSuaveBeanImGlitch',
			'',
			'# August 18th, 2020 Livestream',
			'* Q&A: Additional Suit Variations in the Coupon Shop ? https://clips.twitch.tv/CourteousMotionlessWrenMcaT',
			'',
			'## Q&A: How did you make the character slide in Satisfactory?',
			'* Part 1 https://clips.twitch.tv/WittyYawningSangJKanStyle',
			'* Part 2 https://clips.twitch.tv/BlueBadWeaselPMSTwin',
			'',
			'# August 11th, 2020 Livestream',
			'* Q&A: FICSIT Pioneer gender confirmed? https://clips.twitch.tv/TriangularLongOctopusOneHand',
			'',
			'## Q&A: Sleep in-game?',
			'* Part 1 https://clips.twitch.tv/DaintyYummyLemurANELE',
			'* Part 2 https://clips.twitch.tv/PrettiestObedientLegItsBoshyTime',
		],
		'features/transportation/vehicles.md' => [
			'# August 25th, 2020 Livestream',
			'* Snutt Talk: Improving on Vehicles https://clips.twitch.tv/AmazonianAnnoyingSushiUncleNox',
			'* Q&A: Any plans to dig my explorer to get it out of the hole it fell into? https://clips.twitch.tv/FuriousRockyDuckPRChase',
			'',
			'## Trucks',
			'* Q&A: Smart Truck Stations? https://clips.twitch.tv/FurtiveHealthyRhinocerosJonCarnage',
			'* Q&A: Trailer for the Trucks? https://clips.twitch.tv/SarcasticNeighborlyPigTebowing',
			'',
			'### Q&A: Tanker Trucks?',
			'* Part 1: https://clips.twitch.tv/TenderSuspiciousSashimiEleGiggle',
			'* Part 2: https://clips.twitch.tv/FunSparklyFishRedCoat',
			'',
			'## Q&A: Can you make modular vehicles?',
			'* Part 1: https://clips.twitch.tv/OriginalMuddyDogePeteZaroll',
			'* Part 2: https://clips.twitch.tv/DistinctConcernedPlumageWow',
			'',
			'## Water Vehicles',
			'* Q&A: If you add Trucks then add Boats? https://clips.twitch.tv/EasyEnticingBearM4xHeh',
			'* Q&A: We need Battleships https://clips.twitch.tv/WildHonorableCakeGrammarKing',
			'',
			'## Cyber Wagon',
			'* Q&A: Make the Cyber Wagon useful ? https://clips.twitch.tv/SpeedyAssiduousCrabKevinTurtle',
			'* Q&A: What can the Cyber Wagon do? https://clips.twitch.tv/SuperHappyEmuOMGScoots',
			'',
			'# August 18th, 2020 Livestream',
			'* Q&A: Are there some other vehicles planned? https://clips.twitch.tv/EsteemedNurturingHyenaWOOP',
			'* Q&A: Are vehicles going to get less sketchy or are we always getting Goat Simulator physics? https://clips.twitch.tv/KawaiiPoorYakinikuJonCarnage',
			'',
			'# August 11th, 2020 Livestream',
			'* Q&A: Elevators? https://clips.twitch.tv/HelpfulSuaveScallionPeanutButterJellyTime',
			'* Q&A: First-person Vehicle Driving? https://clips.twitch.tv/ShinySilkyMelonGivePLZ',
			'## Two-seated vehicles',
			'* Part 1 https://clips.twitch.tv/OilySourBeaverAMPEnergy',
			'* Part 2 https://clips.twitch.tv/CooperativeFurtiveWasabiOhMyDog',
		],
		'features/buildables/foundations.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Set a specific Foundation as the keystone https://clips.twitch.tv/GoodAnimatedSproutPipeHype',
			'* Q&A: Grid- a radius would be perfect https://clips.twitch.tv/GeniusConcernedEggDogFace',
			'',
			'## Q&A: What about holes for Foundations?',
			'* Part 1: https://clips.twitch.tv/CrepuscularEnergeticPartridgePanicVis',
			'* Part 2: https://clips.twitch.tv/SparklingGiftedDumplingsSquadGoals',
			'',
			'# August 11th, 2020 Livestream',
			'* Q&A: Floating Factories vs. Structural Supports https://clips.twitch.tv/GiftedSincereDillDoubleRainbow',
		],
		'features/buildings.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Fixing machines that break? https://clips.twitch.tv/EnergeticInexpensiveDillCurseLit',
			'* Snutt Talk: Machines breaking & Base Defense https://clips.twitch.tv/ElegantKawaiiGnatOneHand',
			'* Q&A: Water Extractors need to snap to grid https://clips.twitch.tv/ExuberantAmorphousCarrotNononoCat',
			'',
			'## Q&A: Internal discussions to significantly rework existing buildings like refineries?',
			'* Part 1: https://clips.twitch.tv/CrispySaltyOcelotOSfrog',
			'* Part 2: https://clips.twitch.tv/CooperativeCrackyAyeayeTheTarFu',
			'* Part 3: https://clips.twitch.tv/SmallProductiveKaleCclamChamp',
			'* Part 4: https://clips.twitch.tv/BoredThankfulPistachioJKanStyle',
			'',
			'## Refineries',
			'* Q&A: Do you not think that Refineries are over-used? https://clips.twitch.tv/LongOpenFlamingoSMOrc',
			'* Q&A: End game is all about building refineries https://clips.twitch.tv/MildNurturingWoodcockYouWHY',
			'* Q&A: Refineries take up so much space https://clips.twitch.tv/FilthyPerfectDragonSwiftRage',
			'',
			'## Hypertubes',
			'* Q&A: Signs for Hypertube Entrances? https://clips.twitch.tv/SpinelessUnsightlyVanillaKeyboardCat',
			'* Q&A: Mk. 2 Hypertubes? https://clips.twitch.tv/CrypticUnusualPandaArsonNoSexy',
			'* Q&A: Why is hyperloop in first person? https://clips.twitch.tv/FairCallousStingrayHeyGuys',
			'',
			'# August 18th, 2020 Livestream',
			'* Q&A: How about adding machine variants during late-game so you can have less machines overall? https://clips.twitch.tv/BlatantEnjoyableTigerStoneLightning',
			'',
			'# August 11th, 2020 Livestream',
			'* Q&A: Hypertube Cannons - Bug or Feature? https://clips.twitch.tv/OilyPatientOtterTBTacoLeft',
			'',
			'# July 21st, 2020 Livestream',
			'* Q&A: How about building underwater? https://clips.twitch.tv/NiceDreamyGarbageBuddhaBar',
		],
		'q-and-a-site.md' => [
			'# August 11th, 2020 Livestream',
			'## Jace Talk: The Q&A Site',
			'* Part 1: https://clips.twitch.tv/BoxyZanyPancakeKeepo',
			'* Part 2: https://clips.twitch.tv/RenownedQuaintBeaverAMPTropPunch',
			'* Part 3: https://clips.twitch.tv/ZealousNastyCiderPeteZarollTie',
		],
		'features/buildings/the-hub.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Will you be able to play Doom on the Hub screens? https://clips.twitch.tv/DifficultDependableGooseAMPEnergyCherry',
			'',
			'## Hub Fridge',
			'* Q&A: Can we get a drink on the fridge in the base? https://clips.twitch.tv/ShyDifferentOcelotRalpherZ',
			'* Snutt Talk: Fridge in the Hub https://clips.twitch.tv/FreezingBovineRutabagaFutureMan',
			'* Snutt Talk: Snutt discovers the fridge. https://clips.twitch.tv/SeductiveAbstemiousBisonDerp',
			'* Q&A: Could we order food from the Food Station, or is it like a buffet? https://clips.twitch.tv/ExuberantDeadDinosaurBatChest',
			'',
			'# August 11th, 2020 Livestream',
			'* Q&A: Anything inside the HUB where the MAM used to be? https://clips.twitch.tv/RespectfulDreamyHabaneroMrDestructoid',
		],
		'coffeestainers.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Are the Devs back from vacation? https://clips.twitch.tv/SeductiveImpartialCobblerOptimizePrime',
			'',
			'# August 11th, 2020 Livestream',
			'* Q&A: Do you have a QA department? https://clips.twitch.tv/WanderingWonderfulTitanTBCheesePull',
		],
		'off-topic.md' => [
			'# August 18th, 2020 Livestream',
			'* Q&A: Why does Snutt have many guitars? https://clips.twitch.tv/AverageRenownedAxeWholeWheat',
			'',
			'# August 11th, 2020 Livestream',
			'* Q&A: New Apartment? https://clips.twitch.tv/CorrectAdorableDinosaurWoofer',
		],
		'environment/resources.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Will you be adding more variety of resources? https://clips.twitch.tv/BraveThankfulBeefFreakinStinkin',
			'',
			'## Q&A: Are limited resources planned?',
			'* Part 1: https://clips.twitch.tv/ConcernedStylishTomatoBabyRage',
			'* Part 2: https://clips.twitch.tv/PrettyBelovedTermiteOptimizePrime',
			'* Part 3: https://clips.twitch.tv/PoorAlluringHerdRitzMitz',
			'',
			'# August 18th, 2020 Livestream',
			'* Q&A: Do you plan to make other resources beyond S.A.M. Ore? https://clips.twitch.tv/InventiveBillowingEggPMSTwin',
			'* Q&A: S.A.M. Ore uses? https://clips.twitch.tv/BovineDistinctOrangeRiPepperonis',
			'* Q&A: Coffee Cups are made out of S.A.M. Ore? https://clips.twitch.tv/SuspiciousImportantOryxSquadGoals',
		],
		'features/multiplayer.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Is there a Battle Royale Mode planned? https://clips.twitch.tv/SavorySlickWombatOSkomodo',
			'* Q&A: When I play multiplayer and the train and host doesn\'t update correctly, is this a known bug? https://clips.twitch.tv/LightAcceptableCheesePermaSmug',
			'* Q&A: The time for multiplayer fix, can\'t use vehicles? https://clips.twitch.tv/PlayfulConfidentRabbitCurseLit',
			'',
			'## Q&A: Are you going to improve networking for multiplayer?',
			'* Part 1: https://clips.twitch.tv/HomelyHyperGnatDoritosChip',
			'* Part 2: https://clips.twitch.tv/SpinelessTsundereBurritoDxAbomb',
			'',
			'# August 18th, 2020 Livestream',
			'* Q&A: Offline Play https://clips.twitch.tv/BashfulDependableBobaWTRuck',
			'',
			'## Multiplayer desync issues',
			'* Part 1: https://clips.twitch.tv/AliveHomelySandwichGivePLZ',
			'* Part 2: https://clips.twitch.tv/VastScrumptiousEyeballLeeroyJenkins',
			'* Part 3: https://clips.twitch.tv/TsundereHandsomeBottleCharlietheUnicorn',
			'',
			'## Q&A: When will multiplayer reach 128 so we can build a tower?',
			'* Part 1: https://clips.twitch.tv/OpenIntelligentPizzaYouWHY',
			'* Part 2: https://clips.twitch.tv/TardyBitterGnatDatSheffy',
			'* Part 3: https://clips.twitch.tv/SavagePopularBatChocolateRain',
			'',
			'## Q&A: Session Privacy / Join Button not working.',
			'* Part 1 https://clips.twitch.tv/PolishedThirstyDinosaurOhMyDog',
			'* Part 2 https://clips.twitch.tv/CrackyBombasticEggUWot',
		],
		'satisfactory-updates/satisfactory-1-0.md' => [
			'# August 25th, 2020 Livestream',
			'* Snutt Talk: 1.0 & Sequels https://clips.twitch.tv/CharmingHeadstrongAsparagusBCouch',
			'* Q&A: Do you want to release updates before you release full game? https://clips.twitch.tv/EmpathicExuberantWeaselAllenHuhu',
			'* Q&A: Do you have any clue on what the alien artefacts do? https://clips.twitch.tv/CulturedEnthusiasticNoodleWTRuck',
			'* Q&A: Will there be any further goals besides Research & Development stages? https://clips.twitch.tv/TsundereOutstandingNuggetsFUNgineer',
			'',
			'## Do you have a set of ideas?',
			'* Part 1: https://clips.twitch.tv/AgitatedProtectiveBaguetteRiPepperonis',
			'* Part 2: https://clips.twitch.tv/NaiveProudZebraWOOP',
			'',
			'## Snutt Talk: Satisfactory 1.0, and beyond',
			'* Part 1: https://clips.twitch.tv/BrainyArbitraryEagleBrokeBack',
			'* Part 2: https://clips.twitch.tv/HomelyCovertParrotNinjaGrumpy',
			'* Part 3: https://clips.twitch.tv/SmellyCarefulRuffPastaThat',
			'* Part 4: https://clips.twitch.tv/GenerousSlickKimchiResidentSleeper',
			'',
			'# August 18th, 2020 Livestream',
			'* Snutt Talk: Macro Plan towards 1.0 https://clips.twitch.tv/CorrectNiceStingraySpicyBoy',
			'* Q&A: Storyline before 1.0? https://clips.twitch.tv/SteamyFurtiveRadishStrawBeary',
			'* Q&A: Is 1.0 the end of the game or will it be expanded? https://clips.twitch.tv/AmazonianWealthyCroquetteDendiFace',
			'* Q&A: Will 1.0 require a reset of the game? https://clips.twitch.tv/SpoopyPlacidPepperoniSoonerLater',
			'* Q&A: When are Somersloops and Orbs have meaning? https://clips.twitch.tv/SarcasticProudWoodpeckerKappaPride',
		],
		'features/accessibility.md' => [
			'# August 25th, 2020 Livestream',
			'## Arachnophobia Mode',
			'* Q&A: More cats in Arachnophobia Mode? https://clips.twitch.tv/KathishConcernedWalrusRedCoat',
			'* Q&A: Arachnophobia Mode is scarier than the actual spiders https://clips.twitch.tv/NeighborlyEnticingMarrowResidentSleeper',
			'',
			'# August 18th, 2020 Livestream',
			'## Arachnophobia Mode',
			'* Q&A: Arachnophobia Mode (part 1) https://clips.twitch.tv/HandsomeJoyousPigeonYouWHY',
			'* Snutt & Jace Talk: Arachnophobia Mode (part 2) https://clips.twitch.tv/ResilientTalentedSalsifySSSsss',
			'* Snutt & Jace Talk: Arachnophobia Mode (part 3) https://clips.twitch.tv/ModernExquisiteJayFeelsBadMan',
			'* Snutt & Jace Talk: Arachnophobia Mode (part 4) https://clips.twitch.tv/NurturingPlayfulSwanTBTacoLeft',
			'',
			'## Accessibility Features',
			'* Snutt Talk: Accessibility (part 1): https://clips.twitch.tv/CrowdedSplendidSalamanderSoonerLater',
			'* Q&A: We get this awesome phobia system but people still have trouble with colour blindness modes? https://clips.twitch.tv/PrettiestBloodyBadgerDendiFace',
			'* Jace Talk: Accessibility - Arachnophobia & Colour Blindness (part 3) https://clips.twitch.tv/DignifiedSmoggyKathyAMPEnergyCherry',
			'* Snutt & Jace Talk: Accessibility - Colour Blindness (part 4) https://clips.twitch.tv/FurtiveConcernedPuppySMOrc',
			'* Snutt & Jace Talk: Accessibility - Hard of Hearing (part 5) https://clips.twitch.tv/RealFastShieldDoubleRainbow',
			'* Snutt & Jace Talk: Accessibility (part 6) https://clips.twitch.tv/BelovedWrongCiderBCouch',
			'* Q&A: I can definitely work around my colour deficiency - but the colour picker doesn\'t work https://clips.twitch.tv/CrepuscularInterestingWerewolfBCWarrior',
		],
		'features/paint.md' => [
			'# August 18th, 2020 Livestream',
			'* Q&A: When will we be able to paint our trains? https://clips.twitch.tv/BelovedBloodyStapleGingerPower',
		],
		'mods/official-mod-support.md' => [
			'# August 25th, 2020 Livestream',
			'## Plans for mod support?',
			'* Part 1: https://clips.twitch.tv/OnerousDeterminedMoonRlyTho',
			'* Part 2: https://clips.twitch.tv/CreativeOptimisticWalrusSwiftRage',
			'* Part 3: https://clips.twitch.tv/HumbleRenownedTofuLitty',
			'',
			'# August 18th, 2020 Livestream',
			'* Q&A: Will you plan to add Steam Workshop support? https://clips.twitch.tv/SwissTameCoffeeDansGame',
		],
		'satisfactory-updates/seasonal-events.md' => [
			'# August 25th, 2020 Livestream',
			'## Free Wekends',
			'* Q&A: Can you make it free for one time only? https://clips.twitch.tv/AlertPleasantReindeerPupper',
			'* Snutt Talk: Previous free weekends https://clips.twitch.tv/ArtisticCrispyGrasshopperBrainSlug',
			'',
			'# August 18th, 2020 Livestream',
			'* Q&A: Quarterly Build Contest? https://clips.twitch.tv/SparklingJazzyJayBCWarrior',
		],
		'features/possible-features/dlc.md' => [
			'# August 18th, 2020 Livestream',
			'* Q&A: Any plans to add toilet paper in the bathroom? https://clips.twitch.tv/AuspiciousPrettiestAlfalfaKAPOW',
		],
		'features/buildables/conveyor-belts.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Please think about adding dedicated Storage Containers like in Ark ? https://clips.twitch.tv/ArbitraryIronicClipsdadPicoMause',
			'',
			'## Q&A: Can players have custom programmers ?',
			'* Part 1: https://clips.twitch.tv/BovineConsiderateSangMVGame',
			'* Part 2: https://clips.twitch.tv/GrossPoisedAardvarkChocolateRain',
			'',
			'# August 18th, 2020 Livestream',
			'* Q&A: Will there ever be conveyor lift splitters & mergers ? https://clips.twitch.tv/MiniatureFlaccidSwanKAPOW',
		],
		'satisfactory-updates/state-of-dev.md' => [
			'# August 25th, 2020 Livestream',
			'## Snutt Talk: State of Development',
			'* Part 1: https://clips.twitch.tv/WealthyModernInternDogFace',
			'* Part 2: https://clips.twitch.tv/SuaveChillyGrouseSaltBae',
			'',
			'### Q&A: State of things = 🤷',
			'* Part 1: https://clips.twitch.tv/WealthyStormySnakeOptimizePrime',
			'* Part 2: https://clips.twitch.tv/EndearingBlitheTruffleJebaited',
			'',
			'## Quality-of-life update?',
			'* Part 1: https://clips.twitch.tv/RudeSpoopyAlligatorVoteYea',
			'* Part 2: https://clips.twitch.tv/AlertFancyAxeFUNgineer',
			'* Part 3: https://clips.twitch.tv/CrunchyGlutenFreeNuggetsMingLee',
			'',
			'## Why do big updates at all - why not just release everything in small bites?',
			'* Part 1: https://clips.twitch.tv/FrozenLuckyRamenDxCat',
			'* Part 2: https://clips.twitch.tv/BrainySecretiveSquidChefFrank',
			'* Part 3: https://clips.twitch.tv/SpunkyFlirtySoybeanJebaited',
			'* Part 4: https://clips.twitch.tv/EnjoyableCrazyVanillaStinkyCheese',
			'* Part 5: https://clips.twitch.tv/CharmingObservantLardSMOrc',
			'* Part 6: https://clips.twitch.tv/LachrymoseCourteousDoveDxAbomb',
		],
		'technology/user-interface.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Today I Learned - there\'s a mass dismantle? https://clips.twitch.tv/OnerousGlamorousMoonAllenHuhu',
			'* Q&A: What about a Tutorial System? https://clips.twitch.tv/EntertainingTenuousCasettePeteZaroll',
		],
		'features/power-mangement/nuclear-energy.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Nuclear is the current end game https://clips.twitch.tv/CoweringHotZebraTheTarFu',
			'',
			'## Snutt PSA: Nuclear Waste disposal',
			'* Part 1: https://clips.twitch.tv/DarlingSteamyCourgetteOneHand',
			'* Part 2: https://clips.twitch.tv/HorribleToughMouseThunBeast',
			'* Part 3: https://clips.twitch.tv/SullenWittyBearHassanChop',
			'* Part 4: https://clips.twitch.tv/QuaintBeautifulMetalWoofer',
			'* Part 5: https://clips.twitch.tv/GoldenTenuousLemurDAESuppy',
			'',
			'### Q&A: If we can\'t delete the radioactive, then please add radioactive-safe containers to store them?',
			'* Part 1: https://clips.twitch.tv/ConfidentImpossibleShingleTBTacoLeft',
			'* Part 2: https://clips.twitch.tv/SnappyExpensiveDootMVGame',
		],
		'features/buildables/walls.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Will I be able to place walls slightly into splitters, mergers, and conveyors? https://clips.twitch.tv/RespectfulGiftedStaplePicoMause',
		],
		'mods/mods-vs-features.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Actual Elevators with floor-select buttons ? https://clips.twitch.tv/SparklingFilthyKathyBleedPurple',
			'* Q&A: Do you have plans for elevators usable for players? https://clips.twitch.tv/DullSmokyWaffleDoggo',
			'',
			'## Q&A: Can players have custom programmers ?',
			'* Part 1: https://clips.twitch.tv/BovineConsiderateSangMVGame',
			'* Part 2: https://clips.twitch.tv/GrossPoisedAardvarkChocolateRain',
		],
		'features/graphics.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Reducing the stupid poly counts? https://clips.twitch.tv/PoliteTallLocustStoneLightning',
			'* Q&A: Real-time reflections for the helmet? https://clips.twitch.tv/LivelyHeartlessRutabagaWholeWheat',
			'* Q&A: UV issues and texture tearing is a known issue? https://clips.twitch.tv/WealthyPunchyAxePeoplesChamp',
			'* Snutt Talk: VR Support https://clips.twitch.tv/DullScrumptiousEagleStinkyCheese',
			'',
			'## Q&A: Will light be added to the game?',
			'* Part 1: https://clips.twitch.tv/FunOilyWolverineCorgiDerp',
			'* Part 2: https://clips.twitch.tv/NeighborlyCharmingBasenjiRlyTho',
			'* Part 3: https://clips.twitch.tv/AbnegateEndearingBottleKlappa',
		],
		'soundtrack.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Will there be any new music soundtracks in the future? https://clips.twitch.tv/UgliestArbitraryOwlDatBoi',
		],
		'features/buildables/jump-pads.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Can we get an indicator for the launch line from the Launch Pad? https://clips.twitch.tv/ShakingBlushingLampNerfRedBlaster',
		],
		'environment/pollution.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: More pollution as you progress? https://clips.twitch.tv/WanderingLitigiousDurianRalpherZ',
		],
		'features/equipment.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Explosive Barrels of Gas we can send through the rail guns ? https://clips.twitch.tv/CrowdedRespectfulNostrilNotATK',
		],
		'environment/plants.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Removing vegetation speeds up the game, yes or no ? https://clips.twitch.tv/BusyHandsomeSmoothiePartyTime',
			'* Q&A: Are the trees instance-based? https://clips.twitch.tv/HandsomeAnnoyingLEDPraiseIt',
		],
		'satisfactory-updates/release-builds.md' => [
			'# August 25th, 2020 Livestream',
			'* Q&A: Plans for official Linux support? https://clips.twitch.tv/DiligentDeafMangoPogChamp',
		],
	],
];

$global_topic_hierarchy = [
	'satisfactory' => [
		'PLbjDnnBIxiEo8RlgfifC8OhLmJl8SgpJE' => [ // State of Dev
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiEp3nVij0OnuqpiuBFlKDB-K' => [ // Satisfactory 2017
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiEp9MC5RZraDAl95pvC0YVvW' => [ // Satisfactory Update 3
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiEq_0QTxH7_C0c8quZsI1uMu' => [ // Satisfactory Fluids Update
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiEpH9vCWSguzYfXrsjagXgyE' => [ // Satisfactory Update 4
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiEov1pe4Y3Fr8AFfJuu7jIR6' => [ // Satisfactory Update 5
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiEpOfQ2ATioPVEQvCuB6oJSR' => [ // Satisfactory Update 6
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiEpmeKjnMqZxXfE3hxJ7ntQo' => [ // Satisfactory Update 8
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiEqrvp3UlLgVHZbY9Yb045zj' => [ // Release Builds
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiEppntOHbTUyrFhnKNkZZVpT' => [ // Satisfactory 1.0
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiErq1cTFe-14F7UISclc1uc-' => [ // Seasonal Events
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiEq84iBBkP2g69rPYXD-yWMy' => [ // FICS⁕MAS
			'Satisfactory Updates',
			'Seasonal Events',
		],
		'PLbjDnnBIxiEo6wrqcweq2pi9HmfRJdkME' => [ // Simon
			'Coffeestainers',
		],
		'PLbjDnnBIxiErABErNV8_bjXIF_CFZILeP' => [ // Mods
			'',
		],
		'PLbjDnnBIxiEr8o-WHAZJf8lAVaNJlbHmH' => [ // Mods vs. Features
			'Mods',
		],
		'PLbjDnnBIxiEolhfvxFmSHBd2Ct-lyZW6N' => [ // Official Mod Support
			'Mods',
		],
		'PLbjDnnBIxiEpeblSG5B1RXiCAdeDiTQPp' => [ // Accessibility
			'Features',
		],
		'PLbjDnnBIxiEqhhpjoy5eqGRQQGUItie6A' => [ // Buildings
			'Features',
		],
		'PLbjDnnBIxiEpz0p3GUy76rNNYI0kiJ0iW' => [ // The HUB
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEo-kXF1M3ONmu_VdZKJyQNc' => [ // Packager
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEosbWcrHc-p6N-xeS77Rv5P' => [ // Trains
			'Features',
			'Transportation',
		],
		'PLbjDnnBIxiErU4o7RRN4bcc2H_F-wO-dl' => [ // Dedicated Servers
			'Features',
			'Planned Features',
		],
		'PLbjDnnBIxiEqn_GaVtEJZl0kP0-zlNlDa' => [ // DLC
			'Features',
			'Possible Features', // to later be moved under planned features
		],
		'PLbjDnnBIxiEoqQvPLEn3mTEcUnBX4Osqd' => [ // Multiplayer
			'Features',
		],
		'PLbjDnnBIxiEoUH4kdC1Vuc8zUrwXyfmUB' => [ // Paint
			'Features',
		],
		'PLbjDnnBIxiEr_y5FuYy4mEW7F87dLs1ve' => [ // Photo Mode
			'Features',
		],
		'PLbjDnnBIxiEqbVv5qFvJ7SDFemhz9imET' => [ // Power Management
			'Features',
		],
		'PLbjDnnBIxiEq5jpYwmYkaBeX-9e9HSPYf' => [ // Geothermal Energy
			'Features',
			'Power Management',
		],
		'PLbjDnnBIxiEoxt-MkPSDtxi9DBMOneqyT' => [ // Green Energy
			'Features',
			'Power Management',
		],
		'PLbjDnnBIxiEqykvbeU5B1yvLZ3EI1NSC6' => [ // Nuclear Energy
			'Features',
			'Power Management',
		],
		'PLbjDnnBIxiEoiskJRYH3hrAA192_4QTQ_' => [ // Console Release
			'Features',
			'Possible Features',
		],
		'PLbjDnnBIxiEqVTlmqhbpntiX-LNPSHZqz' => [ // Decor
			'Features',
			'Possible Features',
		],
		'PLbjDnnBIxiEr4RIwd7JK5NWkjYLh0-Wg1' => [ // Mass Building
			'Features',
			'Unplanned Features',
		],
		'PLbjDnnBIxiEp81cEwQOHVVRhOUNWWsrWT' => [ // Unreleased Content
			'Features',
			'Unreleased Features',
		],
		'PLbjDnnBIxiEpmT1vmxxciR96Op1TkWR8J' => [ // Walls
			'Features',
			'Buildables',
		],
		'PLbjDnnBIxiEpED5R4C4489wyekiGkNVe2' => [ // Creatures
			'Environment',
		],
		'PLbjDnnBIxiEq1P6bQ-17tMjKuFJyxcfSU' => [ // Plants
			'Environment'
		],
		'PLbjDnnBIxiErB8M3t_-CDtAh-q9TXdooO' => [ // Polution
			'Environment',
		],
		'PLbjDnnBIxiEr1a23kLgbSYkwgfuD1tQao' => [ // Resources
			'Environment',
		],
		'PLbjDnnBIxiErbdgIq2TL2rTjyGvOiwC9O' => [ // Pioneer
			'Features',
		],
		'PLbjDnnBIxiEqjQuyYsNElrE2MqzMywC4c' => [ // Emotes
			'Features',
		],
		'PLbjDnnBIxiErmYlQEEHD-GrNpRC1_6R9b' => [ // Third-Party Service Integration
			'Features',
			'Possible Features',
		],
		'PLbjDnnBIxiErdYb5Nn5q1dZBDC_ktf1h_' => [ // Signs
			'Features',
			'Planned Features',
		],
		'PLbjDnnBIxiEp3QMTxKRnehenCwo17MuJy' => [ // Conveyor Belts
			'Features',
			'Buildables',
		],
		'PLbjDnnBIxiEq1lyHQw0wVik2U0W0lmO1p' => [ // Foundations
			'Features',
			'Buildables',
		],
		'PLbjDnnBIxiEpVKlAtlIftyOD0SUkwZuNw' => [ // Jump Pads
			'Features',
			'Buildables',
		],
		'PLbjDnnBIxiEq2Ir2tWVfwVVZJnnYr127W' => [ // Ladders
			'Features',
			'Buildables',
		],
		'PLbjDnnBIxiEr5Ks1V8bqkO3qCZeI796Xk' => [ // Pipes
			'Features',
			'Fluids',
		],
		'PLbjDnnBIxiErgJbWa-PCNwFesV_2X2VPp' => [ // Pumps
			'Features',
			'Fluids',
		],
		'PLbjDnnBIxiEomyjHFvEis2-_puqS6kLbl' => [ // Valves
			'Features',
			'Fluids',
		],
		'PLbjDnnBIxiErakQW-p-n78uCwn21Irv7v' => [ // Character Customisation
			'Features',
			'Possible Features', // to later be moved under Pioneer
		],
		'PLbjDnnBIxiEpZqJYXU-zIKp_92qIaMC3F' => [ // Equipment
			'Features',
		],
		'PLbjDnnBIxiEqIkJnV32EKANow9vvvZtgO' => [ // Chainsaw
			'Features',
			'Equipment',
		],
		'PLbjDnnBIxiEolThmLnFdhDVd_6guCwPXS' => [ // Fluids
			'Features',
		],
		'PLbjDnnBIxiEovsoSQPihKXk6g7ttQ95sI' => [ // Weather
			'Features', // to later be moved under environment
			'Possible Features', // to later be removed once under environment
		],
		'PLbjDnnBIxiEqkzbujtsOp2ySj671M8CR3' => [ // Crafting
			'Features',
		],
		'PLbjDnnBIxiEqOZ_KZZ80tAfzJmF5RNakK' => [ // Tiers
			'Features',
		],
		'PLbjDnnBIxiEqkVCgSUhCfhs0up_sRMN2L' => [ // Graphics
			'Technology',
		],
		'PLbjDnnBIxiEp5eh0gKJcMENUPjCcoHNVH' => [ // Unreal Engine
			'Technology',
		],
		'PLbjDnnBIxiErigMS9awrudaD0lSjyUN13' => [ // User Interface
			'Technology',
		],
		'PLbjDnnBIxiEoi-oa-SAwEGKc4YFrkMVvd' => [ // Aerial Travel
			'Features',
			'Unplanned Features',
		],
		'PLbjDnnBIxiEr97STV5beB_aCFM6jZaVDL' => [ // Vehicles
			'Features',
			'Transportation',
		],
		'PLbjDnnBIxiEralVcWNAwdbnP_tRGbAhFx' => [ // Game Modes
			'Features',
			'Possible Features',
		],
		'PLbjDnnBIxiErqg0B590-PblxF9Yu5aGnR' => [ // World Map
			'Environment',
		],
	],
];

$global_topic_hierarchy = array_map(
	static function (array $in) : array {
		uasort($in, static function (array $a, array $b) : int {
			return strnatcasecmp(implode('/', $a), implode('/', $b));
		});

		return $in;
	},
	$global_topic_hierarchy
);

$slugify = new Slugify();

foreach ($playlist_metadata as $json_file => $save_path) {
	$data = json_decode(file_get_contents($json_file), true);

	$basename = basename($save_path);

	$topic_hierarchy = $global_topic_hierarchy[$basename] ?? [];

	$topic_append = $global_topic_append[$basename] ?? [];

	$file_path = $save_path . '/../' . $basename . '/topics.md';

	file_put_contents($file_path, '');

	$data_by_date = [];

	$playlists_by_date = [];

	foreach ($data as $playlist_id => $filename) {
		$unix = strtotime(mb_substr($filename, 0, -3));
		$readable_date = date('F jS, Y', $unix);

		$data_by_date[$playlist_id] = [$unix, $readable_date];

		$playlists_by_date[$playlist_id] = ((($cache['playlists'] ?? [])[$playlist_id] ?? [])[2] ?? []);
	}

	$playlist_ids = array_keys(($cache['playlists'] ?? []));

	$topic_hierarchy_keys = array_keys($topic_hierarchy);

	usort($playlist_ids, static function (string $a, string $b) use ($topic_hierarchy, $topic_hierarchy_keys, $cache) : int {
		$a_chunks = $topic_hierarchy[$a] ?? [$cache['playlists'][$a][1]];
		$b_chunks = $topic_hierarchy[$b] ?? [$cache['playlists'][$b][1]];

		if ([''] === $a_chunks) {
			$a_chunks = [$cache['playlists'][$a][1]];
		}

		if ([''] === $b_chunks) {
			$b_chunks = [$cache['playlists'][$b][1]];
		}

		if (isset($topic_hierarchy[$a], $topic_hierarchy[$b])) {
			return array_search($a, $topic_hierarchy_keys) - array_search($b, $topic_hierarchy_keys);
		}

		return strnatcasecmp(
			implode(' > ', $a_chunks),
			implode(' > ', $b_chunks)
		);
	});

	foreach ($playlist_ids as $playlist_id) {
		if (isset($data[$playlist_id])) {
			continue;
		}

		$playlist_data = $cache['playlists'][$playlist_id];

		[, $playlist_title, $playlist_items] = $playlist_data;

		$slug = $topic_hierarchy[$playlist_id] ?? [];
		$slug[] = $playlist_title;

		$slug = array_filter($slug);

		$slug_count = count($slug);

		$slug_title = implode(' > ', $slug);

		$slug = array_map(
			[$slugify, 'slugify'],
			$slug
		);

		$slug_string = implode('/', $slug);

		$slug_path = $save_path . '/../' . $basename . '/topics/' . $slug_string . '.md';

		$playlist_items_data = [];

		foreach ($playlists_by_date as $other_playlist_id => $other_playlist_items) {
			foreach ($playlist_items as $video_id) {
				if (in_array($video_id, $other_playlist_items, true)) {
					if ( ! isset($playlist_items_data[$other_playlist_id])) {
						$playlist_items_data[$other_playlist_id] = [];
					}
					$playlist_items_data[$other_playlist_id][] = $video_id;
				}
			}
		}

		$slug_dir = dirname($slug_path);

		if ( ! is_dir($slug_dir)) {
			mkdir($slug_dir, 0755, true);
		}

		file_put_contents(
			$slug_path,
			(
				'[Topics](' . str_repeat('../', $slug_count) . 'topics.md)' .
				' > ' .
				$slug_title .
				"\n"
			)
		);

		file_put_contents(
			$file_path,
			'* [' . $slug_title . '](./topics/' . implode('/', $slug) . '.md)' . "\n",
			FILE_APPEND
		);

		foreach ($playlist_items_data as $playlist_id => $video_ids) {
			file_put_contents(
				$slug_path,
				(
					"\n" .
					'# ' .
					$data_by_date[$playlist_id][1] .
					' Livestream' .
					"\n"
				),
				FILE_APPEND
			);

			foreach ($video_ids as $video_id) {
				file_put_contents(
					$slug_path,
					(
						'* ' .
						$cache['playlistItems'][$video_id][1] .
						' https://www.youtube.com/watch?' .
						http_build_query([
							'v' => $video_id,
						]) .
						"\n"
					),
					FILE_APPEND
				);
			}
		}

		if (isset($topic_append[$slug_string . '.md'])) {
			file_put_contents($slug_path, "\n", FILE_APPEND);

			foreach ($topic_append[$slug_string . '.md'] as $append_this) {
				file_put_contents(
					$slug_path,
					$append_this . "\n",
					FILE_APPEND
				);
			}
		}
	}
}

foreach ($playlist_metadata as $json_file => $save_path) {
	$data = json_decode(file_get_contents($json_file), true);

	$basename = basename($save_path);

	$file_path = $save_path . '/../' . $basename . '.md';

	file_put_contents($file_path, '# Archives ' . "\n");

	if (is_file($save_path . '/FAQ.md')) {
		file_put_contents(
			$file_path,
			sprintf('* [FAQ](%s/FAQ.md)' . "\n", $basename)
		);
	}

	if (is_file($save_path . '/topics.md')) {
		file_put_contents(
			$file_path,
			sprintf('* [Topics](%s/topics.md)' . "\n", $basename)
		);
	}

	file_put_contents($file_path, '# Archives By Date' . "\n", FILE_APPEND);

	foreach (($index_prefill[$basename] ?? []) as $prefill_line) {
		file_put_contents($file_path, $prefill_line . "\n", FILE_APPEND);
	}

	$grouped = [];

	$sortable = [];

	foreach ($data as $filename) {
		$unix = strtotime(mb_substr($filename, 0, -3));
		$readable_month = date('F Y', $unix);
		$readable_date = date('F jS, Y', $unix);

		if ( ! isset($grouped[$readable_month])) {
			$grouped[$readable_month] = [];
			$sortable[$readable_month] = strtotime(date('Y-m-01', $unix));
		}

		$grouped[$readable_month][] = [$readable_date, $filename, $unix];
	}

	$grouped = array_map(
		static function (array $month) : array {
			usort(
				$month,
				static function (array $a, array $b) : int {
					return $a[2] - $b[2];
				}
			);

			return $month;
		},
		$grouped
	);

	uasort($sortable, static function(int $a, int $b) : int {
		return $a - $b;
	});

	foreach (array_keys($sortable) as $readable_month) {
		file_put_contents(
			$file_path,
			sprintf("\n" . '## %s' . "\n", $readable_month),
			FILE_APPEND
		);

		foreach ($grouped[$readable_month] as $line_data) {
			[$readable_date, $filename] = $line_data;

			file_put_contents(
				$file_path,
				sprintf(
					'* [%s](%s/%s)' . "\n",
					$readable_date,
					$basename,
					$filename
				),
				FILE_APPEND
			);
		}
	}
}
