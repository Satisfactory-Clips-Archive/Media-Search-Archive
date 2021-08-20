const container = document.querySelector('#topic-charts');

if ( ! container ) {
	throw new Error('Could not find container!');
}

const json_source = container.querySelector(
	'link[rel="preload"][as="fetch"][href^="/data/dated-topic-statistics"][href$=".json"]'
);

const container_title = container.querySelector('h1');
const svg_template = container.querySelector('template#svg').content;
const bars_template = container.querySelector('template#bars').content;
const checkbox_template = container.querySelector('#checkbox').content;
const controls_template = container.querySelector('#controls').content;
const controls_container = container.querySelector('#topic-charts-controls');

const topics = Object.entries(await (await fetch(json_source.href)).json());

function max_value(numbers) {
	let max = 0;

	numbers.forEach((column) => {
		max = Math.max(max, column[1]);
	});

	return max;
}

let filtered_topics = [];

function svg() {
	const filtered = filtered_topics.length > 0 ? topics.filter((topic) => {
		return filtered_topics.includes(topic[0]);
	}) : topics;

	const numbers = (new Array(Object.keys(filtered[0][1][1]).length));

	Object.keys(filtered[0][1][1]).forEach((date, i) => {
		numbers[i] = [date, 0, 0];
	});

	filtered.forEach((e) => {
		const days = Object.entries(e[1][1]);

		days.forEach((day, i) => {
			numbers[i][1] += day[1][0];
			numbers[i][2] += day[1][1];
		});
	});

	const max = max_value(numbers);

	const svg = svg_template.cloneNode(true).querySelector('svg');
	const svg_width = 32 + (numbers.length * 32);
	const svg_height = (max * 16) + 64;

	svg.setAttribute('width', svg_width);
	svg.setAttribute('height', svg_height);
	svg.setAttribute('viewBox', `0 0 ${svg_width} ${svg_height}`);

	numbers.forEach((column, i) => {
		const height_a = column[1] * 16;
		const height_b = column[2] * 16;

		const nodes = bars_template.cloneNode(true).querySelector('g');
		const all = nodes.querySelector('.all');
		const qna = nodes.querySelector('.qna');

		all.setAttribute('x', 32 + (i * 32));
		all.setAttribute('y', 32 + ((max * 16) - height_a));
		all.setAttribute('height', height_a);
		all.querySelector('title').textContent = `Total topic assignations for ${
			column[0]
		}`;

		qna.setAttribute('x', 32 + (i * 32));
		qna.setAttribute('y', 32 + ((max * 16) - height_b));
		qna.setAttribute('height', height_b);
		qna.querySelector('title').textContent = `${
				column[2]
			} questions in topic assignations for ${
				column[0]
			}`;

		svg.appendChild(nodes);
	});

	const old_svg = container.querySelector('svg');

	old_svg.parentNode.replaceChild(svg, old_svg);
}

svg();

const controls = controls_template.cloneNode(true);
const controls_topic_checkbox_container = controls.querySelector(
	'#topic-checkboxes'
);
const form = controls.querySelector('form');

topics.forEach((e) => {
	const [id, topic] = e;
	const checkbox = checkbox_template.cloneNode(true);
	const input = checkbox.querySelector('input');
	const label = checkbox.querySelector('label');

	label.setAttribute('for', input.id = `topic-${id}`);
	input.value = id;
	label.textContent = Object.values(topic[0]).join(' > ');

	controls_topic_checkbox_container.appendChild(checkbox);
});

controls_container.textContent = '';
controls_container.appendChild(controls);

form.addEventListener('click', (e) => {
	if ('update' === e.target.dataset?.action) {
		const data = new FormData(form);

		filtered_topics = data.getAll('topic[]');

		controls_topic_checkbox_container.querySelectorAll('.checked').forEach(
			(item) => {
				item.classList.remove('checked');
			}
		);

		filtered_topics.forEach((topic_id) => {
			controls_topic_checkbox_container.querySelector(
				`input[value="${topic_id}"]`
			).parentNode.classList.add('checked');
		});

		svg();
	}
});

container_title.childNodes.forEach((node) => {
	if (3 === node.nodeType) {
		node.textContent = node.textContent.replace(
			'Clips',
			'Clips assigned to topics'
		).replace(
			'Questions',
			'Questions assigned to topics'
		);
	}
});
