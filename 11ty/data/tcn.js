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

		out[permalink] = row;

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
	});

	return out;
}, {});

module.exports = docs;
