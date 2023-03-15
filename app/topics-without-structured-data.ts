const {
	existsSync:exists,
	writeFileSync:write,
} = require('fs');

const topic_slugs = Object.keys(require(`${__dirname}/topics-satisfactory.json`));

const needs_structured_data = [];

for (const topic_slug of topic_slugs) {
	if ( ! exists(`${__dirname}/../Media-Archive-Metadata/src/permalinked/topics/${topic_slug}.js`)) {
		needs_structured_data.push(topic_slug);
	}
}

write(
	`${__dirname}/topics-without-structured-data.json`,
	JSON.stringify(needs_structured_data.sort(), null, '\t') + '\n'
);
