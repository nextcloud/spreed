app_name=spreed

project_dir=$(CURDIR)/../$(app_name)
build_dir=$(CURDIR)/build
source_dir=$(build_dir)/$(app_name)
sign_dir=$(build_dir)/sign

all: dev-setup build-js-production

dev-setup: clean clean-dev npm-init

dependabot: dev-setup npm-update build-js-production

npm-init:
	npm install

npm-update:
	npm update

build-js:
	npm run dev

build-js-production:
	npm run build

lint:
	npm run lint

lint-fix:
	npm run lint:fix

watch-js:
	npm run watch

clean:
	rm -f ../js/admin/*.js
	rm -f ../js/admin/*.js.map
	rm -f ../js/collections.js
	rm -f ../js/collections.js.map
	rm -f ../js/collectionsintegration.js
	rm -f ../js/collectionsintegration.js.map
	rm -rf $(build_dir)

clean-dev:
	rm -rf node_modules
