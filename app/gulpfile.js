const gulp = require('gulp');
const lunr = require('lunr');
const rename = require('gulp-rename');
const json_transform = require('gulp-json-transform');

const synonyms = require('./synonyms.json');
const gulpJsonTransform = require('gulp-json-transform');
const synonym_keys = Object.keys(synonyms);

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

gulp.task('default', () => {
	return gulp.src('./lunr/docs-*.json').pipe(
		json_transform((json) => {
			const documents = Object.values(json);

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

			return JSON.stringify(index, "\t");
		})
	).pipe(rename((path) => {
		path.basename = path.basename.replace('docs-', 'lunr-');
	})).pipe(
		gulp.dest('./lunr/')
	);
});
