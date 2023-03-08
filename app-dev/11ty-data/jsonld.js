const topics = require('../../src/topics.json');
/** @var {[key:string]: string} */
const reverse_lookup = require('../../11ty/data/topicStrings_reverse.json');
const playlist_cache = require('../../app/data/api-cache/playlists-unmapped.json');

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
					'name': 'Satisfactory Clips Archive',
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
			const playlist_id = reverse_lookup[slug];
			const playlist_url = `https://www.youtube.com/playlist?list=${
				encodeURIComponent(playlist_id)
			}`;

			out[permalink] = [
				{
					"@context": "https://schema.org",
					"@type": "WebPage",
					"name": data[data.length - 1],
					"description": `Clips about ${
						data[data.length - 1]
					}`,
					url: archive_url,
					"about": [
						{
							'@type': 'CreativeWorkSeries',
							'name': data[data.length - 1],
							url: playlist_url,
						},
					],
				},
			];

			if (
				playlist_id in playlist_cache
				&& 'snippet' in playlist_cache[playlist_id]
				&& 'description' in playlist_cache[playlist_id].snippet
				&& '' !== playlist_cache[playlist_id].snippet.description.trim()
			) {
				out[permalink][0].description = playlist_cache[playlist_id].snippet.description.trim();
			}
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
				'VideoGame',
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

			if ('/topics/coffee-stainers/g2/' === permalink) {
				data[0].name = 'G2';
			}
		} else if (
			('@type' in data[0])
			&& [
				'WebPage',
			].includes(data[0]['@type'])
			&& ! ('url' in data[0])
		) {
			data[0].url = archive_url;
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

			if (
				! data[0].relatedLink.includes(playlist_url)
				&& ! (
					'WebPage' === data[0]['@type']
					&& 'about' in data[0]
					&& data[0].about instanceof Array
					&& data[0].about.length > 0
					&& 'CreativeWorkSeries' === data[0].about[0]['@type']
					&& playlist_url === data[0].about[0].url
				)
			) {
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
		if (('relatedLink' in data[0]) && data[0].relatedLink.length < 1) {
			delete data[0].relatedLink;
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
