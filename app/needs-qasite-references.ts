import {
	questions_needing_qasite_references,
} from './ts/questions-needing-qasite-references';

(async () => {
	const ids = [];

	for await (const video_id of await questions_needing_qasite_references()) {
		ids.push(video_id);
	}

	console.log(ids.join('\n'));
})();
