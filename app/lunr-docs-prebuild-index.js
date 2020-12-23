const lunr = require('lunr');
const documents = require('./lunr-docs-preload.json');

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
