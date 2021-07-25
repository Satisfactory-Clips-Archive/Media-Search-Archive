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

	for(chunk of chunks) {
		await Promise.all(chunk.map(foo));

		await new Promise((yup) => {
			setTimeout(() => {
				yup();
			}, 100);
		});
	}
}

bar();
