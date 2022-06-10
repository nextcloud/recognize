# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
