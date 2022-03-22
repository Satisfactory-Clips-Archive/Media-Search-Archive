const {
	writeFile,
} = require('fs/promises');

(async () => {
	const arrays = [
		'./11ty/data/tweets.json',
		'./11ty/data/transcriptions.json',
		'./11ty/data/topic_charts.json',
		'./11ty/data/indexDatedKeys.json',
		'./11ty/data/dated.json',

	];

	const objects = [
		'./11ty/data/topicStrings.json',
		'./11ty/data/topicStrings_reverse.json',
		'./11ty/data/topics.json',
		'./11ty/data/indexDated.json',
		'./11ty/data/faq.json',
	];

	const proms = [];

	for (let filename of arrays) {
		proms.push(writeFile(filename, '[]'));
	}

	for (let filename of objects) {
		proms.push(writeFile(filename, '{}'));
	}

	await Promise.all(proms);

	console.log(`${arrays.length + objects.length} files blanked`);
})();
