const lunr = require('lunr');
const documents = Object.entries(require('./lunr-docs-preload.json')).map(
	(e) => {
		return e[1];
	}
);
const synonyms = require('./synonyms.json');
const synonym_keys = Object.keys(synonyms);

const stdout = process.stdout;

function aliasSatisfactoryVocabulary(builder) {
	const pipeline = (token) => {
		const lowercase = token.toString().toLowerCase();

		if (synonym_keys.includes(synonyms)) {
			return token.update(() => {
				return synonyms[lowercase];
			});
		}

		return token;
	};

	lunr.Pipeline.registerFunction(pipeline, 'aliasSatisfactoryVocabulary');

	builder.pipeline.before(lunr.stemmer, pipeline);
	builder.searchPipeline.before(lunr.stemmer, pipeline);
}

const index = lunr(function () {
	this.ref('id');
	/*
	this.field('game');
	this.field('date');
	*/
	this.field('title');
	this.field('transcription');
	/*
	this.field('urls');
	this.field('topics');
	*/
	this.field('quotes');
	this.use(aliasSatisfactoryVocabulary);
	/*
	this.metadataWhitelist = [
		'position',
	];
	*/

	documents.forEach((doc) => {
		this.add(doc);
	});
});

stdout.write(JSON.stringify(index, "\t"));
