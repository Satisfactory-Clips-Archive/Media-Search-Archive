const topics = require('../../src/topics.json');
/** @var {[key:string]: string} */
const reverse_lookup = require('./topicStrings_reverse.json');

module.exports = async () => {
	const [
		{default:satisfactory},
		{default:data},
	] = await Promise.all([
		import(
			'../../Media-Archive-Metadata/src/common/satisfactory.js'
		),
		import(
			'../../Media-Archive-Metadata/index.js'
		),
	]);

	const out = Object.assign(
		{},
		data,
		{
			"/": [
				{
					"@context": "https://schema.org",
					"@type": "WebSite",
					'name': 'Satisfactory Q&A Clips Archive',
					"url": "https://archive.satisfactory.video/",
					"potentialAction": {
						"@type": "SearchAction",
						"target": "https://archive.satisfactory.video/search?q={search_term_string}",
						"query-input": "required name=search_term_string"
					}
				}
			],
		}
	);

	Object.entries(topics).forEach((e) => {
		const [slug, data] = e;
		const permalink = `/topics/${slug}/`;

		const archive_url = `https://archive.satisfactory.video${permalink}`;

		if ( ! (permalink in out)) {
			out[permalink] = [
				{
					"@context": "https://schema.org",
					"@type": "WebPage",
					"name": data[data.length - 1],
					"description": `Satisfactory clips about ${
						data[data.length - 1]
					}`,
					url: archive_url,
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

		let breadcrumbs = [];

		const maybe_topic_slugs = /^\/topics\/(.+)\//.exec(permalink);

		if (maybe_topic_slugs) {
			breadcrumbs.push(...maybe_topic_slugs[1].split('/').map(
				(subslug) => {
					subslugs.push(subslug);

					const topic = topics[subslugs.join('/')];

					if ( ! topic) {
						console.log('Topic not found!', [...subslugs]);

						return [];
					}

					return [
						topic[topic.length - 1],
						`https://archive.satisfactory.video/topics/${
							subslugs.join('/')
						}/`
					];
				}
			));
		}

		breadcrumbs = breadcrumbs.filter((entry) => {
			return 2 === entry.length;
		});

		const archive_url = `https://archive.satisfactory.video${permalink}`;

		if (
			('@type' in data[0])
			&& [
				'Person',
				'CreativeWorkSeries',
			].includes(data[0]['@type'])
		) {
			data[0] = {
				"@context": "https://schema.org",
				"@type": "WebPage",
				"name": data[0].name,
				"description": `Satisfactory Livestream clips about ${
					data[0].name
				}`,
				url: archive_url,
				"about": [
					data[0],
				]
			};

			if ('image' in data[0].about[0]) {
				data[0].image = data[0].about[0].image;
			}
		}

		const slug = permalink.replace(/^\/topics\/(.+)\//, '$1');

		if (
			(slug in reverse_lookup)
			&& reverse_lookup[slug].startsWith('PLbjDnnBIxi')
		) {
			if ( ! ('relatedLink' in data[0])) {
				data[0].relatedLink = [];
			}
			if (
				data[0].url !== archive_url
				&& ! data[0].relatedLink.includes(archive_url)
			) {
				data[0].relatedLink.push(archive_url);
			}

			const playlist_url = `https://www.youtube.com/playlist?list=${
				encodeURIComponent(reverse_lookup[slug])
			}`;

			if ( ! data[0].relatedLink.includes(playlist_url)) {
				data[0].relatedLink.push(playlist_url);
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

	out['/FAQ/'] = out['/FAQ/'] || [];
	out['/FAQ/'].push({
		'@context': 'https://schema.org',
		'@type': 'https://schema.org/FAQPage',
		'url': 'https://archive.satisfactory.video/FAQ/',
		'about': satisfactory,
	});

	return out;
};
