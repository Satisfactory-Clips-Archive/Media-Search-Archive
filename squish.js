const {compress:brotli} = require('wasm-brotli');
const {gzip} = require('wasm-zopfli');
const {minify} = require('html-minifier');

const {
	readFile,
	writeFile,
} = require('fs');

const {
	promisify,
} = require('util');

const write = promisify(writeFile);
const read = promisify(readFile);

(async () => {
	const uncompressed = await Promise.all([
		read('./src/docs.json'),
		read('./src/lunr.json'),
		read('./src/topics.json'),
		read('./src/synonyms.json'),
		read('./src/.htaccess'),
		read('./src/favicon.ico'),
		read('./src/index.html'),
		read('./src/lunr.min.js'),
		read('./src/lunr-highlighter.js'),
	]);

	const compressed = await Promise.all([
		brotli(Buffer.from(uncompressed[0], 'utf8')),
		gzip(Buffer.from(uncompressed[0], 'utf8')),
		brotli(Buffer.from(uncompressed[1], 'utf8')),
		gzip(Buffer.from(uncompressed[1], 'utf8')),
		brotli(Buffer.from(uncompressed[2], 'utf8')),
		gzip(Buffer.from(uncompressed[2], 'utf8')),
		brotli(Buffer.from(uncompressed[6], 'utf8')),
		gzip(Buffer.from(uncompressed[6], 'utf8')),
		brotli(Buffer.from(uncompressed[7], 'utf8')),
		gzip(Buffer.from(uncompressed[7], 'utf8')),
	]);

	await Promise.all([
		write('./dist/docs.json', uncompressed[0]),
		write('./dist/docs.json.br', compressed[0]),
		write('./dist/docs.json.gz', compressed[1]),
		write('./dist/lunr.json', uncompressed[1]),
		write('./dist/lunr.json.br', compressed[2]),
		write('./dist/lunr.json.gz', compressed[3]),
		write('./dist/topics.json', uncompressed[2]),
		write('./dist/topics.json.br', compressed[4]),
		write('./dist/topics.json.gz', compressed[5]),
		write('./dist/synonyms.json', uncompressed[3]),
		write('./dist/.htaccess', uncompressed[4]),
		write('./dist/favicon.ico', uncompressed[5]),
		write('./dist/index.html', minify(uncompressed[6].toString(), {
			collapseInlineTagWhitespace: true,
			collapseWhitespace: true,
			minifyCSS: true,
			minifyJs: true,
			removeAttributeQuotes: true,
			removeComments: true,
			useShortDoctype: true,
		})),
		write('./dist/index.html.br', compressed[6]),
		write('./dist/index.html.gz', compressed[7]),
		write('./dist/lunr.min.js', uncompressed[7]),
		write('./dist/lunr.min.br', compressed[8]),
		write('./dist/lunr.min.gz', compressed[9]),
		write('./dist/lunr-highlighter.js', uncompressed[8]),
	]);

	console.log('done');
})();
