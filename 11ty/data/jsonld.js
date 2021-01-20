const topics = require('../../src/topics.json');

const coffee_stain = {
	"@type" : "Organization",
	"name": "Coffee Stain Studios AB",
	"url" : "https://www.coffeestainstudios.com/"
};

const satisfactory = {
	"@type": "VideoGame",
	"name": "Satisfactory",
	"author": coffee_stain,
	"operatingSystem": "Windows",
	"applicationCategory": [
		"Game",
		"Factory Construction"
	]
};

module.exports = () => {
	const out = {
	"/topics/coffee-stainers/ben/": [
		{
			"@context": "https://schema.org",
			"@type": "Person",
			"name": "Ben de Hullu",
			"jobTitle": "Tech Artist",
			"worksFor": coffee_stain,
			"subjectOf": [
				{
					"@type": "VideoObject",
					"name": "Dev Vlog: Tech Art & Optimisation with Ben!",
					"description": "save the frames",
					"uploadDate": "2020-11-05",
					"thumbnailUrl": "http://i3.ytimg.com/vi/omjFqZQV9fI/hqdefault.jpg",
					"url": "https://www.youtube.com/watch?v=omjFqZQV9fI",
					"embedUrl": "https://www.youtube.com/embed/omjFqZQV9fI"
				}
			],
			"url": [
				"https://twitter.com/BenHullu"
			]
		}
	],
	"/topics/coffee-stainers/dylan/": [
		{
			"@context": "https://schema.org",
			"@type": "Person",
			"name": "Dylan Kelly",
			"jobTitle": "Programmer",
			"worksFor": coffee_stain,
			"url": [
				"https://twitter.com/SnyggLich"
			]
		}
	],
	"/topics/coffee-stainers/jace/": [
		{
			"@context": "https://schema.org",
			"@type": "Person",
			"name": "Jace Varlet",
			"jobTitle": "Community Manager",
			"worksFor": coffee_stain,
			"subjectOf": [
				{
					"@type": "VideoObject",
					"name": "Game Dev Circle - Episode 1 - Jace Varlet",
					"description": "This is Jace Varlet, he's a community manager and colleague at Coffee Stain Studios. Follow Jace as he talks about his story leading up until this point, including his time in Japan where he started his game development career after having moved from his home country Australia.",
					"uploadDate": "2019-07-26",
					"thumbnailUrl": "http://i3.ytimg.com/vi/v2JdPmTvQKg/hqdefault.jpg",
					"url": "https://www.youtube.com/watch?v=v2JdPmTvQKg",
					"embedUrl": "https://www.youtube.com/embed/v2JdPmTvQKg"
				}
			],
			"image": [
				{
					"@type": "ImageObject",
					"name": "our lord and savior Jace",
					"contentUrl": "https://static.wikia.nocookie.net/satisfactory_gamepedia_en/images/5/51/Jace.jpg/revision/latest/scale-to-width-down/400?cb=20180706203523",
					"encodingFormat": "image/jpeg",
					"width": {
						"@type": "QuantitativeValue",
						"value": 400
					},
					"height": {
						"@type": "QuantitativeValue",
						"value": 400
					},
					"usageInfo": [
						"https://satisfactory.gamepedia.com/Template:Copyright_first-party",
						"https://www.fandom.com/licensing"
					],
					"discussionUrl": [
						"https://satisfactory.gamepedia.com/File_talk:Jace.jpg"
					]
				}
			],
			"url": [
				"https://twitter.com/jembawls"
			]
		}
	],
	"/topics/coffee-stainers/simon/": [
		{
			"@context": "https://schema.org",
			"@type": "Person",
			"name": "Simon Begby",
			"jobTitle": "VFX Artist",
			"worksFor": coffee_stain,
			"subjectOf": [
				{
					"@type": "CreativeWorkSeries",
					"name": "Simon Saga",
					"startDate": "2018-10-17",
					"endDate": "2019-03-04",
					"url": "https://www.youtube.com/playlist?list=PLzGEn7MzkWRsyTI-94PoqpuRh9a2YcKXK"
				}
			],
			"url": [
				"https://twitter.com/SBegby"
			]
		}
	],
	"/topics/coffee-stainers/snutt/": [
		{
			"@context": "https://schema.org",
			"@type": "Person",
			"name": "Snutt Treptow",
			"jobTitle": "Community Manager",
			"worksFor": coffee_stain,
			"subjectOf": [
				{
					"@type": "VideoObject",
					"name": "I was a SPEAKER at a GAME DEV CONFERENCE",
					"description": "They invited me to speak at a game developer conference! Weird. Here's a video about that!",
					"uploadDate": "2020-03-02",
					"thumbnailUrl": "http://i3.ytimg.com/vi/N6yki_HwBNQ/hqdefault.jpg",
					"url": "https://www.youtube.com/watch?v=N6yki_HwBNQ",
					"embedUrl": "https://www.youtube.com/embed/N6yki_HwBNQ"
				}
			],
			"url": [
				"https://twitter.com/BustaSnutt"
			]
		}
	],
	"/topics/coffee-stainers/tim/": [
		{
			"@context": "https://schema.org",
			"@type": "Person",
			"name": "Tim Badylak",
			"jobTitle": "Producer",
			"image": [
				{
					"@type": "ImageObject",
					"name": "Tim's only known sighting whilst working at Coffee Stain.",
					"contentUrl": "https://static.wikia.nocookie.net/satisfactory_gamepedia_en/images/a/a7/Tim_Badylak.png/revision/latest?cb=20201201220500&format=original",
					"encodingFormat": "image/png",
					"width": {
						"@type": "QuantitativeValue",
						"value": 726
					},
					"height": {
						"@type": "QuantitativeValue",
						"value": 428
					},
					"usageInfo": [
						"https://satisfactory.gamepedia.com/Template:Copyright_first-party",
						"https://www.fandom.com/licensing"
					],
					"discussionUrl": [
						"https://satisfactory.gamepedia.com/File_talk:Tim_Badylak.png"
					]
				}
			]
		}
	],
	"/topics/features/power-management/nuclear-energy/": [
		{
			"@context": "https://schema.org",
			"@type": "WebPage",
			"name": "Nuclear Energy",
			"description": "Satisfactory Livestream clips about Nuclear Energy",
			"image": [
				{
					"@type": "ImageObject",
					"name": "In-game building icon for the Nuclear Power Plant.",
					"contentUrl": "https://static.wikia.nocookie.net/satisfactory_gamepedia_en/images/4/46/Nuclear_Power_Plant.png/revision/latest?cb=20200311145339&format=original",
					"encodingFormat": "image/png",
					"width": {
						"@type": "QuantitativeValue",
						"value": 512
					},
					"height": {
						"@type": "QuantitativeValue",
						"value": 512
					},
					"usageInfo": [
						"https://satisfactory.gamepedia.com/Template:Copyright_first-party",
						"https://www.fandom.com/licensing"
					],
					"discussionUrl": [
						"https://satisfactory.gamepedia.com/File_talk:Nuclear_Power_Plant.png"
					]
				},
				{
					"@type": "ImageObject",
					"name": "In-game item icon for Nuclear Waste.",
					"contentUrl": "https://static.wikia.nocookie.net/satisfactory_gamepedia_en/images/a/a1/Nuclear_Waste.png/revision/latest?cb=20190626163606&format=original",
					"encodingFormat": "image/png",
					"width": {
						"@type": "QuantitativeValue",
						"value": 256
					},
					"height": {
						"@type": "QuantitativeValue",
						"value": 256
					},
					"usageInfo": [
						"https://satisfactory.gamepedia.com/Template:Copyright_first-party",
						"https://www.fandom.com/licensing"
					],
					"discussionUrl": [
						"https://satisfactory.gamepedia.com/File_talk:Nuclear_Waste.png"
					]
				}
			],
			"about": [
				satisfactory,
			]
		}
	],
	"/": [
		{
			"@context": "https://schema.org",
			"@type": "WebSite",
			"url": "https://clips.satisfactory.signpostmarv.name/",
			"potentialAction": {
				"@type": "SearchAction",
				"target": "https://clips.satisfactory.signpostmarv.name/search?q={search_term_string}",
				"query-input": "required name=search_term_string"
			}
		}
	]
	};

	Object.entries(topics).forEach((e) => {
		const [slug, data] = e;
		const permalink = `/topics/${slug}/`;

		if ( ! (permalink in out)) {
			out[permalink] = [
				{
					"@context": "https://schema.org",
					"@type": "WebPage",
					"name": data[data.length - 1],
					"description": `Satisfactory Livestream clips about ${
						data[data.length - 1]
					}`,
					"about": [
						satisfactory,
					]
				}
			];
		}
	});

	Object.entries(out).forEach((e) => {
		const [permalink, data] = e;
		const subslugs = [];

		const breadcrumbs = [];

		const maybe_topic_slugs = /^\/topics\/(.+)\//.exec(permalink);

		if (maybe_topic_slugs) {
			breadcrumbs.push(...maybe_topic_slugs[1].split('/').map(
				(subslug) => {
					subslugs.push(subslug);

					const topic = topics[subslugs.join('/')];

					return [
						topic[topic.length - 1],
						`https://clips.satisfactory.signpostmarv.name/topics/${
							subslugs.join('/')
						}/`
					];
				}
			));
		}

		if (
			('@type' in data[0])
			&& ['Person'].includes(data[0]['@type'])
		) {
			data[0] = {
				"@context": "https://schema.org",
				"@type": "CreativeWork",
				"name": data[0].name,
				"description": `Satisfactory Livestream clips about ${
					data[0].name
				}`,
				"about": [
					data[0],
				]
			};

			if ('image' in data[0].about[0]) {
				data[0].image = data[0].about[0].image;
			}
		}

		if (
			breadcrumbs.length > 0
			&& ('@type' in data[0])
			&& ['WebPage'].includes(data[0]['@type'])
			&& ! ('breadcrumb' in data[0])
		) {
			data[0]['breadcrumb'] = {
				'@type': 'BreadcrumbList',
				'itemListElement': breadcrumbs.map((breadcrumb, i) => {
					return {
						'@type': 'ListItem',
						'additionalType': 'WebPage',
						item: breadcrumb[1],
						url: breadcrumb[1],
						name: breadcrumb[0],
						position: i,
					};
				}),
			};
		}
	})

	return out;
};
