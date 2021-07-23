const gulp = require('gulp');
const htmlmin = require('gulp-htmlmin');
const rev = require('gulp-rev');
const rev_replace = require('gulp-rev-replace');
const newer = require('gulp-newer');
const changed = require('gulp-changed');
const brotli = require('gulp-brotli');
const zopfli = require('gulp-zopfli-green');
const postcss = require('gulp-postcss');
const postcss_plugins = {
	nested: require('postcss-nested'),
	import: require('postcss-import'),
};
const cssnano = require('cssnano');
const rename = require('gulp-rename');
const replace = require('gulp-replace');
const sitemap = require('gulp-sitemap');
const clean = require('gulp-clean');
const json_transform = require('gulp-json-transform');
const {
	readFileSync,
	writeFileSync,
} = require('fs');
const inline_source = require('gulp-inline-source');
const lunr = require('lunr');

const synonyms = require('./app/synonyms.json');
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

gulp.task('lunr-index', () => {
	return gulp.src('./app/lunr/docs-*.json').pipe(
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
		gulp.dest('./app/lunr/')
	);
});

gulp.task('lunr', () => {
	return gulp.src('./app/lunr/*.json').pipe(
		gulp.dest('./src/lunr/')
	);
});

gulp.task('topics', () => {
	return gulp.src('./app/topics-satisfactory.json').pipe(
		rename('topics.json')
	).pipe(
		gulp.dest('./src/')
	);
});

gulp.task('css', () => {
	return gulp.src('./src/*.postcss').pipe(
		postcss([
			postcss_plugins.import(),
			postcss_plugins.nested(),
			cssnano({
				cssDeclarationSorter: 'concentric-css',
			}),
		])
	).pipe(
		rename({
			extname: '.css'
		})
	).pipe(
		gulp.dest('./src/')
	).pipe(
		gulp.dest('./tmp-error_docs/')
	)
});

gulp.task('rev', () => {
	return gulp.src('./src/**/*.{js,json,css}').pipe(
		rev()
	).pipe(
		gulp.dest('./tmp/')
	).pipe(
		rev.manifest('./asset.manifest')
	).pipe(
		gulp.dest('./tmp/')
	);
});

gulp.task('lunr-rev', () => {
	const manifest = JSON.parse(readFileSync('./tmp/asset.manifest'));

	return gulp.src('./src/lunr/search.json').pipe(
		json_transform((data) => {
			const entries = Object.entries(data);

			return JSON.stringify(
				Object.fromEntries(entries.map(
					(e) => {
						const [key, val] = e;

						return [
							manifest['lunr/' + key].substr(5),
							manifest['lunr/' + val].substr(5),
						];
					}
				))
			);
		})
	).pipe(gulp.dest('./src/lunr/'));
});
gulp.task('lunr-clean', () => {
	return gulp.src('./tmp/lunr/search-*.json').pipe(clean());
});
gulp.task('sync-browserconfig', () => {
	return gulp.src('./src/browserconfig.xml').pipe(
		gulp.dest('./tmp/')
	);
});
gulp.task('sync-ossd', () => {
	return gulp.src('./src/ossd.xml').pipe(
		gulp.dest('./tmp/')
	);
});
gulp.task('sync-lunr', () => {
	return gulp.src('./node_modules/lunr/lunr.min.js').pipe(
		gulp.dest('./src/')
	);
});

function make_brotli_task(src) {
	return gulp.src(
		src
	).pipe(
		newer({
			dest: './tmp/',
			ext: '.br',
		})
	).pipe(
		brotli.compress({
			quality: 11,
		})
	).pipe(
		gulp.dest('./tmp/')
	)
}

const brotli_subtasks = [
	'brotli--most-everything',
	'brotli--json',
	'brotli--html',
];

gulp.task('brotli--most-everything', () => {
	return make_brotli_task('./tmp/**/*.{js,css,xml}');
});

gulp.task('brotli--json', () => {
	return make_brotli_task('./tmp/**/*.json');
});

gulp.task('brotli--html', () => {
	return make_brotli_task('./tmp/**/*.html');
});

gulp.task('zopfli', () => {
	return gulp.src(
		'./tmp/**/*.{js,json,css,html,xml}'
	).pipe(
		newer({
			dest: './tmp/',
			ext: '.gz',
		})
	).pipe(
		zopfli({
			verbose: false,
			verbose_more: false,
			numiterations: 15,
			blocksplitting: true,
			blocksplittinglast: false,
			blocksplittingmax: 15,
		})
	).pipe(
		gulp.dest('./tmp/')
	)
});

