const {
	readFileSync,
} = require('fs');

const bgs = {
	blue_graph_paper: `data:image/webp;base64,${
		readFileSync(
			`${__dirname}/../../Media-Search-Archive-Images/images-ref/banner-1571861/blue-graph-paper.webp`,
			{
				encoding: 'base64'
			}
		)
	}`,
};

module.exports = bgs;
