declare type info_card = [string, number, 'video'|'playlist'|'url'|'channel', string];

const questions = require(`${__dirname}/../data/q-and-a.json`) as {
	[key: string]: {
		title:string,
		date:string,
		topics?:string[],
		duplicates?:string[],
		replaces?:string[],
		replacedby?:string,
		duplicatedby?:string,
		seealso?:string[],
		seealso_video_cards?:string[],
		seealso_topic_cards?:string[],
		seealso_card_urls?:[string, string, string][],
		seealso_card_channels?:[string, string][],
		incoming_video_cards?:string[],
		legacyalts?:string[]
	}
};
const skipping = require(`${__dirname}/../data/skipping-cards.json`);

async function questions_needing_qasite_reference() : Promise<string[]> {
	return Object.entries(questions).filter(
		(e) => {
			const {topics} = e[1];

			for (const maybe of (topics as string[])) {
				if (
					maybe.startsWith('features/requested-features')
					|| maybe.startsWith('features/possible-features')
					|| maybe.startsWith('features/unplanned-features')
					|| maybe.startsWith('environment/biomes/unplanned-biomes')
				) {
					return true;
				}
			}

			return false;
		}
	).map(e => e[0]);
}

async function info_cards(video_id:string) : Promise<info_card[]> {
	const file_id = 11 === video_id.length ? `yt-${video_id}` : video_id;

	return require(`${__dirname}/../cards-cache/${file_id}.json`) as info_card[];
}

async function video_does_not_have_qasite_post_info_card(video_id:string) : Promise<boolean> {
	const cards = await info_cards(video_id);

	return ! cards.find((maybe) => {
		const [,,type, payload] = maybe;

		if ('url' === type) {
			const [,,url] = JSON.parse(payload);

			return url.startsWith('https://questions.satisfactorygame.com/post/');
		}

		return false;
	});
}

async function video_does_not_have_qasite_description(video_id:string) : Promise<boolean> {
	if ( ! /^yt-/.test(video_id)) {
		return false;
	}

	const description = require(
		`${
			__dirname
		}/../data/api-cache/video-descriptions/${
			video_id
				.replace(/,.+/, '')
				.replace(/^yt-/, '')
		}.json`
	);

	return ! description.includes('https://questions.satisfactorygame.com/post/');
}

export async function* questions_needing_qasite_references() : AsyncGenerator<string, void, void> {
	for (const video_id of await questions_needing_qasite_reference()) {
		if (
			await video_does_not_have_qasite_post_info_card(video_id)
			&& await video_does_not_have_qasite_description(video_id)
		) {
			yield video_id;
		}
	}
}
