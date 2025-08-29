app_name=recognize

project_dir=$(CURDIR)/../$(app_name)
build_dir=$(CURDIR)/build/artifacts
appstore_dir=$(build_dir)/appstore
source_dir=$(build_dir)/source
sign_dir=$(build_dir)/sign
package_name=$(app_name)
cert_dir=$(HOME)/.nextcloud/certificates
version+=8.2.2

all: dev-setup build-js-production

release: remove-devdeps remove-binaries appstore

# Dev env management
dev-setup: clean clean-dev npm-init composer-install install-binaries

npm-init:
	npm ci

npm-update:
	npm update

composer-install:
	composer install
	composer install

install-binaries:
	mkdir -p bin

# Building
build-js:
	npm run dev

build-js-production:
	npm run build

remove-binaries:
	# make it download appropriate tf binaries
	rm -rf node_modules/@tensorflow/tfjs-node/deps/lib/*
	rm -rf node_modules/@tensorflow/tfjs-node/lib/*
	rm -rf node_modules/@tensorflow/tfjs-node-gpu/lib/*
	rm -rf node_modules/@tensorflow/tfjs-node-gpu/deps/lib/*
	rm -rf node_modules/ffmpeg-static/ffmpeg

remove-devdeps:
	rm -rf node_modules
	npm install --omit dev

watch-js:
	npm run watch

# Linting
lint:
	npm run lint

lint-fix:
	npm run lint:fix

# Style linting
stylelint:
	npm run stylelint

stylelint-fix:
	npm run stylelint:fix

# Cleaning
clean:
	rm -rf js
	rm -rf $(sign_dir)

clean-dev:
	rm -rf vendor
	rm -rf node_modules

appstore:
	rm -rf vendor
	composer install --no-dev
	composer install --no-dev
	mkdir -p $(sign_dir)
	rsync -a --delete \
	--include=/CHANGELOG.md \
	--include=/README.md \
	--include=/templates \
	--include=/node_modules \
	--include=/package.json \
	--include=/package-lock.json \
	--include=/composer.json \
	--include=/composer.lock \
	--include=/src \
	--include=/js \
	--include=/lib \
	--include=/l10n \
	--include=/img \
	--include=/appinfo \
	--include=/bin \
	--include=/vendor \
	--exclude=**/*.map \
	--exclude=/* \
	--exclude=node \
	$(project_dir)/ $(sign_dir)/$(app_name)
	tar -czf $(build_dir)/$(app_name)-$(version).tar.gz \
		-C $(sign_dir) $(app_name)
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing package…"; \
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name)-$(version).tar.gz | openssl base64; \
	fi
