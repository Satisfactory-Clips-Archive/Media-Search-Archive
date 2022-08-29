const {
	writeFileSync,
} = require('fs');
const fetch = require('node-fetch');
const needs_fetching = require('./data/needs-fetching.json');

console.log(needs_fetching);

let progress = 0;

async function foo(id)
{
	const url = `https://www.youtube.com/watch?v=${id}`

	const response = await (await fetch(url)).text();

	writeFileSync(`./app/captions/${id}.html`, response);

	++progress;

	console.log(`${(progress / needs_fetching.length) * 100}%\r`);

	return true;
}

async function bar()
{
	const chunks = [];

	for (let i = 0; i < needs_fetching.length; i += 10) {
		chunks.push(needs_fetching.slice(i, i + 10));
	}

	let count = 0;

	for(const chunk of chunks) {
		++count;

		await Promise.all(chunk.map(foo));

		const sleep_for = Math.max(100, count % 30000);

		if (sleep_for > 10000) {
			console.log(`sleeping for ${sleep_for}`);
		}

		if (count > 100) {
			throw new Error('Enough, continue regardless');
		}

		await new Promise((yup) => {
			setTimeout(() => {
				yup();
			}, sleep_for);
		});
	}
}

bar();
