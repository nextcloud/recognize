# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [7.0.2-beta.2] - 2024-06-24

### Fixed

* fix(FileListener)

## [7.0.2-beta.1] - 2024-06-18

### Fixed

* fix(FileListener): Clear MountCache before checking access
* fix(FileListener): Use MountCache and ShareManager and groupfolder manager to get access list
* fix(FileListener): Listen for ShareAcceptedEvent in addition to ShareCreatedEvent
* fix(FileListener): Try to remember source file for moves
* fix(ViewAdmin): Add title prop to text fields

## [7.0.0] - 2024-05-08

### Breaking changes

 - Bump required Node.js to v20
 - Drop NC 28 support

### Fixed

* fix(Classify command): make it clear queues and jobs
* fix(DAV\FacesHome): Don't list empty clusters
* fix(DAV): Don't make clients load vector data
* Fix(l10n): Update translations from Transifex
* fix(FileListener): Listen to file movements across share boundaries
* fix plsatform check (Thanks to @mr-bryn)
* Fixed spelling mistake in appinfo/routes.php (Thanks to @asp.nut)
* fix(InstallDeps): Use ffmpegDir to install ffmpeg

## [6.1.0] - 2024-02-28

### Fixed
- fix(deps): Isolate all dependencies
- Make 'nice' check cross-platform

### Changed
- Upgrade nextcloud/vue to v8.x

