# Media Search Archive

Static site generator for
	[video-clip-notes](https://github.com/Satisfactory-Clips-Archive/video-clip-notes)
	data.

# See Also

* [Satisfactory Clips Archive](https://archive.satisfactory.video/) - Serves as an archive for Clips for Coffee Stain Studio's Satisfactory-related livestreams & other videos.

# Changelog

## 2022-03-04
* added line breaks to better support multi-line descriptions

## 2022-02-27
* fixed bugs identified when rebuilding cache with transcription cache conflict bug fixed

## 2022-02-26
* avoided conflicts in transcription cache names

## 2022-02-25
* expanded supported card urls

## 2022-02-14
* adjusted sort order of topics
* deployed long-planned calendar view for index

## 2022-01-24
* added support for YouTube embed links in the info card regex

## 2021-12-22
* separate indexes by category to reduce initial download with default search options

## 2021-12-21
* ported typescript code to php, added test to ensure consistency in behaviour

## 2021-12-20
* worked on a replacement classifier for what is & isn't a question

## 2021-12-18
* remove long-deprecated quotes field from search

## 2021-12-03
* tweaked `determine_date_for_video()`

## 2021-11-24
* adding tool to automatically correct CSV timestamps

## 2021-11-20
* Ensure skip list is sorted

## 2021-11-18
* Partially restoring support for local filesystem captions data

## 2021-11-13
* Adding support for generating data for blamehannah.com

## 2021-11-10
* Increased logging to alert for missing transcriptions
* Fixing transcriptions cache bypass bug
* Modified custom Slugify class to transpose "&" to literal "and"
* Updated logo
* Rebranded from "Q&A Clips Archive" to "Satisfactory Clips Archive"

## 2021-10-24
* Adding a tool to aid in the identification of clips needing native YouTube clips
* Move array reversal to avoid cache expiry on recently-added clips

## 2021-08-27
* Sorting the "no structured data" dump for consistent diffs

## 2021-08-24
* Added structured data for Snutt Burger Time with image of tweet via Puppeteer

## 2021-08-20
* Interactive per-topic statistics rather than separate charts

## 2021-08-16
* Experimenting with per-topic statistics

## 2021-08-15
* Experimenting with bar charts for statistics

## 2021-08-14
* Added tool to auto swap seealso entries (useful for when the parent entry for a list of duplicates has changed)
* Added suggested wiki reference code
* Tweaked sitemap generation process

## 2021-08-04
* Amended auto-WebPage types in structured data handler
* Assigned url to WebPage types lacking an url
* Corrected & Updated wiki links

## 2021-08-01
* Refactored copypasted json_encode usage to single function
* Implemented statistics page

## 2021-07-31
* Amended rendering of custom images

## 2021-07-27
* Rendering custom embed images

## 2021-07-24
* Fixed a copy/paste typo in a utility script
* Initial implementation of tracking skipped cards
* Decoupled fetching of fresh data from main update process

## 2021-07-23
* Further escaping of front matter content
* Fixing bug in brotli selector
* Fixing bug that prevented transcriptions being used with YouTube's native clip feature
* Removing trailing whitespace

## 2021-07-22
* Fixed bug in transcription generation
* Prepared for some updates to cache handling
* Adjusted time margin for xml-sourced transcriptions

## 2021-07-17
* Fixing bug in transcription preparation
* Added ability to remove cache entries without manually opening tarball
* Adding initial support for info cards
	* merging internal video card data with the manual "see also" data
	* merging internal playlists into the "see also" section
* Improved overriden video url support
* Compressing captions tarball

## 2021-07-16
* Added topic page link in structured data
* Swapped non-semantic external link for structured data related link
* Modified support for primary structured data

## 2021-07-15
* Changed search defaults
* Added browser search plugin

## 2021-07-11
* fixed some bugs with transcript caching & fetching

## 2021-07-10
* added external playlist links, closing [issue #5](https://github.com/Satisfactory-Clips-Archive/Media-Search-Archive/issues/5)

## 2021-07-03
* fixing typo
* changing how unlisted content is skipped
* alter sort order for captions processing
* alter logging for build process

## 2021-07-02
* skipping unlisted content

## 2021-06-26
* getting consistent i/o performance by using a tarball for the 9500+ caption files
* fixing bug in sort order algorithm

## 2021-06-19
* partial implementation of video descriptions via VideoObject embeds

## 2021-06-13
* fixed specific case where video sorting did not behave as expected
* sorted matching external video clips by start time

## 2021-06-12
* partial implementation of structured data for clips

## 2021-06-11
* finally render related videos into the transcription pages
* added "Subnautica" and "Crossovers" topics

## 2021-06-06
* added topic docs for topics that have no direct content

## 2021-06-05
* fixed bug overlooked in previous commit with eleventy config

## 2021-06-01
* moved files around to locations more appropriate to single-project management (original intent was to track multiple games in one repository, it's now more managable to refactor & fork the repository)

## 2021-03-11
* implemented (possibly inefficient) FAQ sorting

## 2021-03-05
* implemented topic support in custom transcripts

## 2021-03-01
* use data-driven FAQ page instead of markdown-driven FAQ page

## 2021-02-28
* adding support for custom transcripts

## 2021-02-26
* implement search filters
* updating FAQ implementation

## 2021-02-25
* duplicates tracking implemented
* replacements tracking implemented
* implement open/close transcripts on search

## 2021-02-24
* merged in q-and-a-tracking branch

## 2021-02-20
* fixed bugs identified in handling of external youtube videos

## 2021-02-19
* Updated build with topic changes originating in q-and-a-tracking branch

## 2021-02-17
* Restored ability to clear out non-dated playlists
* Improved build script

## 2021-02-16
* Applied a new sorting algorithm to video lists
* Filtered legacy clips from the FAQ

## 2021-02-13
* Updated transcription generation to trim the last blank line
* Updated transcription generation to prefer english transcriptions

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
