const lunr = require('lunr');
const documents = Object.entries(require('./lunr-docs-preload.json')).map(
	(e) => {
		return e[1];
	}
);

const stdout = process.stdout;

const index = lunr(function () {
	this.ref('id');
	this.field('game');
	this.field('date');
	this.field('title');
	this.field('transcription');
	this.field('urls');
	this.field('topics');
	this.field('quotes');

	documents.forEach((doc) => {
		this.add(doc);
	});
});

stdout.write(JSON.stringify(index, "\t"));
