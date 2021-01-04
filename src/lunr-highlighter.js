/**
 * Adapted from https://olivernn.github.io/moonwalkers/index.js
 */
function lunrhighlighter (element, unsorted) {
	const nodeFilter = {
		acceptNode: function (node) {
			if (/^[\t\n\r ]*$/.test(node.nodeValue)) {
				return NodeFilter.FILTER_SKIP
			}

			return NodeFilter.FILTER_ACCEPT
		}
	};

	let index = 0;
	let matches = unsorted.sort(function (a, b) { return a[0] - b[0] }).slice();
	let previousMatch = [-1, -1];
	let match = matches.shift();
	const walker = document.createTreeWalker(
		element,
		NodeFilter.SHOW_TEXT,
		nodeFilter,
		false
	);

	while (node = walker.nextNode()) {
		if (match == undefined) {
			break;
		} else if (match[0] == previousMatch[0]) {
			continue;
		}

		const nodeEndIndex = index + node.length;

		if (match[0] < nodeEndIndex) {
			const range = document.createRange();
			const tag = document.createElement('mark');
			const rangeStart = match[0] - index;
			const rangeEnd = rangeStart + match[1];

			range.setStart(node, rangeStart)
			range.setEnd(node, rangeEnd)
			range.surroundContents(tag)

			index = match[0] + match[1]

			// the next node will now actually be the text we just wrapped, so
			// we need to skip it
			walker.nextNode()
			previousMatch = match
			match = matches.shift()
		} else {
			index = nodeEndIndex
		}
	}
}
