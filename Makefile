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

dependabot: dev-setup npm-update build-js-production compile-handlebars-templates bundle-simplewebrtc

release: appstore create-tag

build-js:
	cd vue/ && npm run dev

build-js-production:
	cd vue/ && npm run build

watch-js:
	cd vue/ && npm run watch

lint:
	cd vue/ && npm run lint

lint-fix:
	cd vue/ && npm run lint:fix

npm-init: npm-init-root npm-init-vue

npm-init-root:
	npm install

npm-init-vue:
	cd vue/ && npm install

npm-update:
	npm update
	cd vue/ && npm update

clean:
	rm -f js/admin/*.js
	rm -f js/admin/*.js.map
	rm -f js/collections.js
	rm -f js/collections.js.map
	rm -f js/collectionsintegration.js
	rm -f js/collectionsintegration.js.map
	rm -rf $(build_dir)

clean-dev: clean
	rm -rf node_modules
	cd vue/ && rm -rf node_modules

compile-handlebars-templates:
	bash compile-handlebars-templates.sh

bundle-simplewebrtc:
	# webrtc-adapter uses JavaScript features not supported by browserify,
	# so the sources need to be transformed using babel to a compatible
	# version of JavaScript.
	npx browserify --standalone SimpleWebRTC --transform [ babelify --global --presets [ @babel/env ] ] js/simplewebrtc/simplewebrtc.js > js/simplewebrtc/bundled.js

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
	--exclude=check-handlebars-templates.sh \
	--exclude=compile-handlebars-templates.sh \
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
	--exclude=js/views/templates \
	--exclude=js/tests \
	--exclude=l10n/no-php \
	--exclude=.tx \
	--exclude=Makefile \
	--exclude=node_modules \
	--exclude=package.json \
	--exclude=phpunit*xml \
	--exclude=README.md \
	--exclude=run-*lint.sh \
	--exclude=.scrutinizer.yml \
	--exclude=.stylelintrc \
	--exclude=tests \
	--exclude=.travis.yml \
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
