<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\TwitchClipNotes;

use function array_filter;
use function basename;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_string;
use function json_decode;
use function json_encode;
use const JSON_PRETTY_PRINT;
use function mb_substr;
use function preg_match;
use RuntimeException;
use function strnatcasecmp;
use function strtotime;

$cache = json_decode(
	file_get_contents(__DIR__ . '/cache-injection.json'),
	true
);

/**
 * @var array{
 *	playlists:array<
 *		string,
 *		array{
 *			0:string,
 *			1:string,
 *			2:list<string>
 *		}
 *	}
 */
$main = json_decode(
	file_get_contents(__DIR__ . '/cache.json'),
	true
);

require_once(__DIR__ . '/global-topic-hierarchy.php');

$global_topic_hierarchy = array_merge_recursive(
	$global_topic_hierarchy,
	$injected_global_topic_hierarchy
);

/**
 * @param array{
 *	playlists:array<
 *		string,
 *		array{
 *			0:string,
 *			1:string,
 *			2:list<string>
 *		}
 *	} $main
 */
function try_find_main_playlist(
	string $playlist_name,
	array $main
) : ? string {
	foreach ($main['playlists'] as $playlist_id => $data) {
		if ($playlist_name === $data[1]) {
			return $playlist_id;
		}
	}

	return null;
}

/**
 * @return array{0:string, 1:string}
 */
function determine_playlist_id(
	string $playlist_name,
	array $cache,
	array $main,
	array $global_topic_hierarchy
) : array {

	/** @var string|null */
	$maybe_playlist_id = null;
	$friendly = $playlist_name;

	if (\preg_match('/^\d{4,}\-\d{2}\-\d{2}$/', $playlist_name)) {
		$unix = strtotime($playlist_name);

		if (false === $unix) {
			throw new RuntimeException(
				'Invalid date found!'
			);
		}

		$friendly = date('F jS, Y', $unix) . ' Livestream';

		$maybe_playlist_id = try_find_main_playlist($friendly, $main);

		if (null === $maybe_playlist_id) {
			$maybe_playlist_id = $playlist_name;
		}
	} else {
		$maybe_playlist_id = try_find_main_playlist($playlist_name, $main);

		if (null === $maybe_playlist_id) {
			$maybe_playlist_id = $playlist_name;
		}

		$friendly = $playlist_name;
	}

	if (null === $maybe_playlist_id) {
		throw new RuntimeException(
			'Could not find playlist id!'
		);
	}

	return [$maybe_playlist_id, $friendly];
}

function add_playlist(
	string $playlist_name,
	array $cache,
	array $main,
	array $global_topic_hierarchy
) : array {
	[$playlist_id, $friendly] = determine_playlist_id(
		$playlist_name,
		$cache,
		$main,
		$global_topic_hierarchy
	);

	if ( ! isset($cache['playlists'][$playlist_id])) {
		$cache['playlists'][$playlist_id] = [
			'',
			$friendly,
			[],
		];
	}

	return $cache;
}

function add_twitch_video(
	string $title,
	string $url,
	bool $faq,
	string $playlist_id,
	array $cache
) : array {
	if (
		! preg_match('/^https:\/\/clips\.twitch\.tv\/(.+)$/', $url, $matches)
	) {
		throw new RuntimeException(
			'Could not find twitch clip id!'
		);
	} elseif ( ! isset($cache['playlists'][$playlist_id])) {
		throw new RuntimeException(
			'Could not find playlist destination!'
		);
	}

	$id = 'tc-' . $matches[1];

	if (! isset($cache['playlistItems'][$id])) {
		$cache['playlistItems'][$id] = ['', ''];
	}

	$cache['playlistItems'][$id][1] = $title;

	if ( ! in_array($id, $cache['playlists'][$playlist_id][2], true)) {
		$cache['playlists'][$playlist_id][2][] = $id;
	}

	if ( ! isset($cache['videoTags'][$id])) {
		$cache['videoTags'][$id] = ['', []];
	}

	if ($faq) {
		if ( ! in_array('faq', $cache['videoTags'][$id][1], true)) {
			$cache['videoTags'][$id][1][] = 'faq';
		}
	}

	return $cache;
}

function add_twitch_video_from_single_string(
	string $string,
	bool $faq,
	string $playlist_id,
	array $cache
) : array {
	if (
		! preg_match(
			'/^(.+)(https:\/\/clips\.twitch\.tv\/.+)$/',
			$string,
			$matches
		)
	) {
		throw new RuntimeException('Could not find twitch clip title & url!');
	}

	return add_twitch_video(
		trim($matches[1]),
		trim($matches[2]),
		$faq,
		$playlist_id,
		$cache
	);
}

const youtube_single_string_regex = '/^(.+)(?:https:\/\/(?:(?:www\.)youtu(?:\.be\/|be\.com\/watch\/?\?v=))(.+))$/';
const youtube_url_regex = '/^https:\/\/(?:(?:www\.)youtu(?:\.be\/|be\.com\/watch\/?\?v=))(.+)$/';

function add_youtube_video(
	string $title,
	string $id,
	bool $faq,
	string $playlist_id,
	array $cache,
	array $main
) : array {
	if ( ! isset($cache['playlists'][$playlist_id])) {
		throw new RuntimeException(
			'Could not find playlist destination!'
		);
	}

	if (isset($main['playlistItems'][$id])) {
		if ($title !== $main['playlistItems'][$id][1]) {
			throw new RuntimeException(
				'Main cache title conflict!'
			);
		}
	}
	if (isset($main['videoTags'][$id])) {
		if ($faq !== in_array('faq', $main['videoTags'][$id][1], true)) {
			throw new RuntimeException(
				'Main cache FAQ mismatch!'
			);
		}
	}
	if (isset($main['playlists'][$playlist_id])) {
		if (
			! in_array(
				$id,
				$main['playlists'][$playlist_id][2],
				true
			)
		) {
			throw new RuntimeException(
				'Main cache playlist found but youtube video not present!'
			);
		}

		return $cache;
	}

	if ( ! in_array($id, $cache['playlists'][$playlist_id][2], true)) {
		$cache['playlists'][$playlist_id][2][] = $id;
	}
	if ( ! isset($cache['playlistItems'][$id])) {
		$cache['playlistItems'][$id] = ['', $title];
	}

	return $cache;
}

function add_youtube_video_from_single_string(
	string $string,
	bool $faq,
	string $playlist_id,
	array $cache,
	array $main
) : array {
	if (
		! preg_match(
			youtube_single_string_regex,
			$string,
			$matches
		)
	) {
		throw new RuntimeException('Could not find youtube clip title & url!');
	}

	return add_youtube_video(
		trim($matches[1]),
		trim($matches[2]),
		$faq,
		$playlist_id,
		$cache,
		$main
	);
}

$dated_glob = array_map(
	static function (string $path) : string {
		return mb_substr(basename($path), 0, -3);
	},
	glob(__DIR__ . '/../coffeestainstudiosdevs/satisfactory/20*-*-*.md')
);

foreach ($dated_glob as $date) {
	$cache = add_playlist($date, $cache, $main, $global_topic_hierarchy);
}

