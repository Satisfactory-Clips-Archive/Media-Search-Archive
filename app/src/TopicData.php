<?php
/**
 * @author SignpostMarv
 */
declare(strict_types=1);

namespace SignpostMarv\VideoClipNotes;

use const ARRAY_FILTER_USE_BOTH;
use function count;
use function dirname;
use function is_array;
use function is_int;
use function is_string;
use UnexpectedValueException;

class TopicData
{
	public const GLOBAL_TOPIC_HIERARCHY = [
		'Pending' => [
			'Satisfactory Updates',
		],
		'Released' => [
			'Satisfactory Updates',
		],
		'Speculative' => [
			'Satisfactory Updates',
		],
		'Free Weekends' => [
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiEo8RlgfifC8OhLmJl8SgpJE' => [ // State of Dev
			-7,
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiEp-sFymxlcEagoTYEPciGUc' => [ // Teasers & Trailers
			-6,
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiEpQ6aOWoeKjQHAvB5VZAJsj' => [ // Update 3 Reveal Trailer
			300,
			'Satisfactory Updates',
			'Teasers & Trailers',
		],
		'PLbjDnnBIxiEoJKFR8oveQqco9GX9ct3MJ' => [ // Update 3 Patch Notes Video
			301,
			'Satisfactory Updates',
			'Teasers & Trailers',
		],
		'PLbjDnnBIxiEq5RG94oLoX2Uh2s1qdO7pT' => [ // Update 4 Teasers
			400,
			'Satisfactory Updates',
			'Teasers & Trailers',
		],
		'PLbjDnnBIxiEooFFzttwXeeagqJmJRQi_V' => [ // Update 4 Launch Stream
			401,
			'Satisfactory Updates',
			'Teasers & Trailers',
		],
		'PLbjDnnBIxiEo1tIwenNsU5NhXb8oK3gC-' => [ // Update 4 Patch Notes Video
			402,
			'Satisfactory Updates',
			'Teasers & Trailers',
		],
		'Update 4 Launch Trailer' => [
			404,
			'Satisfactory Updates',
			'Teasers & Trailers',
		],
		'PLbjDnnBIxiEpSyvmQgCEC-hEKtmowhDVV' => [ // Update 5 Teasers
			500,
			'Satisfactory Updates',
			'Teasers & Trailers',
		],
		'PLbjDnnBIxiEp1tfSvG0zuVBRf93GM6-Np' => [ // Update 5 Patch Notes Video
			502,
			'Satisfactory Updates',
			'Teasers & Trailers',
		],
		'PLbjDnnBIxiEqV8w9wOvrxi26vETzqpoFE' => [ // Update 5 Launch Stream
			503,
			'Satisfactory Updates',
			'Teasers & Trailers',
		],
		'PLbjDnnBIxiErc1n63NJjRMX1hAq4CqmxI' => [ // Update 6 Teasers
			600,
			'Satisfactory Updates',
			'Teasers & Trailers',
		],
		'PLbjDnnBIxiEruhM2QTAuTcg4QbgDFx_7u' => [ // Update 6 Patch Notes Video
			601,
			'Satisfactory Updates',
			'Teasers & Trailers',
		],
		'PLbjDnnBIxiEqmCgJaOWMdicmhcQuchNow' => [ // Update 7 Teasers
			700,
			'Satisfactory Updates',
			'Teasers & Trailers',
		],
		'PLbjDnnBIxiErudM4oVI8PObNJ3llSQOC5' => [ // Update 7 Patch Notes Video
			701,
			'Satisfactory Updates',
			'Teasers & Trailers',
		],
		'Update 7 Patch Notes Video - Behind the Scenes' => [
			'Satisfactory Updates',
			'Teasers & Trailers',
			'Update 7 Patch Notes Video',
		],
		'PLbjDnnBIxiEr446crgxZOiVsi-_Ltncyq' => [ // Update 8 Teasers
			800,
			'Satisfactory Updates',
			'Teasers & Trailers',
		],
		'PLbjDnnBIxiEp-iNKKFdFOt-2pFR2GQpYI' => [ // Update 8 Patch Notes Video
			801,
			'Satisfactory Updates',
			'Teasers & Trailers',
		],
		'PLbjDnnBIxiErYA3_jozBFWgptVsuJEatP' => [ // 1.0 Teasers
			900,
			'Satisfactory Updates',
			'Teasers & Trailers',
		],
		'PLbjDnnBIxiEpVx5PB5tPoSbLHEht1SxcY' => [ // 1.0 Patch Notes Video
			901,
			'Satisfactory Updates',
			'Teasers & Trailers',
		],
		'PLbjDnnBIxiEpXQI-Sa6xvbYt7NvygO7v1' => [ // 1.1 Teasers
			1000,
			'Satisfactory Updates',
			'Teasers & Trailers',
		],
		'Update 5 Quiz: Underrated/Overrated' => [
			1,
			'Satisfactory Updates',
			'Teasers & Trailers',
			'Update 5 Launch Stream',
		],
		'Update 5 Loot' => [
			2,
			'Satisfactory Updates',
			'Teasers & Trailers',
			'Update 5 Launch Stream',
		],
		'Update 5 Art Giveaway' => [
			3,
			'Satisfactory Updates',
			'Teasers & Trailers',
			'Update 5 Launch Stream',
		],
		'Update 5 Challenge Run' => [
			4,
			'Satisfactory Updates',
			'Teasers & Trailers',
			'Update 5 Launch Stream',
		],
		'Update 5 Final Countdown' => [
			5,
			'Satisfactory Updates',
			'Teasers & Trailers',
			'Update 5 Launch Stream',
		],
		'Update 5 Revealed' => [
			6,
			'Satisfactory Updates',
			'Teasers & Trailers',
			'Update 5 Launch Stream',
		],
		'PLbjDnnBIxiEq-eP01Lynsg2Jv-wLEWQ7e' => [ // Satisfactory Prototypes
			-5,
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiErefLJefhTwZ4IQWai8n8iH' => [ // Pre-Alpha
			-4,
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiEoe5rHqE8w9OpWENlICvWKD' => [ // Satisfactory Alpha
			100,
			'Satisfactory Updates',
			'Released',
		],
		'PLbjDnnBIxiErt_GKKuC2z1kLZfTg1-ior' => [ // Alpha Weekend
			'Satisfactory Updates',
			'Released',
			'Satisfactory Alpha',
		],
		'PLbjDnnBIxiEp3nVij0OnuqpiuBFlKDB-K' => [ // Satisfactory 2017
			-1,
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiErz0qdIMhOw3L8lo7hamPXK' => [ // Satisfactory Update 1
			1,
			'Satisfactory Updates',
			'Released',
		],
		'PLbjDnnBIxiEprAq_zqoVsjr3Cii2tpty-' => [ // Satisfactory Update 2
			2,
			'Satisfactory Updates',
			'Released',
		],
		'PLbjDnnBIxiEp9MC5RZraDAl95pvC0YVvW' => [ // Satisfactory Update 3
			3,
			'Satisfactory Updates',
			'Released',
		],
		'PLbjDnnBIxiEq_0QTxH7_C0c8quZsI1uMu' => [ // Satisfactory Fluids Update
			4,
			'Satisfactory Updates',
			'Released',
		],
		'PLbjDnnBIxiEpH9vCWSguzYfXrsjagXgyE' => [ // Satisfactory Update 4
			4,
			'Satisfactory Updates',
			'Released',
		],
		'PLbjDnnBIxiEqNB46ydy3DN3k5Pn2ZyHrS' => [ // World Update
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiEov1pe4Y3Fr8AFfJuu7jIR6' => [ // Satisfactory Update 5
			5,
			'Satisfactory Updates',
			'Released',
		],
		'PLbjDnnBIxiEpOfQ2ATioPVEQvCuB6oJSR' => [ // Satisfactory Update 6
			6,
			'Satisfactory Updates',
			'Released',
		],
		'PLbjDnnBIxiEq99AIuldrDf7WJJpvEtkEO' => [ // Satisfactory Update 7
			7,
			'Satisfactory Updates',
			'Released',
		],
		'PLbjDnnBIxiEpmeKjnMqZxXfE3hxJ7ntQo' => [ // Satisfactory Update 8
			8,
			'Satisfactory Updates',
			'Released',
		],
		'PLbjDnnBIxiEoEnwlt8CGTgTcPFQVZWpmx' => [ // Satisfactory Update 9
			9,
			'Satisfactory Updates',
			'Speculative',
		],
		'PLbjDnnBIxiErDHmsYYI45RArzpcfAk9lu' => [ // Satisfactory Update 10
			10,
			'Satisfactory Updates',
			'Speculative',
		],
		'PLbjDnnBIxiEppd05OYJXBsjk1nu8ZHL4g' => [ // Satisfactory Update 11
			11,
			'Satisfactory Updates',
			'Speculative',
		],
		'PLbjDnnBIxiErIs7tyigBsVwTUg4hsKvx1' => [ // Modular Builds
			998,
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiEqrvp3UlLgVHZbY9Yb045zj' => [ // Release Builds
			999,
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiEppntOHbTUyrFhnKNkZZVpT' => [ // Satisfactory 1.0
			1000,
			'Satisfactory Updates',
			'Released',
		],
		'PLbjDnnBIxiErF0fgx4tkyBd-6AS142lat' => [ // Satisfactory 1.0 Closed Beta
			1001,
			'Satisfactory Updates',
			'Released',
			'Satisfactory 1.0',
		],
		'PLbjDnnBIxiEpEFyjc7EIHVjKwQDfUpYMS' => [ // Satisfactory 1.1
			1100,
			'Satisfactory Updates',
			'Pending',
		],
		'PLbjDnnBIxiEruo0Lbpj6lJAWFcxyKZpJY' => [ // Satisfactory 1.2
			1200,
			'Satisfactory Updates',
			'Speculative',
		],
		'PLbjDnnBIxiEpF2A_3mRQgPp91Wzvw9vBQ' => [ // Satisfactory 1.3
			1300,
			'Satisfactory Updates',
			'Speculative',
		],
		'PLbjDnnBIxiEplw0-EILli-fUEWMi19oPp' => [ // Satisfactory 1.4
			1400,
			'Satisfactory Updates',
			'Speculative',
		],
		'PLbjDnnBIxiEp3NZrYFYvIXhstYb2TAZNS' => [ // Satisfactory 2.0
			2000,
			'Satisfactory Updates',
			'Speculative',
		],
		'PLbjDnnBIxiEoRob6NyQbluiHfVVGjCyoJ' => [ // Satisfactory 2.1
			2100,
			'Satisfactory Updates',
			'Speculative',
		],
		'PLbjDnnBIxiEqSwg9dGzkBJA9_2ONaz2O1' => [ // Satisfactory 2.2
			2200,
			'Satisfactory Updates',
			'Speculative',
		],
		'PLbjDnnBIxiEoai53GV4a7pwFhnxXv_ru_' => [ // Satisfactory 2.3
			2300,
			'Satisfactory Updates',
			'Speculative',
		],
		'PLbjDnnBIxiEqE5DASA1__JLm7-DBlT-i5' => [ // Satisfactory 2.4
			2400,
			'Satisfactory Updates',
			'Speculative',
		],
		'PLbjDnnBIxiErRrH_qPAYNMWNmORwXhGh2' => [
			2500,
			'Satisfactory Updates',
			'Speculative',
		],
		'PLbjDnnBIxiEqS1vHwtV1OOqDluevVNZx5' => [ // Satisfactory 3.0
			3000,
			'Satisfactory Updates',
			'Speculative',
		],
		'PLbjDnnBIxiErq1cTFe-14F7UISclc1uc-' => [ // Seasonal Events
			800,
			'Satisfactory Updates',
		],
		'PLbjDnnBIxiEq84iBBkP2g69rPYXD-yWMy' => [ // FICS⁕MAS
			'Satisfactory Updates',
			'Seasonal Events',
		],
		'PLbjDnnBIxiEo6wrqcweq2pi9HmfRJdkME' => [ // Simon
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEr8o-WHAZJf8lAVaNJlbHmH' => [ // Mods vs. Features
			'Mods',
		],
		'PLbjDnnBIxiEolhfvxFmSHBd2Ct-lyZW6N' => [ // Official Mod Support
			'Mods',
		],
		'Signs Mod' => [
			'Mods',
		],
		'Power Suit' => [
			'Mods',
		],
		'Structural Solutions' => [
			'Mods',
		],
		'Flags' => [
			'Mods',
		],
		'Refined Power' => [
			'Mods',
		],
		'Inserters' => [
			'Mods',
		],
		'Teleporters' => [
			'Mods',
		],
		'Statue Mod' => [
			'Mods',
		],
		'Pak Utility' => [
			'Mods',
		],
		'Zip Strips' => [
			'Mods',
		],
		'Moar Factory' => [
			'Mods',
		],
		'Mk++' => [
			'Mods',
		],
		'Ghost Construction' => [
			'Mods',
		],
		'Farming Mod' => [
			'Mods',
		],
		'3D Text' => [
			'Mods',
		],
		'Utility Signs' => [
			'Mods',
		],
		'Decoration' => [
			'Mods',
		],
		'Teleporter' => [
			'Mods',
		],
		'Micro Manage' => [
			'Mods',
		],
		'Area Actions' => [
			'Mods',
		],
		'Item Dispenser' => [
			'Mods',
		],
		'Transportation' => [
			'Features',
		],
		'Planned Features' => [
			'Features',
		],
		'PLbjDnnBIxiErM4iiDcDRhjHFCEOWBCe0O' => [ // Possible Features
			'Features',
		],
		'Unreleased Features' => [
			'Features',
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
			'Multiplayer',
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
		'PLbjDnnBIxiEqtHXWfST1R6GksAclU7-ES' => [ // FISHLABS
			'Features',
			'Possible Features',
			'Console Release',
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
		'Doors' => [
			'Features',
			'Buildables',
		],
		'Stairs' => [
			'Features',
			'Buildables',
		],
		'PLbjDnnBIxiEpED5R4C4489wyekiGkNVe2' => [ // Creatures
			'Environment',
		],
		'PLbjDnnBIxiEq1P6bQ-17tMjKuFJyxcfSU' => [ // Plants
			'Environment',
		],
		'PLbjDnnBIxiErB8M3t_-CDtAh-q9TXdooO' => [ // Polution
			'Features',
			'Requested Features',
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
			'Buildables',
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
		'PLbjDnnBIxiErrTkpIMaw_Tux2tBC2jANp' => [ // Parachute
			'Features',
			'Equipment',
		],
		'PLbjDnnBIxiEolThmLnFdhDVd_6guCwPXS' => [ // Fluids
			'Features',
		],
		'PLbjDnnBIxiEovsoSQPihKXk6g7ttQ95sI' => [ // Weather
			'Environment',
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
		],
		'PLbjDnnBIxiEpI_eZ6JseCdaLBHV5IokQ1' => [ // Creative Mode
			'Features',
			'Game Modes',
		],
		'PLbjDnnBIxiEpP_8-58Z-qL-wqL5BQGgNl' => [ // Peaceful Mode
			'Features',
			'Game Modes',
		],
		'PLbjDnnBIxiEqeYZfeOtegMyeFFDc5NpFT' => [ // Advanced Game Settings
			'Features',
			'Game Modes',
		],
		'PLbjDnnBIxiEooahfGwRQQQM-mLkDwO83I' => [ // Flight Mode
			'Features',
			'Game Modes',
			'Advanced Game Settings',
		],
		'God Mode' => [
			'Features',
			'Game Modes',
			'Advanced Game Settings',
		],
		'Give Items' => [
			'Features',
			'Game Modes',
			'Advanced Game Settings',
		],
		'No Build Cost' => [
			'Features',
			'Game Modes',
			'Advanced Game Settings',
		],
		'No Unlock Cost' => [
			'Features',
			'Game Modes',
			'Advanced Game Settings',
		],
		'Unlock Alternate Recipe Instantly' => [
			'Features',
			'Game Modes',
			'Advanced Game Settings',
		],
		'No Power' => [
			'Features',
			'Game Modes',
			'Advanced Game Settings',
		],
		'Set Starting Tier' => [
			'Features',
			'Game Modes',
			'Advanced Game Settings',
		],
		'Set Game Phase' => [
			'Features',
			'Game Modes',
			'Advanced Game Settings',
		],
		'Unlock All' => [
			'Features',
			'Game Modes',
			'Advanced Game Settings',
		],
		'Unlock All Tiers' => [
			'Features',
			'Game Modes',
			'Advanced Game Settings',
			'Unlock All',
		],
		'Unlock All Research in the M.A.M.' => [
			'Features',
			'Game Modes',
			'Advanced Game Settings',
			'Unlock All',
		],
		'Unlock All in the A.W.E.S.O.M.E. Shop' => [
			'Features',
			'Game Modes',
			'Advanced Game Settings',
			'Unlock All',
		],
		'Keep Inventory' => [
			'Features',
			'Game Modes',
			'Advanced Game Settings',
		],
		'Disable Arachnid Creatures' => [
			'Features',
			'Game Modes',
			'Advanced Game Settings',
		],
		'PLbjDnnBIxiEoPhqRIy60XVEui9X3o6p0S' => [ // Battle Royale
			'Features',
			'Possible Features',
		],
		'PLbjDnnBIxiErqg0B590-PblxF9Yu5aGnR' => [ // World Map
			'Environment',
		],
		'PLbjDnnBIxiErQ8_2rnJ749i4017On9Ss3' => [ // Dylan
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEoKp-47IB5MQO0gc_m6I_k6' => [ // Hypertubes
			'Features',
			'Transportation',
		],
		'PLbjDnnBIxiEpAPImk7F8X3ri0poiQrhf2' => [ // Hypertube Junctions
			'Features',
			'Transportation',
			'Hypertubes',
		],
		'PLbjDnnBIxiEqC9fXtj3M18h0ZeCfZ2u6I' => [ // Jace
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEogzZ5ihqFGBEDRmk9cEgAk' => [ // Jetpack
			'Features',
			'Equipment',
		],
		'PLbjDnnBIxiEp_OX3mKaOyTdkVijB78MU4' => [ // Radar Tower
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEoAqIqsBIdN_uoV-HsP8YDJ' => [ // Snutt
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEpUlcYrbBsHT-yeqGGhuH02' => [ // Snutt's on-stream playthrough
			'Coffee Stainers',
			'Snutt',
		],
		'PLbjDnnBIxiEpCLYuhM0AvprUEscp3hebv' => [ // Snutt's Adventures with Creatures
			'Coffee Stainers',
			'Snutt',
			'Snutt\'s on-stream playthrough',
		],
		'PLbjDnnBIxiErtgprdAliPEnLc7vfNT2Er' => [ // Eevee
			'Coffee Stainers',
			'Snutt',
		],
		'PLbjDnnBIxiErau2lNl-y0Uv9FHhiZc6Pl' => [ // Storage Containers
			'Features',
			'Buildables',
		],
		'Dimensional Depot' => [
			'Features',
			'Buildables',
			'Storage Containers',
		],
		'PLbjDnnBIxiErkd_71h9F1jHbOJBnku5mI' => [ // Superposition Oscillators
			'Features',
			'Crafting',
		],
		'AI Expansion Server' => [
			'Features',
			'Crafting',
		],
		'Dark Matter Crystal' => [
			'Features',
			'Crafting',
		],
		'Dark Matter Residue' => [
			'Features',
			'Crafting',
		],
		'Excited Photonic Matter' => [
			'Features',
			'Crafting',
		],
		'Ficsonium' => [
			'Features',
			'Crafting',
		],
		'PLbjDnnBIxiEr8-tKvQxrwXxCKw4R5_PoP' => [ // Arachnophobia Mode
			'Features',
			'Accessibility',
		],
		'PLbjDnnBIxiEprHLlT4mBHmpA1Hbsdd9TV' => [ // Procedural Generation
			'Features',
			'Unplanned Features',
		],
		'PLbjDnnBIxiEo_Z7JjldxMswBGTxOI9cDH' => [ // Terraforming
			'Features',
			'Unplanned Features',
		],
		'PLbjDnnBIxiEq2kx3e7IHxW0MNZocN_pup' => [ // Tim
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEqVBjXPO21-ZkvLV76plSGV' => [ // Blender
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEp-vHRgDqKw29M-XZJOd8D7' => [ // Ben
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEq3TKcPLzSagH1kjcX0FieC' => [ // Gases
			'Features',
		],
		'PLbjDnnBIxiEr_8eU2Hmzfvu1fyz46917f' => [ // Resource Well Extractor
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEosnfIbQBLgkkxOx-UNe6t8' => [ // Resource Well Pressurizer
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEp2Wt-93hcr6HjooDor6QYr' => [ // Birk
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEo8-KZTgxIJ71UXOd1d9Vn9' => [ // Goat Simulator
			'Off-Topic',
		],
		'PLbjDnnBIxiEqWsWfqsODRxzdiXYbKbHzX' => [ // Hannah
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEqV2RcUFiWJFx2JzyJMRdNX' => [ // Kristoffer
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEoYdHzkwIdOZJ_4WfDeAB-z' => [ // Nathalie
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEp8wlgAM7rcgGivq1zvs1Do' => [ // S.A.M. Ore
			'Environment',
			'Resources',
		],
		'PLbjDnnBIxiErjDwRaqXFiQTvINaWCY2Ne' => [ // Assembler
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEoavILe5ochrdZT10xLLyV9' => [ // AWESOME Sink
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEr6Nss4Y03AxUncqfZfcu1L' => [ // AWESOME Store
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEoD25ogJ2FI5QrNv6DMLSO_' => [ // Biomes
			'Environment',
		],
		'PLbjDnnBIxiEqYVwptXS9sZ59BdRPk4L35' => [ // Red Jungle
			'Environment',
			'Biomes',
		],
		'PLbjDnnBIxiEoOlTxmOqYZ2ZkyD1dtpyz5' => [ // Grass Fields
			'Environment',
			'Biomes',
		],
		'Snaketree Forest' => [
			'Environment',
			'Biomes',
		],
		'PLbjDnnBIxiEpHMQnntpucC_MweaYIIJ_U' => [ // Dune Desert
			'Environment',
			'Biomes',
		],
		'Dune Desert Maze' => [
			'Environment',
			'Biomes',
			'Dune Desert',
		],
		'PLbjDnnBIxiEoCacOLYEU4xHZMKJwPyTzl' => [ // Blue Crater
			'Environment',
			'Biomes',
		],
		'PLbjDnnBIxiErtxu53LKc8Z8vFR13XeKfG' => [ // Northern Forest
			'Environment',
			'Biomes',
		],
		'Maze Canyons' => [
			'Environment',
			'Biomes',
			'Northern Forest',
		],
		'PLbjDnnBIxiEoGfsgeuz9AkG6fxwj9EHJz' => [ // Spire Coast
			'Environment',
			'Biomes',
		],
		'PLbjDnnBIxiErav3ZtCkRrz9pFsE151oDk' => [ // Paradise Island
			'Environment',
			'Biomes',
		],
		'PLbjDnnBIxiEqK9nRVDCg0Z3bb47I6gPod' => [ // Caves
			'Environment',
			'Biomes',
		],
		'PLbjDnnBIxiEo7Ms7bNf5SWMTAacyDC6M1' => [ // Red Bamboo Fields
			'Environment',
			'Biomes',
		],
		'PLbjDnnBIxiEouDCpMNBcTf8W6gqQ85Qzz' => [ // Swamp
			'Environment',
			'Biomes',
		],
		'PLbjDnnBIxiEowf3xp2Div_KZ3R2y-Frn3' => [ // Desert Canyons
			'Environment',
			'Biomes',
		],
		'PLbjDnnBIxiEqI-OK9xVPZNsouE12dCynP' => [ // The Great Canyon
			'Environment',
			'Biomes',
		],
		'PLbjDnnBIxiEq7HBkdsrL-CNtflfQw2AOM' => [ // Rocky Desert
			'Environment',
			'Biomes',
		],
		'Green Valley' => [
			'Environment',
			'Biomes',
		],
		'Gates of Beugernath' => [
			'Environment',
			'Biomes',
		],
		'PLbjDnnBIxiEq-wqDyEyzuV3H3ng4CcdyD' => [ // Titan Forest
			'Environment',
			'Biomes',
		],
		'Crater Lakes' => [
			'Environment',
			'Biomes',
		],
		'PLbjDnnBIxiEqPt1VZbD73L2ZFH2xxqndu' => [ // Eastern Dune Forest
			'Environment',
			'Biomes',
		],
		'Coin Tree Forest' => [
			'Environment',
			'Biomes',
			'Eastern Dune Forest',
		],
		'Abyss Cliffs' => [
			'Environment',
			'Biomes',
		],
		'Western Beaches' => [
			'Environment',
			'Biomes',
		],
		'PLbjDnnBIxiEpj8SNfavR1AzoRISHvpi6Z' => [ // Long Beach
			'Environment',
			'Biomes',
			'Western Beaches',
		],
		'PLbjDnnBIxiEq9lwnDNBMwgh9TCrRWeBPQ' => [ // Beach Islands
			'Environment',
			'Biomes',
			'Western Beaches',
		],
		'PLbjDnnBIxiEq7ogXSsK2gmzp4Z8qejtw-' => [ // Western Dune Forest
			'Environment',
			'Biomes',
		],
		'Southern Forest' => [
			'Environment',
			'Biomes',
		],
		'Desert Mountain Plateaus' => [
			'Environment',
			'Biomes',
		],
		'Lake Forest' => [
			'Environment',
			'Biomes',
		],
		'Jungle Spires' => [
			'Environment',
			'Biomes',
		],
		'Unplanned Biomes' => [
			'Environment',
			'Biomes',
		],
		'PLbjDnnBIxiErkd_Mfx2t-MUAbsERuK3fd' => [ // Fuel
			'Features',
			'Crafting',
		],
		'PLbjDnnBIxiEpeue2wFBDqf_qrb1h1Fvgs' => [ // Lizard Doggo
			'Environment',
			'Creatures',
		],
		'PLbjDnnBIxiEpKeYAwpLKTlSswecwcGpR7' => [ // Lizard Doggo Biology
			'Environment',
			'Creatures',
			'Lizard Doggo',
		],
		'PLbjDnnBIxiEpNdRJ8p4FPF7P0Ll5YIpYV' => [ // M.A.M.
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEpy0dbl8UJNZFunrmillCCR' => [ // Mark
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEoqeifeAJSpwnQVcQUC0FzZ' => [ // Oil
			'Environment',
			'Resources',
		],
		'PLbjDnnBIxiEoUp0wJsZwKk1B0FacoYg-T' => [ // Oil Extractor
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEqr7yS5ijjDUeaivXoHd4Rm' => [ // Pizza
			'Off-Topic',
			'Food & Drink',
		],
		'PLbjDnnBIxiErPq-K_XJmklK3wFLBycvVr' => [ // Refinery
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEo-t9KKeKOJS_Q_pVDfTt9i' => [ // Resource Wells
			'Environment',
		],
		'PLbjDnnBIxiEp9qgkHSjAgFlLTm5-ZWSQD' => [ // Sanctum
			'Off-Topic',
		],
		'PLbjDnnBIxiErRO8WsKyL84ktw50L1KBD9' => [ // Space Elevator
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiErFYB5etMa4dBURmJ9kDAH-' => [ // Valheim
			'Off-Topic',
		],
		'PLbjDnnBIxiErDOgZMrAyAUG1I0PWgMobn' => [ // Space Giraffe-Tick-Penguin-Whale Thing
			'Environment',
			'Creatures',
		],
		'PLbjDnnBIxiEpWutLTcipkGc48H1VdSe2K' => [ // Crab Boss
			'Environment',
			'Creatures',
		],
		'PLbjDnnBIxiEpKl9s2jfplHWpckgIRIh32' => [ // Underwater
			'Environment',
			'Biomes',
			'Unplanned Biomes',
		],
		'PLbjDnnBIxiEoJtf2OIfMXFmMDHaxZJBfg' => [ // Snow
			'Environment',
			'Biomes',
			'Unplanned Biomes',
		],
		'PLbjDnnBIxiEolepny6NyMIt3ItHySMb-x' => [ // Factory Cart
			'Features',
			'Transportation',
			'Vehicles',
		],
		'PLbjDnnBIxiErrg7lK40K9vEj_Jl2dS1hd' => [ // Train Signals
			'Features',
			'Transportation',
			'Trains',
		],
		'PLbjDnnBIxiEqp_c5jfKfMjjFOjbTAWrFE' => [ // Base Defense
			'Features',
			'Unplanned Features',
		],
		'PLbjDnnBIxiEqrupg1p4jryyE_AvpWTWzF' => [ // Truck
			'Features',
			'Transportation',
			'Vehicles',
		],
		'PLbjDnnBIxiEoYQyB8xMKJc0fOKdwncF5o' => [ // Explorer
			'Features',
			'Transportation',
			'Vehicles',
		],
		'PLbjDnnBIxiEojsQkXgy-tvIIjyMK_7x4n' => [ // Nuclear Waste
			'Features',
			'Power Management',
			'Nuclear Energy',
		],
		'PLbjDnnBIxiErz2wvc36rgBLUdiLdLIQdb' => [ // Autosave
			'Features',
			'Save System',
		],
		'PLbjDnnBIxiEqDSLRIdyHmUlI71j458DqR' => [ // Lights
			'Features',
			'Buildables',
		],
		'PLbjDnnBIxiErLIqP7klFJBCBImVRLTrqD' => [ // Linus
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEquIZfkx9IHxCSgbaA1JK7r' => [ // Tier 0
			'Features',
			'Tiers',
		],
		'PLbjDnnBIxiEqKW01FG2TkgBDQO9NtmuJx' => [ // Tier 1
			'Features',
			'Tiers',
		],
		'PLbjDnnBIxiEotFjYXBVhIYTrMvWMYeDfw' => [ // Tier 2
			'Features',
			'Tiers',
		],
		'PLbjDnnBIxiEqY2nvhibREOqsw2uQ7lXJV' => [ // Tier 3
			'Features',
			'Tiers',
		],
		'PLbjDnnBIxiEroI7PiwagLCfedWDhcakIN' => [ // Tier 4
			'Features',
			'Tiers',
		],
		'PLbjDnnBIxiErC6mOXeFD7RPvN8qZaju8f' => [ // Tier 5
			'Features',
			'Tiers',
		],
		'PLbjDnnBIxiEp40Wz_gOPUUxdAb9fDf9QG' => [ // Tier 6
			'Features',
			'Tiers',
		],
		'PLbjDnnBIxiEqZ6jA6KOjykO3SA7o4k5NL' => [ // Tier 7
			'Features',
			'Tiers',
		],
		'PLbjDnnBIxiEruS8Xi3bkV9I-Hi-0E8DO7' => [ // Tier 8
			'Features',
			'Tiers',
		],
		'PLbjDnnBIxiEp4Qe-sQjkMStIywC-iIGDR' => [ // Tier 9
			'Features',
			'Tiers',
		],
		'PLbjDnnBIxiEqf1Wl9_sGGwfZG55fsCNbS' => [ // Tier 10
			'Features',
			'Tiers',
		],
		'PLbjDnnBIxiEoXhNOpIRZfGPOUVxgPQFBa' => [ // Physics
			'Technology',
		],
		'PLbjDnnBIxiErS9sKol90eUQvUOe0Bl_GI' => [ // Wiki
			'Community',
		],
		'PLbjDnnBIxiEps-bmHeQJHnTRQ-AsP3YfL' => [ // Joshie
			'Community',
		],
		'Haigen' => [
			'Community',
		],
		'PLbjDnnBIxiEob8i8BnGXs3MaviNDLKxQ5' => [ // Mercer Sphere
			'Environment',
			'Resources',
		],
		'PLbjDnnBIxiErVEFr3VZqHIAHfUd0Z7AUj' => [ // Somersloop
			'Environment',
			'Resources',
		],
		'PLbjDnnBIxiEqfFrKHQh-XOvtmMN7F0Rqa' => [ // Deep Rock Galactic
			'Off-Topic',
		],
		'PLbjDnnBIxiEp0LTS_gLsiacb7JQimuAra' => [ // The Cycle
			'Off-Topic',
		],
		'PLbjDnnBIxiEpXfdIxaVNlbrgTajarPMeg' => [ // Midnight Ghost Hunt
			'Off-Topic',
		],
		'PLbjDnnBIxiEqKzRDBMtWg2XJqwJzLgmQY' => [ // G2
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEri_ODC3QlSZgQ1QsAKEX4K' => [ // Markus
			'Coffee Stainers',
		],
		'PLbjDnnBIxiErkymYcX3dP0NvEKzXeYON8' => [ // Panakotta
			'Community',
		],
		'PLbjDnnBIxiEq-TyD3B3FYRk-YaTyPt7_k' => [ // Semlor
			'Off-Topic',
			'Food & Drink',
		],
		'PLbjDnnBIxiEpWd9yLFdA-rdx0uR6EJfRh' => [ // Farming
			'Features',
			'Possible Features',
		],
		'PLbjDnnBIxiErj1SNTDxK3-OgUFy3b9x9U' => [ // Ficsit Farming
			'Mods',
		],
		'PLbjDnnBIxiEpT_HG6yiXztZ5oNXPOutgR' => [ // Space Exploration
			'Features',
			'Unplanned Features',
		],
		'Characters' => [
			'Story & Lore',
		],
		'PLbjDnnBIxiErZY7hh-HMSgx77BlCLUgRb' => [ // ADA
			'Story & Lore',
			'Characters',
		],
		'PLbjDnnBIxiEpCVGFI_9IN1KQVz_CTwCRP' => [ // Marie
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEontiBSe54bXKMMdsRG7Q3_' => [ // Josh
			'Community',
		],
		'PLbjDnnBIxiEopIEcpam21k5zDe3w-I0oA' => [ // Steam Workshop
			'Mods',
			'Official Mod Support',
		],
		'PLbjDnnBIxiEpwHLyUK0939CsZtMpZgu56' => [ // Crossplay
			'Features',
			'Multiplayer',
		],
		'PLbjDnnBIxiEqaTHSimlEIVkVtJC8gMWrh' => [ // Smerkin
			'Community',
		],
		'PLbjDnnBIxiEqHIuDkJqcpobQ_z7NknHVJ' => [ // Caterina Parks
			'Story & Lore',
			'Characters',
		],
		'PLbjDnnBIxiEpZz_Py2KcHNItA7jY1nTrB' => [ // Factorio
			'Off-Topic',
		],
		'PLbjDnnBIxiEqRTal-bQ-tlGQPcGhOg1UL' => [ // FicsIt-Networks
			'Mods',
		],
		'PLbjDnnBIxiErs1LeFED3Y0pNoUtFtZHNu' => [ // Infinifactory
			'Off-Topic',
		],
		'PLbjDnnBIxiErdRwJCqU5y5d6Hxm2lKd46' => [ // Kibitz
			'Community',
		],
		'PLbjDnnBIxiErSWX2noEv1twPQ9F4lRZi1' => [ // Neshkor
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEqq2pvrf2fFltB6Nq9SX_4H' => [ // Ros
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEoePaWQkbGQfaANu4qRPb6C' => [ // Steel
			'Environment',
			'Resources',
		],
		'PLbjDnnBIxiEr4KkkPWBDMJdu4Y4S3Pn7x' => [ // Steve
			'Story & Lore',
			'Characters',
		],
		'PLbjDnnBIxiEoNob1WtMvqzwc3KqxW9dvM' => [ // VR
			'Features',
			'Unplanned Features',
		],
		'PLbjDnnBIxiEqV3H-fkFZVMqPZZ8M-Sexh' => [ // Water Extractor
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEoN8SCD5zn_8BK5d2HV34sw' => [ // Baine
			'Community',
		],
		'PLbjDnnBIxiEr6QJ8QlJx7grzrcplzUTDt' => [ // Zip Lines
			'Features',
			'Equipment',
		],
		'PLbjDnnBIxiEozm5BT2Qi4Mvu9uI6ga2uJ' => [ // Linux
			'Technology',
		],
		'PLbjDnnBIxiEoVPxq0wwyMpJfdC5xitGeZ' => [ // Save System
			'Features',
		],
		'PLbjDnnBIxiEpO7bRoOBAsKPkxnUtC0Rdp' => [ // Cloud Sync
			'Features',
			'Save System',
		],
		'PLbjDnnBIxiEqfDAWTcOyXdQBpZx1oiEim' => [ // Tractor
			'Features',
			'Transportation',
			'Vehicles',
		],
		'PLbjDnnBIxiEqPm28aPUU6rg5QQLbrnpRy' => [ // Autopilot
			'Features',
			'Transportation',
			'Vehicles',
		],
		'PLbjDnnBIxiEopYhuGHxbk-YOT6Fyy0pU8' => [ // Custom Component: Instanced Spline Mesh
			'Technology',
			'Unreal Engine',
		],
		'PLbjDnnBIxiEqt8EmeHWLHcgo5uLhafUAi' => [ // Microtransactions
			'Features',
			'Unplanned Features',
		],
		'PLbjDnnBIxiEqv3IjSWWO1kTyDloN8abwF' => [ // Loot Boxes
			'Features',
			'Unplanned Features',
		],
		'PLbjDnnBIxiErF6kzFEUGxmP_TLQUXbkxm' => [ // Food Court
			'Features',
			'Buildings',
			'Space Elevator',
		],
		'PLbjDnnBIxiErZMU9GqSYTxgx4QvM_i2hK' => [ // Achievements
			'Features',
		],
		'PLbjDnnBIxiEoIpaioyzE9nqrU_5CxfkUC' => [ // Miner
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEqRBeGXVroVP5HrbSUxSTz6' => [ // Biomass Burner
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEp472ylbvaGd3FDUiIP7MbR' => [ // Non Flying Bird
			'Environment',
			'Creatures',
		],
		'PLbjDnnBIxiEqf-Zh7cNTVq_Ad6E6PgK3r' => [ // Smelter
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEqRDaqJAxsDu8ejfRc_h1fn' => [ // Constructor
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEouF1RjsE2gpHJhjhoHNlCP' => [ // Coffee Stain Holding
			'Embracer Group',
		],
		'PLbjDnnBIxiEqOpJsmyxhwRVWzfYKwmIKI' => [ // Gearbox Software
			'Embracer Group',
		],
		'PLbjDnnBIxiErLM-OUYLIMpPRKrDekzBfT' => [ // Giant Flying Manta
			'Environment',
			'Creatures',
		],
		'PLbjDnnBIxiEruD11x_g9qPkDYlsRkEYNz' => [ // Smart!
			'Mods',
		],
		'PLbjDnnBIxiEoLg1lGISuIdkOC1u2PuiCe' => [ // Analytics
			'Technology',
		],
		'PLbjDnnBIxiEqY1dlDDnwf4Fo9Z9l7YtM4' => [ // DLSS
			'Technology',
			'Graphics',
		],
		'PLbjDnnBIxiErp1LdzC2TRke-xZQJTc3m5' => [ // Power Storage
			'Features',
			'Power Management',
		],
		'PLbjDnnBIxiEqd09Y91Qt4sfvmiIY_jN_J' => [ // Ray Tracing
			'Technology',
			'Graphics',
		],
		'PLbjDnnBIxiEoJs6pgkkriityQb1BaPgnx' => [ // Simon Saga
			'Coffee Stainers',
			'Simon',
		],
		'PLbjDnnBIxiEo6DXBwcUjUqDH7Mqjx5zmM' => [ // Terrible Jokes
			'Off-Topic',
		],
		'PLbjDnnBIxiErPmwCcdEax-8TIvyvjdlm2' => [ // Mac
			'Technology',
		],
		'PLbjDnnBIxiEoRw9O4Lx_d3-RNK0Grlm1c' => [ // Epic Store
			'Retail',
		],
		'PLbjDnnBIxiEofikmXnRJhU7m4yhEZfPhH' => [ // Epic Store Exclusivity
			'Retail',
			'Epic Store',
		],
		'PLbjDnnBIxiEoztbnzfvEZjurwzZnL1wv4' => [ // Steam Store
			'Retail',
		],
		'PLbjDnnBIxiEqZex4vbgYkVTsT4vBy6ycE' => [ // Steam Release
			'Retail',
			'Steam Store',
		],
		'PLbjDnnBIxiEqe6yJj8TvkF6VxhLGiah3b' => [ // Uzu
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEqRI8yWKc7Oxm5T5P3q4Kx6' => [ // Sacha
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEr-AG-93Qqd43nuzOUIcufC' => [ // Power Switch
			'Features',
			'Power Management',
		],
		'PLbjDnnBIxiEoxIy2QXhvKUmi6kTjM4sVS' => [ // Blade Runners
			'Features',
			'Equipment',
		],
		'PLbjDnnBIxiErp1tjQWToGbsDM6fsjInME' => [ // Recipes
			'Features',
			'Crafting',
		],
		'PLbjDnnBIxiEqkEomU289GxvxUQx8hM_i8' => [ // Alternate Recipes
			'Features',
			'Crafting',
			'Recipes',
		],
		'PLbjDnnBIxiEqa47-NYtnxl93i8azA089W' => [ // Object Limit
			'Technology',
			'Unreal Engine',
		],
		'PLbjDnnBIxiEpMIxj6CKI55n7fF0PTReng' => [ // Early Access
			'Retail',
		],
		'PLbjDnnBIxiEq8_QoVM--eNIMMoOq1IlfE' => [ // Nuclear Refinement
			'Features',
			'Power Management',
			'Nuclear Energy',
		],
		'PLbjDnnBIxiEqV2AcIJNb7Y3TkYLQ3Bif-' => [ // Drones
			'Features',
			'Transportation',
			'Vehicles',
		],
		'PLbjDnnBIxiEoJXfr9MUsQgAjnRHJrZJWM' => [ // Hover Pack
			'Features',
			'Equipment',
		],
		'PLbjDnnBIxiEoxTyK_HelUP2U9cdXFgVK6' => [ // Particle Accelerator
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEr_iLGFpJxH-pKRn9D0qsna' => [ // Water
			'Environment',
			'Resources',
		],
		'PLbjDnnBIxiEogLaUpHfzYrDFWtqkpZ-Fw' => [ // GeForce Now
			'Retail',
		],
		'PLbjDnnBIxiEpeF2A25b6waQZkQA2EMlvx' => [ // Cyber Wagon
			'Features',
			'Transportation',
			'Vehicles',
		],
		'PLbjDnnBIxiEoBmj-QZNgr4liNREljxxSr' => [ // Walkways
			'Features',
			'Buildables',
		],
		'PLbjDnnBIxiEo9mOjyYPzHqEmuA09FKcgd' => [ // Community Highlights
			'Community',
		],
		'PLbjDnnBIxiErIOO8TJyFU5Z1Snw8Ze7-O' => [ // Blastroid
			'Community',
		],
		'Light It Up' => [
			'Mods',
		],
		'PLbjDnnBIxiEoqFaAHiwnkN6nPi2T2LPzi' => [ // Lars
			'Embracer Group',
			'THQ Nordic',
		],
		'PLbjDnnBIxiEoAITjLL1cZSEVM6EPXPKyu' => [ // THQ Nordic
			'Embracer Group',
		],
		'PLbjDnnBIxiEr245KxK6CPHEThfipuYc3R' => [ // Satisfactory News
			'Community',
		],
		'PLbjDnnBIxiEq_Sp5_CYEUpa-kgDN5m6Tf' => [ // Alex
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEoik7dzzDmMntF6tKuv-my2' => [ // Sweden
			'Off-Topic',
		],
		'PLbjDnnBIxiErJj29LAC45t7vdxXY53esO' => [ // Nuclear Pasta
			'Environment',
			'Resources',
		],
		'PLbjDnnBIxiEq3w8mU-U9_obtyMVEPYDVO' => [ // Pontus
			'Coffee Stainers',
		],
		'PLbjDnnBIxiErKnSCIxjj74FJZ_655tJXK' => [ // DrawingXaos
			'Community',
		],
		'PLbjDnnBIxiEpUuYgu0-pxFGsbN2YXpbOP' => [ // Blueprints
			'Features',
		],
		'PLbjDnnBIxiEomDrLRY8jOvB_l8DEGgrnG' => [ // Satisfactory Calculator
			'Community',
		],
		'PLbjDnnBIxiEq4mhfpNDUXpX3dVhF5Vnmw' => [ // Rifle
			'Features',
			'Equipment',
		],
		'PLbjDnnBIxiEq6HX-1Nude7wjby2B29wEt' => [ // Multiple Body Slots
			'Features',
		],
		'PLbjDnnBIxiEqQq_lP1_OYqhjcEng5hkOx' => [ // Gas Mask
			'Features',
			'Equipment',
		],
		'PLbjDnnBIxiEpTVb0_x1OKn63RM6I9ScVq' => [ // Color Gun
			'Features',
			'Equipment',
		],
		'PLbjDnnBIxiEpQHQYvzPxQNBl2NvsugIO0' => [ // Holstering Equipment
			'Features',
		],
		'PLbjDnnBIxiEo53bRP06aC5lXPWjSx84Cx' => [ // Cup
			'Features',
			'Equipment',
		],
		'PLbjDnnBIxiEqy8rMgGZL8AmMomES_Hzf8' => [ // Holograms
			'Technology',
			'User Interface',
		],
		'PLbjDnnBIxiEqUEBB3kU_ZkkEp6YBvweep' => [ // Golf
			'Features',
			'Unplanned Features',
		],
		'PLbjDnnBIxiEot9p9zUqcWo-SABtobaM9V' => [ // Gnutt
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEoIWT_BxcmHV8fcZ1m38gGz' => [ // Sofi
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEqFD6h2jA0BQQ2mkp0R5joi' => [ // Cheatcrete
			'Features',
			'Buildables',
			'Foundations',
		],
		'PLbjDnnBIxiEpQNWBETWpyzpgjN3Q6bHnj' => [ // Snutt Burger Time
			'Coffee Stainers',
			'Snutt',
		],
		'PLbjDnnBIxiErx_HMSb_4tVLA_MBo9bphw' => [ // Tom
			'Community',
		],
		'PLbjDnnBIxiEppNaw6qpmiVy9MlWIiTQnY' => [ // SignpostMarv
			'Community',
		],
		'PLbjDnnBIxiEq__Ywoco-mzedChOI5rI5s' => [ // Weebl
			'Community',
		],
		'PLbjDnnBIxiEoaUKIHhuYY0F1iQexBj2ll' => [ // Distance Fields
			'Technology',
			'Unreal Engine',
		],
		'PLbjDnnBIxiEpS1BDtD6ZonY3QdNz6g9Gj' => [ // DirectX
			'Technology',
		],
		'PLbjDnnBIxiEpOl-wg1r4-qo3TyvXUy-ev' => [ // Requested Features
			'Features',
		],
		'PLbjDnnBIxiEqGt2sjZVdmGPpirMrXcDkA' => [ // Elevators
			'Features',
			'Requested Features',
		],
		'PLbjDnnBIxiEqpoKbEqKcyh5D006HnL3xJ' => [ // Underground Biome
			'Features',
			'Possible Features',
		],
		'PLbjDnnBIxiErB5o0Ng2yayZnaugDJX4BM' => [ // Setting up a Coal Generator
			'June 2021 Epic Mega Sale Stream',
		],
		'PLbjDnnBIxiEphc1QenCFLI8cOhJF8szkh' => [ // Power Slug
			'Environment',
			'Resources',
		],
		'PLbjDnnBIxiErkUXKPTQ6N5HfY2SOgKGQg' => [ // Setting up Modular Frame production
			'June 2021 Epic Mega Sale Stream',
		],
		'PLbjDnnBIxiErcP0g2Ihv6H7ZJUVB8sOqH' => [ // Overclocking & Underclocking
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEonaHIGbDAvB_2O_6WVLwSp' => [ // Overclocked Mk.3 Miner output bottlenecked by Mk.5 Belts
			'Features',
			'Buildings',
			'Overclocking & Underclocking',
		],
		'PLbjDnnBIxiErj8197l1Qd4RzczUtlYgF4' => [ // Coal
			'Environment',
			'Resources',
		],
		'PLbjDnnBIxiEr1rRsrKhfeFO8lpQ3ETpbe' => [ // Coal Generator
			'Features',
			'Buildings',
		],
		'Alien Power Augmenter' => [
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiErc9wI1JHHSzSswjQs9PRdF' => [ // Dimensional Depot
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEqxcLZFqCYFQ3rWBOcmY_Mw' => [ // Quantum Converter
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEokd3ivH8Ri4t0-rOcr6f3g' => [ // Quantum Encoder
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiEqE6cyJPicfKslpaX5zwjCq' => [ // Unreal Engine 5
			'Technology',
			'Unreal Engine',
		],
		'PLbjDnnBIxiEpb1WTHN6Sos7AvvW-rY_Cr' => [ // Subnautica
			'Off-Topic',
		],
		'PLbjDnnBIxiEpKiw3DB9RBHUTClS4pQa5s' => [ // Crosovers
			'Features',
			'Requested Features',
		],
		'PLbjDnnBIxiErnqhP4QT4xxKBYd7q9wqH5' => [ // Roadmap
			'Features',
		],
		'PLbjDnnBIxiEqyvBwUfe5uPRaYXnxqzV7F' => [ // Food & Drink
			'Off-Topic',
		],
		'PLbjDnnBIxiEoAocvkqdiufABlldkC0MiI' => [ // Coffee
			'Off-Topic',
			'Food & Drink',
		],
		'PLbjDnnBIxiEpAdZpi9bywSwC-YRlCfSq4' => [ // Milk
			'Off-Topic',
			'Food & Drink',
		],
		'PLbjDnnBIxiEqfR1MnhVFg3l7gIipzbOSD' => [ // Songs of Conquest
			'Off-Topic',
		],
		'PLbjDnnBIxiErOg6qL6X20cbjiFMP7nhFj' => [ // Torsten
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEq56xXfXdlR0ILAI6JtYU41' => [ // E3
			'Off-Topic',
		],
		'PLbjDnnBIxiEorrhm28SKQy37Zdc9gfiMr' => [ // EU Merch Store
			'Merch',
		],
		'Merch Prototypes' => [
			'Merch',
		],
		'FICSIT Cup Prototypes' => [
			'Merch',
			'Merch Prototypes',
		],
		'PLbjDnnBIxiEovRzGRSnxOCvPCJODjbey_' => [ // Stefan
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEp9DQ8xVeTCFqcFSLB_9tJj' => [ // Buildables
			'Features',
		],
		'PLbjDnnBIxiEqY7gI4bEGW0mH9AWoFv7P6' => [ // Third-person View
			'Features',
			'Requested Features',
		],
		'PLbjDnnBIxiErB8iPe1PpO-x4cGtPIEN24' => [ // Build Modes
			'Features',
		],
		'PLbjDnnBIxiEo6-Jsza66SWDUgX688DEh5' => [ // Flushable Toilet
			'Features',
			'Buildings',
			'The HUB',
		],
		'PLbjDnnBIxiEprsambYVkRLAij70izxfVD' => [ // Toilet Paper DLC
			'Features',
			'Possible Features',
			'DLC',
		],
		'PLbjDnnBIxiEq6PNjgFFfdxBwNkW0KOJeI' => [ // Factory Maintenance
			'Features',
			'Unplanned Features',
		],
		'PLbjDnnBIxiErkR6x1_jWiiIrfVBF2fatT' => [ // Mk.2 Buildings
			'Features',
			'Unplanned Features',
		],
		'PLbjDnnBIxiErwuxp-AwGwlUCua_gQh6QU' => [ // Ultrawide Monitors
			'Technology',
			'Graphics',
		],
		'PLbjDnnBIxiErGWyROgM1QN46mY7Am9SgY' => [ // LOD
			'Technology',
			'Graphics',
		],
		'PLbjDnnBIxiEr68wcM_BJQsE35xXzjmwqX' => [ // Global Build Grid
			'Features',
		],
		'PLbjDnnBIxiEoYcnK5o9ipaJlkK58MApga' => [ // Vulkan
			'Technology',
		],
		'PLbjDnnBIxiErDv_Gc-PQatZJDvGk1JA9l' => [ // Project Assembly
			'Story & Lore',
		],
		'PLbjDnnBIxiEpVJCCTQQ_c4bLewQLEd0Bv' => [ // Localization Community Highlight
			'Localisation',
		],
		'PLbjDnnBIxiEptL0Ii53upU5uykokXvIpE' => [ // McGalleon
			'Community',
		],
		'PLbjDnnBIxiEqF0rRv9aGsHsbBLogS1lLp' => [ // RogerHN
			'Community',
		],
		'PLbjDnnBIxiEp8Ab7iwgw8Y9_QVtMmfH_t' => [ // PionR
			'Features',
			'Requested Features',
		],
		'PLbjDnnBIxiErXB1CoQVmEvgh_SrtAj90t' => [ // Australia
			'Off-Topic',
		],
		'PLbjDnnBIxiEooqHzCQjhtwuRXP4-M-ojs' => [ // Bacon
			'Off-Topic',
			'Food & Drink',
		],
		'PLbjDnnBIxiEpoFpjdF4FZ_AMTdxdsmO1U' => [ // Coffee Stainers can't pronounce hannah's last name
			'Coffee Stainers',
			'Hannah',
		],
		'PLbjDnnBIxiEoUB0-Iz-ci4sxnKfg1zVa_' => [ // "Fix Jace" QA Site Posts
			'Coffee Stainers',
			'Jace',
		],
		'PLbjDnnBIxiEoG6lwU-O6r80aNvpbMk7HK' => [ // Jace Art
			'Coffee Stainers',
			'Jace',
		],
		'PLbjDnnBIxiEp58J3v3DhH_Z9Gh5uKVi7H' => [ // Coffee Stain North
			'Embracer Group',
			'Coffee Stain Holding',
		],
		'PLbjDnnBIxiEptX96QtBua2K2IhL4SbwWv' => [ // Lavapotion
			'Embracer Group',
			'Coffee Stain Holding',
		],
		'PLbjDnnBIxiEoUgIUnNtnxxto0lrwkWz9V' => [ // Coffee Stain Studios
			'Embracer Group',
			'Coffee Stain Holding',
		],
		'PLbjDnnBIxiEon4YC9uF1z7ghCoifQKLnH' => [ // Coffee Stain Gothenburg
			'Embracer Group',
			'Coffee Stain Holding',
		],
		'PLbjDnnBIxiEqTZMJS_m2VuTmUNM_Vh_JQ' => [ // Easy Trigger Games
			'Embracer Group',
			'Coffee Stain Holding',
		],
		'PLbjDnnBIxiEoxEs7Cr_q6_oCNolhCLydl' => [ // Box Dragon
			'Embracer Group',
			'Coffee Stain Holding',
		],
		'Ghost Ship Games' => [
			'Embracer Group',
		],
		'PLbjDnnBIxiEoFLmKQaDWq_Rl7qd-H_GIA' => [ // Flannel
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEqNe4SQNEAvhKRLQX39g9Ix' => [ // #SaveTheWillows
			'Satisfactory Updates',
			'Released',
			'Satisfactory Update 5',
		],
		'PLbjDnnBIxiEoRdndnsQZEZnZcL35VZRmD' => [ // Northern Forest World Update Q&A with Hannah
			'Satisfactory Updates',
			'Released',
			'Satisfactory Update 5',
		],
		'PLbjDnnBIxiEq2WihzbvSUt1YdFzZ4CBP8' => [ // Meza
			'Community',
		],
		'PLbjDnnBIxiEpUYS6KsdUeKIGxi-NsEL_0' => [ // Unplanned Features
			'Features',
		],
		'PLbjDnnBIxiErA3azFQ6tmG_bui881Xkpm' => [ // I Love Strawberries
			'Off-Topic',
		],
		'PLbjDnnBIxiEqASInsJuI3DFUgB2Tzv01M' => [ // Railings
			'Features',
			'Buildables',
		],
		'PLbjDnnBIxiErZo9O0eu76IWRdAr1DQ0p6' => [ // Roofs
			'Features',
			'Buildables',
		],
		'PLbjDnnBIxiEpb4NX67UDEpTqags7V2VGB' => [ // Windows
			'Features',
			'Buildables',
		],
		'PLbjDnnBIxiErS0f-MViNtR-_BU0wrUNxS' => [ // Beams
			'Features',
			'Buildables',
		],
		'PLbjDnnBIxiEr7u2HkV8x8-1lzS6wvH9fr' => [ // Pillars
			'Features',
			'Buildables',
		],
		'PLbjDnnBIxiEq3FD_LvPcP2toMFd8Go0Gm' => [ // Power Tower
			'Features',
			'Buildables',
		],
		'PLbjDnnBIxiErG8xome5LS0FQBrQsdR_vb' => [ // Hard & Soft Clearance
			'Features',
			'Build Modes',
		],
		'PLbjDnnBIxiErGzhsUN4suUKqAYQpEPrbm' => [ // Quick Switch
			'Features',
			'Build Modes',
		],
		'PLbjDnnBIxiEolMY_wpcZjAioLu7rg_Ol2' => [ // Nudge Mode
			'Features',
			'Build Modes',
		],
		'PLbjDnnBIxiEp1xjfUrcMp_nkvxSUd5_Pf' => [ // Blueprint Dismantle Mode
			'Features',
			'Build Modes',
		],
		'PLbjDnnBIxiEo_Zh8wGap9N34W6DrbfWN-' => [ // Zooping
			'Features',
			'Build Modes',
		],
		'PLbjDnnBIxiEobP4EgydtWaC_BJKd0w9Vi' => [ // Rasmus
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEqilvgLbk4PUBQSyokspJyD' => [ // Tobias
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEpJ3RlWlRcK-b0kRERkWQvC' => [ // Foundation Stencils
			'Features',
			'Buildables',
			'Foundations',
		],
		'PLbjDnnBIxiEoBGB6CsIIsXKJwXx_cRwrg' => [ // Update 5 Torsten's Cosmetics Whiteboard
			'Satisfactory Updates',
			'Released',
			'Satisfactory Update 5',
		],
		'PLbjDnnBIxiEoS0LAKAPcnVrP2IDwb4kqi' => [ // Jace's HelloFresh Deliveries
			'Coffee Stainers',
			'Jace',
		],
		'PLbjDnnBIxiErnQCb-1B-c4ZGlEyHNjJeJ' => [ // Xeno-Zapper
			'Features',
			'Equipment',
		],
		'PLbjDnnBIxiErK7fd-2751XSaF1RQrw2a8' => [ // Xeno-Basher
			'Features',
			'Equipment',
		],
		'PLbjDnnBIxiEpBFizujOmT7WUoGNxq-qFg' => [ // Resource Scanner
			'Features',
			'Equipment',
		],
		'PLbjDnnBIxiEpuOTI51V9B1koMUCsNYuIA' => [ // Nobelisk
			'Features',
			'Equipment',
		],
		'PLbjDnnBIxiEpMcQCwqBC_U-M431lx1DoG' => [ // Nobelisk Detonator
			'Features',
			'Equipment',
			'Nobelisk',
		],
		'PLbjDnnBIxiErBLos6ENvogn0gMaKYXchP' => [ // Stockholm
			'Off-Topic',
			'Sweden',
		],
		'PLbjDnnBIxiEopm69tGZl3zGYMEiuNszkt' => [ // Gothenburg
			'Off-Topic',
			'Sweden',
		],
		'PLbjDnnBIxiEr2vtUnFdTP3FRsy4HvyZhc' => [ // Skövde
			'Off-Topic',
			'Sweden',
		],
		'PLbjDnnBIxiEq_ghuvlyXJ2TEs1dfgjh-3' => [ // Complex Clearance
			'Features',
			'Build Modes',
			'Hard & Soft Clearance',
		],
		'PLbjDnnBIxiEozxUoff-ZKsVPXst-9HXPY' => [ // Update 5 Wager on releasing Golf versus Dedicated Servers
			1,
			'Satisfactory Updates',
			'Teasers & Trailers',
			'Update 5 Patch Notes Video',
		],
		'PLbjDnnBIxiEoMPT69J5UFBMeR3yqm2XEY' => [ // Snutty Mays & Juice Velvet Present: The Customizer™
			2,
			'Satisfactory Updates',
			'Teasers & Trailers',
			'Update 5 Patch Notes Video',
		],
		'PLbjDnnBIxiErHf4MzxQldu5ULKXwa8BhQ' => [ // Customizer
			'Features',
			'Equipment',
		],
		'Finishes' => [
			'Features',
		],
		'PLbjDnnBIxiErPJIX0NWiekKXFIrs8d4mO' => [ // Snutty Mays
			'Coffee Stainers',
			'Snutt',
		],
		'PLbjDnnBIxiEosygjoYTHIREXy9ZtlTDfQ' => [ // Juice Velvet
			'Coffee Stainers',
			'Jace',
		],
		'PLbjDnnBIxiEpRC5dr7s1leuOT-eStCxyU' => [ // Floor Holes
			'Features',
			'Buildables',
		],
		'PLbjDnnBIxiEoXYctP6dDr666y85Qe74Nl' => [ // Vilsol
			'Community',
		],
		'PLbjDnnBIxiEryYdXQnzrtmN8P6h6BSsA2' => [ // Coffee Stainer Karaoke
			0,
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEq4wbB8B6tiSFThCg1tfmAu' => [ // Hot Potato Save File
			1,
			'Coffee Stainers',
		],
		'Oscar' => [
			'Coffee Stainers',
		],
		'PLbjDnnBIxiErr7298DNhFlly1QnCyG6iu' => [ // Jannik
			'Coffee Stainers',
		],
		'Joel' => [
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEosbwjvWVnak8Pn-9SbPcI2' => [ // Etienne
			'Coffee Stainers',
		],
		'Anna' => [
			'Coffee Stainers',
		],
		'Bogdan' => [
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEr_4xLQAm_Jk85CJRW6hjhT' => [ // Lym
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEoun-5sdS3OekZiIK9hdQkd' => [ // Final Fantasy
			'Off-Topic',
		],
		'PLbjDnnBIxiErdTAJdeOIHMN2m3pR245LA' => [ // Robo Jace
			'Coffee Stainers',
			'Jace',
		],
		'PLbjDnnBIxiEpxr6FUca-fshICRZu7e6zj' => [ // The Official Satisfactory PODCAST
			'Off-Topic',
			'Final Fantasy',
		],
		'PLbjDnnBIxiEq1h5_NQ0g0vcgaXGQ2k-Dq' => [ // Coffee Stain Malmö
			'Embracer Group',
			'Coffee Stain Holding',
		],
		'PLbjDnnBIxiEq9lna6BfVSSq_ahKhtr0j7' => [ // Coffee Stain Publishing
			'Embracer Group',
			'Coffee Stain Holding',
		],
		'Sprint 1' => [
			'Satisfactory Updates',
			'Satisfactory Prototypes',
		],
		'Sprint 2' => [
			'Satisfactory Updates',
			'Satisfactory Prototypes',
		],
		'Sprint 3' => [
			'Satisfactory Updates',
			'Satisfactory Prototypes',
		],
		'Sprint 6' => [
			'Satisfactory Updates',
			'Satisfactory Prototypes',
		],
		'Sprint 8' => [
			'Satisfactory Updates',
			'Satisfactory Prototypes',
		],
		'Sprint 12' => [
			'Satisfactory Updates',
			'Satisfactory Prototypes',
		],
		'Sprint 20' => [
			'Satisfactory Updates',
			'Satisfactory Prototypes',
		],
		'Sprint 23' => [
			'Satisfactory Updates',
			'Satisfactory Prototypes',
		],
		'Sprint 26' => [
			'Satisfactory Updates',
			'Satisfactory Prototypes',
		],
		'PLbjDnnBIxiErlTL3QMfUXxSdriWgMu4n2' => [ // Portal
			'Off-Topic',
		],
		'PLbjDnnBIxiEqxitfhaNUPTZSUGulBEU9u' => [ // Elden Ring
			'Off-Topic',
		],
		'PLbjDnnBIxiErhPBfSCwaZO_ToMm3FyVFu' => [ // J1mbers
			'Community',
		],
		'PLbjDnnBIxiEoT479WM0xVC9zbUY_nHJRS' => [ // Eat shit, chat!
			'Coffee Stainers',
			'Snutt',
		],
		'PLbjDnnBIxiEoKi2d-j2Gh6JCb_5zeGEJ4' => [ // Beacon
			'Features',
			'Equipment',
		],
		'PLbjDnnBIxiEqEAR3Wx0pIkb07wtrRi2qL' => [ // Markers
			'Technology',
			'User Interface',
		],
		'PLbjDnnBIxiEpC-NAZq1zvnHD5fWHVqAUw' => [ // Stamps
			'Technology',
			'User Interface',
		],
		'PLbjDnnBIxiEpqlYReUyGQTwlAccY_VXj-' => [ // Ping
			'Technology',
			'User Interface',
		],
		'PLbjDnnBIxiEoDvA8WqCrij0iTUq44vMju' => [ // The Legend of Zelda
			'Off-Topic',
		],
		'PLbjDnnBIxiEp0PLs0-wTfqXeuWWvookwm' => [ // The Lord of the Rings
			'Off-Topic',
		],
		'PLbjDnnBIxiEoAUpfDNoyyk9jvgkUasWtM' => [ // Crash Site
			'Features',
			'Buildings',
		],
		'PLbjDnnBIxiErY8-S-JLKk_ewHAJHUrzwM' => [ // Rebar Gun
			'Features',
			'Equipment',
		],
		'PLbjDnnBIxiEqYcK9flkYreg2WOtulKEki' => [ // Boom Box
			'Features',
			'Equipment',
		],
		'PLbjDnnBIxiEqdAntA83_NEfB5D19rGlwh' => [ // Its_BitZ
			'Community',
		],
		'PLbjDnnBIxiErMbbLL3R98Zi20naPbgCUT' => [ // Blueprint Designer
			'Features',
			'Buildings',
		],
		'Blueprint Designer Mk.2' => [
			'Features',
			'Buildings',
			'Blueprint Designer',
		],
		'PLbjDnnBIxiErnlskkk5ZE5FdX9Zo_qH_H' => [ // Runescape
			'Off-Topic'
		],
		'PLbjDnnBIxiEr_TAa3FE8nm5yA3FCPl0SS' => [ // Monkey Island
			'Off-Topic',
		],
		'ChatGPT' => [
			'Technology',
		],
		'PLbjDnnBIxiEqkEjUcuv57s_DfvqYAnw0L' => [ // İlayda
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEoll0qbcokyoAZHnVeLgkUv' => [ // Ghostwood Empire
			'Soundtrack',
		],
		'PLbjDnnBIxiEr4chbOlma5qHhDa_2PzFQ1' => [ // Mason
			'Community',
		],
		'PLbjDnnBIxiEpI1ms7j_6oBnugsgNUvKr-' => [ // Mikael
			'Coffee Stainers',
		],
		'Vladimir' => [
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEpAW5w66c_j6zkWrmCx1H55' => [ // Guru
			'Coffee Stainers',
		],
		'David' => [
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEr1i6YbP5kKSOvIjD0eVncL' => [ // Gab
			'Coffee Stainers',
		],
		'Max' => [
			'Coffee Stainers',
		],
		'Vilhelm' => [
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEoBCg8SDvadXeU8IlQmRLbf' => [ // Conrad
			'Coffee Stainers',
		],
		'K2' => [
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEosbfE0UQ1jAsYX7vikvCW4' => [ // Quantum Technology
			'Features',
		],
		'Portals' => [
			'Features',
			'Quantum Technology',
		],
		'PLbjDnnBIxiEppd5l36c5OFfQst74E9zpt' => [ // Emmet
			'Coffee Stainers',
		],
		'Angelica' => [
			'Coffee Stainers',
		],
		'Margit' => [
			'Coffee Stainers',
		],
		'Robert' => [
			'Coffee Stainers',
		],
		'Nick' => [
			'Coffee Stainers',
		],
		'Theo' => [
			'Coffee Stainers',
		],
		'Josef' => [
			'Coffee Stainers',
		],
		'Rosario' => [
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEruT5Zg7JLcY5LC8fkn2Wkq' => [ // Jason
			'Coffee Stainers',
		],
		'PLbjDnnBIxiEqR9ixLOUWOXOLEv0Igt_-e' => [ // Samuel
			'Coffee Stainers',
		],
	];

	public const NOT_A_LIVESTREAM = [
		'PLbjDnnBIxiEp8MbNepfH1unmPDOqQanxR' => 'Satisfactory x Portal Bonus Stream',
		'PLbjDnnBIxiEqAMkhgXTQCn7bK98wWQbPg' => 'Hot Potato Bonus Stream',
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
		'2022-09-20' => 'Update 6 Release Stream',
		'2023-07-05' => 'Last stream with Jace 💔',
	];

	public const VIDEO_IS_FROM_A_LIVESTREAM = [
		'yt-y1Znn6SBS6w',
		'yt-oLl9SZht-bE',
		'yt-5NBgetrxtpw',
	];

	/**
	 * @var array<string, string>
	 */
	public readonly array $not_a_livestream;

	/**
	 * @var array<string, string>
	 */
	public readonly array $not_a_livestream_date_lookup;

	protected function __construct()
	{
		$this->not_a_livestream = array_merge(
			self::NOT_A_LIVESTREAM,
			array_reduce(
				array_filter(
					glob(__DIR__ . '/../data/dated/*/yt-*.json'),
					static function (string $maybe) : bool {
						if (
							in_array(
								preg_replace('/\.json$/', '', basename($maybe)),
								self::VIDEO_IS_FROM_A_LIVESTREAM,
								true
							)
						) {
							return false;
						}

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

					if (!isset($data->title)) {
						var_dump($data);exit(1);
					}

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
