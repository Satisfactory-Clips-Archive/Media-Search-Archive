const {sync:glob} = require('glob');
const mime = {
	svg: 'image/svg+xml',
	png: 'image/png',
	webp: 'image/webp',
};
const size = /^logo\-(\d+)\./;

module.exports = glob(
	'./images/internal/logo*.{svg,png,webp}'
).map((filename) => {
	const href = `https://i.img.archive.satisfactory.video/${
		filename.replace('./images/internal/', '')
	}`;
	const maybe = size.exec(href);

	return [
		href,
		mime[href.replace(/^.+\./, '')],
		maybe ? maybe[1] : 800,
	];
});
