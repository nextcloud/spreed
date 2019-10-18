# Makefile for building the project

app_name=spreed

project_dir=$(CURDIR)/../$(app_name)
build_dir=$(CURDIR)/build/artifacts
appstore_dir=$(build_dir)/appstore
source_dir=$(build_dir)/source
sign_dir=$(build_dir)/sign
package_name=$(app_name)
cert_dir=$(HOME)/.nextcloud/certificates
version+=master

all: dev-setup build-js-production

dev-setup: clean-dev npm-init

dependabot: dev-setup npm-update build-js-production bundle-simplewebrtc

release: appstore create-tag

build-js:
	npm run dev

build-js-production:
	npm run build

watch-js:
	npm run watch

lint:
	npm run lint

lint-fix:
	npm run lint:fix

npm-init:
	npm install

npm-update:
	npm update

clean:
	rm -f js/*
	rm -rf $(build_dir)

clean-dev: clean
	rm -rf node_modules

bundle-simplewebrtc:
	# webrtc-adapter uses JavaScript features not supported by browserify,
	# so the sources need to be transformed using babel to a compatible
	# version of JavaScript.
	# Its main module does no longer provide "module.exports", which is
	# expected by the code using it, so it needs to be added back with a
	# plugin.
	npx browserify --standalone SimpleWebRTC --transform [ babelify --global --presets [ @babel/env ] --plugins [ add-module-exports ] ] js/simplewebrtc/simplewebrtc.js > js/simplewebrtc/bundled.js

create-tag:
	git tag -a v$(version) -m "Tagging the $(version) release."
	git push origin v$(version)

appstore:
	rm -rf $(build_dir)
	mkdir -p $(sign_dir)
	rsync -a \
	--exclude=bower.json \
	--exclude=.bowerrc \
	--exclude=/build \
	--exclude=docs \
	--exclude=.drone.yml \
	--exclude=.eslintignore \
	--exclude=.eslintrc.yml \
	--exclude=.git \
	--exclude=.gitattributes \
	--exclude=.github \
	--exclude=.gitignore \
	--exclude=.jscsrc \
	--exclude=.jshintignore \
	--exclude=js/**.js.map \
	--include=js/simplewebrtc/bundled.js \
	--exclude=js/simplewebrtc/*.js \
	--exclude=js/tests \
	--exclude=l10n/no-php \
	--exclude=.l10nignore \
	--exclude=Makefile \
	--exclude=node_modules \
	--exclude=package.json \
	--exclude=package-lock.json \
	--exclude=phpunit*xml \
	--exclude=README.md \
	--exclude=run-*lint.sh \
	--exclude=.scrutinizer.yml \
	--exclude=src \
	--exclude=.stylelintrc \
	--exclude=tests \
	--exclude=.travis.yml \
	--exclude=.tx \
	$(project_dir)/  $(sign_dir)/$(app_name)
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing app files…"; \
		php ../../occ integrity:sign-app \
			--privateKey=$(cert_dir)/$(app_name).key\
			--certificate=$(cert_dir)/$(app_name).crt\
			--path=$(sign_dir)/$(app_name); \
	fi
	tar -czf $(build_dir)/$(app_name)-$(version).tar.gz \
		-C $(sign_dir) $(app_name)
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing package…"; \
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name)-$(version).tar.gz | openssl base64; \
	fi
