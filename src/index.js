(async () => {
	if ( ! (URLSearchParams)) {
		throw new Error('Unsupported');
	}

	const ordinals = [
		'th',
		'st',
		'nd',
		'rd',
		'th',
		'th',
		'th',
		'th',
		'th',
		'th',
	];
	const maybe_date_max = [0, 20200901];
	const maybe_date_min = [Infinity, 20200901];
	const date_values = [];
	const date_values_sorted = [];
	const topic_prefix = 'https://github.com/SignpostMarv/twitch-clip-notes/blob/main/coffeestainstudiosdevs/satisfactory/topics/';
	const topic_suffix = '.md';

	let timeout;
	let last_query = '';

	function search (query, on_index) {
		return on_index.search(query).map((result) => {
			return [docs[result.ref], result];
		});
	};

	function nudge_visibility() {
		result_count.parentNode.style.visibility = (
			('' === searchbar.value)
				? 'hidden'
				: 'visible'
		);
	}

	function perform_search (on_index, modify_state = true) {
		results_container.textContent = '';

		cancelAnimationFrame(timeout);
		nudge_visibility();

		timeout = requestAnimationFrame(() => {
			const query = searchbar.value;
			const title = `Q&A Clips Archive - Search - ${query}`;

			last_query = query;

			const formdata = new FormData(form);
			const a = date_values_sorted[parseInt(formdata.get('df'), 10)];
			const b = date_values_sorted[parseInt(formdata.get('dt'), 10)];

			formdata.set('df', date_int_to_date(Math.min(a, b)));
			formdata.set('dt', date_int_to_date(Math.max(a, b)));

			const filter_date_from = parseInt(
				formdata.get('df').replace(/\-/g, ''),
				10
			);
			const filter_date_to = parseInt(
				formdata.get('dt').replace(/\-/g, ''),
				10
			);
			const filter_topic = formdata.get('t');
			const sort_by_relevance = 'd' !== formdata.get('s');

			formdata.set('s', sort_by_relevance ? 'r' : 'd');

			document.head.querySelector('title').textContent = title;

			if (modify_state) {
				history.pushState(
					{},
					title,
					`?q=${
						encodeURIComponent(last_query)
					}&df=${
						encodeURIComponent(formdata.get('df'))
					}&dt=${
						encodeURIComponent(formdata.get('dt'))
					}&t=${
						encodeURIComponent(formdata.get('t'))
					}&s=${
						encodeURIComponent(formdata.get('s'))
					}`
				);
			}

			let docs_results = search(
				query.split(' ').map((e) => {
					return e.replace(/\.+$/, '');
				}).join(' '),
				on_index
			).filter((e) => {
				const [doc, result] = e;
				const dateint = parseInt(doc.date.replace(/\-/g, ''), 10);

				return (
					dateint >= filter_date_from
					&& dateint <= filter_date_to
					&& (
						'' === filter_topic
						|| doc.topics.includes(filter_topic)
					)
				);
			});

			if ( ! sort_by_relevance) {
				docs_results = docs_results.sort((a, b) => {
					const aint = parseInt(a[0].date.replace(/\-/g, ''), 10);
					const bint = parseInt(b[0].date.replace(/\-/g, ''), 10);

					return bint - aint;
				});
			}

			result_count.textContent = `${
					docs_results.length
				} results found matching the term "${
					query
				}", under ${
					'' === filter_topic
						? ' all topics'
						: (
							' the "'
							+ (topics[filter_topic] || ['unknown']).join(' > ')
							+ '" topic '
						)
				}`;
			date_from_output_update(true);
			date_to_output_update(true);

			if (docs_results.length > 0) {
				const frag = document.createElement('ol');

				docs_results.map((e) => {
					const [doc, result] = e;
					const node = search_result_template.cloneNode(true);
					const doc_date = new Date(doc.date);

					node.querySelector('h1').textContent = (
						date_format(doc_date)
						+ ' Livestream'
					);
					node.querySelector('h2').textContent = doc.title;

					if (doc.urls.length > 1) {
						var urls_dest = document.createElement('ol');

						doc.urls.map((url, i) => {
							const anchor = document.createElement('a');
							const part = document.createElement('li');

							anchor.rel = 'noopener';
							anchor.target = '_blank';
							anchor.textContent = url;
							anchor.href = url;

							part.textContent = `Part ${i + 1}: `;
							part.appendChild(anchor);

							return part;
						}).forEach((subnode) => {
							urls_dest.appendChild(subnode);
						});
					} else {
						var urls_dest = document.createElement('p');
						const anchor = document.createElement('a');

						anchor.rel = 'noopener';
						anchor.target = '_blank';
						anchor.textContent = doc.urls[0];
						anchor.href = doc.urls[0];

						urls_dest.appendChild(anchor);
					}

					doc.topics.map((topic) => {
						const subnode = document.createElement('li');
						const anchor = document.createElement('a');

						anchor.rel = 'noopener';
						anchor.target = 'blank';
						anchor.textContent = topic;
						anchor.href = `${topic_prefix}${topic}${topic_suffix}`;

						subnode.appendChild(anchor);

						return subnode
					}).forEach((subnode) => {
						node.querySelector('ul').appendChild(subnode);
					});

					node.querySelector('section').replaceChild(urls_dest, node.querySelector('div'));

					const transcription = node.querySelector('aside');
					transcription.textContent = doc.transcription;

					const positions = [];

					Object.keys(result.matchData.metadata).forEach((term) => {
						positions.push(...(
								result.matchData.metadata[term].transcription
								|| {position:[]}
							).position
						);
					});
					lunrhighlighter(
						transcription,
						positions
					);

					return node;
				}).forEach((node) => {
					frag.appendChild(node);
				});

				results_container.appendChild(frag);
			}
		});
	};

	function hashsearch(on_index, modify_state = true) {
		const params = new URLSearchParams(location.search);

		if (params.has('q')) {
			searchbar.value = params.get('q');

			if (params.has('df')) {
				date_from_value_by_date(params.get('df'));
			} else {
				date_from_value_by_index(date_from.min);
			}

			if (params.has('dt')) {
				date_to_value_by_date(params.get('dt'));
			} else {
				date_to_value_by_index(date_to.min);
			}

			if (params.has('t')) {
				const maybe_topic = topic_filter.querySelector(
					`[value="${params.get('t')}"]`
				);

				if (maybe_topic) {
					maybe_topic.selected = true;
				} else {
					topic_filter.querySelector('[value=""]').selected = true;
				}
			} else {
				topic_filter.querySelector('[value=""]').selected = true;
			}

			if (params.has('s')) {
				if ('d' === params.get('s')) {
					sort_by_date_input.checked = true;
				} else {
					sort_by_relevance_input.checked = true;
				}
			}

			date_from_output_update();
			date_to_output_update();
			perform_search(on_index, modify_state);
		} else {
			nudge_visibility();
		}
	}

	function aliasSatisfactoryVocabulary (token) {
		const lowercase = token.toString().toLowerCase();

		if (synonym_keys.includes(synonyms)) {
			return token.update(() => {
				return synonyms[lowercase];
			});
		}

		return token;
	}

	function date_int_to_date(int) {
		const parts = /^(\d+)(\d{2})(\d{2})$/.exec(int.toString(10));

		return `${parts[1]}-${parts[2]}-${parts[3]}`;
	}

	//#region template & output

	const not_ready = document.getElementById('not-ready');
	const loading = document.getElementById('loading').content.cloneNode(
		true
	);
	const search_result_template = document.getElementById(
		'document-result'
	).content;

	//#region page ready

	const ready = document.getElementById('ready').content.cloneNode(
		true
	);

	const searchbar = ready.querySelector('input[type="search"]');
	const results_container = ready.querySelector('.results');
	const result_count = ready.querySelector('header output[for="search"]');

	//#region form

	const form = ready.querySelector('form');
	const date_from = form.querySelector('#date-from');
	const date_to = form.querySelector('#date-to');
	const topic_filter = form.querySelector('[name="t"]');
	const sort_by_relevance_input = form.querySelector(
		'[name="s"][value="r"]'
	);
	const sort_by_date_input = form.querySelector(
		'[name="s"][value="d"]'
	);
	const date_output = form.querySelector('output[for="date-from date-to"]');
	const date_from_output = date_output.querySelector('[for="date-from"]');
	const date_to_output = date_output.querySelector('[for="date-to"]');
	const readable_date_output = ready.querySelector('output[for="date-from date-to"]');
	const readable_date_from_output = readable_date_output.querySelector('[for="date-from"]');
	const readable_date_to_output = readable_date_output.querySelector('[for="date-to"]');
	const date_from_output_update = (update_readable = false) => {
		let value = date_values_sorted[date_from.value] || 'n/a';

		if (Number.isInteger(value)) {
			value = date_int_to_date(value);
		}

		date_from_output.textContent = value;

		if (update_readable) {
			readable_date_from_output.textContent = date_format(new Date(value));
		}
	};
	const date_to_output_update = (update_readable = false) => {
		let value = date_values_sorted[date_to.value] || 'n/a';

		if (Number.isInteger(value)) {
			value = date_int_to_date(value);
		}

		date_to_output.textContent = value;

		if (update_readable) {
			readable_date_to_output.textContent = date_format(new Date(value));
		}
	};

	date_from.addEventListener('change', () => {date_from_output_update(); });
	date_to.addEventListener('change', () => {date_to_output_update(); });
	date_from.addEventListener('input', () => {date_from_output_update(); });
	date_to.addEventListener('input', () => {date_to_output_update(); });

	function date_from_value_by_index(index) {
		date_from.value = index;
		date_from_output_update();
	}

	function date_from_value_by_date(date) {
		date_from_value_by_index(
			date_values_sorted.indexOf(
				parseInt(date.replace(/\-/g, ''), 10)
			)
		);
	}

	function date_to_value_by_index(index) {
		date_to.value = index;
		date_to_output_update();
	}

	function date_to_value_by_date(date) {
		date_to_value_by_index(
			date_values_sorted.indexOf(
				parseInt(date.replace(/\-/g, ''), 10)
			)
		);
	}

	function date_format (date) {
		return (
			date.toLocaleDateString(
				'en-GB',
				{ month: 'long'}
			)
			+ ' '
			+ date.getDate()
			+ ordinals[date.getDate() % 10]
			+ ', '
			+ date.toLocaleDateString(
				'en-GB',
				{ year: 'numeric'}
			)
		);
	}

	//#endregion

	//#endregion

	//#endregion

	//#region fetches

	const fetches = {};

	[
		'./lunr.json',
		'./docs.json',
		'./synonyms.json',
		'./topics.json'
	].forEach((path) => {
		const parts = /^(.+)(\.[a-z]+)$/.exec(path);

		const preload = document.head.querySelector(
			`link[rel="preload"][as="fetch"][href^="${
				parts[1]
			}"][href$="${
				parts[2]
			}"]`
		);

		if ( ! preload) {
			throw new Error('Could not find preload for ' + path);
		}

		fetches[path] = preload.href;
	});

	const fetch_prebuilt_index = fetch(fetches['./lunr.json']);
	const fetch_docs = fetch(fetches['./docs.json']);
	const fetch_synonyms = fetch(fetches['./synonyms.json']);
	const fetch_topics = fetch(fetches['./topics.json']);

	const [
		prebuilt_index,
		docs,
		synonyms,
		topics,
	] = await Promise.all((await Promise.all([
		fetch_prebuilt_index,
		fetch_docs,
		fetch_synonyms,
		fetch_topics,
	])).map((e) => e.json()));

	const synonym_keys = Object.keys(synonyms);

	//#endregion

	not_ready.parentNode.replaceChild(loading, not_ready);

	lunr.Pipeline.registerFunction(
		aliasSatisfactoryVocabulary,
		'aliasSatisfactoryVocabulary'
	);

	const index = lunr.Index.load(prebuilt_index);

	//#region date min/max

	for (const doc_entry of Object.entries(docs)) {
		const [, doc] = doc_entry;
		const maybe = parseInt(doc.date.replace(/\-/g, ''), 10);

		if ( ! maybe_date_max.includes(maybe)) {
			maybe_date_max.push(maybe);
			maybe_date_min.push(maybe);
			date_values.push(maybe);
		}
	}

	const date_max = (
		Math.max(...maybe_date_max).toString(10)
	);
	const date_min = (
		Math.min(...maybe_date_min).toString(10)
	);
	date_values.sort().forEach((e) => {
		date_values_sorted.push(e);
	});

	date_from.value = date_from.min = date_to.min = 0;
	date_to.value = date_from.max = date_to.max = (date_values.length - 1);
	date_from_output_update();
	date_to_output_update();

	//#endregion

	const topic_filter_frag = document.createDocumentFragment();

	const optgroups = {};

	Object.entries(topics).forEach((e) => {
		const [slug, unslugged] = e;

		let optgroup_label = [unslugged[0]];

		for (let i=1;i<(unslugged.length - 1); ++i) {
			optgroup_label.push(unslugged[i]);
		}

		const optgroup_label_joined = optgroup_label.join(' > ');

		if ( ! (optgroup_label_joined in optgroups)) {
			optgroups[optgroup_label_joined] = [];
		}

		optgroups[optgroup_label_joined].push(e);
	});

	Object.entries(optgroups).forEach((e) => {
		const optgroup = document.createElement('optgroup');
		const [label, grouped_topics] = e;

		optgroup.label = label;

		optgroup.appendChild(grouped_topics.reduce(
			(out, topic) => {
				const [slug, unslugged] = topic;

				const option = document.createElement('option');

				option.value = slug;

				option.textContent = (
					(1 === unslugged.length ? 'All in ' : '')
					+ unslugged[unslugged.length - 1]
				);

				out.appendChild(option);

				return out;
			},
			document.createDocumentFragment()
		));

		topic_filter.appendChild(optgroup);
	});

	form.addEventListener('submit', (e) => {
		e.preventDefault();
		if (searchbar.checkValidity()) {
			perform_search(index);
		}
	});

	window.onpopstate = () => {
		hashsearch(index, false);
	};
	hashsearch(index, false);

	sort_by_relevance_input.addEventListener('input', () => {
		const params = new URLSearchParams(location.search);

		if (params.has('q')) {
		perform_search(index, false);
		}
	});
	sort_by_date_input.addEventListener('input', () => {
		const params = new URLSearchParams(location.search);

		if (params.has('q')) {
		perform_search(index, false);
		}
	});

	document.body.firstElementChild.parentNode.replaceChild(
		ready,
		document.body.firstElementChild
	);

	/**
	document.body.addEventListener(
		'click',
		(e) => {
			console.log('foo');
			if ((e.target instanceof HTMLButtonElement) && e.target.classList.contains('copy')) {
				navigator.clipboard.writeText(e.target.parentNode.querySelector('section').textContent);
			}
		},
		{
			capture: true,
			passive: true,
		}
	);
	*/
})();
