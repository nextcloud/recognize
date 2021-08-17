app_name=recognize

project_dir=$(CURDIR)/../$(app_name)
build_dir=$(CURDIR)/build/artifacts
appstore_dir=$(build_dir)/appstore
source_dir=$(build_dir)/source
sign_dir=$(build_dir)/sign
package_name=$(app_name)
cert_dir=$(HOME)/.nextcloud/certificates
version+=1.5.1

node_version=v14.17.4

all: dev-setup build-js-production composer-no-dev

release: appstore create-tag

create-tag:
	git tag -s -a v$(version) -m "Tagging the $(version) release."
	git push origin v$(version)

# Dev env management
dev-setup: clean clean-dev npm-init composer-install install-binaries

npm-init:
	npm ci

npm-update:
	npm update

composer-install:
	composer install

composer-no-dev:
	composer install --no-dev

install-binaries:
	mkdir bin
	curl -sL "https://nodejs.org/dist/$(node_version)/node-$(node_version)-linux-arm64.tar.gz" | tar -xzf - node-$(node_version)-linux-arm64/bin/node --to-stdout > bin/node-$(node_version)-linux-arm64
	curl -sL "https://nodejs.org/dist/$(node_version)/node-$(node_version)-linux-armv7l.tar.gz" | tar -xzf - node-$(node_version)-linux-armv7l/bin/node --to-stdout > bin/node-$(node_version)-linux-armv7l
	curl -sL "https://nodejs.org/dist/$(node_version)/node-$(node_version)-linux-x64.tar.gz" | tar -xzf - node-$(node_version)-linux-x64/bin/node --to-stdout > bin/node-$(node_version)-linux-x64
	chmod 0777 bin/node-$(node_version)-linux-arm64 # for good measure, we have a repair step for this, but still
	chmod 0777 bin/node-$(node_version)-linux-armv7l
	chmod 0777 bin/node-$(node_version)-linux-x64

# Building
build-js:
	npm run dev

build-js-production:
	npm run build

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
	rm -rf bin
	rm -rf js
	rm -rf $(sign_dir)

clean-dev:
	rm -rf node_modules

appstore:
	rm -rf node_modules
	npm install --omit dev
	mkdir -p $(sign_dir)
	rsync -a \
	--include=/vendor \
	--include=/CHANGELOG.md \
	--include=/README.md \
	--include=/composer.json \
	--include=/composer.lock \
	--include=/vendor \
	--include=/templates \
	--include=/node_modules \
	--include=/package.json \
	--include=/package-lock.json \
	--include=/src \
	--include=/js \
	--include=/lib \
	--include=/img \
	--include=/appinfo \
	--include=/bin \
	--exclude=**/.bin \
	--exclude=**/*.map \
	--exclude=/* \
	$(project_dir)/ $(sign_dir)/$(app_name)
	tar -czf $(build_dir)/$(app_name)-$(version).tar.gz \
		-C $(sign_dir) $(app_name)
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing package…"; \
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name)-$(version).tar.gz | openssl base64; \
	fi
