import {exists as existsAsync} from 'fs';
import {writeFile} from 'fs/promises';
import {promisify} from 'util';
import fetch from 'node-fetch';

const exists = promisify(existsAsync);

const playlists_ids = Object.keys(require(`${__dirname}/data/api-cache/playlists.json`));

(async () => {
	let progress = 0;
	for (const playlist_id of playlists_ids) {
		++progress;

		console.log(`${progress} of ${playlists_ids.length}`);

		const cache_html = `${__dirname}/playlists-html-cache/${playlist_id}.html`;

		const html = await (await fetch(`https://www.youtube.com/playlist?list=${playlist_id}`)).text();

		await writeFile(cache_html, html);
	}
})();
