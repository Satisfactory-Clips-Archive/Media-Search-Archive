import {exists as existsAsync} from 'fs';
import {writeFile} from 'fs/promises';
import {promisify} from 'util';
import fetch from 'node-fetch';

const exists = promisify(existsAsync);

const playlists_ids = Object.keys(require(`${__dirname}/data/api-cache/playlists.json`));

(async () => {
	let progress = 0;
	const batch_size = 8;
	for (let index = 0; index < playlists_ids.length; index += batch_size) {
		const playlist_ids_in_batch = playlists_ids.slice(index, index + batch_size);

		await Promise.all(playlist_ids_in_batch.map(async (playlist_id) => {
		++progress;

		console.log(`${progress} of ${playlists_ids.length}`);

		const cache_html = `${__dirname}/playlists-html-cache/${playlist_id}.html`;

		const html = await (await fetch(`https://www.youtube.com/playlist?list=${playlist_id}`)).text();

		await writeFile(cache_html, html);
		}))
	}
})();
