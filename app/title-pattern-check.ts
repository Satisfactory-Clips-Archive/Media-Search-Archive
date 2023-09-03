import {
	doc,
	regexes_type,
	filter_title_pattern_check as filter,
} from './ts/exports';

(async () => {
	const {
		promisify,
	} = require('util');
	const glob = promisify(require('glob'));

	const files = (
		await glob(`${__dirname}/lunr/docs-*.json`) as string[]
	);

	if (files.length < 1) {
		console.log('No files to check!');

		return;
	}

	const docs = (
		files
	).map(
		(filename) => {
			return require(filename);
		}
	).reduce(
		(
			flattened:{[key:string]:{id:string}},
			docs:{[key:string]:{id:string}}
		) => {
			const keys = Object.keys(flattened);
			const docs_keys = Object.keys(docs);

			docs_keys.forEach((maybe) => {
				if (keys.includes(maybe)) {
					const a = JSON.stringify(flattened[maybe]);
					const b = JSON.stringify(docs[maybe]);

					if (a !== b) {
						throw new Error('Duplicate id found!');
					}
				}

				flattened[maybe] = docs[maybe];
			});

			return flattened;
		}
	) as {
		[key:string]: doc
	};

	const keys = Object.keys(docs);

	const titles = Object.fromEntries(Object.entries(docs).map((e) => {
		return [e[0], e[1].title];
	}));

	const qanda = filter(keys, 'qanda', titles);

	const talk = filter(keys, 'talk', titles);

	const community_fyi = filter(keys, 'community_fyi', titles);

	const state_of_dev = filter(keys, 'state_of_dev', titles);

	const community_highlights = filter(keys, 'community_highlights', titles);

	const trolling = filter(keys, 'trolling', titles);

	const jace_art = filter(keys, 'jace_art', titles);

	const random = filter(keys, 'random', titles);

	const terrible_jokes = filter(keys, 'terrible_jokes', titles);

	const special_guest = filter(keys, 'special_guest', titles);

	const intro = filter(keys, 'intro', titles);

	let not_matching = keys.filter((maybe) => {
		return (
			! qanda.includes(maybe)
			&& ! talk.includes(maybe)
			&& ! community_fyi.includes(maybe)
			&& ! state_of_dev.includes(maybe)
			&& ! community_highlights.includes(maybe)
			&& ! trolling.includes(maybe)
			&& ! jace_art.includes(maybe)
			&& ! terrible_jokes.includes(maybe)
			&& ! random.includes(maybe)
			&& ! special_guest.includes(maybe)
			&& ! intro.includes(maybe)
		);
	});

	const not_matching_youtube = not_matching.filter((maybe) => {
		return maybe.includes(',') || maybe.startsWith('tc-');
	});

	not_matching = not_matching.filter((maybe) => {
		return ! not_matching_youtube.includes(maybe);
	});

	if (not_matching.length > 0) {
		console.table(Object.fromEntries(not_matching.map((key) => {
			return [key, titles[key]];
		})));

		throw new Error('Titles not matching expected pattern found!');
	}

	console.table({
		'Q&A:': qanda.length,
		'Talk:': talk.length,
		'Community FYI:': community_fyi.length,
		'State of Dev:': state_of_dev.length,
		'Community Highlights:': state_of_dev.length,
		'Trolling:': state_of_dev.length,
		'Jace Art:': jace_art.length,
		'Random:': random.length,
		'Terrible Jokes:': terrible_jokes.length,
		'not matching (YouTube/Twitch):': not_matching_youtube.length,
		'not matching (everything else)': not_matching.length,
	});
})();
