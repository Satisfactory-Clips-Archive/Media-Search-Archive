import {
	TwitterApi,
	InlineErrorV2,
	TweetV2SingleResult,
} from 'twitter-api-v2';

declare type TweetThread = {
	title: string,
	tweet_ids: string[],
	topics: string[],
	seealso: string[],
};

declare type TweetAuthor = {
	name: string,
	username: string,
};

declare type DecoratedTweet = TweetV2SingleResult & {
	author: TweetAuthor,
};

declare type TweetThreadWithTweets = TweetThread & {
	id: string,
	archive_date: string,
	tweets: DecoratedTweet[],
	authors: TweetAuthor[],
	all_in_same_thread: boolean,
};

const auth = require(`${__dirname}/twitter-auth.json`);
const tweet_threads = require(`${__dirname}/data/tweets.json`) as TweetThread[];
const tweets_not_found = require(`${__dirname}/data/tweets-not-found.json`) as string[];


const {
	readFile,
	writeFile,
	stat,
} = require('fs/promises');

let tweets_not_found_changed = false;

const client = (new TwitterApi(auth)).readOnly;

(async () => {
	const unsupported_errors:InlineErrorV2[] = [];
	const tweets_to_cache:string[] = [];

	async function cache_not_stale(tweet_id:string) : Promise<boolean> {
		if ( ! /^\d+$/.test(tweet_id)) {
			throw new Error('Unsupported tweet id found!');
		}

		try {
			const maybe_stat = await stat(
				`${__dirname}/tweet-cache/${tweet_id}.json`
			);

			if (
				(
					(new Date()).getTime() -
					(new Date(maybe_stat.mtime)).getTime()
				) > 3600_000
			) {
				return false;
			}
		} catch (err) {
			if (
				'object' === typeof(err)
				&& 'code' in (err as object)
				&& 'ENOENT' === (err as {code:any}).code
			) {
				return false;
			} else {
				console.error(err);

				throw new Error('Error checking state of cache!');
			}
		}

		return true;
	}

	async function tweet(tweet_id:string, fresh = false) : Promise<DecoratedTweet> {
		let from_api = fresh;

		if ( ! fresh) {
			from_api = await cache_not_stale(tweet_id);
		}

		let twitter_tweet:TweetV2SingleResult;

		if (from_api) {
			twitter_tweet = await client.v2.singleTweet(tweet_id, {
				expansions: [
					'attachments.media_keys',
					'author_id',
					'referenced_tweets.id',
					'entities.mentions.username',
				],
				'media.fields': [
					'preview_image_url',
					'alt_text',
					'media_key',
					'duration_ms',
					'width',
					'height',
					'type',
					'url',
				],
				'tweet.fields': [
					'attachments',
					'author_id',
					'context_annotations',
					'conversation_id',
					'created_at',
					'entities',
					'geo',
					'id',
					'in_reply_to_user_id',
					'lang',
					'possibly_sensitive',
					'text'
				]
			});

			if ('errors' in twitter_tweet) {
				(twitter_tweet.errors as InlineErrorV2[]).forEach((error) => {
					if (
						'tweet' === error?.resource_type
						&& 'Not Found Error' === error.title
					) {
						if ( ! ('resource_id' in error)) {
							console.error(error);

							throw new Error(
								'resource id not present on tweet error'
							);
						}

						tweets_not_found.push(error.resource_id as string);
						tweets_not_found_changed = true;
					} else {
						unsupported_errors.push(error);
					}
				});
			} else {
				await writeFile(
					`${__dirname}/tweet-cache/${tweet_id}.json`,
					JSON.stringify(twitter_tweet, null, '\t')
				);
			}
		} else {
			twitter_tweet = JSON.parse(
				await readFile(`${__dirname}/tweet-cache/${tweet_id}.json`)
			) as TweetV2SingleResult;
		}

		const author = twitter_tweet.includes?.users?.find((user) => {
			return user.id === twitter_tweet.data.author_id;
		});

		if ( ! author) {
			throw new Error('Cannot find author!');
		}

		return Object.assign({}, twitter_tweet, {
			author: {
				username: author.username,
				name: author.name,
			},
		}) as DecoratedTweet;
	}

	for (let thread of tweet_threads) {
		for (
			let tweet_id of thread.tweet_ids.filter((tweet_id) => {
				return ! tweets_not_found.includes(tweet_id);
			})
		) {
			if ( ! /^\d+$/.test(tweet_id)) {
				throw new Error('Unsupported tweet id found!');
			}

			if ( ! await cache_not_stale(tweet_id)) {
				tweets_to_cache.push(tweet_id);
			}
		}
	}

	for (
		let tweet_id of tweets_to_cache.filter(
			(maybe) => {
				return ! tweets_not_found.includes(maybe);
			}
		)
	) {
		await tweet(tweet_id, true);
	}

	if (tweets_not_found_changed) {
		await writeFile(
			`${__dirname}/data/tweets-not-found.json`,
			JSON.stringify(
				[...new Set(tweets_not_found)].sort(),
				null,
				'\t'
			)
		);
	}

	if (unsupported_errors.length) {
		unsupported_errors.forEach((error) => {
			console.error(error);
		});

		throw new Error('Unsupported errors detected!');
	}

	const eleventy_tweets:TweetThreadWithTweets[] = [];

	for (let thread of tweet_threads) {
		const id = `tt-${thread.tweet_ids.join(',')}`;

		const tweets = await Promise.all(thread.tweet_ids.filter(
			(tweet_id:string) => {
				return ! tweets_not_found.includes(tweet_id);
			}).map(async (tweet_id) : Promise<DecoratedTweet> => {
				return await tweet(tweet_id);
			})
		);

		if (tweets.length < 1) {
			continue;
		}

		const archive_date = (
			new Date(tweets[0].data.created_at as string)
		).toISOString().split('T')[0];

		eleventy_tweets.push(Object.assign({}, thread, {
			id,
			archive_date,
			tweets,
			authors: Object.values(tweets.map(e => e.author).reduce(
				(
					out:{[key:string]:TweetAuthor},
					value:TweetAuthor
				):{[key:string]:TweetAuthor} => {
					out[value.username] = value;

					return out;
				},
				{}
			)),
			all_in_same_thread: tweets.reduce(
				(maybe, tweet) => {
					if (
						maybe
						&& tweets[0].data.id === (
							tweet.data.conversation_id ?? undefined
						)
					) {
						return true;
					}

					return false;
				},
				true
			),
		}));
	}

	await writeFile(
		`${__dirname}/../11ty/data/tweets.json`,
		JSON.stringify(eleventy_tweets, null, '\t')
	);
})();
