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
};
const cssnano = require('cssnano');
const rename = require('gulp-rename');

gulp.task('css', () => {
	return gulp.src('./src/**/*.postcss').pipe(
		postcss([
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

gulp.task('brotli', () => {
	return gulp.src(
		'./tmp/**/*.{js,json,css,html}'
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
});

gulp.task('zopfli', () => {
	return gulp.src(
		'./tmp/**/*.{js,json,css,html}'
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
		'./src/index.html'
	).pipe(
		rev_replace({
			manifest: gulp.src('./tmp/asset.manifest'),
		})
	).pipe(
		htmlmin({
			collapseInlineTagWhitespace: true,
			collapseWhitespace: true,
			minifyCSS: true,
			minifyJs: true,
			removeAttributeQuotes: true,
			removeComments: true,
			useShortDoctype: true,
		})
	).pipe(
		gulp.dest('./tmp')
	);
});

gulp.task('sync-favicon', () => {
	return gulp.src('./src/favicon.ico').pipe(gulp.dest('./dist/'));
});

gulp.task('sync-tmp-to-store', () => {
	return gulp.src('./tmp/**/*.{js,css,html,json,gz,br}').pipe(
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
	'css',
	'rev',
	'html',
	gulp.parallel(
		'zopfli',
		'brotli'
	),
	gulp.parallel(
		'sync-favicon',
	),
	'sync-tmp-to-store'
));
