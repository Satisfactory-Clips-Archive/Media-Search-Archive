import {
	readFileSync,
	writeFileSync,
} from 'fs';

const file = `${__dirname}/offset-youtube-chapters.txt`;

const lines = (readFileSync(file) + '').split('\n');

const offset = lines[0].split(' ')[0];

function time_string_to_decimal(offset:string) : number {
	return offset.split(':').reverse().map((offset_part, offset_part_index) => {
		if (offset_part_index > 2) {
			throw new Error('Video too long');
		}

		return parseInt(offset_part, 10) * Math.pow(60, offset_part_index);
	}).reduce((was, is) => {
		return was + is;
	});
}

function decimal_to_offset(decimal:number) : string {
	if (decimal > 86400) {
		throw new Error('Video too long');
	}

	const hours = Math.floor(decimal / 3600);
	const minutes = Math.floor((decimal - (hours * 3600)) / 60);
	const seconds = decimal % 60;

	const parts = [];

	if (hours > 0) {
		parts.push(hours.toString().padStart(2, '0'));
	}

	if (minutes > 0) {
		parts.push(minutes.toString().padStart(2, '0'));
	} else {
		parts.push('00');
	}

	parts.push(seconds.toString().padStart(2, '0'));

	return parts.join(':');
}

const offset_decimal = time_string_to_decimal(offset);

const file_contents = lines.map((line) => {
	if (line.trim().length <= 0) {
		return '';
	}

	const parts = line.split(' ');

	parts[0] = decimal_to_offset(time_string_to_decimal(parts[0]) - offset_decimal);

	return parts.join(' ');
}).join('\n');

console.log(file_contents);

writeFileSync(file, file_contents);
