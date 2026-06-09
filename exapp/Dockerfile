# SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Recognize ExApp classification backend.
#
# This image is self-contained: it fetches the matching recognize sources
# (classifier scripts + assets) at build time, so it can be built straight from
# this directory (`context: ./exapp`) as the Nextcloud App Store / CI expects.
#
# BUILD_TYPE selects the Tensorflow backend:
#   cpu (default) → node:20 base, @tensorflow/tfjs-node
#   gpu           → CUDA base image, @tensorflow/tfjs-node-gpu
#
#   docker build -t recognize_exapp:latest .
#   docker build --build-arg BUILD_TYPE=gpu -t recognize_exapp:latest-gpu .

ARG BUILD_TYPE=cpu

###############################################################################
# Base images
###############################################################################
FROM node:20-bookworm-slim AS base-cpu
ENV RECOGNIZE_GPU=false
# Build tools for native node modules (@tensorflow/tfjs-node postinstall).
RUN apt-get update \
	&& apt-get install -y --no-install-recommends python3 make g++ \
	&& rm -rf /var/lib/apt/lists/*

FROM nvidia/cuda:12.2.2-cudnn8-devel-ubuntu22.04 AS base-gpu
ENV RECOGNIZE_GPU=true
SHELL ["/bin/bash", "-o", "pipefail", "-c"]
RUN apt-get update \
	&& apt-get install -y --no-install-recommends curl ca-certificates gnupg python3 make g++ \
	&& curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
	&& apt-get install -y --no-install-recommends nodejs \
	&& rm -rf /var/lib/apt/lists/*

###############################################################################
# Final image
###############################################################################
# hadolint ignore=DL3006
FROM base-${BUILD_TYPE}

# The recognize git ref to pull the classifier sources from. Defaults to the
# tag matching this ExApp's version; CI overrides it to the exact release tag.
ARG RECOGNIZE_REF=v12.1.0

ENV APP_HOST=0.0.0.0
ENV APP_PORT=9000
ENV RECOGNIZE_SRC_DIR=/app/src

WORKDIR /app

RUN apt-get update \
	&& apt-get install -y --no-install-recommends curl ca-certificates tar \
	&& rm -rf /var/lib/apt/lists/*

# Fetch the recognize sources (src/ + package.json) at the pinned ref.
RUN curl -fsSL "https://github.com/nextcloud/recognize/archive/${RECOGNIZE_REF}.tar.gz" -o recognize.tar.gz \
	&& tar -xzf recognize.tar.gz --strip-components=1 --wildcards \
		"*/src" "*/package.json" "*/package-lock.json" \
	&& rm recognize.tar.gz

# Install the recognize node dependencies (Tensorflow.js etc.).
RUN npm ci --omit=dev

# The ExApp HTTP server.
COPY server.js ./server.js

# Pre-download the models so the container is ready to classify on first request.
# (Comment out to download lazily on the AppAPI /init hook instead.)
RUN node -e "require('./src/model-manager.js').downloadAll()" || echo "Model download deferred to runtime"

EXPOSE 9000

CMD ["node", "server.js"]
