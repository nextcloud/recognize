# SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
.DEFAULT_GOAL := help

APP_ID := recognize_exapp
APP_NAME := Recognize classification backend
APP_VERSION := $$(xmlstarlet sel -t -v "//version" appinfo/info.xml)
APP_PORT := 9000
JSON_INFO := "{\"id\":\"$(APP_ID)\",\"name\":\"$(APP_NAME)\",\"daemon_config_name\":\"manual_install\",\"version\":\"$(APP_VERSION)\",\"secret\":\"12345\",\"port\":$(APP_PORT)}"

.PHONY: help
help:
	@echo "  Welcome to $(APP_NAME) $(APP_VERSION)!"
	@echo " "
	@echo "  Please use \`make <target>\` where <target> is one of"
	@echo " "
	@echo "  build-push        builds the CPU image and uploads it to ghcr.io"
	@echo "  build-push-gpu    builds the GPU image and uploads it to ghcr.io"
	@echo " "
	@echo "  > Commands for the nextcloud-docker-dev environment (run on the host):"
	@echo " "
	@echo "  run               registers $(APP_NAME) on Nextcloud Latest via the App Store manifest"
	@echo "  register          registers a manually-running $(APP_NAME) into the 'manual_install' daemon"

.PHONY: build-push
build-push:
	docker login ghcr.io
	docker buildx build --push --platform linux/amd64 \
		--build-arg BUILD_TYPE=cpu \
		--build-arg RECOGNIZE_REF=v$(APP_VERSION) \
		--tag ghcr.io/nextcloud/$(APP_ID):$(APP_VERSION) \
		--tag ghcr.io/nextcloud/$(APP_ID):latest .

.PHONY: build-push-gpu
build-push-gpu:
	docker login ghcr.io
	docker buildx build --push --platform linux/amd64 \
		--build-arg BUILD_TYPE=gpu \
		--build-arg RECOGNIZE_REF=v$(APP_VERSION) \
		--tag ghcr.io/nextcloud/$(APP_ID):$(APP_VERSION)-gpu \
		--tag ghcr.io/nextcloud/$(APP_ID):latest-gpu .

.PHONY: run
run:
	docker exec master-nextcloud-1 sudo -u www-data php occ app_api:app:unregister $(APP_ID) --silent --force || true
	docker exec master-nextcloud-1 sudo -u www-data php occ app_api:app:register $(APP_ID) \
		--info-xml https://raw.githubusercontent.com/nextcloud/recognize/main/exapp/appinfo/info.xml

.PHONY: register
register:
	docker exec master-nextcloud-1 sudo -u www-data php occ app_api:app:unregister $(APP_ID) --silent --force || true
	docker exec master-nextcloud-1 sudo -u www-data php occ app_api:app:register $(APP_ID) manual_install --json-info $(JSON_INFO) --wait-finish
