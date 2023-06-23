import {
	promisify,
} from 'util';
import {
	unlink,
} from 'fs/promises';

const glob = promisify(require('glob'));

(async () => {
	let removed = 0;
	const files = await glob('./{tmp,dist,images}/**/*.{gz,br}');

	for (const file of files) {
		await unlink(file);
		++removed;
	}

	console.log(`${removed} files removed`);
})();
