import {writeFileSync} from "fs";

const topic_slug_history = require(`${__dirname}/topic-slug-history.json`) as {[key: string]: {[key: string]: number}};

const needs_redirects = Object.values(topic_slug_history).filter((maybe) => {
	return Object.values(maybe).length > 1;
}).sort((a, b) => {
	const last_timestamp_a = Object.values(a).slice(-1)[0];
	const last_timestamp_b = Object.values(b).slice(-1)[0];

	return last_timestamp_a - last_timestamp_b;
});

const lines:string[] = [];

for (const topic_set of needs_redirects) {
	const values = Object.keys(topic_set);

	const destination = values.pop();

	for (const url of values) {
		lines.push(`location /topics/${url}/index.html {`);
		lines.push(`\treturn 301 /topics/${destination}$is_args$args;`);
		lines.push('}');
		lines.push(`location /topics/${url} {`);
		lines.push(`\treturn 301 /topics/${destination}$is_args$args;`);
		lines.push('}');
	}
}

writeFileSync(`${__dirname}/nginx-conf-for-topics.txt`, lines.join('\n'));