gulp.task('html', () => {
	return gulp.src(
		'./src/**/*.html'
	).pipe(
		rev_replace({
			manifest: gulp.src('./tmp/asset.manifest'),
		})
	).pipe(
		replace(/"\.\.\/(\d{4,}\-\d{2}\-\d{2})\.md"/g, '/$1/')
	).pipe(
		replace(/"\.\/((?:yt|tc|is)\-.+).md"/g, '../$1/')
	).pipe(
		replace('.md"', '/"')
	).pipe(
		replace('"./topics/', '"/topics/')
	).pipe(
		replace(/"(?:\.\.\/)+topics\/?/g, '"/topics/')
	).pipe(
		replace(/"(?:(?:\.\.\/)+|\.\/)transcriptions\/?/g, '"/transcriptions/')
	).pipe(
		replace(
			/<a href="https:\/\//g,
			'<a rel="noopener" target="_blank" href="https://'
		)
	).pipe(
		htmlmin({
			collapseInlineTagWhitespace: false,
			collapseWhitespace: true,
			minifyCSS: true,
			minifyJs: true,
			removeAttributeQuotes: true,
			preserveLineBreaks: true,
			removeComments: true,
			useShortDoctype: true,
		})
	).pipe(
		gulp.dest('./tmp')
	);
});

gulp.task('sync-favicon', () => {
	return gulp.src('./images/internal/favicon.ico').pipe(gulp.dest('./dist/'));
});

gulp.task('sitemap', () => {
	return gulp.src(
		'./tmp/**/*.html'
	).pipe(
		sitemap({
			siteUrl: 'https://archive.satisfactory.video/'
		})
	).pipe(
		gulp.dest('./tmp')
	)
});

gulp.task('q-and-a-tracking', (cb) => {
	const data = Object.fromEntries(Object.entries(
		require('./app/data/q-and-a.json')
	).map((e) => {
		const [video_id, video_data] = e;

		return [
			video_id,
			{
				date: video_data.date,
				duplicates: video_data.duplicates,
				replaces: video_data.replaces,
				seealso: video_data.seealso,
				duplicatedby: video_data.duplicatedby || null,
				replacedby: video_data.replacedby || null,
			}
		];
	}));

	writeFileSync('./src/data/q-and-a-tracking.json', JSON.stringify(data));

	return cb();
});

gulp.task('sync-tmp-to-store', () => {
	return gulp.src('./tmp/**/*.{js,css,html,json,xml,gz,br}').pipe(
		changed(
			'./dist/',
			{
				hasChanged: changed.compareContents,
			}
		)
	).pipe(
		gulp.dest(
			'./dist/'
		)
	);
});

gulp.task('build', gulp.series(
	'lunr-index',
	gulp.parallel(
		'q-and-a-tracking',
		'sync-lunr',
		'css',
		'topics',
		'sync-browserconfig',
		'sync-ossd',
		'lunr'
	),
	'rev',
	'lunr-rev',
	'lunr-clean',
	'rev',
	'html',
	'sitemap',
	gulp.parallel(
		...brotli_subtasks
	),
	gulp.parallel(
		'sync-favicon',
	),
	'sync-tmp-to-store'
));

gulp.task('html-error_docs', () => {
	return gulp.src('./tmp-error_docs/**/*.html').pipe(
		replace('.css">', '.css" inline>')
	).pipe(
		inline_source()
	).pipe(
		htmlmin({
			collapseInlineTagWhitespace: false,
			collapseWhitespace: true,
			minifyCSS: true,
			minifyJs: true,
			removeAttributeQuotes: true,
			preserveLineBreaks: true,
			removeComments: true,
			useShortDoctype: true,
		})
	).pipe(
		gulp.dest('./error_docs')
	);
});



gulp.task('brotli-images', () => {
	return gulp.src(
		'./images/**/*.svg'
	).pipe(
		newer({
			dest: './images/',
			ext: '.br',
		})
	).pipe(
		brotli.compress({
			quality: 11,
		})
	).pipe(
		gulp.dest('./images/')
	)
});

gulp.task('zopfli-images', () => {
	return gulp.src(
		'./images/**/*.svg'
	).pipe(
		newer({
			dest: './images/',
			ext: '.gz',
		})
	).pipe(
		zopfli({
			verbose: false,
			verbose_more: false,
			numiterations: 15,
			blocksplitting: true,
			blocksplittinglast: false,
			blocksplittingmax: 15,
		})
	).pipe(
		gulp.dest('./images/')
	)
});

gulp.task('images', gulp.series(
	gulp.parallel(
		/*
		'zopfli-images',
		*/
		'brotli-images'
	)
));
