const {
	promisify,
} = require('util');
const {
	readFile:readFileAsync,
	existsSync,
} = require('fs');
const {
	dirname,
	basename,
} = require('path');
const readFile = promisify(readFileAsync);
const glob = promisify(require('glob'));
const csv = promisify(require('csv-parse'));

const done = require('./playlists/url-overrides.json');
const done_keys = Object.keys(done);

(async () => {
	const files = (await Promise.all(((await Promise.all(
		(
			await glob('./app/data/*/yt-*.csv')
		).filter((maybe:string) => {
			if ( ! existsSync(maybe.replace(/\.csv$/, '.json'))) {
				console.error(`Corresponding JSON file for ${maybe} missing!`);

				return false;
			}

			return true;
		}).map(
			async (
				file:string
			) : Promise<[string, string, string, string]> => {
				return Promise.all([
					basename(dirname(file)),
					basename(file, '.csv'),
					readFile(file),
					readFile(file.replace(/\.csv$/, '.json')),
				]);
			}
		)
	)) as [string, string, string, Buffer][]).map(
		async (fileset) : Promise<[
			string,
			string,
			Array<[string, string, string]>,
			{
				title: string,
				topics: (false|string[])[]
			}
		]> => {
			const data = csv(fileset[2]) as Promise<Array<[string, string, string]>>;

			return [
				fileset[0],
				fileset[1],
				(await csv(fileset[2])) as Array<[string, string, string]>,
				JSON.parse(fileset[3].toString()),
			];
		}
	))).map((fileset) : [string, string, [string, string, string]][] => {
		const [
			date,
			id,
			csv_data,
			json_data,
		] = fileset;

		return Object.entries(json_data.topics).filter((e) => {
			const [, maybe] = e;

			return maybe !== false;
		}).map((e) : [string, string, [string, string, string]] => {
			const [key] = e;
			const i = parseInt(key, 10);

			return [
				date,
				`${id},${csv_data[i][0]},${csv_data[i][1]}`,
				csv_data[i],
			];
		});
	}).reduce(
		(result, datasets) => {
			return result.concat(datasets);
		},
		[]
	).filter((maybe) => {
		const length = parseFloat(maybe[2][1]) - parseFloat(maybe[2][0]);

		return (
			length <= 60
			&& ! done_keys.includes(maybe[1])
		);
	}).reduce(
		(
			result:{[key:string]: [string, [string, string, string]][]},
			row:[string, string, [string, string, string]]
		) => {
			if ( ! (row[0] in result)) {
				result[row[0]] = [];
			}

			result[row[0]].push([row[1], row[2]]);

			return result;
		},
		{}
	);

	class Row
	{
		id:string;
		start:string;
		end:string;
		length:number;
		title:string;

		constructor(id:string, start:string, end:string, title:string)
		{
			[
				this.id,
				this.start,
				this.end,
				this.title,
			] = [
				id,
				start,
				end,
				title,
			];

			this.length = parseFloat(this.end) - parseFloat(this.start);
		}
	}

	Object.entries(files).forEach((e) => {
		const [date, rows] = e;

		console.log(date);
		console.table(
			rows.map((row) => {
				return new Row(row[0], ...row[1]);
			})
		);
	});
})()
