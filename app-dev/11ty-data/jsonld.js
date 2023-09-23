const topics = require('../../11ty/data/topics.json');
/** @var {[key:string]: string} */
const reverse_lookup = require('../../11ty/data/topicStrings_reverse.json');
const playlist_cache = require('../../app/data/api-cache/playlists-unmapped.json');

/** @var {[key: string]: {[key: string]: number}} */
const topic_slug_history = require('../../app/topic-slug-history.json');

module.exports = async () => {
	const [
		{default:satisfactory},
		{default:data},
		{SignpostMarv},
	] = await Promise.all([
		import(
			'../../Media-Archive-Metadata/src/common/satisfactory.js'
		),
		import(
			'../../Media-Archive-Metadata/index.js'
		),
		import(
			'../../Media-Archive-Metadata/src/permalinked/topics/community/signpostmarv.js'
		),
	]);

	const out = Object.assign(
		{},
		Object.fromEntries(Object.entries(data).map((entry) => {
			return [entry[0], entry[1].map((item) => {
				if (
					item['@type'] !== 'WebpPage'
					&& '@context' in item
					&& 'https://schema.org' === item['@context']
				) {
					item = Object.assign({}, item);

					delete item['@context'];
				}

				return item;
			})];
		})),
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
					},
					author: SignpostMarv,
				}
			],
		}
	);

	/**
	 * @return {string}
	 */
	function playlist_id_from_slug(slug) {
		const playlist_id = reverse_lookup[slug] || Object.keys(topic_slug_history).find((topic_id) => {
			const slugs = Object.keys(topic_slug_history[topic_id]);

			return !!slugs.includes(slug);
		});

		if (undefined === playlist_id) {
			throw new Error(`${slug} not found!`);
		}

		return playlist_id;
	}

	function definitely_has_playlist_url(slug) {
		const playlist_id = playlist_id_from_slug(slug);

		return `https://www.youtube.com/playlist?list=${
			encodeURIComponent(playlist_id)
		}`;
	}

	function playlist_object(slug, data, name) {
		const playlist_id = playlist_id_from_slug(slug);

		const slug_to_use = Object.keys(topic_slug_history[playlist_id]).findLast(e => true);
		const permalink = `/topics/${slug_to_use}/`;
		const archive_url = `https://archive.satisfactory.video${permalink}`;

		const playlist_url = definitely_has_playlist_url(slug_to_use);

		const playlist_object = {
			"@context": "https://schema.org",
			"@type": "WebPage",
			name,
			"description": `Clips about ${
				name
			}`,
			url: archive_url,
		};

		if (
			(slug_to_use in reverse_lookup)
			&& reverse_lookup[slug_to_use].startsWith('PLbjDnnBIxi')
		) {
			playlist_object.about = [
				{
					'@type': 'CreativeWorkSeries',
					name,
					url: playlist_url,
				},
			];
		}

		if (
			playlist_id in playlist_cache
			&& 'snippet' in playlist_cache[playlist_id]
			&& 'description' in playlist_cache[playlist_id].snippet
			&& '' !== playlist_cache[playlist_id].snippet.description.trim()
		) {
			playlist_object.description = playlist_cache[playlist_id].snippet.description.trim();
		}

		return playlist_object;
	}

	Object.entries(topics).forEach((e) => {
		const [slug] = e;
		const permalink = `/topics/${slug}/`;
		if (
			! (permalink in out)
		) {
			const [, data] = e;

			out[permalink] = [
				playlist_object(slug, data, data[data.length - 1]),
			];
		}
	});

	Object.entries(out).forEach((e) => {
		const [permalink, data] = e;
		const subslugs = [];

		let breadcrumbs = [];
		let add_playlist_as_related_link = true;

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
		const original_data_0 = data[0];

		if (
			('@type' in data[0])
			&& [
				'Software',
				'Person',
				'CreativeWorkSeries',
				'VideoGame',
			].includes(data[0]['@type'])
		) {
			data[0] = {
				"@context": "https://schema.org",
				"@type": "WebPage",
				"name": original_data_0.name,
				"description": `Satisfactory Livestream clips about ${
					original_data_0.name
				}`,
				url: archive_url,
				"about": [
					original_data_0,
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

		if (maybe_topic_slugs) {
			const playlist_url = definitely_has_playlist_url(maybe_topic_slugs[1]);

			function maybe_sub_is_about(maybe_about) {
				return (
					(
						'CreativeWorkSeries' === maybe_about['@type']
						&& playlist_url === maybe_about.url
					)
					|| has_about(maybe_about)
				);
			}

			function has_about(maybe) {
				return (
					(
						'about' in maybe
						&& maybe.about instanceof Array
						&& maybe.about.length > 0
						&& maybe.about.find(maybe_sub_is_about)
					)
					|| (
						'subjectOf' in maybe
						&& maybe.subjectOf instanceof Array
						&& maybe.subjectOf.find(maybe_sub_is_about)
					)
				);
			}

			if (! has_about(data[0])) {
				if ( ! ('about' in data[0])) {
					data[0].about = [];
				}
				const playlist_page = playlist_object(`${maybe_topic_slugs[1]}`, original_data_0, original_data_0.name);

				if (data[0].url === playlist_page.url && 'about' in playlist_page) {
					data[0].about.push(...playlist_page.about);
				} else if (data[0].url !== playlist_page.url) {
					data[0].about.push(playlist_page);
				}

				add_playlist_as_related_link = false;
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

			function maybe_sub_is_about(maybe_about) {
				return (
					(
						'CreativeWorkSeries' === maybe_about['@type']
						&& playlist_url === maybe_about.url
					)
					|| has_about(maybe_about)
				);
			}

			function has_about(maybe) {
				return (
					(
						'about' in maybe
						&& maybe.about instanceof Array
						&& maybe.about.length > 0
						&& maybe.about.find(maybe_sub_is_about)
					)
					|| (
						'subjectOf' in maybe
						&& maybe.subjectOf instanceof Array
						&& maybe.subjectOf.find(maybe_sub_is_about)
					)
				);
			}

			if (
				! data[0].relatedLink.includes(playlist_url)
				&& add_playlist_as_related_link
				&& ! data.find(has_about)
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
		if ('about' in data[0]) {
			data[0].about = data[0].about.reduce(
				(was, is) => {
					const json = JSON.stringify(is);

					if ( ! was[1].includes(json)) {
						was[0].push(is);
						was[1].push(json);
					}

					return was;
				},
				[[], []]
			)[0];
		}

		for (const property of [
			'relatedLink',
			'about',
		]) {
			if ((property in data[0]) && data[0][property].length < 1) {
				delete data[0][property];
			}
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
