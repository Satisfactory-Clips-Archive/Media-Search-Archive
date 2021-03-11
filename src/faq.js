const actual_main = document.querySelector('main');
const main = actual_main.cloneNode(true);
const recent_date_copy = document.createDocumentFragment();
const answered_copy = document.createDocumentFragment();
const fieldset = document.querySelector('fieldset:disabled');
const form = fieldset.parentNode;

fieldset.addEventListener('change', () => {
	actual_main.textContent = '';

	if ('answered' !== (new FormData(form)).get('sort')) {
		actual_main.appendChild(recent_date_copy.cloneNode(true));
	} else {
		actual_main.appendChild(answered_copy.cloneNode(true));
	}
});

[...main.childNodes].forEach((e) => {
	recent_date_copy.appendChild(e);
});

const faqs = [...(recent_date_copy.cloneNode(true)).querySelectorAll(
	'[data-previously-answered]'
)].sort((a, b) => {
	return (
		parseInt(b.dataset.previouslyAnswered, 10)
		- parseInt(a.dataset.previouslyAnswered)
	);
});

for (let faq of faqs) {
	answered_copy.appendChild(faq);
};

fieldset.disabled = false;
