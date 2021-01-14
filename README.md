# Media Search Archive

Static site generator for
	[twitch-clip-notes](https://github.com/SignpostMarv/twitch-clip-notes)
	data.

# See Also

* [Satisfactory Q&A Clips Archive](https://clips.satisfactory.signpostmarv.name/) - Serves as an archive for Q&A Clips for Coffee Stain Studio's Satisfactory-related livestream

# Changelog

## 2020-01-14
* Implemented more structured data

## 2020-01-13
* Fixed bugs.

* Implemented a change to the client-side search index,
	where the data was split by date to aid in browser caching.

	Splitting by date means not having to discard the previous search index when a new livestream comes out.

	There's a slightly longer load delay on first visit as each index & set of documents are fetched, but subsequent visits and date-filtered searches should load faster.
