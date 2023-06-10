export declare type doc = {
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

export declare type regexes_type = {
	qanda: string[],
	talk: string[],
	community_fyi: string[],
	state_of_dev: string[],
	community_highlights: string[],
	trolling: string[],
	jace_art: string[],
	random: string[],
	terrible_jokes: string[],
	special_guest: string[],
	intro: string[],
};

export const regexes_title_pattern_check = Object.fromEntries(
	Object.entries(
		require(`${__dirname}/../title-pattern-check.json`) as regexes_type
	).map((e) => {
		return [
			e[0],
			e[1].map((str) => {
				return new RegExp(str);
			}),
		];
	})
);

export const regexes_title_pattern_check_keys = Object.keys(
	regexes_title_pattern_check
);

export function filter_title_pattern_check(
	list_of_keys:string[],
	str:keyof regexes_type,
	titles:{[key:string]:string}
) : string[] {
	return list_of_keys.filter((maybe:string) : boolean => {
		let result = false;

		for (let regex of regexes_title_pattern_check[str]) {
			if (regex.test(titles[maybe])) {
				result = true;
				break;
			}
		}

		if ('qanda' === str && result) {
			for (
				let other_str of regexes_title_pattern_check_keys.filter(
					(maybe_str) => {
						return 'qanda' !== maybe_str;
					}
				)
			) {
				if (
					1 === filter_title_pattern_check(
						[maybe],
						other_str as keyof regexes_type,
						titles
					).length
				) {
					return false;
				}
			}
		}

		return result;
	});
}
