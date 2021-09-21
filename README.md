# Recognize for Nextcloud

[![Join the chat at https://gitter.im/marcelklehr/recognize](https://badges.gitter.im/marcelklehr/recognize.svg)](https://gitter.im/marcelklehr/recognize?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

![](https://github.com/marcelklehr/recognize/raw/master/screenshots/screenshot.png)

> Image recognition for Nextcloud

This app goes through your photo collection and adds fitting tags, automatically categorizing your photos.
It also recognizes faces from photos set in your contacts.
Tagging works via Nextcloud's Collaborative Tags.
You can view your tagged photos with the photos app, as seen in the screenshot above.

### Privacy
This app does not send any sensitive data to cloud providers or similar services. All image processing is done on your nextcloud machine, using Tensorflow.js running in Node.js, which comes bundled with this app.

### Categories
This is the [list of recognized things and which categories they are currently mapped to](https://github.com/marcelklehr/recognize/blob/master/src/rules.yml). I'm happy to accept pull requests for this file to fine tune predictions.

## Behind the scenes
Recognize uses [Efficient](https://github.com/google/automl/tree/master/efficientnetv2)[Net v2](https://tfhub.dev/google/collections/efficientnet_v2/1) for ImageNet object detection

Recognize uses [face-api.js](https://github.com/justadudewhohacks/face-api.js) to extract and compare face features.


## Install

### Requirements

- php 7.3 and above
- App "collaborative tags" enabled
- Processor
  - x86 64bit
  - probably ARMv7 (32bit) (untested)
- System with glibc (usually the norm; Alpine linux is *not* such a system)
- ~3GB of free RAM (if you're cutting it close, make sure you have some swap available)

### One click

Go to "Apps" in your nextcloud, search for "recognize" and click install.

### Configuration

Any configuration is done in Settings/Recognize of your Nextcloud instance.

You can also ignore directories (and their children) by adding a `.noimage` or `.nomedia` file in them.

### Manual install

#### Dependencies

- [git](https://git-scm.org/)
- [Node.js and npm](https://nodejs.org/)
- [php](https://php.net/)
- [composer](https://getcomposer.org/)

#### Setup

```
cd /path/to/nextcloud/apps/
git clone https://github.com/marcelklehr/recognize.git
cd recognize
make
```

## Maintainers

- [Marcel Klehr](https://github.com/marcelklehr)

## Donate

If you'd like to support the creation and maintenance of this software, consider donating.

| [<img src="https://img.shields.io/badge/paypal-donate-blue.svg?logo=paypal&style=for-the-badge">](https://www.paypal.me/marcelklehr1) | [<img src="http://img.shields.io/liberapay/receives/marcelklehr.svg?logo=liberapay&style=for-the-badge">](https://liberapay.com/marcelklehr/donate) |[<img src="https://img.shields.io/badge/github-sponsors-violet.svg?logo=github&style=for-the-badge">](https://github.com/sponsors/marcelklehr) |
| :-----------------------------------------------------------------------------------------------------------------------------------: | :-------------------------------------------------------------------------------------------------------------------------------------------------: |:--:|


## Contribute

We always welcome contributions. Have an issue or an idea for a feature? Let us know. Additionally, we happily accept pull requests.

In order to make the process run more smoothly, you can make sure of the following things:

- Announce that you're working on a feature/bugfix in the relevant issue
- Make sure the tests are passing
- If you have any questions you can let the maintainers above know privately via email, or simply open an issue on github

Please read the [Code of Conduct](https://nextcloud.com/community/code-of-conduct/). This document offers some guidance to ensure Nextcloud participants can cooperate effectively in a positive and inspiring atmosphere, and to explain how together we can strengthen and support each other.

More information on how to contribute: https://nextcloud.com/contribute/

Happy hacking :heart:

## License

This software is licensed under the terms of the AGPL written by the Free Software Foundation and available at [COPYING](./COPYING).
