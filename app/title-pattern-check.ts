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

	const keys = Object.keys(docs);

	const titles = Object.fromEntries(Object.entries(docs).map((e) => {
		return [e[0], e[1].title];
	}));

	const qanda = keys.filter((key) => {
		return (
			/^((?:Mod highlight part 2 )?Q&A): /.test(titles[key])
			|| /\?$/.test(titles[key])
		);
	});

	const talk = keys.filter((key) => {
		return /^(?:[^:]+ (?:Talk|Math)|(?:(?:Snutt) )?PSA|Special Guest|State of Stream): /.test(titles[key]);
	});

	const community_fyi = keys.filter((key) => {
		return /^Community FYI: /.test(titles[key]);
	});

	const state_of_dev = keys.filter((key) => {
		return /^State of Dev: /.test(titles[key]);
	});

	const community_highlights = keys.filter((key) => {
		return /^(?:(?:(?:Localization) )?Community|Mod)(?: (?:Highlights?|highlight))?(?: part \d+)?: /.test(titles[key]);
	});

	const trolling = keys.filter((key) => {
		return /^Trolling: /.test(titles[key]);
	});

	const jace_art = keys.filter((key) => {
		return /^Jace Art: /.test(titles[key]);
	});

	const random = keys.filter((key) => {
		return /^Random: /.test(titles[key]);
	});

	const terrible_jokes = keys.filter((key) => {
		return /^Terrible Jokes?: /.test(titles[key]);
	});

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

	console.table(Object.fromEntries(not_matching.map((key) => {
		return [key, titles[key]];
	})));

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
