const {
	existsSync,
	writeFile,
	mkdirSync,
} = require('fs');

const {
	readFile,
} = require('fs/promises');

const {
	promisify,
} = require('util');

const fetch = require('node-fetch');
const {createHash} = require("crypto");

const writeFilePromise = promisify(writeFile);

module.exports = async() => {
	const jsonld = require('../data/jsonld.json');

	const out = await Promise.all(Object.entries(jsonld).filter((e) => {
		const [, data] = e;

		return 'image' in data[0];
	}).map(async (e) => {
		const permalink = `${e[0].slice(0, -1)}`;
		const hash = createHash('sha256').update(e[1][0].image[0].contentUrl).digest('hex');
		const cache_path = `${__dirname}/../cache/${hash}-svg-source.bin`;

		if (!existsSync(cache_path)) {
			await writeFilePromise(cache_path, Buffer.from(
				await (
					await fetch(e[1][0].image[0].contentUrl)
				).arrayBuffer()
			));
		}

		return {
			permalink: `${permalink}.svg`,
			source: (await readFile(cache_path)).toString('base64'),
			name: e[1][0].name,
			url: e[1][0].image[0].contentUrl,
		};
	}));

	return out;
};
