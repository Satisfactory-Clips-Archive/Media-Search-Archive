const {
	existsSync,
	writeFile,
	mkdirSync,
} = require('fs');

const {
	promisify,
} = require('util');

const fetch = require('node-fetch');

const writeFilePromise = promisify(writeFile);

module.exports = async() => {
	const jsonld = require('../data/jsonld.json');

	const out = await Promise.all(Object.entries(jsonld).filter((e) => {
		const [, data] = e;

		return 'image' in data[0];
	}).map(async (e) => {
		const permalink = `${e[0].slice(0, -1)}`;
		return {
			permalink: `${permalink}.svg`,
			source: Buffer.from(
				await (
					await fetch(e[1][0].image[0].contentUrl)
				).arrayBuffer()
			).toString('base64'),
			name: e[1][0].name,
			url: e[1][0].image[0].contentUrl,
		};
	}));

	return out;
};
