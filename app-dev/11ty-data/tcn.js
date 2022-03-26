const glob = require('glob');
const {resolve} = require('path');

function video_id_to_url(video_id) {
	if (/^tc\-/.test(video_id)) {
		return `https://clips.twitch.tv/${video_id.substring(3)}`;
	} else if (/^yt\-/.test(video_id)) {
		return `https://youtu.be/${video_id.substring(3)}`;
	}

	return undefined;
}

const docs = glob.sync(
	resolve(__dirname + '/../../src/lunr/') + '/docs-*.json'
).reduce((out, path) => {
	const data = require(path);

	Object.values(data).forEach((row) => {
		const permalink = `/transcriptions/${row.id}/`;

		out[permalink] = {alts: row.alts};

		out[permalink].alts = out[permalink].alts.filter((video_id) => {
			return /^(?:yt|tc)\-/.test(video_id);
		}).map((video_id) => {
			if (/^tc-/.test(video_id)) {
				return `https://clips.twitch.tv/${video_id.substring(3)}`;
			}

			return `https://youtu.be/${video_id.substring(3)}`;
		});

		const maybe = video_id_to_url(row.id);

		if (maybe) {
			out[permalink].alts.push(maybe);
		}

		out[permalink].alts = out[permalink].alts.filter((str) => {
			return ! str.includes(',');
		});
	});

	return Object.fromEntries(Object.entries(out).filter((pair) => {
		return pair[1].alts.length > 0;
	}));
}, {});

module.exports = docs;
