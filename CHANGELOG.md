# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## v1.6.3

### Fixed

- Fix pure JS mode for imagenet classifier

## v1.6.2

### Fixed
- Don't require wasm

## v1.6.1

### Fixed
- Reduce bundle size
- Disable GPU accelaration for now


## v1.6.0

### New

- Implement a pure JS option

## v1.5.8

### Fixed

- Fix file too short error
- Implement js-only fallback for cases where binaries can't be loaded


## v1.5.7

### Fixed

- Fix build: Dereference symlinks instead of deleting them

## v1.5.6

### Fixed

- Command: Catch classifier errors
- Fix out-of-path file extraction
- Fix broken pipe error

## v1.5.5

### Fixed

- Reduce bundle size

## v1.5.4

### Fixed

- Fix "file too short" error
- Requirements: Add note about alpine
- Fix EAGAIN error in classifier_imagenet
- ReferenceFacesFinder: Don't use system address book
- Fix "More than 1000 expressions on Oracle" warning
- Remove WASM mode for now
- classifier_imagenet: Fix model download
- Add setting for tfjs-node-gpu

## v1.5.3

### Fixed
- Classifier.js: use fs/promises
- Support 'uri:' photos in contacts
- Add note in admin settings about first run delay
- Fix findMissedClassifications
- Avoid symlink errors

## v1.5.2

### Fixed

- classifier_faces: Don't fail on unreadable images


## v1.5.1

### Fixed

- Reduced bundle size to be able to install from app store

## v1.5.0

### New

- New imagenet model with increased accuracy (EfficientNet v2 XL)
- Face recognition using contact photos
- Support GPU


### Fixed
- Support .nomedia and .noimage files

## v1.4.2

### Fixed
- Include admin settings in build
- Increase JPEG decoder memory limit

## v1.4.0

### New
- admin settings
- README and info.xml: Add note about privacy

### Fixed
- Improve classification
- classifier_imagenet.js: Catch errors

## v1.3.1

### Fixed

- Fix build

## v1.3.0

### New

- Add a command line interface to run classifier on full speed

## v1.2.2

### Fixed

- Add support for ARMv7l


## v1.2.1

### Fixed

- Fix installation via appstore

## v1.2.0

### New

- Support ARM64

### Fixed

- Fix build to allow installation via app store

## v1.1.0

### New

- Use new rules to tag photos

### Fixed

- Fix build to allow installation via app store

## v1.0.0
Initial version
