declare type doc = {
	id: string,
	game: 'satisfactory',
	date: string,
	title: string,
	transcription: string,
	urls: string[],
	topics: string[],
	quotes: string[],
	alts: string[],
};

declare type regexes_type = {
	qanda: string[],
	talk: string[],
	community_fyi: string[],
	state_of_dev: string[],
	community_highlights: string[],
	trolling: string[],
	jace_art: string[],
	random: string[],
	terrible_jokes: string[],
};

(async () => {
	const {
		promisify,
	} = require('util');
	const glob = promisify(require('glob'));

	const docs = (
		await glob(`${__dirname}/../src/lunr/docs-*.json`) as string[]
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
					console.error(maybe);
					throw new Error('Duplicate id found!');
				}

				flattened[maybe] = docs[maybe];
			});

			return flattened;
		}
	) as {
		[key:string]: doc
	};

	const regexes = Object.fromEntries(
		Object.entries(
			require(`${__dirname}/title-pattern-check.json`) as regexes_type
		).map((e) => {
			return [
				e[0],
				e[1].map((str) => {
					return new RegExp(str);
				}),
			];
		})
	);

	function filter(list_of_keys:string[], str:keyof regexes_type) : string[] {
		return list_of_keys.filter((maybe:string) : boolean => {
			let result = false;

			for (let regex of regexes[str]) {
				if (regex.test(titles[maybe])) {
					result = true;
					break;
				}
			}

			if ('qanda' === str && result) {
				for (
					let other_str of Object.keys(regexes).filter((maybe_str) => {
						return 'qanda' !== maybe_str;
					})
				) {
					if (
						1 === filter(
							[maybe],
							other_str as keyof regexes_type
						).length
					) {
						return false;
					}
				}
			}

			return result;
		});
	}

	const keys = Object.keys(docs);

	const titles = Object.fromEntries(Object.entries(docs).map((e) => {
		return [e[0], e[1].title];
	}));

	const qanda = filter(keys, 'qanda');

	const talk = filter(keys, 'talk');

	const community_fyi = filter(keys, 'community_fyi');

	const state_of_dev = filter(keys, 'state_of_dev');

	const community_highlights = filter(keys, 'community_highlights');

	const trolling = filter(keys, 'trolling');

	const jace_art = filter(keys, 'jace_art');

	const random = filter(keys, 'random');

	const terrible_jokes = filter(keys, 'terrible_jokes');

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
