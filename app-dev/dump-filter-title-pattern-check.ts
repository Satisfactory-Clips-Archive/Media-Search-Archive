import {
	writeFileSync
} from 'fs';
import {
	regexes_title_pattern_check_keys,
	filter_title_pattern_check,
	regexes_type,
} from '../app/ts/exports';

const kvp = require(
	`${__dirname}/../tests/fixtures/title-kvp.json`
) as {[key:string]:string};

const video_ids = Object.keys(kvp);

const result = regexes_title_pattern_check_keys.reduce(
	(result:{[key:string]:string[]}, str:string) => {
		result[str] = filter_title_pattern_check(
			video_ids,
			str as keyof regexes_type,
			kvp
		);

		return result;
	},
	{}
);

writeFileSync(
	`${__dirname}/../tests/fixtures/title-pattern-check.ts.json`,
	JSON.stringify(result, null, '\t')
);
