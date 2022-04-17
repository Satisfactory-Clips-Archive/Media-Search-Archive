module.exports = (e) => {
	const markdownIt = require('markdown-it');
	const markdown = markdownIt({
		html: true,
		breaks: true,
		linkify: true,
	});

	//#region adapted from https://github.com/markdown-it/markdown-it/blob/bda94b0521f206a02427ec58cb9a848d9c993ccb/docs/architecture.md#renderer

	const defaultRender = markdown.renderer.rules.link_open || function(
		tokens,
		idx,
		options,
		env,
		self
	) {
		return self.renderToken(tokens, idx, options);
	};

	markdown.renderer.rules.link_open = (tokens, idx, options, env, self) => {
		// If you are sure other plugins can't add `target` - drop check below
		const target = tokens[idx].attrIndex('target');
		const rel = tokens[idx].attrIndex('rel');

		if (target < 0) {
			// add new attribute
			tokens[idx].attrPush(['target', '_blank']);
		} else {
			// replace value of existing attr
			tokens[idx].attrs[target][1] = '_blank';
		}

		if (rel < 0) {
			tokens[idx].attrPush(['rel', 'noopener']);
		} else {
			throw new Error('Unsupported modification needed here');
		}

		// pass token to default renderer.
		return defaultRender(tokens, idx, options, env, self);
	};

	//#endregion

	e.setLibrary('md', markdown);

	e.addFilter('json', (value) => {
		return JSON.stringify(value, null, "\t");
	});

	e.addFilter('jsonld_description', (value) => {
		let maybe = value.find((e) => {
			return 'description' in e;
		});

		if (maybe) {
			return maybe.description;
		}

		maybe = value.find((e) => {
			return (
				('@type' in e)
				&& ['Person', 'Article'].includes(e['@type'])
				&& ('name' in e)
			);
		});

		if (maybe) {
			return `Satisfactory Lviestream clips about ${maybe.name}.`;
		}

		return 'Serves as an unofficial archive for Clips for Coffee Stain Studio\'s Satisfactory-related livestreams';
	});

	e.addFilter('markdown_blockquote', (value) => {
		return markdown.render(value.map((e) => {
			return `> ${e}`.trim() + "\n" + '>';
		}).join("\n"));
	});

	e.addFilter('markdownify', (value) => {
		return markdown.render(value);
	});

	e.addFilter('timestampify', (description, url) => {
		if ( ! /^https:\/\/youtu\.be\/[^,\?]+$/.test(url)) {
			return description;
		}

		return description.replaceAll(/(\d+:\d+) ([^\n]+)(\n)?/g, (
			_line,
			timestamp,
			text,
			newline
		) => {
			const [seconds, minutes, hours] = timestamp.split(':').reverse();

			return `<a href="${
				url
			}?t=${
				(parseInt(hours || 0) * 3600) + parseInt((minutes || 0) * 60) + parseInt(seconds || 0)
			}" rel="noopener" target="_blank">${
				timestamp
			}</a> ${
				text
			}${newline || ''}`;
		})
	});

	return {
		dir: {
			data: '../../11ty/data',
			input: './video-clip-notes/docs',
			layouts: '../../11ty/layouts',
			includes: '../../11ty/includes',
			output: './src',
		},
	};
};
