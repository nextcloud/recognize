# Recognize ExApp (classification backend container)

This directory contains a Nextcloud **External App** (ExApp) that runs the
recognize machine-learning classifiers inside a container, so that the heavy
inference workload can be offloaded from the Nextcloud server to a different,
more powerful machine — optionally one with a GPU.

This implements the "dedicated devices / ExApp as backend" feature requested in
[issue #73](https://github.com/nextcloud/recognize/issues/73).

## How it works

```
┌──────────────────────┐         AppAPI (authenticated HTTP)        ┌─────────────────────────┐
│ Nextcloud + recognize │  ── POST /classify  (model + image files) ─▶ │ recognize_exapp (this)  │
│  (classifier.backend  │                                            │  Node.js + Tensorflow.js │
│        = "exapp")     │  ◀── newline-delimited JSON results ──────── │  classifier_<model>.js   │
└──────────────────────┘                                            └─────────────────────────┘
```

The recognize app (PHP) prepares the same downscaled preview images it would
normally feed to the local `classifier_<model>.js` process, but instead uploads
them to this ExApp via AppAPI. The ExApp writes them to a temporary directory,
spawns the *unmodified* `classifier_<model>.js` script (reading paths from stdin,
just like the local backend), and streams the JSON result lines back. Because the
same scripts are reused, the results are byte-for-byte compatible with local
classification.

## Installation (recommended: App Store)

The default and recommended way to deploy this is via the Nextcloud App Store,
exactly like the other AI ExApps (llm2, context_chat_backend, …):

1. Install the [**AppAPI**](https://apps.nextcloud.com/apps/app_api) app and
   configure a Deploy Daemon (Administration settings → AppAPI).
2. Install the [**Recognize**](https://apps.nextcloud.com/apps/recognize) app.
3. On the **External Apps** page, install **"Recognize classification backend"**.
   AppAPI pulls the container image (`ghcr.io/nextcloud/recognize_exapp`) and
   deploys it on your daemon.
4. In **Administration settings → Recognize → Classification backend**, select
   *"Offload classification to an External App"*. The App ID defaults to
   `recognize_exapp` and the connection is verified automatically.

For GPU acceleration, deploy the ExApp on a daemon that runs the GPU image
variant and set the `RECOGNIZE_GPU` environment variable to `true`.

## Endpoints

| Method | Route        | Purpose                                                              |
|--------|--------------|----------------------------------------------------------------------|
| GET    | `/heartbeat` | AppAPI liveness probe (`{"status":"ok"}`)                            |
| PUT    | `/init`      | AppAPI deploy hook — triggers model download                         |
| PUT    | `/enabled`   | AppAPI enable/disable hook                                            |
| POST   | `/classify`  | multipart: `model` field + `files[N]` image files → JSON result lines |

All requests except `/heartbeat` are authenticated using the AppAPI shared secret
(`AUTHORIZATION-APP-API` header) injected into the container at deploy time.

## Building the image manually

The image is **self-contained**: it fetches the matching recognize sources at
build time, so it builds straight from this directory:

```sh
cd exapp
# CPU
make build-push            # builds & pushes ghcr.io/nextcloud/recognize_exapp:<version>
# GPU
make build-push-gpu
```

Or directly with Docker:

```sh
docker build -t recognize_exapp:latest .
docker build --build-arg BUILD_TYPE=gpu -t recognize_exapp:latest-gpu .
```

`RECOGNIZE_REF` controls which recognize git tag the classifier sources are
pulled from (defaults to the version tag; CI sets it to the exact release tag).

## Manual registration (dev / no App Store)

For development with `nextcloud-docker-dev`, or to register a manually-running
container without the App Store:

```sh
# register the App Store manifest against a running Nextcloud
make run

# OR register a container you started yourself into the manual_install daemon
make register
```

## Release / publishing

Two GitHub workflows handle releases (see `.github/workflows/exapp-*.yml` in the
repo root):

* `exapp-publish-docker.yml` — on a `v*` tag, builds and pushes the container
  image to `ghcr.io/nextcloud/recognize_exapp`.
* `exapp-appstore-build-publish.yml` — on a published release, packages, signs
  and uploads the manifest to the Nextcloud App Store.

The `image-tag` in `appinfo/info.xml` must match the `version`, and both must
match the release tag.
