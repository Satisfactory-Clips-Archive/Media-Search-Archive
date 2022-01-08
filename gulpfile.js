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
	readFile,
} = require('fs');
const inline_source = require('gulp-inline-source');
const lunr = require('lunr');
const libsquoosh = require('gulp-libsquoosh');
const {
	promisify,
} = require('util');
const glob = promisify(require('glob'));
const prom = {
	readFile: promisify(readFile),
};
const svgToImg = require('svg-to-img');
const squoosh = require('gulp-libsquoosh');
const puppeteer = require('puppeteer');

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
	return gulp.src('./src/**/*.{js,json,css,svg}').pipe(
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
	'brotli--svg',
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

gulp.task('brotli--svg', () => {
	return make_brotli_task('./tmp/**/*.svg');
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
		'./dist/**/*.html'
	).pipe(
		sitemap({
			siteUrl: 'https://archive.satisfactory.video/',
		})
	).pipe(
		replace(
			'<loc>https://archive.satisfactory.video/</loc>',
			'<loc>https://archive.satisfactory.video/</loc><changefreq>weekly</changefreq>'
		)
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
	return gulp.src('./tmp/**/*.{js,css,html,json,xml,svg,gz,br}').pipe(
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

gulp.task('images-svg-conversion--png', () => {
	return gulp.src('./images-tmp/internal/content/**/*.png').pipe(
		libsquoosh({
			webp: {},
		})
	).pipe(gulp.dest('./images/internal/content/'));
});

gulp.task('images-svg-conversion', async (cb) => {
	const files = await glob('./images-tmp/internal/content/**/*.svg');

	for (let file of files) {
		const svg = await prom.readFile(file);

		await svgToImg.from(svg).toPng({path: file.replace(/\.svg$/, '.png')});
	}

	cb();
});

const webp_options = {
	preprocessOptions: {
		resize: {
			enabled: false,
			premultiply: true,
			linearRGB: true,
		},
	},
	encodeOptions: {
		webp: {
			lossless: 1,
			near_lossless: 50,
			effort: 9,
		},
	},
}

gulp.task('images-svg--background--flannel', () => {
	const squoosh_options = Object.assign({}, webp_options);

	squoosh_options.preprocessOptions.resize.width = 504;
	squoosh_options.preprocessOptions.resize.height = 504;

	return gulp.src(
		'./images-ref/red-flannel/12951396883_d05fb22ed8_o.webp'
	).pipe(
		squoosh(squoosh_options)
	).pipe(
		rename('flannel--bg.webp')
	).pipe(gulp.dest(
		'./images/internal/content/topics/coffee-stainers/'
	))
});

gulp.task('images-svg--background--golf', () => {
	const squoosh_options = Object.assign({}, webp_options);

	squoosh_options.preprocessOptions.resize.height = 504;

	return gulp.src(
		'./images-ref/golf-2571830/golf-2571830.jpg'
	).pipe(
		squoosh(squoosh_options)
	).pipe(
		rename('golf--bg.webp')
	).pipe(gulp.dest(
		'./images/internal/content/topics/features/requested-features/'
	))
});

gulp.task('images-svg--background--vulkan', () => {
	const squoosh_options = Object.assign({}, webp_options);

	squoosh_options.preprocessOptions.resize.width = 504;
	squoosh_options.preprocessOptions.resize.height = 504;

	return gulp.src(
		'./images-ref/vulkan.webp'
	).pipe(
		squoosh(squoosh_options)
	).pipe(
		rename('vulkan--bg.webp')
	).pipe(gulp.dest(
		'./images/internal/content/topics/technology/'
	))
});

gulp.task('images-svg', gulp.series(...[
	'images-svg-conversion',
	'images-svg-conversion--png',
]));

gulp.task('chart--all-topics', async (cb) => {
	const data = JSON.parse(readFileSync(
		`${__dirname}/app/data/dated-video-statistics.json`
	));

	const entries = Object.entries(data);

	let max = 0;

	entries.forEach((e) => {
		max = Math.max(max, e[1][0], e[1][1]);
	});

	const bars = entries.map((e, i) => {
		const height_a = e[1][0] * 16;
		const height_b = e[1][1] * 16;

		return `<g><rect class="all" x="${
			32 + (i * 32)}" y="${
				32 + ((max * 16) - height_a)
			}" width="16" height="${
				height_a
			}"><title>${
				e[1][0]
			} Total clips for ${
				e[0]
			}</title></rect><rect class="qna" x="${
				32 + (i * 32)}" y="${
					32 + ((max * 16) - height_b)
			}" width="16" height="${
				height_b
			}"><title>${
				e[1][1]
			} questions of ${
				e[1][0]
			} total clips for ${
				e[0]
			} questions</title></rect></g>`
	});

	const svg_width = 32 + (entries.length * 32) + 32;
	const svg_height = (max * 16) + 64;

	const svg = `<svg width="${
			svg_width
		}" height="${
			svg_height
		}" viewbox="0 0 ${
			svg_width
		} ${
			svg_height
		}"
		preserveAspectRatio="none"
		xmlns="http://www.w3.org/2000/svg"><style>${
			[
				'.all{ fill:#fa9549; }',
				'.qna{ fill:#5f668c; }',
			].join('\n')
		}</style>${bars.join('\n')}</svg>`;

	writeFileSync(`${__dirname}/11ty/charts/stats.svg`, svg);

	cb();
});

gulp.task('chart--separate-topics', (cb) => {
	const data = JSON.parse(readFileSync(
		`${__dirname}/app/data/dated-topic-statistics.json`,
	));

	const processed = Object.entries(data).map((entry) => {
		const entries = Object.entries(entry[1][1]);

		let max = 0;

		entries.forEach((e) => {
			max = Math.max(max, e[1][0], e[1][1]);
		});

		const bars = entries.map((e, i) => {
			const height_a = e[1][0] * 16;
			const height_b = e[1][1] * 16;

			return `<g><rect class="all" x="${
				32 + (i * 32)}" y="${
					32 + ((max * 16) - height_a)
				}" width="16" height="${
					height_a
				}"><title>${
					e[1][0]
				} Total clips for ${
					e[0]
				}</title></rect><rect class="qna" x="${
					32 + (i * 32)}" y="${
						32 + ((max * 16) - height_b)
				}" width="16" height="${
					height_b
				}"><title>${
					e[1][1]
				} questions of ${
					e[1][0]
				} total clips for ${
					e[0]
				} questions</title></rect></g>`
		});

		const svg_width = 32 + (entries.length * 32) + 32;
		const svg_height = (max * 16) + 64;

		const svg = `<svg width="${
				svg_width
			}" height="${
				svg_height
			}" viewbox="0 0 ${
				svg_width
			} ${
				svg_height
			}"
			preserveAspectRatio="none"
			xmlns="http://www.w3.org/2000/svg"><style>${
				[
					'.all{ fill:#fa9549; }',
					'.qna{ fill:#5f668c; }',
				].join('\n')
			}</style>${bars.join('\n')}</svg>`;

		writeFileSync(
			`${__dirname}/src/statistics/charts/topics/${entry[0]}.svg`,
			svg
		);

		return [entry[1][0], entry[0], svg_width, svg_height];
	});

	writeFileSync(
		`${__dirname}/11ty/data/topic_charts.json`,
		JSON.stringify(processed)
	);

	cb();
});

const charts = [
	'chart--all-topics',
	/*
	'chart--separate-topics',
	*/
];

gulp.task('charts', gulp.parallel(...charts));

gulp.task('snutt-burger-time', async () => {
	const browser = await puppeteer.launch();
	const page = await browser.newPage();
	await page.setViewport({
		width: 1920,
		height: 1080,
	});
	await page.goto(
		'https://twitter.com/BustaSnutt/status/1430230082270937090',
		{
			waitUntil: 'networkidle0',
		}
	);
	await page.evaluate(() => {
		const buttons = document.querySelector('article [role="group"]');

		buttons.parentNode.removeChild(buttons);
	});
	const tweet = await page.$('article');

	const filename = `${
		__dirname
	}/images/internal/content/topics/coffee-stainers/snutt/snutt-burger-time--bg.png`;

	return await tweet.screenshot({
		path: filename,
		type: 'png',
	});
});

gulp.task('build', gulp.series(...[
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
	gulp.parallel(
		...brotli_subtasks
	),
	gulp.parallel(
		'sync-favicon',
	),
	'sync-tmp-to-store',
	'sitemap',
	'brotli--most-everything',
	'sync-tmp-to-store',
]));

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

gulp.task('fixtsc', () => {
	return gulp.src('./src/**/*.js').pipe(
		replace('    ', '\t')
	).pipe(gulp.dest('./src/'))
});