foreach ($global_topic_hierarchy['satisfactory'] as $playlist_id => $prefiltered_data) {
	$data = array_filter($prefiltered_data, 'is_string');

	foreach ($data as $topic_name) {
		$cache = add_playlist(
			$topic_name,
			$cache,
			$main,
			$global_topic_hierarchy
		);
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
			'ETA for Update 4? (Part 1) https://clips.twitch.tv/DeadPrettySaladMoreCowbell',
			'ETA for Update 4? (Part 2) https://clips.twitch.tv/SavageBenevolentEndiveChocolateRain',
			'ETA for Update 4? (Part 3) https://clips.twitch.tv/GoodSaltyPepperoniPunchTrees',
			'ETA for Update 4? (Part 4) https://clips.twitch.tv/UnsightlyApatheticHornetKreygasm',
			'ETA for Update 4? (Part 5) https://clips.twitch.tv/AmazingEagerGorillaHeyGuys',
			'Q&A: Will Gas be in Update 4? https://clips.twitch.tv/SpinelessSneakySalsifyNerfRedBlaster',
			'Q&A: Will there be new items coming to the AWESOME Shop between now and Update 4? https://clips.twitch.tv/PerfectNurturingTrollRiPepperonis',
			'Snutt Talk: Minor stuff before Update 4 https://clips.twitch.tv/FrozenEndearingCodEleGiggle',
			'Q&A: Update 4, just a quality-of-life thing? https://clips.twitch.tv/GleamingCheerfulWatercressRaccAttack',
			'Q&A: Please tell me Update 4 will use S.A.M. Ore https://clips.twitch.tv/ArtisticGlutenFreeSpindleDxAbomb',
			'Q&A: When will the next patch even get released? https://clips.twitch.tv/BlitheKitschySnoodTwitchRaid',
		],
	],
	'Tiers' => [
		'2020-07-28' => [
			'Jace Talk: Content & Tiers https://clips.twitch.tv/SwissFurryPlumPlanking',
		],
	],
	'Tier 7' => [
		'2020-08-18' => [
			'Q&A: Might we see additions to Tier 7 before the end of the year? https://clips.twitch.tv/DoubtfulNaiveCroquettePeoplesChamp',
		],
		'2020-09-08' => [
			'Q&A: What additions to Tier 7 might be coming & when ? https://www.youtube.com/watch?v=lGbJwWh5W_I',
		],
	],
	'Tier 8' => [
		'2020-08-18' => [
			'Q&A: Tier 8 before 1.0? https://clips.twitch.tv/AgreeableTentativeBeeCurseLit',
			'Q&A: What\'s in Tier 8? (part 1) https://clips.twitch.tv/RelievedRelievedCroissantMingLee',
			'Q&A: What\'s in Tier 8? (part 2) https://clips.twitch.tv/AwkwardBloodyNightingaleShadyLulu',
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
		'2020-07-28' => [
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
			'Q&A: Underwater biome when? https://clips.twitch.tv/HonorableCautiousDonutStoneLightning',
			'Q&A: Terraforming? https://clips.twitch.tv/CourageousTardyLarkShazBotstix',
			'Q&A: Will you guys be hiding more stuff throughout the world for the Story Mode? https://clips.twitch.tv/VastAlertBadgerTF2John',
			'Q&A: Why can\'t we explode some stones in the map? https://clips.twitch.tv/HeartlessAntsyMelonCharlieBitMe',
			'Q&A: Like a new map for Satisfactory? https://clips.twitch.tv/ArtisticAthleticCroissantRalpherZ',
			'Q&A: How about procedural maps? https://clips.twitch.tv/ProtectiveWonderfulFrogVoteYea',
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
		'2020-07-28' => [
			'Q&A: Coffee Mug? https://clips.twitch.tv/SpunkyHyperWasabi4Head',
		],
		'2020-08-11' => [
			'Q&A: gravity-defying FICSIT-branded coffee https://clips.twitch.tv/TalentedIntelligentGazelleFunRun',
			'Q&A: Lizard Doggo Plushies https://clips.twitch.tv/TolerantPunchyNewtJKanStyle',
			'Q&A: Doggo Toys? https://clips.twitch.tv/FlirtyScarySushiYouWHY',
			'Q&A: FICSIT employee t-shirt? https://clips.twitch.tv/SuspiciousAlluringDolphinThunBeast',
			'Q&A: How much will the Merch cost? https://clips.twitch.tv/SmallSullenTomatoTheThing',
			'Q&A: How much will the Merch cost? (part 2) https://clips.twitch.tv/EnticingPricklyWitchM4xHeh',
			'Q&A: Figurine? https://clips.twitch.tv/ShortKathishAardvarkUnSane',
			'Q&A: zip-up hoodie? https://clips.twitch.tv/SpoopyCrowdedOctopusTBTacoLeft',
			'Q&A: FICSIT Masks/Helmets https://clips.twitch.tv/ClearColdbloodedCakeVoHiYo',
			'Q&A: remote-control Factory Cart https://clips.twitch.tv/MoistSmellyReubenDoubleRainbow',
			'Q&A: t-shirt material? https://clips.twitch.tv/ComfortableAltruisticHerringDansGame',
			'Jace Talk: Additional Merch, Launch & later Merch https://clips.twitch.tv/EndearingBraveSeahorseBloodTrail',
			'Jace Merch Talk: US vs. EU Merch Warehousing https://clips.twitch.tv/ColdStormySalsifyArgieB8',
		],
		'2020-08-18' => [
			'Q&A: Is there a Merch Store? https://clips.twitch.tv/CleanCarefulMoonAMPEnergyCherry',
			'Q&A: When will have Merch? https://clips.twitch.tv/FunOriginalPistachioNerfRedBlaster',
		],
	],
	'Merch Prototypes' => [
		'2020-08-11' => [
			'Pioneer Helmet t-shirt (black): https://clips.twitch.tv/PunchyGloriousMoonPanicBasket',
			'FICSIT employee hoodie (light grey) https://clips.twitch.tv/FaithfulFrigidFinchKappaPride',
			'Fine Art by Jace Varlet https://clips.twitch.tv/CrispyAstuteBeeNerfRedBlaster',
		],
	],
	'FICSIT Cup Prototypes' => [
		'2020-08-11' => [
			'Jace Talk: Launch & FICSIT Cup https://clips.twitch.tv/AmazingOriginalMeerkatArgieB8',
			'Jace Talk: FICSIT Cup https://clips.twitch.tv/InquisitiveCooperativeMallardWholeWheat',
			'Q&A: FICSIT Cup material? https://clips.twitch.tv/SarcasticWildBeanRitzMitz',
		],
	],
];

foreach ($preloaded_faq as $topic => $topic_data) {
	$cache = add_playlist(
		$topic,
		$cache,
		$main,
		$global_topic_hierarchy
	);

	[$topic_playlist_id] = determine_playlist_id(
		$topic,
		$cache,
		$main,
		$global_topic_hierarchy
	);

	foreach ($topic_data as $topic_date => $dated_data) {
		$cache = add_playlist(
			$topic_date,
			$cache,
			$main,
			$global_topic_hierarchy
		);

		[$topic_date_playlist_id] = determine_playlist_id(
			$topic_date,
			$cache,
			$main,
			$global_topic_hierarchy
		);

		foreach ($dated_data as $dated_data_entry) {
			if (preg_match(youtube_single_string_regex, $dated_data_entry)) {
				$cache = add_youtube_video_from_single_string(
					$dated_data_entry,
					true,
					$topic_playlist_id,
					$cache,
					$main
				);
				$cache = add_youtube_video_from_single_string(
					$dated_data_entry,
					true,
					$topic_date_playlist_id,
					$cache,
					$main
				);
			} else {
				$cache = add_twitch_video_from_single_string(
					$dated_data_entry,
					true,
					$topic_playlist_id,
					$cache
				);
				$cache = add_twitch_video_from_single_string(
					$dated_data_entry,
					true,
					$topic_date_playlist_id,
					$cache
				);
			}
		}
	}
}

$from_markdown = [
	'2020-07-08' => [
		'Snutt & Jace Talk: not adding mass building tools into the vanilla game https://clips.twitch.tv/NimbleAgitatedPeanutNotLikeThis' => [
			'Mass Building',
			'Mods vs. Features',
		],
	],
	'2020-07-21' => [
		'Q&A: Puppies, Train Fix https://clips.twitch.tv/ColdBraveShieldSMOrc' => [
			'Creatures',
			'Trains',
		],
		'Q&A: How\'s the Merch Store coming along? https://clips.twitch.tv/OilySillySproutNotLikeThis' => [
			'Merch',
		],
		'Q&A: How about building underwater? https://clips.twitch.tv/NiceDreamyGarbageBuddhaBar' => [
			'Underwater',
		],
		'Q&A: What do you think about a game mode with weather effects doing damage or slowing machines? https://clips.twitch.tv/ProudArbitraryClintmullinsPeanutButterJellyTime' => [
			'Game Modes',
			'Weather Systems',
			'Buildings',
		],
		'Q&A: Why no mass building? https://clips.twitch.tv/SoftBovineArmadilloNerfRedBlaster' => [
			'Mass Building',
		],
	],
	'2020-07-28' => [
		'Q&A: update 4 will rethink power situation? https://clips.twitch.tv/ProudRockyInternTooSpicy' => [
			'Satisfactory Update 4',
			'Power Management',
		],
		'Q&A: Gas Manufacturing https://clips.twitch.tv/ThirstyJoyousSparrowSoBayed' => [
			'Gases',
		],
		'Q&A: Unreal Engine 5 https://clips.twitch.tv/PiliableZanyGrassFreakinStinkin' => [
			'Unreal Engine',
		],
		'Q&A: Will you be expanding on the survival aspect of the game? https://clips.twitch.tv/IntelligentBlatantOrangeBrokeBack' => [
			'Game Modes',
		],
		'Jace Talk: Content & Tiers https://clips.twitch.tv/SwissFurryPlumPlanking' => [
			'Tiers',
		],
		'Q&A: Signs & Planets https://clips.twitch.tv/ArtisticTrustworthyHamOSkomodo' => [
			'Signs',
		],
		'Q&A: More Wildlife? https://clips.twitch.tv/DirtyHilariousPancakeWow' => [
			'Creatures',
			'Crab Boss',
		],
		'Jace Talk: Flight & map size perception https://clips.twitch.tv/ElatedBlueNightingaleMau5' => [
			'Aerial Travel',
			'World Map',
		],
		'Q&A: Satisfactory Console Release https://clips.twitch.tv/FragileNimbleEggnogDatSheffy' => [
			'Console Release',
		],
		'Q&A: Dedicated Server cost https://clips.twitch.tv/ConfidentLittleSnood4Head' => [
			'Dedicated Servers',
		],
		'Q&A: Has Pipe Overflow been discussed? https://clips.twitch.tv/VainArtsyLeopardUncleNox' => [
			'Pipes',
			'Valves',
		],
		'Q&A: Coffee Mug? https://clips.twitch.tv/SpunkyHyperWasabi4Head' => [
			true, // indicates is a FAQ
			'FICSIT Cup Prototypes',
		],
	],
	'2020-08-11' => [
		'Q&A: Green Energy? https://clips.twitch.tv/BloodyIcyDragonflyStoneLightning' => [
			'Green Energy',
		],
		'Q&A: Gas Tanks? https://clips.twitch.tv/FitAlertTurtleDogFace' => [
			'Gases',
		],
		'Mods vs. Features (Part 1) https://clips.twitch.tv/ElegantKindPrariedogGrammarKing' => [
			'Mods vs. Features',
		],
		'Mods vs. Features (Part 2) https://clips.twitch.tv/NimbleFurryDumplingsBudBlast' => [
			'Mods vs. Features',
		],
		'Q&A: Randomly Generated Maps: https://clips.twitch.tv/OilyBloodyMangoFutureMan' => [
			'World Map',
			'Procedural Generation',
		],
		'Q&A: Do you plan to release a World Editor? https://clips.twitch.tv/AnnoyingImpartialGaurChefFrank' => [
			'World Map',
		],
		'Q&A: What about Weather systems? https://clips.twitch.tv/SilkyFurryCheetahMVGame' => [
			'Weather Systems',
		],
		'Q&A: Story / End-game? https://clips.twitch.tv/AmorphousVictoriousTrayPartyTime' => [
			'Story & Lore',
		],
		'Q&A: Sleep in-game? (Part 1) https://clips.twitch.tv/DaintyYummyLemurANELE' => [
			'The HUB',
		],
		'Q&A: Sleep in-game? (Part 2) https://clips.twitch.tv/PrettiestObedientLegItsBoshyTime' => [
			'The HUB',
		],
		'Q&A: Any plans to make vertical building easier? https://clips.twitch.tv/ImpartialHardSageBigBrother' => [
		],
		'Q&A: Elevators? https://clips.twitch.tv/HelpfulSuaveScallionPeanutButterJellyTime' => [
			true,
			'Buildings',
		],
		'Q&A: Floating Factories vs. Structural Supports https://clips.twitch.tv/GiftedSincereDillDoubleRainbow' => [
			'Foundations',
		],
		'Q&A: Will Drones be added to the game for aerial travel? https://clips.twitch.tv/CredulousWimpyMosquitoResidentSleeper' => [
			'Vehicles',
			'Aerial Travel',
		],
		'Q&A: First-person Vehicle Driving? https://clips.twitch.tv/ShinySilkyMelonGivePLZ' => [
			'Vehicles',
		],
		'Q&A: Hypertube Cannons - Bug or Feature? https://clips.twitch.tv/OilyPatientOtterTBTacoLeft' => [
			'Hyper Tubes',
		],
		'Q&A: Two-seated vehicles (Part 1) https://clips.twitch.tv/OilySourBeaverAMPEnergy' => [
			'Vehicles',
		],
		'Q&A: Two-seated vehicles (Part 2) https://clips.twitch.tv/CooperativeFurtiveWasabiOhMyDog' => [
			'Vehicles',
		],
		'Q&A: Are Dedicated Servers coming? https://clips.twitch.tv/BigDeadPhoneKappaWealth' => [
			'Dedicated Servers',
		],
		'Q&A: What\'s the hold-up on Dedicated Servers? https://clips.twitch.tv/ShinyAthleticCrocodileKappaPride' => [
			'Dedicated Servers',
		],
		'Jace Talk: Massive Bases, Multiplayer lag, and Dedicated Servers https://clips.twitch.tv/RealPrettiestKoalaBloodTrail' => [
			'Dedicated Servers',
			'Multiplayer',
		],
		'Q&A: Dedicated Servers, start building a community around that? https://clips.twitch.tv/EagerPeacefulMonkeyDoubleRainbow' => [
			'Dedicated Servers',
		],
		'Jace Talk: The Q&A Site (Part 1): https://clips.twitch.tv/BoxyZanyPancakeKeepo' => [
		],
		'Jace Talk: The Q&A Site (Part 2): https://clips.twitch.tv/RenownedQuaintBeaverAMPTropPunch' => [
		],
		'Jace Talk: The Q&A Site (Part 3): https://clips.twitch.tv/ZealousNastyCiderPeteZarollTie' => [
		],
		'Q&A: Next Update? https://clips.twitch.tv/CrunchyMistyAsparagus4Head' => [
			'Satisfactory Update 4',
		],
		'Pioneer Helmet t-shirt (black): https://clips.twitch.tv/PunchyGloriousMoonPanicBasket' => [
			'Merch Prototypes',
		],
		'FICSIT employee hoodie (light grey) https://clips.twitch.tv/FaithfulFrigidFinchKappaPride' => [
			'Merch Prototypes',
		],
		'Fine Art by Jace Varlet https://clips.twitch.tv/CrispyAstuteBeeNerfRedBlaster' => [
			'Merch Prototypes',
		],
		'Jace Talk: Launch & FICSIT Cup https://clips.twitch.tv/AmazingOriginalMeerkatArgieB8' => [
			'FICSIT Cup Prototypes',
		],
		'Jace Talk: FICSIT Cup https://clips.twitch.tv/InquisitiveCooperativeMallardWholeWheat' => [
			'FICSIT Cup Prototypes',
		],
		'Q&A: FICSIT Cup material? https://clips.twitch.tv/SarcasticWildBeanRitzMitz' => [
			'FICSIT Cup Prototypes',
		],
		'Q&A: gravity-defying FICSIT-branded coffee https://clips.twitch.tv/TalentedIntelligentGazelleFunRun' => [
			'Merch',
		],
		'Q&A: Lizard Doggo Plushies https://clips.twitch.tv/TolerantPunchyNewtJKanStyle' => [
			'Merch',
			'Lizard Doggo',
		],
		'Q&A: Doggo Toys? https://clips.twitch.tv/FlirtyScarySushiYouWHY' => [
			'Merch',
			'Lizard Doggo',
		],
		'Q&A: FICSIT employee t-shirt? https://clips.twitch.tv/SuspiciousAlluringDolphinThunBeast' => [
			'Merch',
		],
		'Q&A: How much will the Merch cost? (Part 1) https://clips.twitch.tv/SmallSullenTomatoTheThing' => [
			'Merch',
		],
		'Q&A: How much will the Merch cost? (Part 2) https://clips.twitch.tv/EnticingPricklyWitchM4xHeh' => [
			'Merch',
		],
		'Q&A: Figurine? https://clips.twitch.tv/ShortKathishAardvarkUnSane' => [
			'Merch',
		],
		'Q&A: zip-up hoodie? https://clips.twitch.tv/SpoopyCrowdedOctopusTBTacoLeft' => [
			'Merch',
		],
		'Q&A: FICSIT Masks/Helmets https://clips.twitch.tv/ClearColdbloodedCakeVoHiYo' => [
			'Merch',
		],
		'Q&A: remote-control Factory Cart https://clips.twitch.tv/MoistSmellyReubenDoubleRainbow' => [
			'Merch',
			'Factory Cart',
		],
		'Q&A: t-shirt material? https://clips.twitch.tv/ComfortableAltruisticHerringDansGame' => [
			'Merch',
		],
		'Jace Talk: Additional Merch, Launch & later Merch https://clips.twitch.tv/EndearingBraveSeahorseBloodTrail' => [
			'Merch',
		],
		'Jace Merch Talk: US vs. EU Merch Warehousing https://clips.twitch.tv/ColdStormySalsifyArgieB8' => [
			'Merch',
		],
		'Q&A: Anything inside the HUB where the MAM used to be? https://clips.twitch.tv/RespectfulDreamyHabaneroMrDestructoid' => [
			'The HUB',
		],
		'Q&A: FICSIT Pioneer gender confirmed? https://clips.twitch.tv/TriangularLongOctopusOneHand' => [
			'Pioneer',
		],
		'Q&A: Do you have a QA department? https://clips.twitch.tv/WanderingWonderfulTitanTBCheesePull' => [
			'Coffee Stainers',
		],
		'Q&A: Any plans for Difficulty Levels? https://clips.twitch.tv/GrotesqueDaintyRamenGivePLZ' => [
			'Game Modes',
		],
		'Q&A: Do you have Goats in Satisfactory? https://clips.twitch.tv/FurryTalentedCrowBleedPurple' => [
			'Creatures',
		],
		'Q&A: New Apartment? https://clips.twitch.tv/CorrectAdorableDinosaurWoofer' => [
			'Jace',
		],
	],
	'2020-08-18' => [
		'Mods vs. Features (Part 1) https://clips.twitch.tv/ShakingCredulousGalagoCopyThis' => [
			'Mods vs. Features',
		],
		'Mods vs. Features (Part 2) https://clips.twitch.tv/OriginalDifficultTeaKevinTurtle' => [
			'Mods vs. Features',
		],
		'Mods vs. Features (Part 3) https://clips.twitch.tv/CorrectAlertEggplantPJSalt' => [
			'Mods vs. Features',
		],
		'Mods vs. Features (Part 4) https://clips.twitch.tv/ShakingNastyJaguarGrammarKing' => [
			'Mods vs. Features',
		],
		'Build Limit (Part 1): https://clips.twitch.tv/SplendidAffluentVampireNotLikeThis' => [
			true, // FAQ
			'Unreal Engine',
		],
		'Build Limit (Part 2): https://clips.twitch.tv/UnusualExquisiteKuduDendiFace' => [
			true, // FAQ
			'Unreal Engine',
		],
		'Build Limit (Part 3): https://clips.twitch.tv/SullenColdbloodedDiscEagleEye' => [
			true, // FAQ
			'Unreal Engine',
		],
		'Build Limit (Part 4): https://clips.twitch.tv/BlitheEnergeticEelPRChase' => [
			true, // FAQ
			'Unreal Engine',
		],
		'Build Limit (Part 5): https://clips.twitch.tv/GiantGeniusGooseCclamChamp' => [
			true, // FAQ
			'Unreal Engine',
		],
		'Build Limit (Part 6): https://clips.twitch.tv/BoxySmallAsparagusSmoocherZ' => [
			true, // FAQ
			'Unreal Engine',
		],
		'Q&A: Do you plan to make other resources beyond S.A.M. Ore? https://clips.twitch.tv/InventiveBillowingEggPMSTwin' => [
			'S.A.M. Ore',
			'Resources',
		],
		'Q&A: S.A.M. Ore uses? https://clips.twitch.tv/BovineDistinctOrangeRiPepperonis' => [
			'S.A.M. Ore',
		],
		'Q&A: Coffee Cups are made out of S.A.M. Ore? https://clips.twitch.tv/SuspiciousImportantOryxSquadGoals' => [
			'S.A.M. Ore',
		],
		'Multiplayer desync issues (Part 1) https://clips.twitch.tv/AliveHomelySandwichGivePLZ' => [
			true, // FAQ
			'Multiplayer',
		],
		'Multiplayer desync issues (Part 2) https://clips.twitch.tv/VastScrumptiousEyeballLeeroyJenkins' => [
			true, // FAQ
			'Multiplayer',
		],
		'Multiplayer desync issues (Part 3) https://clips.twitch.tv/TsundereHandsomeBottleCharlietheUnicorn' => [
			true, // FAQ
			'Multiplayer',
		],
		'Q&A: When will multiplayer reach 128 so we can build a tower? (Part 1) https://clips.twitch.tv/OpenIntelligentPizzaYouWHY' => [
			'Multiplayer',
		],
		'Q&A: When will multiplayer reach 128 so we can build a tower? (Part 2) https://clips.twitch.tv/TardyBitterGnatDatSheffy' => [
			'Multiplayer',
		],
		'Q&A: When will multiplayer reach 128 so we can build a tower? (Part 3) https://clips.twitch.tv/SavagePopularBatChocolateRain' => [
			'Multiplayer',
		],
		'Q&A: Session Privacy / Join Button not working? (Part 1) https://clips.twitch.tv/PolishedThirstyDinosaurOhMyDog' => [
			'Multiplayer',
		],
		'Q&A: Session Privacy / Join Button not working? (Part 2) https://clips.twitch.tv/CrackyBombasticEggUWot' => [
			'Multiplayer',
		],

		'Q&A: Might we see additions to Tier 7 before the end of the year? https://clips.twitch.tv/DoubtfulNaiveCroquettePeoplesChamp' => [
			'Tier 7',
		],
		'Q&A: Tier 8 before 1.0? https://clips.twitch.tv/AgreeableTentativeBeeCurseLit' => [
			'Tier 8',
			'Satisfactory 1.0',
			'Satisfactory Update 4',
		],
		'Q&A: What\'s in Tier 8? (part 1) https://clips.twitch.tv/RelievedRelievedCroissantMingLee' => [
			'Tier 8',
			'Satisfactory Update 4',
		],
		'Q&A: What\'s in Tier 8? (part 2) https://clips.twitch.tv/AwkwardBloodyNightingaleShadyLulu' => [
			'Tier 8',
			'Satisfactory Update 4',
		],
		'Q&A: Is there a Merch Store? https://clips.twitch.tv/CleanCarefulMoonAMPEnergyCherry' => [
			'Merch',
		],
		'Q&A: When will have Merch? https://clips.twitch.tv/FunOriginalPistachioNerfRedBlaster' => [
			'Merch',
		],
		'Q&A: When is Update 4 pencilled for? https://clips.twitch.tv/RelievedTawdryEelDogFace' => [
			'Satisfactory Update 4',
		],
		'Snutt Talk: There\'s also discussions about how we release Update 4 https://clips.twitch.tv/FaintToughRingYee' => [
			'Satisfactory Update 4',
		],
		'Q&A: What are some of the priorities for the next update? https://clips.twitch.tv/SneakyLovelyCrabsAMPEnergyCherry' => [
			'Satisfactory Update 4',
		],
		'Q&A: How often will there be updates to the game? https://clips.twitch.tv/CheerfulZanyWebVoteYea' => [
			'Satisfactory Update 4',
		],
		'Snutt Talk: Macro Plan towards 1.0 https://clips.twitch.tv/CorrectNiceStingraySpicyBoy' => [
			'Satisfactory 1.0',
		],
		'Q&A: Storyline before 1.0? https://clips.twitch.tv/SteamyFurtiveRadishStrawBeary' => [
			'Satisfactory 1.0',
			'Story & Lore',
		],
		'Q&A: Is 1.0 the end of the game or will it be expanded? https://clips.twitch.tv/AmazonianWealthyCroquetteDendiFace' => [
			'Satisfactory 1.0',
			'DLC',
		],
		'Q&A: Will 1.0 require a reset of the game? https://clips.twitch.tv/SpoopyPlacidPepperoniSoonerLater' => [
			'Satisfactory 1.0',
		],
		'Q&A: Are there some other vehicles planned? https://clips.twitch.tv/EsteemedNurturingHyenaWOOP' => [
			'Vehicles',
		],
		'Q&A: Are vehicles going to get less sketchy or are we always getting Goat Simulator physics? https://clips.twitch.tv/KawaiiPoorYakinikuJonCarnage' => [
			'Vehicles',
			'Goat Simulator',
		],
		'Snutt & Jace Talk: Arachnophobia Mode (part 1) https://clips.twitch.tv/HandsomeJoyousPigeonYouWHY' => [
			'Arachnophobia Mode',
		],
		'Snutt & Jace Talk: Arachnophobia Mode (part 2) https://clips.twitch.tv/ResilientTalentedSalsifySSSsss' => [
			'Arachnophobia Mode',
		],
		'Snutt & Jace Talk: Arachnophobia Mode (part 3) https://clips.twitch.tv/ModernExquisiteJayFeelsBadMan' => [
			'Arachnophobia Mode',
		],
		'Snutt & Jace Talk: Arachnophobia Mode (part 4) https://clips.twitch.tv/NurturingPlayfulSwanTBTacoLeft' => [
			'Arachnophobia Mode',
		],
		'Snutt Talk: Accessibility (part 1): https://clips.twitch.tv/CrowdedSplendidSalamanderSoonerLater' => [
			'Accessibility',
		],
		'Q&A: We get this awesome phobia system but people still have trouble with colour blindness modes? https://clips.twitch.tv/PrettiestBloodyBadgerDendiFace' => [
			'Accessibility',
		],
		'Jace Talk: Accessibility - Arachnophobia & Colour Blindness (part 3) https://clips.twitch.tv/DignifiedSmoggyKathyAMPEnergyCherry' => [
			'Accessibility',
		],
		'Snutt & Jace Talk: Accessibility - Colour Blindness (part 4) https://clips.twitch.tv/FurtiveConcernedPuppySMOrc' => [
			'Accessibility',
		],
		'Snutt & Jace Talk: Accessibility - Hard of Hearing (part 5) https://clips.twitch.tv/RealFastShieldDoubleRainbow' => [
			'Accessibility',
		],
		'Snutt & Jace Talk: Accessibility (part 6) https://clips.twitch.tv/BelovedWrongCiderBCouch' => [
			'Accessibility',
		],
		'Q&A: I can definitely work around my colour deficiency - but the colour picker doesn\'t work https://clips.twitch.tv/CrepuscularInterestingWerewolfBCWarrior' => [
			'Accessibility',
		],
		'Q&A: How did you make the character slide in Satisfactory? (part 1) https://clips.twitch.tv/WittyYawningSangJKanStyle' => [
			'Pioneer',
		],
		'Q&A: How did you make the character slide in Satisfactory? (part 2) https://clips.twitch.tv/BlueBadWeaselPMSTwin' => [
			'Pioneer',
		],
		'Q&A: When will we be able to paint our trains? https://clips.twitch.tv/BelovedBloodyStapleGingerPower' => [
			true, // faq
			'Trains',
			'Paint',
		],
		'Q&A: Any thoughts on whether Trains will ever collide? https://clips.twitch.tv/SaltyJazzyPasta4Head' => [
			'Train Signals',
		],
		'Q&A: Will there be any underwater resources? https://clips.twitch.tv/RelievedCleanBibimbapDancingBanana' => [
			'Underwater',
		],
		'Q&A: Terraforming? https://clips.twitch.tv/AmericanSpineyWitchTinyFace' => [
			'Terraforming',
		],
		'Q&A: Any ice/snow biome plans? https://clips.twitch.tv/AlluringScrumptiousBaboonHeyGirl' => [
			'Snow',
		],
		'Q&A: Any different maps planned? https://clips.twitch.tv/PlausibleEnthusiasticGrassRedCoat' => [
			'World Map',
		],
		'Q&A: Will you be able to create your own map? https://clips.twitch.tv/ChillyRockyWalrusUnSane' => [
			'World Map',
		],
		'Q&A: Is there any way to prioritise power plant pipes? https://clips.twitch.tv/AnnoyingSavageParrotWoofer' => [
			'Pipes',
			'Power Management',
		],
		'Q&A: What convinced you to add pipes? https://clips.twitch.tv/BashfulFantasticPotDAESuppy' => [
			'Pipes',
		],
		'Q&A: Will you plan to add Steam Workshop support? https://clips.twitch.tv/SwissTameCoffeeDansGame' => [
			'Mods',
		],
		'Q&A: Is Satisfactory affected by Epic vs. Apple? https://clips.twitch.tv/FurryAwkwardStrawberryWoofer' => [
			'Off-Topic',
		],
		'Q&A: Offline Play https://clips.twitch.tv/BashfulDependableBobaWTRuck' => [
		],
		'Q&A: Custom game engine? https://clips.twitch.tv/ViscousFuriousPonyPhilosoraptor' => [
			'Unreal Engine',
		],
		'Q&A: When is Creative Mode coming? https://clips.twitch.tv/MagnificentImpartialSmoothieMikeHogu' => [
			true, // FAQ
			'Game Modes',
		],
		'Q&A: Will there be a no combat/fight version? https://clips.twitch.tv/ScaryTangibleTeaMrDestructoid' => [
			true, // FAQ
			'Game Modes',
		],
		'Q&A: Quarterly Build Contest? https://clips.twitch.tv/SparklingJazzyJayBCWarrior' => [
		],
		'Q&A: Will there be animals that attack the base? https://clips.twitch.tv/ProtectiveTubularCatJebaited' => [
			true, // FAQ
			'Base Defense',
		],
		'Q&A: When are Somersloops and Orbs have meaning? https://clips.twitch.tv/SarcasticProudWoodpeckerKappaPride' => [
			'Story & Lore',
			'Satisfactory 1.0',
		],
		'Q&A: Any news about autosave freezes? https://clips.twitch.tv/CrispyCheerfulCrocodilePanicBasket' => [
			true, // FAQ
		],
		'Q&A: How about adding machine variants during late-game so you can have less machines overall? https://clips.twitch.tv/BlatantEnjoyableTigerStoneLightning' => [
			'Buildings',
		],
		'Q&A: Are you going to upgrade to UE5? https://clips.twitch.tv/GloriousTangentialSalmonPastaThat' => [
			'Unreal Engine',
		],
		'Q&A: Did I miss the status update of Dedicated Servers? https://clips.twitch.tv/ElatedWittyVelociraptorThunBeast' => [
			'Dedicated Servers',
		],
		'Q&A: Any plans to add toilet paper in the bathroom? https://clips.twitch.tv/AuspiciousPrettiestAlfalfaKAPOW' => [
			'The HUB',
		],
		'Q&A: Why does Snutt have many guitars? https://clips.twitch.tv/AverageRenownedAxeWholeWheat' => [
			'Snutt',
		],
		'Q&A: Are we ever going to add taming mounts? https://clips.twitch.tv/BoldAgileSquidDoggo' => [
			'Creatures',
		],
		'Q&A: Any plans for 1-click multi-building? https://clips.twitch.tv/CheerfulLightAsteriskGOWSkull' => [
			'Mass Building',
		],
		'Q&A: Will there ever be conveyor lift splitters & mergers ? https://clips.twitch.tv/MiniatureFlaccidSwanKAPOW' => [
			'Conveyor Belts',
		],
		'Q&A: Will you be able to pet the doggo? https://clips.twitch.tv/DullHyperSpindlePanicVis' => [
			'Lizard Doggo',
		],
		'Q&A: Additional Suit Variations in the Coupon Shop ? https://clips.twitch.tv/CourteousMotionlessWrenMcaT' => [
			'Character Customisation',
		],
		'Q&A: Are there any plans to port the game to console? https://clips.twitch.tv/CogentRichJackalHeyGirl' => [
			'Console Release',
		],
	],
	'2020-08-25' => [
		'Snutt Talk: State of Development (Part 1) https://clips.twitch.tv/WealthyModernInternDogFace' => [
			'State of Dev',
		],
		'Snutt Talk: State of Development (Part 2) https://clips.twitch.tv/SuaveChillyGrouseSaltBae' => [
			'State of Dev',
		],
		'Q&A: State of things = 🤷? (Part 1) https://clips.twitch.tv/WealthyStormySnakeOptimizePrime' => [
			'State of Dev',
		],
		'Q&A: State of things = 🤷? (Part 2) https://clips.twitch.tv/EndearingBlitheTruffleJebaited' => [
			'State of Dev',
		],
		'Quality-of-live update? (Part 1): https://clips.twitch.tv/RudeSpoopyAlligatorVoteYea' => [
			'Satisfactory Fluids Update',
		],
		'Quality-of-live update? (Part 2): https://clips.twitch.tv/AlertFancyAxeFUNgineer' => [
			'Satisfactory Fluids Update',
		],
		'Quality-of-live update? (Part 3): https://clips.twitch.tv/CrunchyGlutenFreeNuggetsMingLee' => [
			'Satisfactory Fluids Update',
		],
		'ETA for Update 4? (Part 1) https://clips.twitch.tv/DeadPrettySaladMoreCowbell' => [
			true,
			'Satisfactory Update 4',
		],
		'ETA for Update 4? (Part 2) https://clips.twitch.tv/SavageBenevolentEndiveChocolateRain' => [
			true,
			'Satisfactory Update 4',
		],
		'ETA for Update 4? (Part 3) https://clips.twitch.tv/GoodSaltyPepperoniPunchTrees' => [
			true,
			'Satisfactory Update 4',
		],
		'ETA for Update 4? (Part 4) https://clips.twitch.tv/UnsightlyApatheticHornetKreygasm' => [
			true,
			'Satisfactory Update 4',
		],
		'ETA for Update 4? (Part 5) https://clips.twitch.tv/AmazingEagerGorillaHeyGuys' => [
			true,
			'Satisfactory Update 4',
		],
		'ETA for Update 4? (Mid-stream reiteration part 1) https://clips.twitch.tv/TangentialHyperFlyBigBrother' => [
			true,
			'Satisfactory Update 4',
		],
		'ETA for Update 4? (Mid-stream reiteration part 2) https://clips.twitch.tv/PlumpEntertainingSandstormYee' => [
			true,
			'Satisfactory Update 4',
		],
		'ETA for Update 4? (Mid-stream reiteration part 3) https://clips.twitch.tv/EntertainingTentativeGaurSmoocherZ' => [
			true,
			'Satisfactory Update 4',
		],
		'Q&A: Update before release of Cyberpunk 2077? https://clips.twitch.tv/AttractiveFrailRaisinKAPOW' => [
			'Satisfactory Update 4',
		],
		'Q&A: Do you have a set of ideas? (Part 1) https://clips.twitch.tv/AgitatedProtectiveBaguetteRiPepperonis' => [
		],
		'Q&A: Do you have a set of ideas? (Part 2) https://clips.twitch.tv/NaiveProudZebraWOOP' => [
		],
		'Q&A: Why do big updates at all - why not just release everything in small bites? (Part 1) https://clips.twitch.tv/FrozenLuckyRamenDxCat' => [
			'Satisfactory Updates',
		],
		'Q&A: Why do big updates at all - why not just release everything in small bites? (Part 2) https://clips.twitch.tv/BrainySecretiveSquidChefFrank' => [
			'Satisfactory Updates',
		],
		'Q&A: Why do big updates at all - why not just release everything in small bites? (Part 3) https://clips.twitch.tv/SpunkyFlirtySoybeanJebaited' => [
			'Satisfactory Updates',
		],
		'Q&A: Why do big updates at all - why not just release everything in small bites? (Part 4) https://clips.twitch.tv/EnjoyableCrazyVanillaStinkyCheese' => [
			'Satisfactory Updates',
		],
		'Q&A: Why do big updates at all - why not just release everything in small bites? (Part 5) https://clips.twitch.tv/CharmingObservantLardSMOrc' => [
			'Satisfactory Updates',
		],
		'Q&A: Why do big updates at all - why not just release everything in small bites? (Part 6) https://clips.twitch.tv/LachrymoseCourteousDoveDxAbomb' => [
			'Satisfactory Updates',
		],
		'Q&A: Can we expect significant performance increase with Update 4? (Part 1) https://clips.twitch.tv/CarelessDepressedShingleHassanChop' => [
			true,
			'Satisfactory Update 4',
		],
		'Q&A: Can we expect significant performance increase with Update 4? (Part 2) https://clips.twitch.tv/LuckyMushyShingleTBTacoRight' => [
			true,
			'Satisfactory Update 4',
		],
		'Q&A: Can we expect significant performance increase with Update 4? (Part 3) https://clips.twitch.tv/SincereProductiveScallionLeeroyJenkins' => [
			true,
			'Satisfactory Update 4',
		],
		'Q&A: Will Gas be in Update 4? https://clips.twitch.tv/SpinelessSneakySalsifyNerfRedBlaster' => [
			'Satisfactory Update 4',
			'Gases',
		],
		'Q&A: Will there be new items coming to the AWESOME Shop between now and Update 4? https://clips.twitch.tv/PerfectNurturingTrollRiPepperonis' => [
			'Satisfactory Update 4',
			'AWESOME Store',
		],
		'Snutt Talk: Minor stuff before Update 4 https://clips.twitch.tv/FrozenEndearingCodEleGiggle' => [
			'Satisfactory Update 4',
		],
		'Q&A: Update 4, just a quality-of-life thing? https://clips.twitch.tv/GleamingCheerfulWatercressRaccAttack' => [
			'Satisfactory Update 4',
		],
		'Q&A: Please tell me Update 4 will use S.A.M. Ore https://clips.twitch.tv/ArtisticGlutenFreeSpindleDxAbomb' => [
			'Satisfactory Update 4',
			'S.A.M. Ore',
		],
		'Q&A: When will the next patch even get released? https://clips.twitch.tv/BlitheKitschySnoodTwitchRaid' => [
			true,
			'Satisfactory Update 4',
		],
		'Snutt Talk: Improving on Vehicles https://clips.twitch.tv/AmazonianAnnoyingSushiUncleNox' => [
			'Vehicles',
		],
		'Q&A: Any plans to dig my explorer to get it out of the hole it fell into? https://clips.twitch.tv/FuriousRockyDuckPRChase' => [
			true,
			'Vehicles',
		],
		'Q&A: Smart Truck Stations? https://clips.twitch.tv/FurtiveHealthyRhinocerosJonCarnage' => [
			'Truck',
		],
		'Q&A: Trailer for the Trucks? https://clips.twitch.tv/SarcasticNeighborlyPigTebowing' => [
			'Truck',
		],
		'Q&A: Tanker Trucks? (Part 1) https://clips.twitch.tv/TenderSuspiciousSashimiEleGiggle' => [
			'Truck',
		],
		'Q&A: Tanker Trucks? (Part 2) https://clips.twitch.tv/FunSparklyFishRedCoat' => [
			'Truck',
		],
		'Q&A: Train Signals https://clips.twitch.tv/OriginalAntsySmoothieStoneLightning' => [
			'Train Signals',
		],
		'Q&A: Add Train tunnels to go through mountains? https://clips.twitch.tv/GleamingHyperBottleRickroll' => [
			'Trains',
			'Terraforming',
		],
		'Q&A: Will the Train always clip? https://clips.twitch.tv/ImpartialEnchantingCider4Head' => [
			'Train Signals',
		],
		'Q&A: Can you make modular vehicles? (Part 1) https://clips.twitch.tv/OriginalMuddyDogePeteZaroll' => [
			'Vehicles',
		],
		'Q&A: Can you make modular vehicles? (Part 2) https://clips.twitch.tv/DistinctConcernedPlumageWow' => [
			'Vehicles',
		],
		'Q&A: If you add Trucks then add Boats? https://clips.twitch.tv/EasyEnticingBearM4xHeh' => [
			'Vehicles',
		],
		'Q&A: We need Battleships https://clips.twitch.tv/WildHonorableCakeGrammarKing' => [
			'Vehicles',
		],
		'Q&A: Implement some kind of hire spaceship thingy for better exploration & faster travelling ? https://clips.twitch.tv/TrappedFaintBulgogiBigBrother' => [
			'Vehicles',
			'Aerial Travel',
		],
		'Q&A: How about a drone to fly around? https://clips.twitch.tv/SteamyViscousGoshawkDancingBaby' => [
			'Vehicles',
			'Aerial Travel',
		],
		'Q&A: Add Planes as Vehicles and we can automate it to carry our resources? (Part 1) https://clips.twitch.tv/AbstruseFrailKathyMrDestructoid' => [
			'Vehicles',
			'Aerial Travel',
		],
		'Q&A: Add Planes as Vehicles and we can automate it to carry our resources? (Part 2) https://clips.twitch.tv/SourManlyMochaBudStar' => [
			'Vehicles',
			'Aerial Travel',
		],
		'Q&A: Add Planes as Vehicles and we can automate it to carry our resources? (Part 3) https://clips.twitch.tv/PowerfulFriendlyKoalaANELE' => [
			'Vehicles',
			'Aerial Travel',
		],
		'Q&A: Add Planes as Vehicles and we can automate it to carry our resources? (Part 4) https://clips.twitch.tv/PoliteEnergeticGrouseHassaanChop' => [
			'Vehicles',
			'Aerial Travel',
		],
		'Q&A: Make the Cyber Wagon useful ? https://clips.twitch.tv/SpeedyAssiduousCrabKevinTurtle' => [
			'Cyber Wagon',
		],
		'Q&A: What can the Cyber Wagon do? https://clips.twitch.tv/SuperHappyEmuOMGScoots' => [
			'Cyber Wagon',
		],
		'Q&A: What about holes for Foundations? (Part 1) https://clips.twitch.tv/CrepuscularEnergeticPartridgePanicVis' => [
			true,
			'Foundations',
		],
		'Q&A: What about holes for Foundations? (Part 2) https://clips.twitch.tv/SparklingGiftedDumplingsSquadGoals' => [
			true,
			'Foundations',
		],
		'Q&A: Will Satisfactory be updated to Unreal Engine 5 / Snutt Talk: Experimental Builds (Part 1) https://clips.twitch.tv/TentativeHardPlumberYee' => [
			'Unreal Engine',
			'Release Builds',
		],
		'Q&A: Will Satisfactory be updated to Unreal Engine 5 / Snutt Talk: Experimental Builds (Part 2) https://clips.twitch.tv/SquareLovelyFriesBudBlast' => [
			'Unreal Engine',
			'Release Builds',
		],
		'Q&A: Will Satisfactory be updated to Unreal Engine 5 / Snutt Talk: Experimental Builds (Part 3) https://clips.twitch.tv/TemperedEnchantingOrangeTBCheesePull' => [
			'Unreal Engine',
			'Release Builds',
		],
		'Q&A: Will Satisfactory be updated to Unreal Engine 5 / Snutt Talk: Experimental Builds (Part 4) https://clips.twitch.tv/FrigidFragileCucumberOneHand' => [
			'Unreal Engine',
			'Release Builds',
		],
		'Q&A: Underwater biome when? https://clips.twitch.tv/HonorableCautiousDonutStoneLightning' => [
			'Underwater',
		],
		'Q&A: Terraforming? https://clips.twitch.tv/CourageousTardyLarkShazBotstix' => [
			'Terraforming',
		],
		'Q&A: Will you guys be hiding more stuff throughout the world for the Story Mode? https://clips.twitch.tv/VastAlertBadgerTF2John' => [
			'World Map',
			'Story & Lore',
		],
		'Q&A: Why can\'t we explode some stones in the map? https://clips.twitch.tv/HeartlessAntsyMelonCharlieBitMe' => [
			'World Map',
		],
		'Q&A: Like a new map for Satisfactory? https://clips.twitch.tv/ArtisticAthleticCroissantRalpherZ' => [
			true,
			'World Map',
		],
		'Q&A: How about procedural maps? https://clips.twitch.tv/ProtectiveWonderfulFrogVoteYea' => [
			true,
			'World Map',
			'Procedural Generation',
		],
		'Q&A: Plans for a Map Editor? (Part 1) https://clips.twitch.tv/ApatheticExpensiveDiscPeoplesChamp' => [
			true,
			'World Map',
		],
		'Q&A: Plans for a Map Editor? (Part 2) https://clips.twitch.tv/WiseToughOstrichYouWHY' => [
			true,
			'World Map',
		],
		'Snutt Talk: Map Builders (Part 1) https://clips.twitch.tv/TsundereProudKiwiRaccAttack' => [
			'World Map',
		],
		'Snutt Talk: Map Builders (Part 2) https://clips.twitch.tv/RichResourcefulSwanRlyTho' => [
			'World Map',
		],
		'Q&A: Is there a Battle Royale Mode planned? https://clips.twitch.tv/SavorySlickWombatOSkomodo' => [
			'Multiplayer',
			'Game Modes',
		],
		'Q&A: When I play multiplayer and the train and host doesn\'t update correctly, is this a known bug? https://clips.twitch.tv/LightAcceptableCheesePermaSmug' => [
			true,
			'Multiplayer',
			'Trains',
		],
		'Q&A: The time for multiplayer fix, can\'t use vehicles? https://clips.twitch.tv/PlayfulConfidentRabbitCurseLit' => [
			true,
			'Multiplayer',
		],
		'Q&A: Are you going to improve networking for multiplayer? Part 1: https://clips.twitch.tv/HomelyHyperGnatDoritosChip' => [
			true,
			'Multiplayer',
		],
		'Q&A: Are you going to improve networking for multiplayer? Part 2: https://clips.twitch.tv/SpinelessTsundereBurritoDxAbomb' => [
			true,
			'Multiplayer',
		],
		'Q&A: Will you be adding more variety of resources? https://clips.twitch.tv/BraveThankfulBeefFreakinStinkin' => [
			'Resources',
		],
		'Q&A: Today I Learned - there\'s a mass dismantle? https://clips.twitch.tv/OnerousGlamorousMoonAllenHuhu' => [
			'User Interface',
		],
		'Q&A: Copy & Paste settings from Machine to Machine? https://clips.twitch.tv/SlickEsteemedTriangleVoteNay' => [
			'Mass Building',
		],
		'Q&A: Drag-to-build Mode? https://clips.twitch.tv/UglyRacyCaribouYouWHY' => [
			'Mass Building',
		],
		'Q&A: Nuclear is the current end game https://clips.twitch.tv/CoweringHotZebraTheTarFu' => [
			'Nuclear Energy',
		],
		'Q&A: Fixing machines that break? https://clips.twitch.tv/EnergeticInexpensiveDillCurseLit' => [
			'Buildings',
		],
		'Snutt Talk: Machines breaking & Base Defence https://clips.twitch.tv/ElegantKawaiiGnatOneHand' => [
			'Buildings',
			'Base Defense',
		],
		'Q&A: Will I be able to place walls slightly into splitters, mergers, and conveyors? https://clips.twitch.tv/RespectfulGiftedStaplePicoMause' => [
			'Buildings',
			'Walls',
			'Conveyor Belts',
		],
		'Q&A: Water Extractors need to snap to grid https://clips.twitch.tv/ExuberantAmorphousCarrotNononoCat' => [
			'User Interface',
			'Crafting',
		],
		'Q&A: Set a specific Foundation as the keystone https://clips.twitch.tv/GoodAnimatedSproutPipeHype' => [
			'User Interface',
			'Crafting',
		],
		'Q&A: Grid- a radius would be perfect https://clips.twitch.tv/GeniusConcernedEggDogFace' => [
			'User Interface',
			'Crafting',
		],
		'Q&A: Blueprints would be awesome for end-game (Part 1) https://clips.twitch.tv/LuckyNastyLionDogFace' => [
			'Mass Building',
		],
		'Q&A: Blueprints would be awesome for end-game (Part 2) https://clips.twitch.tv/FreezingCuriousHeronDatBoi' => [
			'Mass Building',
		],
		'Q&A: Blueprints would be awesome for end-game (Part 3) https://clips.twitch.tv/CrunchyGlamorousQuailSwiftRage' => [
			'Mass Building',
		],
		'Q&A: Blueprints would be awesome for end-game (Part 4) https://clips.twitch.tv/RacyHilariousMangoStinkyCheese' => [
			'Mass Building',
		],
		'Q&A: Internal discussions to significantly rework existing buildings like refineries? (Part 1) https://clips.twitch.tv/CrispySaltyOcelotOSfrog' => [
			'Refinery',
		],
		'Q&A: Internal discussions to significantly rework existing buildings like refineries? (Part 2) https://clips.twitch.tv/CooperativeCrackyAyeayeTheTarFu' => [
			'Refinery',
		],
		'Q&A: Internal discussions to significantly rework existing buildings like refineries? (Part 3) https://clips.twitch.tv/SmallProductiveKaleCclamChamp' => [
			'Refinery',
		],
		'Q&A: Internal discussions to significantly rework existing buildings like refineries? (Part 4) https://clips.twitch.tv/BoredThankfulPistachioJKanStyle' => [
			'Refinery',
		],
		'Q&A: Do you not think that Refineries are over-used? https://clips.twitch.tv/LongOpenFlamingoSMOrc' => [
			'Refinery',
		],
		'Q&A: End game is all about building refineries https://clips.twitch.tv/MildNurturingWoodcockYouWHY' => [
			'Refinery',
		],
		'Q&A: Refineries take up so much space https://clips.twitch.tv/FilthyPerfectDragonSwiftRage' => [
			'Refinery',
		],
		'Q&A: Are limited resources planned? (Part 1) https://clips.twitch.tv/ConcernedStylishTomatoBabyRage' => [
			'S.A.M. Ore',
		],
		'Q&A: Are limited resources planned? (Part 2) https://clips.twitch.tv/PrettyBelovedTermiteOptimizePrime' => [
			'S.A.M. Ore',
		],
		'Q&A: Are limited resources planned? (Part 3) https://clips.twitch.tv/PoorAlluringHerdRitzMitz' => [
			'S.A.M. Ore',
		],
		'Q&A: Can players have custom programmers ? (Part 1) https://clips.twitch.tv/BovineConsiderateSangMVGame' => [
			'Conveyor Belts',
			'Mods vs. Features',
		],
		'Q&A: Can players have custom programmers ? (Part 2) https://clips.twitch.tv/GrossPoisedAardvarkChocolateRain' => [
			'Conveyor Belts',
			'Mods vs. Features',
		],
		'Q&A: What about a more complex power system with transformers and stuff? https://clips.twitch.tv/FrozenVivaciousLaptopGivePLZ' => [
			'Power Management',
		],
		'Q&A: AI in an Electricity Management System that can handle power surges when we\'re away from base? https://clips.twitch.tv/FancyPiercingLardOneHand' => [
			'Power Management',
		],
		'Q&A: Potential to get better management for power grids? https://clips.twitch.tv/SoftTangentialGaurJonCarnage' => [
			'Power Management',
		],
		'Q&A: When will you ad UI for the Steam Geyser Power Plant? https://clips.twitch.tv/WanderingBashfulGoatTBCheesePull' => [
			'Geothermal Energy',
		],
		'Snutt PSA: Nuclear Waste disposal (Part 1) https://clips.twitch.tv/DarlingSteamyCourgetteOneHand' => [
			'Nuclear Waste',
		],
		'Snutt PSA: Nuclear Waste disposal (Part 2) https://clips.twitch.tv/HorribleToughMouseThunBeast' => [
			'Nuclear Waste',
		],
		'Snutt PSA: Nuclear Waste disposal (Part 3) https://clips.twitch.tv/SullenWittyBearHassanChop' => [
			'Nuclear Waste',
		],
		'Snutt PSA: Nuclear Waste disposal (Part 4) https://clips.twitch.tv/QuaintBeautifulMetalWoofer' => [
			'Nuclear Waste',
		],
		'Snutt PSA: Nuclear Waste disposal (Part 5) https://clips.twitch.tv/GoldenTenuousLemurDAESuppy' => [
			'Nuclear Waste',
		],
		'Q&A: If we can\'t delete the radioactive, then please add radioactive-safe containers to store them? (Part 1) https://clips.twitch.tv/ConfidentImpossibleShingleTBTacoLeft' => [
			'Nuclear Waste',
		],
		'Q&A: If we can\'t delete the radioactive, then please add radioactive-safe containers to store them? (Part 2) https://clips.twitch.tv/SnappyExpensiveDootMVGame' => [
			'Nuclear Waste',
		],
		'Q&A: Why are there no Solar Panels ? https://clips.twitch.tv/CleverPluckyOctopusRedCoat' => [
			'Green Energy',
		],
		'Q&A: Put in Solar & Wind Power to make it ultra limited? https://clips.twitch.tv/DeliciousStylishOctopusTBTacoRight' => [
			'Green Energy',
		],
		'Q&A: What about wind turbines? https://clips.twitch.tv/TriangularColdShingleSquadGoals' => [
			'Green Energy',
		],
		'Q&A: Any chance we can have a power switch so we can shut down power generators? (Part 1) https://clips.twitch.tv/SmokyBreakableAyeayeEagleEye' => [
			'Power Management',
		],
		'Q&A: Any chance we can have a power switch so we can shut down power generators? (Part 2) https://clips.twitch.tv/SassyLightSkirretOSsloth' => [
			'Power Management',
		],
		'Q&A: Any chance we can have a power switch so we can shut down power generators? (Part 3) https://clips.twitch.tv/KawaiiOddGrasshopperMrDestructoid' => [
			'Power Management',
		],
		'Q&A: Any chance we can have a power switch so we can shut down power generators? (Part 4) https://clips.twitch.tv/ElegantNaivePorpoiseTF2John' => [
			'Power Management',
		],
		'Q&A: New enemies / creatures? https://clips.twitch.tv/WonderfulNurturingYamYouWHY' => [
			'Crab Boss',
			'Satisfactory Update 3',
			'Satisfactory Update 4',
			'Satisfactory Update 5',
		],
		'Q&A: Will we have more monsters? https://clips.twitch.tv/GrotesqueDelightfulLyrebirdPrimeMe' => [
			'Crab Boss',
			'Creatures',
		],
		'Q&A: More cats in Arachnophobia Mode? https://clips.twitch.tv/KathishConcernedWalrusRedCoat' => [
			'Arachnophobia Mode',
		],
		'Q&A: Arachnophobia Mode is scarier than the actual spiders https://clips.twitch.tv/NeighborlyEnticingMarrowResidentSleeper' => [
			'Arachnophobia Mode',
		],
		'Q&A: When\'s Tier 8 coming? https://clips.twitch.tv/BlueMildLaptopHassaanChop' => [
			'Tier 8',
		],
		'Q&A: What is expected for Tier 9? https://clips.twitch.tv/FrigidWiseSnakeOSfrog' => [
			'Tier 9',
		],
		'Q&A: Tier 10, when? https://clips.twitch.tv/ThoughtfulDepressedAlfalfaOSfrog' => [
			'Tier 10',
		],
		'Q&A: Dedicated Servers update? https://clips.twitch.tv/AgitatedAltruisticAnacondaStinkyCheese' => [
			'Dedicated Servers',
		],
		'Q&A: Will Dedicated Servers be available on Linux, or Windows? https://clips.twitch.tv/SeductiveInnocentFerretHeyGirl' => [
			'Dedicated Servers',
		],
		'Q&A: Linux would be useful for Servers https://clips.twitch.tv/UglyAwkwardCiderSSSsss' => [
			'Dedicated Servers',
		],
		'Q&A: Will the Server source code be available for Custom Mods, or with pre-compiled binaries? https://clips.twitch.tv/ShinyFunnyJellyfishSMOrc' => [
			'Dedicated Servers',
			'Mods',
		],
		'Q&A: Signs for Hypertube Entrances? https://clips.twitch.tv/SpinelessUnsightlyVanillaKeyboardCat' => [
			'Hyper Tubes',
		],
		'Q&A: Mk. 2 Hypertubes? https://clips.twitch.tv/CrypticUnusualPandaArsonNoSexy' => [
			'Hyper Tubes',
		],
		'Q&A: Why is hyperloop in first person? https://clips.twitch.tv/FairCallousStingrayHeyGuys' => [
			'Hyper Tubes',
		],
		'Plans for mod support? (Part 1) https://clips.twitch.tv/OnerousDeterminedMoonRlyTho' => [
			'Official Mod Support',
		],
		'Plans for mod support? (Part 2) https://clips.twitch.tv/CreativeOptimisticWalrusSwiftRage' => [
			'Official Mod Support',
		],
		'Plans for mod support? (Part 3) https://clips.twitch.tv/HumbleRenownedTofuLitty' => [
			'Official Mod Support',
		],
		'Q&A: Actual Elevators with floor-select buttons ? https://clips.twitch.tv/SparklingFilthyKathyBleedPurple' => [
			'Mods vs. Features',
		],
		'Q&A: Do you have plans for elevators usable for players? https://clips.twitch.tv/DullSmokyWaffleDoggo' => [
			'Mods vs. Features',
		],
		'Snutt Talk: 1.0 & Sequels https://clips.twitch.tv/CharmingHeadstrongAsparagusBCouch' => [
			'Satisfactory 1.0',
		],
		'Snutt Talk: Satisfactory 1.0, and beyond (Part 1) https://clips.twitch.tv/BrainyArbitraryEagleBrokeBack' => [
			'Satisfactory 1.0',
		],
		'Snutt Talk: Satisfactory 1.0, and beyond (Part 2) https://clips.twitch.tv/HomelyCovertParrotNinjaGrumpy' => [
			'Satisfactory 1.0',
		],
		'Snutt Talk: Satisfactory 1.0, and beyond (Part 3) https://clips.twitch.tv/SmellyCarefulRuffPastaThat' => [
			'Satisfactory 1.0',
		],
		'Snutt Talk: Satisfactory 1.0, and beyond (Part 4) https://clips.twitch.tv/GenerousSlickKimchiResidentSleeper' => [
			'Satisfactory 1.0',
		],
		'Q&A: Will you be able to play Doom on the Hub screens? https://clips.twitch.tv/DifficultDependableGooseAMPEnergyCherry' => [
			'The HUB',
		],
		'Q&A: Can we get a drink on the fridge in the base? https://clips.twitch.tv/ShyDifferentOcelotRalpherZ' => [
			'The HUB',
		],
		'Snutt Talk: Fridge in the Hub https://clips.twitch.tv/FreezingBovineRutabagaFutureMan' => [
			'The HUB',
		],
		'Snutt Talk: Snutt discovers the fridge. https://clips.twitch.tv/SeductiveAbstemiousBisonDerp' => [
			'The HUB',
			'Snutt',
		],
		'Q&A: Better Autosave system? https://clips.twitch.tv/CarefulBashfulHyenaWOOP' => [
			'Autosave',
		],
		'Snutt Talk: If you think Autosave is annoying https://clips.twitch.tv/InventiveStylishGerbilWow' => [
			'Autosave',
		],
		'Q&A: Is it possible to have the Autosave not noticeable at all ? https://clips.twitch.tv/ThirstyTubularHamMikeHogu' => [
			'Autosave',
		],
		'Q&A: Please consider adding a third-person view? (Part 1) https://clips.twitch.tv/PeacefulInventiveDogWOOP' => [
			'Graphics',
		],
		'Q&A: Please consider adding a third-person view? (Part 2) https://clips.twitch.tv/FrailSuaveBeanImGlitch' => [
			'Graphics',
		],
		'Q&A: Will light be added to the game? (Part 1) https://clips.twitch.tv/FunOilyWolverineCorgiDerp' => [
			'Lights',
		],
		'Q&A: Will light be added to the game? (Part 2) https://clips.twitch.tv/NeighborlyCharmingBasenjiRlyTho' => [
			'Lights',
		],
		'Q&A: Will light be added to the game? (Part 3) https://clips.twitch.tv/AbnegateEndearingBottleKlappa' => [
			'Lights',
		],
		'Q&A: Can you make it free for one time only? https://clips.twitch.tv/AlertPleasantReindeerPupper' => [
			'Free Weekends',
		],
		'Snutt Talk: Previous free weekends https://clips.twitch.tv/ArtisticCrispyGrasshopperBrainSlug' => [
			'Free Weekends',
		],
		'Q&A: Do you want to release updates before you release full game? https://clips.twitch.tv/EmpathicExuberantWeaselAllenHuhu' => [
			'Satisfactory Updates',
		],
		'Q&A: Some new Machines in the next update? https://clips.twitch.tv/CourteousSmellyNewtTTours' => [
			'Buildings',
			'Satisfactory Fluids Update',
			'Satisfactory Update 4',
		],
		'Q&A: Will there be any new music soundtracks in the future? https://clips.twitch.tv/UgliestArbitraryOwlDatBoi' => [
			'Soundtrack',
		],
		'Q&A: A mark on pipes to show the meters ? https://clips.twitch.tv/AltruisticSuperBobaBudBlast' => [
			'Pipes',
			'User Interface',
		],
		'Q&A: What about a Tutorial System? https://clips.twitch.tv/EntertainingTenuousCasettePeteZaroll' => [
			'User Interface',
		],
		'Q&A: Can we get an indicator for the launch line from the Launch Pad? https://clips.twitch.tv/ShakingBlushingLampNerfRedBlaster' => [
			'Jump Pads',
		],
		'Q&A: Found a big pink flower thing in a cave, is that just some cool scenery or is it a WIP ? https://clips.twitch.tv/VibrantExpensiveRaisinStinkyCheese' => [
			'World Map',
			'Plants',
		],
		'Q&A: Are the Devs back from vacation? https://clips.twitch.tv/SeductiveImpartialCobblerOptimizePrime' => [
			'Coffee Stainers',
		],
		'Q&A: What game will come out first, Satisfactory or Star Citizen? https://clips.twitch.tv/AdventurousUninterestedBasenji4Head' => [
			'Off-Topic',
		],
		'Q&A: Could we order food from the Food Station, or is it like a buffet? https://clips.twitch.tv/ExuberantDeadDinosaurBatChest' => [
			'The HUB',
		],
		'Q&A: She!? Not me !? https://clips.twitch.tv/InexpensiveChillyWheelItsBoshyTime' => [
			'Pioneer',
		],
		'Q&A: Reducing the stupid poly counts? https://clips.twitch.tv/PoliteTallLocustStoneLightning' => [
			'Graphics',
			'Buildings',
		],
		'Q&A: Is the sink going to accept liquids in the future? https://clips.twitch.tv/ArtisticCoweringTortoiseRitzMitz' => [
			'AWESOME Sink',
		],
		'Q&A: Will there be a rocket to leave the planet? https://clips.twitch.tv/BusyPowerfulWombatSoonerLater' => [
			'Space Exploration',
		],
		'Q&A: Do you have any clue on what the alien artefacts do? https://clips.twitch.tv/CulturedEnthusiasticNoodleWTRuck' => [
			'Story & Lore',
		],
		'Q&A: Let me personalise my character? https://clips.twitch.tv/CharmingRespectfulFlyFUNgineer' => [
			'Character Customisation',
		],
		'Q&A: Real-time reflections for the helmet? https://clips.twitch.tv/LivelyHeartlessRutabagaWholeWheat' => [
			'Pioneer',
			'Graphics',
		],
		'Q&A: More pollution as you progress? https://clips.twitch.tv/WanderingLitigiousDurianRalpherZ' => [
			'Pollution',
		],
		'Q&A: UV issues and texture tearing is a known issue? https://clips.twitch.tv/WealthyPunchyAxePeoplesChamp' => [
			'Graphics',
		],
		'Q&A: Explosive Barrels of Gas we can send through the rail guns ? https://clips.twitch.tv/CrowdedRespectfulNostrilNotATK' => [
			'Equipment',
			'Gases',
		],
		'Q&A: Please think about adding dedicated Storage Containers like in Ark ? https://clips.twitch.tv/ArbitraryIronicClipsdadPicoMause' => [
			'Storage Containers',
		],
		'Q&A: Removing vegetation speeds up the game, yes or no ? https://clips.twitch.tv/BusyHandsomeSmoothiePartyTime' => [
			true,
			'Plants',
		],
		'Q&A: Are the trees instance-based? https://clips.twitch.tv/HandsomeAnnoyingLEDPraiseIt' => [
			'Plants',
			'Graphics',
		],
		'Q&A: Will there be more narrative? https://clips.twitch.tv/DarlingPoisedPotCopyThis' => [
			'Story & Lore',
		],
		'Snutt Talk: VR Support https://clips.twitch.tv/DullScrumptiousEagleStinkyCheese' => [
			true,
			'Graphics',
		],
		'Q&A: Will there be any further goals besides Research & Development stages? https://clips.twitch.tv/TsundereOutstandingNuggetsFUNgineer' => [
			'Tiers',
		],
		'Q&A: Will we ever have proper multi-core support? https://clips.twitch.tv/VenomousProtectiveDonutTheTarFu' => [
			'Technology',
		],
		'Q&A: Is the Story a mode, or can I play with my actual save game? https://clips.twitch.tv/GeniusInventiveMomPRChase' => [
			'Story & Lore',
		],
		'Q&A: Plans for official Linux support? https://clips.twitch.tv/DiligentDeafMangoPogChamp' => [
			true,
			'Release Builds',
		],
		'Q&A: Please make the Walking Bean stop clipping https://clips.twitch.tv/WanderingGloriousWallabyPunchTrees' => [
			'Space Giraffe-Tick-Penguin-Whale Thing',
		],
	],
];

foreach ($from_markdown as $date => $data) {
	$cache = add_playlist(
		$date,
		$cache,
		$main,
		$global_topic_hierarchy
	);

	[$date_playlist_id] = determine_playlist_id(
		$date,
		$cache,
		$main,
		$global_topic_hierarchy
	);

	foreach ($data as $twitch_line => $topics) {
		$is_faq = in_array(true, $topics, true);

		$topics = array_filter($topics, 'is_string');

		if ( ! $is_faq) {
			foreach ($topics as $topic) {
				if (
					in_array(
						$topic,
						[
							'Mass Building',
							'Dedicated Servers',
							'Console Release',
							'Base Defense',
							'Nuclear Waste',
						],
						true
					)
				) {
					$is_faq = true;
					break;
				}
			}
		}

		$cache = add_twitch_video_from_single_string(
			$twitch_line,
			$is_faq,
			$date_playlist_id,
			$cache
		);

		foreach ($topics as $topic) {
			$cache = add_playlist(
				$topic,
				$cache,
				$main,
				$global_topic_hierarchy
			);

			[$topic_playlist_id] = determine_playlist_id(
				$topic,
				$cache,
				$main,
				$global_topic_hierarchy
			);

			$cache = add_twitch_video_from_single_string(
				$twitch_line,
				$is_faq,
				$topic_playlist_id,
				$cache
			);
		}
	}
}

ksort($cache['videoTags']);
ksort($cache['playlistItems']);

$cache['stubPlaylists'] = array_filter(
	$cache['playlists'],
	static function (array $maybe) : bool {
		return 0 === count($maybe[2]);
	}
);

$cache['playlists'] = array_filter(
	$cache['playlists'],
	static function (array $maybe) : bool {
		return count($maybe[2]) > 0;
	}
);

uksort(
	$cache['playlists'],
	static function (
		string $a,
		string $b
	) use (
		$main
	) : int {
		$a = isset($main['playlists'][$a]) ? $main['playlists'][$a][1] : $a;
		$b = isset($main['playlists'][$b]) ? $main['playlists'][$b][1] : $b;

		return strnatcasecmp($a, $b);
	}
);

file_put_contents(
	__DIR__ . '/cache-injection.json',
	json_encode(
		$cache,
		JSON_PRETTY_PRINT
	)
);
