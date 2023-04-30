const nlevel = Object.fromEntries(require(`${__dirname}/topicsNested.json`).map((entry) => {
	return [entry.id, entry];
}));
const permalink_to_name = require(`${__dirname}/topics.json`);
const id_to_permalink = require(`${__dirname}/topicStrings.json`);

const name_to_permalink = Object.fromEntries(Object.entries(permalink_to_name).map((e) => {
	const [permalink, name] = e;

	return [name.join('\n'), permalink];
}));

const dates_from_index = Object.values(require(`${__dirname}/indexDated.json`)).reduce(
	(was, month) => {
		for (const week of Object.values(month)) {
			for (const days of Object.entries(week)) {
				for (const day of Object.entries(days[1])) {
					const [date, [, maybe]] = day;

					if (maybe && ! was.includes(date)) {
						was.push(date);
					}
				}
			}
		}

		return was;
	},
	[]
);
const friendly_dates = require(`${__dirname}/friendly_dates.json`);

const transcriptions = Object.fromEntries(require(`${__dirname}/transcriptions.json`).map((entry) => {
	return [entry.id, entry];
}));

const info_cards = require(`${__dirname}/../../app/data/info-cards--augmented.json`);

const exportable_data = [];

function remapper(clip) {
	return [
		(
			(clip.id in transcriptions)
				? {
					permalink: `transcriptions/${clip.id}`,
					title: clip.title,
				}
				: {
					title: clip.title,
				}
		),
		{
			permalink: clip.video_url_from_id,
			rel: 'nofollow noopener',
		},
	];
}

for (const entry of Object.entries(id_to_permalink)) {
	const [id, permalink] = entry;

	const topic_name = permalink_to_name[permalink];

	if ( ! topic_name) {
		continue;
	}

	const breadcrumbs = [];

	for (let i = 0; i < (topic_name.length - 1); ++i) {
		breadcrumbs.push({
			permalink: `topics/${name_to_permalink[topic_name.slice(0, i + 1).join('\n')]}`,
			title: topic_name[i],
		});
	}

	const dates = Object.fromEntries(dates_from_index.map((date) => {
		return [date, {
			date,
			friendly_date: friendly_dates[date],
			primary: [],
			references: [],
		}];
	}).sort((a, b) => {
		return b[0].localeCompare(a[0]);
	}));

	for (const date of dates_from_index) {
		const primary = {};
		const references = [];
		for (const clip of info_cards) {
			if (date !== clip.date) {
				continue;
			}

			if (clip.topics.includes(id)) {
				primary[clip.id] = clip;
			} else if (clip.cards.find((card) => {
				return 'playlist' === card[2] && id === card[3];
			})) {
				references.push(clip);
			}
		}
		const primary_keys = Object.keys(primary);
		dates[date].primary = Object.values(primary);
		dates[date].references = references.filter((maybe) => {
			return ! primary_keys.includes(maybe.id);
		});
		dates[date].primary = dates[date].primary.map(remapper).reverse();
		dates[date].references = dates[date].references.map(remapper).reverse();
	}

	exportable_data.push({
		id,
		permalink: `topics/${permalink}`,
		title: topic_name[topic_name.length - 1],
		breadcrumbs: breadcrumbs,
		children: nlevel[id].children.map((child_id) => {
			const child_permalink = id_to_permalink[child_id];
			const child_topic_name = permalink_to_name[child_permalink];
			return {
				permalink: `topics/${child_permalink}`,
				title: child_topic_name[child_topic_name.length - 1],
			};
		}),
		dates: Object.values(Object.fromEntries(Object.entries(dates).filter((maybe) => {
			return maybe[1].primary.length > 0 || maybe[1].references.length > 0;
		}))),
	});
}

module.exports = exportable_data;
