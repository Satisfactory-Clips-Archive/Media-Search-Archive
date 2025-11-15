import {
	fetchTranscript,
	YoutubeTranscriptDisabledError,
	YoutubeTranscriptNotAvailableError,
} from 'youtube-transcript-plus';
import Ajv from 'ajv';

import definitely_skipped from './definitely-skipped.json' assert {type: 'json'};
import skipped from './skipping-transcriptions.json' assert {type: 'json'};
import {dirname} from 'path';
import {fileURLToPath} from 'url';
import {writeFile} from 'fs/promises';

const __dirname = dirname(fileURLToPath(import.meta.url));

let skipped_to_recache_later = [...skipped];

const checking = skipped
	.filter((maybe) => maybe.startsWith('yt-'))
	.reduce(
		(was, is) => {
			if (is.includes(',')) {
				is = is.split(',')[0];
			}
			is = is.replace(/^yt-/, '');

			if (!was.includes(is)) {
				was.push(is);
			}

			return was;
		},
		[]
	)
	.filter((maybe) => !definitely_skipped.includes(maybe));

const validator = (new Ajv({
	strict: true,
	verbose: true,
})).compile({
	type: 'array',
	minItems: 1,
	items: {
		type: 'object',
		required: ['text', 'duration', 'offset', 'lang'],
		additionalProperties: false,
		properties: {
			text: {
				type: 'string',
			},
			duration: {
				type: 'number',
			},
			offset: {
				type: 'number',
			},
			lang: {
				type: 'string',
			},
		},
	},
});

const current_lang_errors = {};

let i = 0;

for (const video_id of checking) {
	console.log(`checking ${i + 1} of ${checking.length}`);

	try {
		const result = await fetchTranscript(video_id);

		if (!validator(result)) {
			console.error(validator.errors);
			throw new Error(`Unsupported result for ${video_id}`);
		}

		const langs = result.reduce((was, is) => {
			if (!was.includes(is.lang)) {
				was.push(is.lang);
			}

			return was;
		}, []);

		if (langs.length > 1) {
			console.error(langs);
			throw new Error(`Multiple langs found for ${video_id}`);
		} else if (langs[0] !== 'en') {
			if (!(langs[0] in current_lang_errors)) {
				current_lang_errors[langs[0]] = [video_id];
			} else {
				current_lang_errors[langs[0]].push(video_id);
			}
		}

		await writeFile(
			`${__dirname}/captions-cache/${video_id}.json`,
			JSON.stringify(
				[result
					.filter((item) => item.text.length > 0)
					.map((item) => item.text)
					.join(' ')],
				null,
				'\t',
			)
		);

		skipped_to_recache_later = skipped_to_recache_later.filter((maybe) => !maybe.startsWith(`yt-${video_id}`));

		await writeFile(
			`${__dirname}/skipping-transcriptions.json`,
			JSON.stringify(skipped_to_recache_later, null, '    ')
		);
	} catch (err) {
		if (
			(err instanceof YoutubeTranscriptDisabledError)
			|| (err instanceof YoutubeTranscriptNotAvailableError)
		) {
			definitely_skipped.push(video_id);

			await writeFile(
				`${__dirname}/definitely-skipped.json`,
				JSON.stringify(definitely_skipped, null, '\t')
			);
		} else {
			throw err;
		}
	}

	++i;
}

console.error(current_lang_errors);

await writeFile(
	`${__dirname}/skipping-transcriptions.json`,
	JSON.stringify(skipped_to_recache_later, null, '    ')
);
