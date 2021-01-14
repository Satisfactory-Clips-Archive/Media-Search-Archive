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
use Google_Service_YouTube_Playlist;
use Google_Service_YouTube_PlaylistItem;
use Google_Service_YouTube_PlaylistItemListResponse;
use Google_Service_YouTube_PlaylistListResponse;
use Google_Service_YouTube_PlaylistSnippet;
use Google_Service_YouTube_ResourceId;
use Google_Service_YouTube_Video;
use Google_Service_YouTube_VideoSnippet;
use Google_Service_YouTube_VideoListResponse;
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

	/** @var Google_Service_YouTube_PlaylistItemListResponse */
	$response = $service->playlistItems->listPlaylistItems(
		implode(',', [
			'id',
			'snippet',
			'contentDetails',
		]),
		$args
	);

	/** @var iterable<Google_Service_YouTube_PlaylistItem> */
	$response_items = $response->items;

	foreach ($response_items as $video) {
		/** @var Google_Service_YouTube_VideoSnippet */
		$video_snippet = $video->snippet;

		/** @var Google_Service_YouTube_ResourceId */
		$video_snippet_resourceId = $video_snippet->resourceId;

		$video_id = $video_snippet_resourceId->videoId;

		if (
			! isset($cache['playlistItems'][$video_id])
			|| $cache['playlistItems'][$video_id][0] !== $video->etag
		) {
			/** @var Google_Service_YouTube_VideoListResponse */
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
				/**
				 * @var array{0:object{
				 *	snippet:Google_Service_YouTube_VideoSnippet
				 * }}
				 */
				$tag_response_items = $tag_response->items;

				if (isset($tag_response_items[0]->snippet->tags)) {
					$cache['videoTags'][$video_id] = [
						$tag_response->etag,
						$tag_response_items[0]->snippet->tags,
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
				$video_snippet->title,
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

	/** @var Google_Service_YouTube_PlaylistListResponse */
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
		/** @var array{0:Google_Service_YouTube_Playlist} */
		$response_items = $response->items;

		/** @var Google_Service_YouTube_PlaylistSnippet */
		$playlist_snippet = $response_items[0]->snippet;

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
			$playlist_snippet->title,
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

	/** @var Google_Service_YouTube_PlaylistListResponse */
	$response = $service->playlists->listPlaylists(
		'id,snippet',
		$args
	);

	/** @var list<Google_Service_YouTube_Playlist> */
	$response_items = $response->items;

	foreach ($response_items as $playlist) {
		if ( ! isset($playlists[$playlist->id])) {
			/** @var Google_Service_YouTube_PlaylistSnippet */
			$playlist_snippet = $playlist->snippet;

			$other_playlists_on_channel[$playlist->id] = [
				$playlist_snippet->title,
				[],
			];

			/** @var Google_Service_YouTube_PlaylistListResponse */
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
					$playlist_snippet->title,
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

$video_playlists = [];

foreach ($cache['playlists'] as $playlist_id => $data) {
	[,, $video_ids] = $data;

	foreach ($video_ids as $video_id) {
		if ( ! isset($video_playlists[$video_id])) {
			$video_playlists[$video_id] = [];
		}

		$video_playlists[$video_id][] = $playlist_id;
	}
}

foreach (array_keys($playlists) as $playlist_id) {
	$video_ids = $cache['playlists'][$playlist_id][2];

	usort($video_ids, static function (string $a, string $b) use($cache) : int {
		return strnatcasecmp(
			$cache['playlistItems'][$a][1],
			$cache['playlistItems'][$b][1]
		);
	});

	$content_arrays = [
		'Related answer clips' => [],
		'Single video clips' => [],
	];

	$title_unix =
			(int) strtotime(
				mb_substr(
					basename($playlists[$playlist_id]),
					0,
					-3
				)
	);

	$title = (
		date(
			'F jS, Y',
			$title_unix
		) .
		' Livestream clips (non-exhaustive)' .
		''
	);

	file_put_contents(
		$playlists[$playlist_id],
		(
			'---' . "\n"
			. sprintf('title: "%s"' . "\n", $title)
			. sprintf('date: "%s"' . "\n", date('Y-m-d', $title_unix))
			. 'layout: livestream' . "\n"
			. '---' . "\n"
			. $title
			. "\n"
		)
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

/** @var array{satisfactory:array<string, list<string>>} */
$global_topic_append = json_decode(
	file_get_contents(__DIR__ . '/global-topic-append.json'),
	true
);

require_once(__DIR__ . '/global-topic-hierarchy.php');

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

$topics_json = [];
$playlist_topic_strings = [];

foreach ($global_topic_hierarchy['satisfactory'] as $playlist_id => $hierarchy) {
	$slug = $hierarchy;

	$playlist_data = $cache['playlists'][$playlist_id];

	[, $playlist_title, $playlist_items] = $playlist_data;

	if (($slug[0] ?? '') !== $playlist_title) {
		$slug[] = $playlist_title;
	}

	$slug = array_values(array_filter(array_filter($slug, 'is_string')));

	$slugged = array_map(
		[$slugify, 'slugify'],
		$slug
	);

	$playlist_topic_strings[$playlist_id] = implode('/', $slugged);

	while(count($slug) > 0) {
		$slug_string = implode('/', $slugged);

		$topics_json[$slug_string] = $slug;

		array_pop($slug);
		array_pop($slugged);
	}
}

ksort($topics_json);

file_put_contents(__DIR__ . '/topics-satisfactory.json', json_encode($topics_json, JSON_PRETTY_PRINT));

if ($transcriptions) {
	$checked = 0;

	foreach(array_keys($playlists) as $playlist_id) {
		foreach($cache['playlists'][$playlist_id][2] as $video_id) {
			$transcriptions_file = (
				__DIR__ .
				'/../coffeestainstudiosdevs/satisfactory/transcriptions/yt-' .
				$video_id .
				'.md'
			);

			$caption_lines = captions($video_id);

			if (count($caption_lines) < 1) {
				echo 'skipping captions for ', $video_id, "\n";

				continue;
			}

			$date = mb_substr(basename($playlists[$playlist_id]), 0, -3);

			file_put_contents(
				$transcriptions_file,
				(
					'---' . "\n"
					. sprintf(
						'title: "%s"' . "\n",
						(
							date('F jS, Y', (int) strtotime($date))
							. ' Livestream '
							. str_replace(
							'"',
							'\\"',
							$cache['playlistItems'][$video_id][1]
						)
						)
					)
					. sprintf(
						'date: "%s"' . "\n",
						date('Y-m-d', (int) strtotime($date))
					)
					. 'layout: transcript' . "\n"
					. sprintf(
						'topics:' . "\n" . '    - "%s"' . "\n",
						implode('"' . "\n" . '    - "', array_map(
							static function (
								string $playlist_id
							) use (
								$playlist_topic_strings
							) {
								return $playlist_topic_strings[
									$playlist_id
								];
							},
							array_filter(
								$video_playlists[$video_id],
								static function (
									string $playlist_id
								) use ($playlist_topic_strings) : bool {
									return isset(
										$playlist_topic_strings[
											$playlist_id
										]
									);
								}
							)
						))
					)
					. '---' . "\n"
					. '# [' . date('F jS, Y', (int) strtotime($date)) .
					' Livestream](../' . $date . '.md)' .
					"\n" .
					'## ' . $cache['playlistItems'][$video_id][1] .
					"\n" .
					(
						'https://www.youtube.com/watch?' .
						http_build_query([
							'v' => $video_id,
						])
					) .
					''
					. "\n\n"
					. '### Topics' . "\n"
					. implode("\n", array_map(
						static function (
							string $playlist_id
						) use (
							$topics_json,
							$playlist_topic_strings
						) {
							return (
								'* ['
								. implode(' > ', $topics_json[$playlist_topic_strings[
									$playlist_id
								]])
								. '](../topics/'
								. $playlist_topic_strings[
									$playlist_id
								]
								. '.md)'
							);
						},
						array_filter(
							$video_playlists[$video_id],
							static function (
								string $playlist_id
							) use ($playlist_topic_strings) : bool {
								return isset(
									$playlist_topic_strings[
										$playlist_id
									]
								);
							}
						)
					))
					. "\n\n"
					. '### Transcript' . "\n"
					. '' .
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

foreach (array_keys($playlist_metadata) as $metadata_path) {
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

file_put_contents(
	$faq_filepath,
	(
		'---' . "\n"
		. 'title: "Q&A Clips Archive - Frequently Asked Questions"' . "\n"
		. 'date: Last Modified' . "\n"
		. '---' . "\n"
	)
);

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
		'July 2020' => [
			[
				'July 8th, 2020',
				'2020-07-08.md',
			],
			[
				'July 21st, 2020',
				'2020-07-21.md',
			],
			[
				'July 28th, 2020',
				'2020-07-28.md',
			],
		],
		'August 2020' => [
			[
				'August 11th, 2020',
				'2020-08-11.md',
			],
			[
				'August 18th, 2020',
				'2020-08-18.md',
			],
			[
				'August 25th, 2020',
				'2020-08-25.md',
			],
		],
	],
];

foreach ($playlist_metadata as $json_file => $save_path) {
	$categorised = [];

	$data = json_decode(file_get_contents($json_file), true);

	$basename = basename($save_path);

	$topic_hierarchy = $global_topic_hierarchy[$basename] ?? [];

	$topic_append = $global_topic_append[$basename] ?? [];

	$file_path = $save_path . '/../' . $basename . '/topics.md';

	$data_by_date = [];

	$playlists_by_date = [];

	foreach ($data as $playlist_id => $filename) {
		$unix = strtotime(mb_substr($filename, 0, -3));
		$readable_date = date('F jS, Y', $unix);

		$data_by_date[$playlist_id] = [$unix, $readable_date];

		$playlists_by_date[$playlist_id] = ((($cache['playlists'] ?? [])[$playlist_id] ?? [])[2] ?? []);
	}

	$playlist_ids = array_keys(($cache['playlists'] ?? []));

	foreach ($playlist_ids as $playlist_id) {
		if (isset($data[$playlist_id])) {
			continue;
		}

		$playlist_data = $cache['playlists'][$playlist_id];

		[, $playlist_title, $playlist_items] = $playlist_data;

		$slug = $topic_hierarchy[$playlist_id] ?? [];

		$categorised_dest = & $categorised;

		foreach ($slug as $slug_part) {
			if (is_int($slug_part)) {
				continue;
			}

			if ( ! isset($categorised_dest[$slug_part])) {
				$categorised_dest[$slug_part] = [];
			}

			$categorised_dest = & $categorised_dest[$slug_part];
		}

		$categorised_dest[] = $playlist_id;

		if (($slug[0] ?? '') !== $playlist_title) {
			$slug[] = $playlist_title;
		}

		$slug = array_filter(array_filter($slug, 'is_string'));

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
				'---' . "\n"
				. sprintf('title: "%s"' . "\n", $slug_title)
				. 'date: Last Modified' . "\n"
				. '---' . "\n"
				. '[Topics](' . str_repeat('../', $slug_count) . 'topics.md)' .
				' > ' .
				$slug_title .
				"\n"
			)
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
						'* ['
						. '' .
						$cache['playlistItems'][$video_id][1] .
						''
						. ']('
						. str_repeat('../', $slug_count)
						. 'transcriptions/yt-'
						. $video_id
						. '.md)'
						. '' .
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

	$decategorise = function (
		array $to_flatten,
		array $pending = [],
		$depth = 0
	) use (
		$topic_hierarchy,
		$cache,
		$slugify,
		& $decategorise
	) : array {
		ksort($to_flatten);

		$but_first = array_filter(
			$to_flatten,
			'is_int',
			ARRAY_FILTER_USE_KEY
		);

		$but_first = array_combine($but_first, array_map(
			static function (string $playlist_id) use ($cache) : string {
				return $cache['playlists'][$playlist_id][1];
			},
			$but_first
		));
		$and_then = array_filter(
			$to_flatten,
			'is_string',
			ARRAY_FILTER_USE_KEY
		);

		asort($but_first);

		foreach ($but_first as $playlist_id => $playlist_title) {
			$slug = $topic_hierarchy[$playlist_id] ?? [];

			$slug = array_filter($slug, 'is_string');

			if (($slug[0] ?? '') !== $playlist_title) {
				$slug[] = $playlist_title;
			}

			$slug = array_map(
				[$slugify, 'slugify'],
				$slug
			);

			$pending[] = '* [' . $playlist_title . '](./topics/' . implode('/', $slug) . '.md)';
		}

		if (count($and_then) > 0) {
			foreach ($and_then as $section => $subsection) {
				$pending[] = '';
				$pending[] = str_repeat('#', $depth + 1) . ' ' . $section;

				$pending = $decategorise($subsection, $pending, $depth + 1);
			}
		}

		return $pending;
	};

	file_put_contents(
		$file_path,
		(
			'---' . "\n"
			. 'title: "Q&A Clips Archive - Browse Topics"' . "\n"
			. 'date: Last Modified' . "\n"
			. '---' . "\n"
		)
	);

	foreach ($decategorise($categorised) as $line) {
		file_put_contents($file_path, $line . "\n", FILE_APPEND);
	}
}

foreach ($playlist_metadata as $json_file => $save_path) {
	$data = json_decode(file_get_contents($json_file), true);

	$basename = basename($save_path);

	$file_path = $save_path . '/../' . $basename . '/index.md';

	file_put_contents($file_path, '# Archives' . "\n");

	file_put_contents(
		$file_path,
		(
			'---' . "\n"
			. 'title: Q&A Clips Archive - Browse' . "\n"
			. 'date: Last Modified' . "\n"
			. 'layout: index' . "\n"
			. '---' . "\n"
			. '# Archives' . "\n"
			. '* [FAQ](FAQ.md)' . "\n"
			. '* [Topics](topics.md)' . "\n"
		)
	);

	file_put_contents($file_path, "\n", FILE_APPEND);
	file_put_contents($file_path, '# Archives By Date' . "\n", FILE_APPEND);

	$grouped = [];

	$sortable = [];

	foreach ($index_prefill[$basename] as $readable_month => $prefilled_data) {
		foreach ($prefilled_data as $prefilled_data_row) {
			[$readable_date, $filename] = $prefilled_data_row;
			$unix = strtotime(mb_substr($filename, 0, -3));

			if ( ! isset($grouped[$readable_month])) {
				$grouped[$readable_month] = [];
				$sortable[$readable_month] = strtotime(date('Y-m-01', $unix));
			}

			$grouped[$readable_month][] = [$readable_date, $filename, $unix];
		}
	}

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
					'* [%s](%s)' . "\n",
					$readable_date,
					$filename
				),
				FILE_APPEND
			);
		}
	}
}
