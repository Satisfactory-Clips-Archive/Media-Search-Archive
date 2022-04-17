declare namespace SatisfactoryClipsArchive {
	type Doc = {
		id: string,
		date: string,
		title: string,
		seealso: string[],
		topics: string[],
		duplicates?: string[],
		replaces?: string[],
		duplicatedby: string|null,
		replacedby: string|null,
		transcription: string,
		urls: string[],
	};

	type DocResultSorable = [Doc, lunr.Index.Result];
}

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
	const date_values:number[] = [];
	const date_values_sorted:number[] = [];
	const topic_prefix = 'https://archive.satisfactory.video/topics/';
	const topic_suffix = '/';
	const searches_cache:{[key:string]: {
		[key:string]: [
			Promise<{[key:string]: SatisfactoryClipsArchive.Doc}>,
			Promise<lunr.Index>,
		]
	}} = {};

	let timeout:number;
	let last_query = '';

	function search (query:string, on_index:lunr.Index, docs:{[key:string]:SatisfactoryClipsArchive.Doc}): [SatisfactoryClipsArchive.Doc, lunr.Index.Result][] {
		return on_index.search(query).map((result) => {
			return [docs[result.ref], result];
		});
	};

	function nudge_visibility() {
		(result_count.parentNode as HTMLParagraphElement).style.visibility = (
			('' === searchbar.value)
				? 'hidden'
				: 'visible'
		);
	}

	async function merge_search(query:string, for_dates:string[], qandaonly:boolean) {
		for_dates.forEach((date) => {
			if ( ! (date in searches_cache)) {
				searches_cache[date] = {};
			}

			Object.entries(searches_dates[date]).forEach((e) => {
				const [category, proms] = e;

				if (qandaonly && 'qanda' !== category) {
					return;
				} else if ( ! (category in searches_cache[date])) {
					searches_cache[date][category] = [
						proms[0](),
						proms[1]().then((prebuilt) => {
							return lunr.Index.load(prebuilt);
						}),
					];
				}
			});
		});

		const merged_result = [];

		for (let i = 0; i < for_dates.length; i++) {
			const date = for_dates[i];

			for (let category in searches_dates[date]) {
				if (qandaonly && 'qanda' !== category) {
					continue;
				}
			const [docs, index] = await Promise.all(
					searches_cache[date][category]
			);

			merged_result.push(...search(query, index, docs));
			};
		}

		return merged_result;
	}

	function perform_search (modify_state = true) {
		results_container.textContent = '';

		cancelAnimationFrame(timeout);
		nudge_visibility();

		timeout = requestAnimationFrame(async () => {
			const query = searchbar.value;
			const title = `Satisfactory Clips Archive - Search - ${query}`;

			last_query = query;

			const formdata = new FormData(form);
			const a = date_values_sorted[parseInt(formdata.get('df') as string, 10)];
			const b = date_values_sorted[parseInt(formdata.get('dt') as string, 10)];

			formdata.set('df', date_int_to_date(Math.min(a, b)));
			formdata.set('dt', date_int_to_date(Math.max(a, b)));

			const filter_date_from = parseInt(
				(formdata.get('df') as string).replace(/\-/g, ''),
				10
			);
			const filter_date_to = parseInt(
				(formdata.get('dt') as string).replace(/\-/g, ''),
				10
			);
			const filter_topic = formdata.get('t') as string;
			const sort_by_ascending = 'd' !== formdata.get('s');
			const toggle_transcripts_closed = '1' !== formdata.get('o');
			const filter_results = '1' === formdata.get('f');
			const qandaonly = '1' === formdata.get('a');

			formdata.set('s', sort_by_ascending ? 'b' : 'd');
			formdata.set('o', toggle_transcripts_closed ? '0' : '1');
			formdata.set('f', filter_results ? '1' : '0');
			formdata.set('a', qandaonly ? '1' : '0');

			(document.head.querySelector('title') as HTMLTitleElement).textContent = title;

			if (modify_state) {
				history.pushState(
					{},
					title,
					`?q=${
						encodeURIComponent(last_query)
					}&df=${
						encodeURIComponent(formdata.get('df') as string)
					}&dt=${
						encodeURIComponent(formdata.get('dt') as string)
					}&t=${
						encodeURIComponent(formdata.get('t') as string)
					}&s=${
						encodeURIComponent(formdata.get('s') as string)
					}&o=${
						encodeURIComponent(formdata.get('o') as string)
					}&f=${
						encodeURIComponent(formdata.get('f') as string)
					}&a=${
						encodeURIComponent(formdata.get('a') as string)
					}`
				);
			}

			const dates_for_search = Object.keys(Object.fromEntries(
				Object.entries(
					Object.keys(searches_dates).reduce(
						(out:{[key:string]: number}, date:string) => {
							out[date] = parseInt(date.replace(/\-/g, ''), 10);

							return out;
						},
						{} as {[key:string]: number}
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
				dates_for_search,
				qandaonly
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
										qandatracking[id].duplicatedby ?? ''
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
										qandatracking[id].replacedby ?? ''
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
				? (a:SatisfactoryClipsArchive.DocResultSorable, b:SatisfactoryClipsArchive.DocResultSorable) => {
					const aint = parseInt(a[0].date.replace(/\-/g, ''), 10);
					const bint = parseInt(b[0].date.replace(/\-/g, ''), 10);

					return aint - bint;
				}
				: (a:SatisfactoryClipsArchive.DocResultSorable, b:SatisfactoryClipsArchive.DocResultSorable) => {
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
					const node = search_result_template.cloneNode(true) as DocumentFragment;
					const doc_date = new Date(doc.date);

					(node.querySelector('li') as HTMLLIElement).dataset.docId = doc.id;

					(node.querySelector('h1') as HTMLHeadingElement).textContent = (
						date_format(doc_date)
						+ ' Livestream'
					);

					if ('' !== doc.transcription) {
						const title = node.querySelector('h2') as HTMLHeadingElement;
						title.textContent = '';
						const title_link = document.createElement('a');
						title_link.setAttribute('href', `/transcriptions/${
							doc.id
						}`);
						title_link.textContent = doc.title;
						title_link.setAttribute('target', '_blank');

						title.appendChild(title_link);
					} else {
						(node.querySelector('h2') as HTMLHeadingElement).textContent = doc.title;
					}

					var urls_dest:HTMLOListElement|HTMLParagraphElement;

					if (doc.urls.length > 1) {
						urls_dest = document.createElement('ol');

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
						urls_dest = document.createElement('p');
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

					(node.querySelector('section') as HTMLElement).replaceChild(urls_dest, node.querySelector('div') as HTMLDivElement);

					const transcription = node.querySelector('details > blockquote') as HTMLElement;
					if ('' !== doc.transcription) {
						transcription.textContent = doc.transcription;
					} else {
						((transcription.parentNode as HTMLElement).parentNode as HTMLElement).removeChild(
							transcription.parentNode as HTMLElement
						);
					}

					if (toggle_transcripts_closed) {
						(transcription.parentNode as HTMLDetailsElement).removeAttribute('open');
					} else {
						(transcription.parentNode as HTMLDetailsElement).setAttribute('open', 'open');
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

	function hashsearch(modify_state = true) {
		const params = new URLSearchParams(location.search);

		if (params.has('q')) {
			searchbar.value = params.get('q') as string;

			if (params.has('df')) {
				date_from_value_by_date(params.get('df') as string);
			} else {
				date_from_value_by_index(parseInt(date_from.min, 10));
			}

			if (params.has('dt')) {
				date_to_value_by_date(params.get('dt') as string);
			} else {
				date_to_value_by_index(parseInt(date_to.max, 10));
			}

			if (params.has('t')) {
				const maybe_topic = topic_filter.querySelector(
					`[value="${params.get('t')}"]`
				) as HTMLOptionElement|null;

				if (maybe_topic) {
					maybe_topic.selected = true;
				} else {
					(topic_filter.querySelector('[value=""]') as HTMLOptionElement).selected = true;
				}
			} else {
				(topic_filter.querySelector('[value=""]') as HTMLOptionElement).selected = true;
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
			perform_search(modify_state);
		} else {
			nudge_visibility();
		}
	}

	function aliasSatisfactoryVocabulary (token:lunr.Token) {
		const lowercase = token.toString().toLowerCase();

		if (synonym_keys.includes(lowercase)) {
			return token.update(() => {
				return synonyms[lowercase];
			});
		}

		return token;
	}

	function date_int_to_date(int:number):string {
		const parts = /^(\d+)(\d{2})(\d{2})$/.exec(int.toString(10)) as RegExpExecArray&[
			string,
			string,
			string,
			string,
		];

		return `${parts[1]}-${parts[2]}-${parts[3]}`;
	}

	//#region template & output

	const not_ready = document.getElementById('not-ready') as HTMLElement;
	const loading = (document.getElementById('loading') as HTMLTemplateElement).content.cloneNode(
		true
	);
	const search_result_template = (document.getElementById(
		'document-result'
	) as HTMLTemplateElement).content;

	//#region page ready

	const ready = (document.getElementById('ready') as HTMLTemplateElement).content.cloneNode(
		true
	) as DocumentFragment;

	const searchbar = ready.querySelector('input[type="search"]') as HTMLInputElement;
	const results_container = ready.querySelector('.results') as HTMLElement;
	const result_count = ready.querySelector('header output[for="search"]') as HTMLOutputElement;

	//#region form

	const form = ready.querySelector('form') as HTMLFormElement;
	const date_from = form.querySelector('#date-from') as HTMLInputElement;
	const date_to = form.querySelector('#date-to') as HTMLInputElement;
	const topic_filter = form.querySelector('[name="t"]') as HTMLSelectElement;
	const sort_by_date_ascending_input = form.querySelector(
		'[name="s"][value="b"]'
	) as HTMLInputElement;
	const sort_by_date_input = form.querySelector(
		'[name="s"][value="d"]'
	) as HTMLInputElement;
	const toggle_transcripts_closed_input = form.querySelector(
		'[name="o"][value="0"]'
	) as HTMLInputElement;
	const toggle_transcripts_open_input = form.querySelector(
		'[name="o"][value="1"]'
	) as HTMLInputElement;
	const filter_results_checkbox = form.querySelector('[name="f"]') as HTMLInputElement;
	const qandaonly_checkbox = form.querySelector('[name="a"]') as HTMLInputElement;
	const date_output = form.querySelector('output[for="date-from date-to"]') as HTMLOutputElement;
	const date_from_output = date_output.querySelector('[for="date-from"]') as HTMLOutputElement;
	const date_to_output = date_output.querySelector('[for="date-to"]') as HTMLOutputElement;
	const readable_date_output = ready.querySelector('output[for="date-from date-to"]') as HTMLOutputElement;
	const readable_date_from_output = readable_date_output.querySelector('[for="date-from"]') as HTMLOutputElement;
	const readable_date_to_output = readable_date_output.querySelector('[for="date-to"]') as HTMLOutputElement;
	const date_from_output_update = (update_readable = false) => {
		let value = date_values_sorted[parseInt(date_from.value, 10)] || 'n/a';

		if (Number.isInteger(value)) {
			value = date_int_to_date(value as number);
		}

		date_from_output.textContent = value as string;

		if (update_readable) {
			readable_date_from_output.textContent = date_format(new Date(value));
		}
	};
	const date_to_output_update = (update_readable = false) => {
		let value = date_values_sorted[parseInt(date_to.value, 10)] || 'n/a';

		if (Number.isInteger(value)) {
			value = date_int_to_date(value as number);
		}

		date_to_output.textContent = value as string;

		if (update_readable) {
			readable_date_to_output.textContent = date_format(new Date(value));
		}
	};

	date_from.addEventListener('change', () => {date_from_output_update(); });
	date_to.addEventListener('change', () => {date_to_output_update(); });
	date_from.addEventListener('input', () => {date_from_output_update(); });
	date_to.addEventListener('input', () => {date_to_output_update(); });

	function date_from_value_by_index(index:number) {
		date_from.value = index.toString(10);
		date_from_output_update();
	}

	function date_from_value_by_date(date:string) {
		date_from_value_by_index(
			date_values_sorted.indexOf(
				parseInt(date.replace(/\-/g, ''), 10)
			)
		);
	}

	function date_to_value_by_index(index:number) {
		date_to.value = index.toString(10);
		date_to_output_update();
	}

	function date_to_value_by_date(date:string) {
		date_to_value_by_index(
			date_values_sorted.indexOf(
				parseInt(date.replace(/\-/g, ''), 10)
			)
		);
	}

	function date_format (date:Date) {
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

	const fetches:{
		'/data/q-and-a-tracking.json'?: string,
		'/lunr/search.json'?: string,
		'/synonyms.json'?: string,
		'/topics.json'?: string,
	} = {};

	[
		'/data/q-and-a-tracking.json',
		'/lunr/search.json',
		'/synonyms.json',
		'/topics.json',
	].forEach((path) => {
		const parts = /^(.+)(\.[a-z]+)$/.exec(path) as RegExpExecArray&[
			string,
			string,
			string,
		];

		const preload = document.head.querySelector(
			`link[rel="preload"][as="fetch"][href^="${
				parts[1]
			}"][href$="${
				parts[2]
			}"]`
		) as HTMLLinkElement|null;

		if ( ! preload) {
			throw new Error('Could not find preload for ' + path);
		}

		fetches[path as keyof (typeof fetches)] = preload.href;
	});

	const fetch_qandatracking = fetch(fetches['/data/q-and-a-tracking.json'] as string);
	const fetch_searches = fetch(fetches['/lunr/search.json'] as string);
	const fetch_synonyms = fetch(fetches['/synonyms.json'] as string);
	const fetch_topics = fetch(fetches['/topics.json'] as string);

	const [
		qandatracking,
		searches,
		synonyms,
		topics,
	] = await (Promise.all((await Promise.all([
		fetch_qandatracking,
		fetch_searches,
		fetch_synonyms,
		fetch_topics,
	])).map((e) => e.json())) as Promise<[
		{[key:string]: SatisfactoryClipsArchive.Doc},
		{[key:string]: string},
		{[key:string]: string},
		{[key:string]: string[]},
	]>);

	const synonym_keys = Object.keys(synonyms);

	const searches_entries = Object.entries(searches);

	const searches_dates:{[key:string]: {
		[key:string]: [
			() => Promise<{[key:string]: SatisfactoryClipsArchive.Doc}>,
			() => Promise<object>,
		],
	}} = {};

	searches_entries.forEach((e) => {
		const [docs_path, lunr_path] = e;

		const date = docs_path.replace(
			/^docs-[^-]+-(\d{4,}\-\d{2}-\d{2}).+$/,
			'$1'
		);
		const category = docs_path.replace(
			/^docs-([^-]+)-(\d{4,}\-\d{2}-\d{2}).+$/,
			'$1'
		);

		if ( ! (date in searches_dates)) {
			searches_dates[date] = {};
		}

		if ( ! (category in searches_dates[date])) {
			searches_dates[date][category] = [
				async () => {
					return await ((await fetch('/lunr/' + docs_path)).json() as Promise<{
						[key:string]: SatisfactoryClipsArchive.Doc,
					}>);
				},
				async () => {
					return await ((await fetch('/lunr/' + lunr_path)).json()) as Promise<object>;
				},
			];
		}
	});

	//#endregion

	(not_ready.parentNode as HTMLBodyElement).replaceChild(loading, not_ready);

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

	date_from.value = date_from.min = date_to.min = '0';
	date_to.value = date_from.max = date_to.max = (date_values.length - 1).toString(10);
	date_from_output_update();
	date_to_output_update();

	//#endregion

	const optgroups:{[key:string]:[string, string[]][]} = {};

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
			if (FormData && URLSearchParams && 'pushState' in history) {
				const formdata = new FormData(form);
				const a = date_values_sorted[
					parseInt(formdata.get('df') as string, 10)
				];
				const b = date_values_sorted[
					parseInt(formdata.get('dt') as string, 10)
				];
				formdata.set('df', date_int_to_date(Math.min(a, b)));
				formdata.set('dt', date_int_to_date(Math.max(a, b)));
				history.pushState(
					{},
					`Satisfactory Clips Archive - Search - ${searchbar.value}`,
					`?${new URLSearchParams(
						(formdata as any)
					)}`
				);
			}
			perform_search(false);
		}
	});

	window.onpopstate = () => {
		hashsearch(false);
	};
	hashsearch(false);

	const refresh_search = () => {
		const params = new URLSearchParams(location.search);

		if (params.has('q')) {
			perform_search(false);
		}
	};

	[
		sort_by_date_ascending_input,
		sort_by_date_input,
		toggle_transcripts_closed_input,
		toggle_transcripts_open_input,
		filter_results_checkbox,
		qandaonly_checkbox,
	].forEach((e:HTMLInputElement):void => {
		e.addEventListener('input', refresh_search);
	});

	((document.body.firstElementChild as HTMLElement).parentNode as HTMLBodyElement).replaceChild(
		ready,
		(document.body.firstElementChild as HTMLElement)
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
