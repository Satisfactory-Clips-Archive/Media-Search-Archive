(async () => {
	if ( ! (URLSearchParams)) {
		throw new Error('Unsupported');
	}

	const ordinals = [
		'th', // 0th
		'st', // 1st
		'nd', // 2nd
		'rd', // 3rd
		'th', // 4th
		'th', // 5th
		'th', // 6th
		'th', // 7th
		'th', // 8th
		'th', // 9th
		'th', // 10th
		'th', // 11th
		'th', // 12th
		'th', // 13th
		'th', // 14th
		'th', // 15th
		'th', // 16th
		'th', // 17th
		'th', // 18th
		'th', // 19th
	];
	const maybe_date_max = [0, 20200901];
	const maybe_date_min = [Infinity, 20200901];
	const date_values = [];
	const date_values_sorted = [];
	const topic_prefix = 'https://archive.satisfactory.video/topics/';
	const topic_suffix = '/';
	const searches_cache = {};

	let timeout;
	let last_query = '';

	function search (query, on_index, docs) {
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

	async function merge_search(query, for_dates) {
		for_dates.forEach((date) => {
			if ( ! (date in searches_cache)) {
				searches_cache[date] = [
					searches_dates[date][0](),
					searches_dates[date][1]().then((prebuilt) => {
						return lunr.Index.load(prebuilt);
					}),
				];
			}
		});

		const merged_result = [];

		for (let i = 0; i < for_dates.length; i++) {
			const [docs, index] = await Promise.all(
				searches_cache[for_dates[i]]
			);

			merged_result.push(...search(query, index, docs));
		}

		return merged_result;
	}

	function perform_search (on_index, modify_state = true) {
		results_container.textContent = '';

		cancelAnimationFrame(timeout);
		nudge_visibility();

		timeout = requestAnimationFrame(async () => {
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
			const sort_by_ascending = 'd' !== formdata.get('s');
			const toggle_transcripts_closed = '1' !== formdata.get('o');
			const filter_results = '1' === formdata.get('f');
			const qandaonly = '1' === formdata.get('a');

			formdata.set('s', sort_by_ascending ? 'b' : 'd');
			formdata.set('o', toggle_transcripts_closed ? '0' : '1');
			formdata.set('f', filter_results ? '1' : '0');
			formdata.set('a', qandaonly ? '1' : '0');

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
					}&o=${
						encodeURIComponent(formdata.get('o'))
					}&f=${
						encodeURIComponent(formdata.get('f'))
					}&a=${
						encodeURIComponent(formdata.get('a'))
					}`
				);
			}

			const dates_for_search = Object.keys(Object.fromEntries(
				Object.entries(
					Object.keys(searches_dates).reduce(
						(out, date) => {
							out[date] = parseInt(date.replace(/\-/g, ''), 10);

							return out;
						},
						{}
					)
				).filter((e) => {
					const [, date_as_int] = e;

					return (
						date_as_int >= filter_date_from
						&& date_as_int <= filter_date_to
					);
				})
			));

			let docs_results = (await merge_search(
				query.split(' ').map((e) => {
					return e.replace(/\.+$/, '');
				}).join(' '),
				dates_for_search
			)).filter((e) => {
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

			if (qandaonly) {
				docs_results = docs_results.filter((maybe) => {
					return /^q&a:/i.test(maybe[0].title);
				});
			}

			if (filter_results) {
				const docs_results_ids = docs_results.map((e) => {
					return e[0].id;
				});

				docs_results = docs_results.filter((maybe) => {
					const vendor_id = maybe[0].id;
					let id = maybe[0].id;

					if (14 === id.length) {
						id = id.substr(3);
					}

					if (
						id in qandatracking
						&& (
							(
								(
									qandatracking[id].duplicatedby
									?? false
								)
								&& (
									docs_results_ids.includes(
										qandatracking[id].duplicatedby
									)
									|| docs_results_ids.includes(
										'yt-' + qandatracking[id].duplicatedby
									)
								)
							)
							|| (
								(
									qandatracking[id].replacedby
									?? false
								)
								&& (
									docs_results_ids.includes(
										qandatracking[id].replacedby
									)
									|| docs_results_ids.includes(
										'yt-' + qandatracking[id].replacedby
									)
								)
							)
						)
					) {
						return false;
					}

					return true;
				});
			}

			const sorter = sort_by_ascending
				? (a, b) => {
					const aint = parseInt(a[0].date.replace(/\-/g, ''), 10);
					const bint = parseInt(b[0].date.replace(/\-/g, ''), 10);

					return aint - bint;
				}
				: (a, b) => {
					const aint = parseInt(a[0].date.replace(/\-/g, ''), 10);
					const bint = parseInt(b[0].date.replace(/\-/g, ''), 10);

					return bint - aint;
				};

				docs_results = docs_results.sort(sorter);

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

					node.querySelector('li').dataset.docId = doc.id;

					node.querySelector('h1').textContent = (
						date_format(doc_date)
						+ ' Livestream'
					);

					if ('' !== doc.transcription) {
						const title = node.querySelector('h2');
						title.textContent = '';
						const title_link = document.createElement('a');
						title_link.setAttribute('href', `/transcriptions/${
							doc.id
						}`);
						title_link.textContent = doc.title;
						title_link.setAttribute('target', '_blank');

						title.appendChild(title_link);
					} else {
					node.querySelector('h2').textContent = doc.title;
					}

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

					/*
					if (doc.topics.length < 1) {
						[...node.querySelectorAll('h3, ul')].forEach((e) => {
							e.parentNode.removeChild(e);
						});
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
					*/

					node.querySelector('section').replaceChild(urls_dest, node.querySelector('div'));

					const transcription = node.querySelector('details > blockquote');
					if ('' !== doc.transcription) {
						transcription.textContent = doc.transcription;
					} else {
						transcription.parentNode.parentNode.removeChild(
							transcription.parentNode
						);
					}

					if (toggle_transcripts_closed) {
						transcription.parentNode.removeAttribute('open');
					} else {
						transcription.parentNode.setAttribute('open', true);
					}

					/*
					const positions = [];

					Object.keys(result.matchData.metadata).forEach((term) => {
						positions.push(...(
								result.matchData.metadata[term].transcription
								|| {position:[]}
							).position
						);
					});
					try {
						lunrhighlighter(
							transcription,
							positions
						);
					} catch (err) {
						console.error(err);
					}
					*/

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
				date_to_value_by_index(date_to.max);
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
				if ('b' !== params.get('s')) {
					sort_by_date_input.checked = true;
				} else {
					sort_by_date_ascending_input.checked = true;
				}
			}

			if ( ! params.has('o') || '1' !== params.get('o')) {
				toggle_transcripts_closed_input.checked = true;
			} else {
				toggle_transcripts_open_input.checked = true;
			}

			if ( ! params.has('f') || '0' !== params.get('f')) {
				filter_results_checkbox.checked = true;
			} else {
				filter_results_checkbox.checked = false;
			}

			if ( ! params.has('a') || '0' !== params.get('a')) {
				qandaonly_checkbox.checked = true;
			} else {
				qandaonly_checkbox.checked = false;
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
	const sort_by_date_ascending_input = form.querySelector(
		'[name="s"][value="b"]'
	);
	const sort_by_date_input = form.querySelector(
		'[name="s"][value="d"]'
	);
	const toggle_transcripts_closed_input = form.querySelector(
		'[name="o"][value="0"]'
	);
	const toggle_transcripts_open_input = form.querySelector(
		'[name="o"][value="1"]'
	);
	const filter_results_checkbox = form.querySelector('[name="f"]');
	const qandaonly_checkbox = form.querySelector('[name="a"]');
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
			+ ordinals[date.getDate() % 20]
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
		'/data/q-and-a-tracking.json',
		'/lunr/search.json',
		'/synonyms.json',
		'/topics.json'
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

	const fetch_qandatracking = fetch(fetches['/data/q-and-a-tracking.json']);
	const fetch_searches = fetch(fetches['/lunr/search.json']);
	const fetch_synonyms = fetch(fetches['/synonyms.json']);
	const fetch_topics = fetch(fetches['/topics.json']);

	const [
		qandatracking,
		searches,
		synonyms,
		topics,
	] = await Promise.all((await Promise.all([
		fetch_qandatracking,
		fetch_searches,
		fetch_synonyms,
		fetch_topics,
	])).map((e) => e.json()));

	const synonym_keys = Object.keys(synonyms);

	const searches_entries = Object.entries(searches);

	const searches_dates = Object.keys(searches).map(
		(filename) => {
			return filename.replace(
				/^docs-(\d{4,}\-\d{2}-\d{2}).+$/,
				'$1'
			);
		}
	).reduce(
		(out, date, i) => {
			const [docs_path, lunr_path] = searches_entries[i];
			out[date] = [
				async () => {
					return await (await fetch('/lunr/' + docs_path)).json();
				},
				async () => {
					return await (await fetch('/lunr/' + lunr_path)).json();
				},
			];

			return out;
		},
		{}
	);

	//#endregion

	not_ready.parentNode.replaceChild(loading, not_ready);

	lunr.Pipeline.registerFunction(
		aliasSatisfactoryVocabulary,
		'aliasSatisfactoryVocabulary'
	);

	/*
	const index = lunr.Index.load(prebuilt_index);
	*/

	//#region date min/max

	for (const date of Object.keys(searches_dates)) {
		const maybe = parseInt(date.replace(/\-/g, ''), 10);

		if ( ! maybe_date_max.includes(maybe)) {
			maybe_date_max.push(maybe);
			maybe_date_min.push(maybe);
			date_values.push(maybe);
		}
	}

	date_values.sort().forEach((e) => {
		date_values_sorted.push(e);
	});

	date_from.value = date_from.min = date_to.min = 0;
	date_to.value = date_from.max = date_to.max = (date_values.length - 1);
	date_from_output_update();
	date_to_output_update();

	//#endregion

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
			perform_search(false);
		}
	});

	window.onpopstate = () => {
		hashsearch(false, false);
	};
	hashsearch(false, false);

	const refresh_search = () => {
		const params = new URLSearchParams(location.search);

		if (params.has('q')) {
			perform_search(false, false);
		}
	};

	[
		sort_by_date_ascending_input,
		sort_by_date_input,
		toggle_transcripts_closed_input,
		toggle_transcripts_open_input,
		filter_results_checkbox,
		qandaonly_checkbox,
	].forEach((e) => {
		e.addEventListener('input', refresh_search);
	})

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
