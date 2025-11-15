import {
	promisify,
} from 'util';

import {dirname} from 'path';
import {fileURLToPath} from 'url';
import {readFile, writeFile} from 'fs/promises';
import _glob from 'glob';

const __dirname = dirname(fileURLToPath(import.meta.url));
const glob = promisify(_glob);

const cached = await glob(`${__dirname}/captions-cache/*.json`);

for (const filepath of cached) {
	const contents = (await readFile(filepath)).toString();

	if (contents.startsWith('"')) {
		await writeFile(
			filepath,
			JSON.stringify([JSON.parse(contents)], null, '\t')
		);
	}
}
