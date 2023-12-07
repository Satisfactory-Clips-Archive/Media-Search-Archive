const dated = require(`${__dirname}/../../Community-Highlights-Archive-Data/data/dated.json`);
const {readFileSync} = require("fs");
const has_author_but_no_links = Object.fromEntries(
	Object.entries(require(
		`${__dirname}/../../Community-Highlights-Archive-Data/data/has-author-but-no-links.json`
	)).map((e) => {
		return [
			e[0],
			Object.fromEntries(Object.entries(e[1]).map((f) => {
				const lines = Object.values(f[1]);

				lines.sort((a, b) => {
					return a.timestamp - b.timestamp;
				});

				return [f[0], lines[0].timestamp];
			}))
		]
	})
);

const remapped = Object.fromEntries(Object.entries(dated).map((e) => {
	return [
		e[0],
		Object.entries(e[1]).map((f) => {
			const timestamp = (readFileSync(
				`${__dirname}/../../Community-Highlights-Archive-Data/data/by-youtube-video/${f[0]}/desc.txt`
			) + '').split('\n').filter((maybe) => {
				return /^\d*:\d{2}(:\d{2})? /.test(maybe);
			})[0].split(' ')[0].split(':').map((g, i) => {
				return parseInt(g, 10) * (60 ** i);
			}).reduce((was, is) => { return was + is; }, 0);

			const t = Math.min(
				timestamp,
				((e[0] in has_author_but_no_links) ? (f[0] in has_author_but_no_links[e[0]] ? has_author_but_no_links[e[0]][f[0]] : Infinity) : Infinity)
			);

			return {
				id: f[0],
				title: f[1],
				link: (
					t
						? `https://youtu.be/${f[0].replace(/^yt-/, '')}?t=${t}`
						: `https://youtu.be/${f[0].replace(/^yt-/, '')}`
				),
			};
		}),
	];
}));

module.exports = remapped;