### Fixed
- fix(Rubix\ML): Isolate rubix
- fix(FaceRoot#getChild): Validate detection matches cluster
- fix(FileListener->ShareCreatedEvent): Cast userIds to string
- Fix(l10n): Update translations from Transifex

## [6.1.0-beta.2] - 2024-01-11

### Fixed
- fix(deps): Isolate all dependencies

## [6.1.0-beta.1] - 2024-01-11

### Changed
 - Upgrade nextcloud/vue to v8.x

### Fixed
 - fix(Rubix\ML): Isolate rubix
 - fix(FaceRoot#getChild): Validate detection matches cluster
 - fix(FileListener->ShareCreatedEvent): Cast userIds to string
 - Fix(l10n): Update translations from Transifex

## [6.0.1] - 2023-12-04

### Fixed

 - fix(dav properties): Re-add missing props
 - fix memory leaks
 - Fix(l10n): Update translations from Transifex
 - fix(ViewAdmin): Disable GPU mode when WASM mode is enabled

## [6.0.0] - 2023-11-19

### Breaking

- Drop support for Nextcloud 27

### New

 - CacheQueryBuilder API changed in 28
 - Add compatibility to new FilesMetadata OCP API

### Fixed

 - Fix(l10n): Update translations from Transifex
 - fix(Classifier): Don't hang because of empty file Marcel Klehr 05.11.23, 13:16
 - Refactor classifier preview generation to allow handling WebP and AVIF and fall back to gdlib when nc preview providers don't work

## [5.0.3] - 2023-10-15

### Fixed

- Fix concurrency limit issue

## [5.0.2] - 2023-10-15

### Fixed

- Fix concurrency limit issue

## [5.0.1] - 2023-10-09

### Fixed
- Fix installation issue

## [5.0.0] - 2023-10-08

### Breaking changes
 - Drop support for Nextcloud 26

### New
- storage crawl job: Increase batch size to 2000
- Allow enabling/disabling classifier concurrency

### Fixed
 - fix(IgnoreService): Always return paths matching filecache format (fixes ignore files in external storage)
 - Remove max execution time limitation and disable parallel classifier job execution (fixes stuck classification)
 - Fix(ViewAdmin): Don't display last bg job execution if it's 0
 - Fix(snap): Improve interoperability with nc snap
 - fix(AdminController): Catch failed cpuinfo readfile
 - Fix(l10n): Update translations from Transifex

## [4.3.2] - 2023-08-19

### Fixed

- Fix scanner event hooks

## [4.3.1] - 2023-08-15

### Fixed
 - Fix face detection mapper
 - Improve classifier error handling
 - Fix for external storage: Listen to scanner events
 - Fix Duplicates in Groupfolders: Only scan each groupfolder once
 - SettingsService: remove confusing JSON encoding step. All settings are strings, period.
 - Musicnn: Cut off shorter audio clip in wasm mode
 - Fix(l10n): Update translations from Transifex

## [4.3.0] - 2023-07-12

### New
 - Add option to change nice value for all classifiers

### Fixed
 - l10n: Update translations from Transifex
 - DAV: Don't list photos that have been deleted
 - Classifier#getConvertedFilePath: Catch all errors from getPreview()
 - ViewAdmin: Fix typo
 - FaceDetectionMapper: Correct countUnclustered
 - ViewAdmin: Add warning if countQueued > 0 but scheduledJobs == 0
 - ViewAdmin: Add background jobs count and last execution time for all tasks
 - recrawl: Clear all background jobs

## [4.2.0] - 2023-06-16

### New
- Expose face-preview-image prop
- Add an unassigned-faces DAV collection
- Increase min cluster separation to 0.35
- Admin settings: Reorder terminal commands

### Fixed
- FaceClusterMapper: Use parameter for int argument to db delete query
- composer.json: Disable authoritative classmap
- Classifier: Correctly clean up temp files
- Admin settings: Mention minimum face count for clustering
- l10n: Update translations from Transifex

## [4.1.0] - 2023-05-21

### New

- Increased face clustering quality based on benchmark
- 🔠 Update translations from Transifex

### Fixed
- Fixed memory leaks in classifiers
- Increased memory cap in WASM mode
- Fix DAV metadata

## [4.0.1] - 2023-04-27

### Breaking changes

 - Drop support for Nextcloud 25 and PHP 7.4

### Fixed

 - Fix BadFunctionCall Exception in Nextcloud 26

## [3.8.0] - 2023-04-27

### New
 - Allow admins to clear the queues and background jobs
 - DAV: allow creating clusters
 - DAV: replace fileId in name with detectionId
 - 🔠 Update translations from Transifex

### Fixed
 - Prevent null nodes in MrdBallTree
 - Fix: Recognize tries to download non-existend nodejs file
 - chore(README): Be more specific that the app only works in WASM mode on AIO
 - Classifier: Don't override env vars with every var we set
 - classifier_musicnn: Always use PUREJS variable instead of env var

## [3.7.0] - 2023-03-19

### New
 - ClusterFaces command: Add batch-size option
 - enh(FaceClusterAnalyzer): Use dynamic min cluster size
 - Limit max edge length and cluster separation (Thanks to @MB-Finski :blue_heart:)
### Fixed
 - fix(FaceClusterAnalyzer): If no batch size is set, remove php memory limit
 - fix(TagManager): Catch exception in resetClassifications
 - 🔠 Update translations from Transifex

## [3.6.3] - 2023-03-14

### Fixed
 - Don't remove all tags in resetClassifications
 - Face clustering: Increase MIN_DATASET_SIZE to avoid shit clusters 
 - Face Clustering: Avoid OOM by not requesting all unclustered detections from db
 - Increase node download timeout
 - FileListener: Add caching of expensive queries and don't recurse when node is created or deleted
 - 🔠 Update translations from Transifex

## [3.6.2] - 2023-03-08

### Fixed

- Revert "Utilize mozart to avoid dependency hell"

## [3.6.1] - 2023-03-07

### Fixed
- Add missing composer.json to build bundle

## [3.6.0] - 2023-03-06

### New
 - Listen to ignore file changes

### Fixed
 - Copy face detections to sharees on ShareCreated; Delete them on ShareDeleted
 - Add face detections for all users that can see the file
 - Implement code paths for moving files
 - limit ffmpeg CPU core usage according to settings
 - Utilize mozart to avoid dependency hell
 - Update translations from Transifex
 - Add note about 1-core policy for WASM
 - Allow downloading node builds for arm64 musl

## [3.5.0] - 2023-02-09

### New
- Clustering: Cap clustering batch size at 10k
- Implement new face clustering algorithm (HDBSCAN) thanks to @MB-Finski
- Add command: recognize:reset-face-clusters

## [3.4.0] - 2023-02-03

### New
- Implement cluster-faces command

### Fixed
 - Classifier: Log real file names
 - Update tfjs
 - InstallDeps: Avoid chmod error by checking if file exists
 - Add another catch handler for previewProvider#getPreview
 - Classifier: Suppress getimagesize errors
 - Classifier: Clean up tmp files after classification

## [3.3.6] - 2023-01-16

### Fixed
- Fix Installation routine
- StorageService: Don't throw if root is not found

## [3.3.5] - 2023-01-12

### Fixed
 - Add Migration step: Add index for detections#file_id column
 - Run custom ffmpeg install in InstallDeps (Support musicnn,movinet on ARM)
 - MusicnnClassifier: Increase WASM timeout
 - ClusteringFaceClassifier: Filter out non-straight faces
 - Set max execution time for classifier cron job
 - ViewAdmin: Don't mark "models still need to be downloaded" as success
 - Don't rerun InstallDeps when node binary exists already

## [3.3.4] - 2022-12-27

### Fixed
 - increase install nodejs download timeout to 300s
 - Implement incremental clustering Marcel Klehr
 - FaceClusterAnalyzer: Increase cluster density
 - FaceClusterAnalyzer: Filter out too small faces
 - ClusteringFaceClassifier: Increase face recognition score

## [3.3.3] - 2022-12-14

### Fixed

- Fix bundle size

## [3.3.2] - 2022-12-14

### Fixed
 - Don't allow setting cluster title to the same value as the ID of a cluster without a title
 - Classifier#getConvertedFilePath: Don't throw if $preview is false
 - Classifier: Don't let images larger than 1024x1024 into the classifier
 - Classify command: Only add items to queue if model is enabled
 - FaceClusterAnalyzer: Ignore clusters that are larger than what could possibly be the same face (Fixes the mega-cluster issue; make sure to delete mega-clusters after the update)

## [3.3.1] - 2022-12-10

### Fixed

- Add localizations to the build

## [3.3.0] - 2022-12-09

### New
 - Implement recognize:remove-legacy-tags command
 - Implement GPU mode
 - Implement recognize:classify command

## [3.2.3] - 2022-11-25

### Fixed
 - Remove SchedulerJob from info.xml
 - Classifier: Only apply 8MB rule to image classifiers
 - Use regex to catch ignored paths instead of enumerating directories
 - Use IgnoreService in FileListener
 - Add bg job mode status in admin settings
 - Add tensorflow WASM status check to admin settings
 - Add debug statements
 - StorageCrawlJob: Restrict search for .ignore files to current storage

## [3.2.2] - 2022-11-16

### Fixed
 - Classifier#getConvertedFilePath: Remove file from queue if it can't be processed
 - SettingsService#setSetting: Fix key existence check

## [3.2.1] - 2022-11-11

### Fixed
 - Admin settings: Fix auto-save for batch size settings

## [3.2.0] - 2022-11-11

### Added
- Add status indicators about Node.js and libtensorflow installation
- Allow setting batch sizes in admin settings
- Support .nomedia tags (thanks to  @fa0311)
- DAV faces endpoint: Provide number of files per face

### Fixed
- Classifier: Don't process images larger than 8MiB
- Classifier: Use nc preview provider to generate smaller tempfiles
- Don't create duplicate queue entries
- Classifier: Remove file from queue if it can't be found in IRootFolder

## [3.1.2] - 2022-11-03

### Fixed

 - Disable timeout for downloads on cli usage @juliushaertl
 - Fix string int conversion
 - Fix static analysis errors
 - Don't reinsert same face detection multiple times

## [3.1.1] - 2022-10-28

### Fixed
 - Stabilize face clustering across manual cluster edits
 - ViewAdmin: Add button to reset faces
 - Remove UserController.php
 - Only reset faces when explicitly asked
 - Fix eslint errors
 - Fix info.xml lint errors
 - DAV endpoint: Prevent duplicate names
 - ClassifierJob: Run with higher frequency
 - ViewAdmin: Show message when checking machine failed
 - StorageCrawlJob: Exit early if storage root doesn't exist
 - Zero safety

## [3.1.0] - 2022-10-19

### New
 - Decrease face distance threshold
 - Stabilize face clustering across manual cluster edits
 - FaceClusterAnalyzer: Knock out less matching faces from a cluster if they are on the same file
 - Add support for HEIC/HEIF/TIFF Convert all images to JPEG and downscale before passing to node

### Fixed
 - Admin settings: Don't repush status info
 - Fix AdminController#avx
 - DownloadModelsService: Unlink archive after extraction
 - Fix StorageCrawlJob path issue

## [3.0.1] - 2022-10-12

Drop support for Nextcloud 24

## [3.0.0] - 2022-10-12

### New
 - Allow scheduling specific classifier/crawl jobs per model
 - AdminSettings: Display last classification time
 - AdminSettings: Add status of downloaded models
 - ClassifierJob: set timeSensitivity

### Fixed
 - Fix StorageCrawlJob
 - Remove non-existent UserSettings from info.xml
 - FaceClusterAnalyzer: Do not delete existing clusters
 - Constants::IMAGE_FORMATS: Don't support tiff
 - DownloadModelsService: Fix array filter
 - InstallDeps: Fix isAVXSupported
 - l10n: Source string improvement
 - Fix label padding in admin settings
 - DownloadModelsService: Increase timeout
 - Correct spelling
 - ClassifierJob: Stop classify job if model is disabled
 - Fix Landmarks switch
 - Make admin settings translatable
 - Polish admin settings

## 3.0.0-beta.3 - 2022-09-20

### Fixed
- FaceDetectionMapper#findByFileIdAndClusterId: setMaxResults 1

## 3.0.0-beta.2 - 2022-09-20

### Changed
- Remove user settings
- Update tfjs
- Extend imagenet rules to be consistent in overarching categories

### Fixed

 - ClassifierJob: Fix undefined offset
 - Fix s3 support
 - Fix SchedulerJob
 - FaceClusterMapper#findByUserId: Don't return empty clusters
 - FileListener: Check children of deleted folders
 - Avoid crawling trash
 - BackgroundJobs: Reduce SQL queries in bg jobs
 - FaceDetectionMapper: Add table prefix to findByFileIdAndClusterId
 - Update translatable strings

## 3.0.0-beta.1 - 2022-08-29

### New
 - Make tags translatable
 - Expose faces via DAV endpoint

### Changed

- No more commands, classification always takes place in cron jobs
- Refactor classifier jobs to scale well
- faces UI moved to photos app
- movinet: reduce time slice length and fps
- Requires Nextcloud >= v24

### Fixed
 - node.js classifiers: Fix fallback to wasm mode
 - Fix file listener
 - Fix admin settings data submission


## 2.1.2 - 2022-06-11

### Fixed

- Fix TagManager#assignTags

## 2.1.1 - 2022-06-10

### Fixed
- TagManager: Use objectMapper#assignTags correctly (no more vanishing tag assignments)
- ViewAdmin: Fix html syntax
- Remove Unrecognized label as it does not work correctly

## 2.1.0 - 2022-06-09

### New
- Implement MoViNet video classifier
- TagManager: add version to 'processed' tag

### Fixed
- Avoid shipping client-side node modules
- Geo classifier: Fix type error from null tag
- AdminSettings: Fix typo

## 2.0.1 - 2022-05-18

### Fixed

- AdminSettings: Load settings using initial state
- InstallDeps: Fix tfjs installation
- InstallDeps: Fix AVX check on hardened systems
- Fix avx check endpoint
- Fix musl and platform check endpoints

## 2.0.0 - 2022-05-16

### Changed
- Drop support for php v7.3
- Drop support for Nextcloud v20 and v21

### New
- Support Nextcloud 24
- ViewAdmin: Add status indicators for image and audio recognition
- info.xml: Add instruction about post-install steps

### Fixed
- FileFinder: Fail graciously when storage is not available
- FileFinder: Only check original owner for shares
- ViewAdmin: Clarify node path setting description
- ViewAdmin: Add examples for each tagging setting
- ViewAdmin: Update Note about background job interval
- InstallDeps: Fix ffmpeg
- Settings: Allow enabling geo tagging
- Fine-tune musicnn

## 1.12.0 - 2022-04-14
### New
 - Implement GeoClassifier
 - Add newly trained musicnn model
 - Admin settings: Add system check for WASM mode
 - InstallDeps: Add check for AVX
 - FileFinderService: Add more debug output

### Fixed
 - InstallDeps: Don't enable WASM mode when installation failed
 - CLI: Don't call the classifiers with *all* files in one go
 - Fix encoding errors in tags
 - Smarter way to detect cpu architecture
 - Don't leak exec errors on installation

## 1.11.0 - 2022-02-09

### New
 - Streamline model download
 - Add support for musl/Alpine Linux (#145)

### Fix
 - Ignore non-owner images (Thanks to jim)

## 1.10.0 - 2022-01-27

### New
 - Landmarks: Add 'landmark' tag to all landmarks
 - Implement commands: cleanup-tags & reset-tags
 
### Fixed
- Fix classifier_faces
- Clarify debug message
- classifier_landmarks: Don't run landmarks models on 'landscape' images
- classifier_landmarks: Increase threshold to 0.9

## 1.9.0 - 2022-01-16

### New

 - Allow setting amount of used cores
 - Speed up image classification models in pureJS mode by using WASM
 - InstallDeps: Automatically enable pureJS on ARM
 - Implement landmarks

### Fixed

 - Reduce BG job batch size in pureJS mode
 - imagenet: Try to improve recognition of historic architecture
 - imagenet: Don't ignore volcano

## 1.8.0 - 2021-12-31

### New
 - Implement Logger proxy and copy logs to cli

### Fixed
- Lint: Run php-cs-fixer
- Refactor: Move classifier classes out of Service folder
- check faces-classify enabled earlier (thanks @bonswouar)
- fix #121 empty contact photo (thanks @bonswouar)
- Fix Classifier timeouts: Add constant time to account for model download

## 1.7.0 - 2021-12-13

### New
- Update node.js version
- Set upper limit for image file size
- Support Nextcloud 23
- New logo and cover art
- Music genre recognition

### Fixed
- Fix ViewAdmin API URLs
- higher timeout values
- fix default checkboxes

## 1.6.10 - 2021-09-10

### Fixed

- Fixed node-pre-gyp execution

## 1.6.9

### Fixed

- Fixed binary permissions

## 1.6.8

### Fixed

- Fixed PureJS config checkbox initial values

## 1.6.7

### Fixed
- Failed to install Tensorflow.js

## 1.6.6

### Fixed

- Classifier_faces: Always return proper output
- Classifier_imagenet: Correct exit code

## 1.6.5

### Fixed
 - Improve some false-positive thresholds
 - Add options for disabling faces/imagenet individually
 - Pure js mode: Adjust image timeout values
 - Add node.js path option
 - Use the TF.js built-in installer for optimal prebuilt binaries

## 1.6.4

### Fixed

- Fix pure JS mode for imagenet classifier


## 1.6.3

### Fixed

- Fix pure JS mode for imagenet classifier

## 1.6.2

### Fixed
- Don't require wasm

## 1.6.1

### Fixed
- Reduce bundle size
- Disable GPU accelaration for now


## 1.6.0

### New

- Implement a pure JS option

## 1.5.8

### Fixed

- Fix file too short error
- Implement js-only fallback for cases where binaries can't be loaded


## 1.5.7

### Fixed

- Fix build: Dereference symlinks instead of deleting them

## 1.5.6

### Fixed

- Command: Catch classifier errors
- Fix out-of-path file extraction
- Fix broken pipe error

## 1.5.5

### Fixed

- Reduce bundle size

## 1.5.4

### Fixed

- Fix "file too short" error
- Requirements: Add note about alpine
- Fix EAGAIN error in classifier_imagenet
- ReferenceFacesFinder: Don't use system address book
- Fix "More than 1000 expressions on Oracle" warning
- Remove WASM mode for now
- classifier_imagenet: Fix model download
- Add setting for tfjs-node-gpu

## 1.5.3

### Fixed
- Classifier.js: use fs/promises
- Support 'uri:' photos in contacts
- Add note in admin settings about first run delay
- Fix findMissedClassifications
- Avoid symlink errors

## 1.5.2

### Fixed

- classifier_faces: Don't fail on unreadable images


## 1.5.1

### Fixed

- Reduced bundle size to be able to install from app store

## 1.5.0

### New

- New imagenet model with increased accuracy (EfficientNet v2 XL)
- Face recognition using contact photos
- Support GPU


### Fixed
- Support .nomedia and .noimage files

## 1.4.2

### Fixed
- Include admin settings in build
- Increase JPEG decoder memory limit

## 1.4.0

### New
- admin settings
- README and info.xml: Add note about privacy

### Fixed
- Improve classification
- classifier_imagenet.js: Catch errors

## 1.3.1

### Fixed

- Fix build

## 1.3.0

### New

- Add a command line interface to run classifier on full speed

## 1.2.2

### Fixed

- Add support for ARMv7l


## 1.2.1

### Fixed

- Fix installation via appstore

## 1.2.0

### New

- Support ARM64

### Fixed

- Fix build to allow installation via app store

## 1.1.0

### New

- Use new rules to tag photos

### Fixed

- Fix build to allow installation via app store

## 1.0.0
Initial version
