const {
	promisify,
} = require('util');
const glob = promisify(require('glob'));
const {
	readFile:readFileAsync,
	writeFile:writeFileAsync,
	existsSync,
} = require('fs');
const export_these = require(
	`${__dirname}/data/blamehannah.com.json`
) as [string, number][];
const url_overrides = require(`${__dirname}/playlists/url-overrides.json`);
const csv = promisify(require('csv-parse'));
const readFile = promisify(readFileAsync);
const writeFile = promisify(writeFileAsync);

const playlist_date_regex = /(January|February|March|April|May|June|July|August|September|October|November|December) (\d+)(?:st|nd|rd|th), (\d{4,}) .+$/;

const dated_playlists = Object.fromEntries(
	Object.entries(
		require(`${__dirname}/data/api-cache/playlists.json`) as {[key:string]: string}
	).filter((e) => {
		const [, title] = e;

		return playlist_date_regex.test(title);
	}).map((e) => {
		const [id, title] = e;

		const date = (new Date(
			title.replace(playlist_date_regex, '$3 $1 $2')
		)).toISOString().split('T')[0];

		return [id, [title, date, require(`${__dirname}/data/api-cache/playlists/${id}.json`)]];
	})
);

async function date_for_video_id(video_id:string) : Promise<string> {
	const maybe = Object.values(dated_playlists).find((maybe) => {
		return maybe[2].includes(video_id);
	});

	if (undefined === maybe) {
		throw new Error(`Could not find date for ${video_id}`);
	}

	return maybe[1];
}

declare type blamehannah = {
	author: {
		id: string,
		name: string,
	},
	source: 'youtube',
	screenshot_timestamp: number,
	id: string,
	date: string,
	title: string,
	url: string,
	via: {
		name: string,
		source: 'youtube',
		id: string,
		date: string,
	},
};

export_these.forEach(async (data, i) => {
	const [id, screenshot_timestamp] = data;
	let result:blamehannah;

	if (/^yt-[^,]+\,/.test(id)) {
		if (
			! (id in url_overrides)
		) {
			throw new Error(`Native YouTube clip not found for ${id}`);
		}

		const [source_id, start, end] = id.substring(3).split(',');

		const maybe = await glob(`${__dirname}/data/*/yt-${source_id}.csv`);

		if (1 !== maybe.length) {
			throw new Error(
				`Could not find source csv for id specified at index ${i}: ${id}`
			);
		}

		const csv_data = (
			await csv(await readFile(maybe[0])) as [string, string, string][]
		).find(
			(maybe) => {
				return start === maybe[0] && end === maybe[1];
			}
		);

		if (undefined === csv_data) {
			throw new Error(
				`Could not find csv data in file for id specified at index ${i}: ${id}`
			);
		}

		const [,,title] = csv_data;

		const date = maybe[0].replace(
			/^.+(\d{4,}\-\d{2}\-\d{2})\/yt-[^\.]+\.csv$/,
			'$1'
		);

		result = {
			author: {
				id: 'UCnXVz_l-_r_sLXNe1ESDhHA',
				name: 'Coffee Stain',
			},
			source: 'youtube',
			screenshot_timestamp,
			id,
			date,
			title,
			url: url_overrides[id],
			via: {
				name: 'Satisfactory Clips Archive',
				source: 'youtube',
				id: 'UCJamaIaFLyef0HjZ2LBEz1A',
				date,
			},
		};
	} else if (/^yt-[^,]+$/.test(id)) {
		const source_id = id.substring(3);

		const [title] = require(
			`${__dirname}/data/api-cache/videos/${source_id}.json`
		);

		const date = await date_for_video_id(source_id);

		result = {
			author: {
				id: 'UCnXVz_l-_r_sLXNe1ESDhHA',
				name: 'Coffee Stain',
			},
			source: 'youtube',
			screenshot_timestamp,
			id,
			date,
			title,
			url: `https://youtu.be/${source_id}`,
			via: {
				name: 'Satisfactory Clips Archive',
				source: 'youtube',
				id: 'UCJamaIaFLyef0HjZ2LBEz1A',
				date,
			},
		};
	} else {
		throw new Error(`Unsupported id specified at index ${i}: ${id}`)
	}

	await writeFile(
		`${
			__dirname
		}/../blamehannah.com/data/youtube/satisfactory-clips-archive/${
			result.id
		}.json`,
		JSON.stringify(result, null, '\t')
	);

	console.log(`done ${i}`);
});
