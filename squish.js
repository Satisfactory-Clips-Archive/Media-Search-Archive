const {compress:brotli} = require('wasm-brotli');
const {gzip} = require('wasm-zopfli');

const {
	readFile,
	writeFile,
} = require('fs');

const {
	promisify,
} = require('util');

const write = promisify(writeFile);
const read = promisify(readFile);

	async function squish(path) {
		const uncompressed = await readFile(path);
	}

(async () => {
	const uncompressed = await Promise.all([
		read('./src/docs.json'),
		read('./src/lunr.json'),
	]);

	const compressed = await Promise.all([
		brotli(Buffer.from(uncompressed[0], 'utf8')),
		brotli(Buffer.from(uncompressed[1], 'utf8')),
		gzip(Buffer.from(uncompressed[0], 'utf8')),
		gzip(Buffer.from(uncompressed[1], 'utf8')),
	]);

	await Promise.all([
		write('./src/docs.json.br', compressed[0]),
		write('./src/lunr.json.br', compressed[1]),
		write('./src/docs.json.gz', compressed[2]),
		write('./src/lunr.json.gz', compressed[3]),
	]);

	console.log('done');
})();
