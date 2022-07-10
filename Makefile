.PHONY: images

docker-image:
	docker build -t satisfactory-clips-archive/media-search-archive:npm-latest - < npm.Dockerfile

install:
	docker run --rm \
		--volume=/${PWD}:/media-search-archive \
		--user $(shell id -u):$(shell id -g) \
		--workdir=/media-search-archive \
		composer:2 install

psalm:
	docker run --rm \
		--volume=/${PWD}:/media-search-archive \
		--user $(shell id -u):$(shell id -g) \
		--workdir=/media-search-archive \
		php:8.0-cli ./vendor/bin/psalm --threads=$(nproc)

npm-install:
	docker run --rm \
		-w /media-search-archive \
		--user $(shell id -u):$(shell id -g) \
		-v $(shell pwd)/:/media-search-archive \
		-v ${HOME}/.npm/:/.npm/ \
		-v $(shell pwd)/node_modules--docker/:/media-search-archive/node_modules/ \
		satisfactory-clips-archive/media-search-archive:npm-latest npm install

shell-npm:
	docker run --rm -it \
		-w /media-search-archive \
		--user $(shell id -u):$(shell id -g) \
		-v $(shell pwd)/:/media-search-archive \
		-v ${HOME}/.npm/:/.npm/ \
		--entrypoint sh \
		satisfactory-clips-archive/media-search-archive:npm-latest

update:
	php app/app.php
	npm run -s typescript
	cd . && ./node_modules/.bin/ts-node ./app/title-pattern-check.ts
	php app/update-q-and-a.php
	php app/part-continued.php
	php app/lunr-docs-preload.php
	php app/statistics.php
	cd . && ./node_modules/.bin/gulp charts 11ty-data

build: tweets update build-post-update

tweets:
	npm run -s tweets

build-post-update: topics images eleventy build-post-update--actually-build

topics:
	cd . && ./node_modules/.bin/gulp topics

eleventy:
	cd . \
	&& ./node_modules/.bin/eleventy --config=./.eleventy.tweets.js \
	&& ./node_modules/.bin/eleventy \
	&& ./node_modules/.bin/eleventy  --config=./.eleventy.pages.js \
	&& ./node_modules/.bin/eleventy  --config=./.eleventy.pages-alt-layout.js

build-post-update--actually-build:
	cd . && ./node_modules/.bin/gulp build

images-prep:
	docker run --rm \
		-w /media-search-archive \
		--user $(shell id -u):$(shell id -g) \
		-v $(shell pwd)/:/media-search-archive \
		-v ${HOME}/.npm/:/.npm/ \
		-v $(shell pwd)/node_modules--docker/:/media-search-archive/node_modules/ \
		satisfactory-clips-archive/media-search-archive:npm-latest ./node_modules/.bin/gulp \
			images-svg--background--flannel \
			images-svg--background--golf \
			images-svg--background--vulkan \
			images-svg--background--final-fantasy

images:
	cd . && ./node_modules/.bin/eleventy --config=./.eleventy.images.js
	cd . && ./node_modules/.bin/gulp \
			images-svg
