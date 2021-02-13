# Media Search Archive

Static site generator for
	[video-clip-notes](https://github.com/Satisfactory-Clips-Archive/video-clip-notes)
	data.

# See Also

* [Satisfactory Q&A Clips Archive](https://archive.satisfactory.video/) - Serves as an archive for Q&A Clips for Coffee Stain Studio's Satisfactory-related livestream

# Changelog

## 2021-02-13
* Worked on Q&A tracking feature

## 2021-02-12
* Worked on Q&A tracking feature

## 2021-02-11
* Updated build process to sync lunr.min.js
* Updated build process to remove `clean` step

## 2021-02-09
* Fixed issue with linkification of transcripts back to their dated index page

## 2021-02-06
* Fixed linkification

## 2021-02-05
* Updated build to use alternate urls from legacy urls
* Added source video url to transcripts
* Added support for external clips

## 2021-02-01
* Updated build without static gzip assets due to widespread support for brotli

## 2021-01-30
* Updated build with workarounds for topics that don't exist yet

## 2021-01-27
* Updated build with n-level filtering for livestream documents
* Added parent & child topic listings
* Fix linkification bug *properly*

## 2021-01-26
* Updated build with n-level sorting code for FAQ
* Fix linkification bug
* Fix ordinal suffixes in client search

## 2021-01-25
* Updated build to skip linking to empty topics
* Reused n-level sorting code for dated indices
* Fixed a bug identified with linkification
* Updated build metadata

## 2021-01-24
* Switched exact match to `startsWith` check for topics
* Updated build to use es6 imported metadata

## 2021-01-23
* Updated build to reflect support for additional clip sources
* Implemented dark mode
* Dropped relevance search in favour of chronological
* Added site description to page footer
* Updated build to reflect topic changes

## 2021-01-22
* Updated build to reflect changes in topic metadata
* Corrected typo in changelog
* Updated build to refactor headings & navigation
* Corrected typo in navigation link
* Updated build to rely less on hardcoded legacy twitch clips, favouring partially dynamic injection
* Moved site to a new domain
* Amended 404 page
* Amended generated page titles for the FAQ

## 2021-01-21
* Added Icons
* Refactored search page to be built by eleventy for template reuse
* Corrected dates in changelog
* Updated github repo links
* Switched from what would've been an ever-expanding monolithic JSON-LD file
	to [modular data defined in a separate repo](
		https://github.com/Satisfactory-Clips-Archive/Media-Archive-Metadata
	)
* Updated portions of site build to use consistently sorted topic metadata
* Finally got around to updating the search tool's topics url prefix

## 20201-01-20
* Updated site build with latest livestream data
* Tweaked template output

## 20201-01-18
* Adjusted opengraph data

## 20201-01-17
* Implemented additional structured data & other metadata.

## 20201-01-16
* Implemented ability to specify structured data not sourced from markdown front matter.
* Moved search page to avoid inclusion of html extension.

## 20201-01-14
* Implemented more structured data.

## 20201-01-13
* Fixed bugs.

* Implemented a change to the client-side search index,
	where the data was split by date to aid in browser caching.

	Splitting by date means not having to discard the previous search index when a new livestream comes out.

	There's a slightly longer load delay on first visit as each index & set of documents are fetched, but subsequent visits and date-filtered searches should load faster.
