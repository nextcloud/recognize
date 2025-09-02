
![](https://raw.githubusercontent.com/nextcloud/recognize/main/screenshots/Logo.png)

# Recognize: Smart media tagging for Nextcloud

[![Join the chat at https://gitter.im/marcelklehr/recognize](https://badges.gitter.im/marcelklehr/recognize.svg)](https://gitter.im/marcelklehr/recognize?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

This app goes through your media collection and adds fitting tags, automatically categorizing your photos and music.

* 📷 👪 Recognizes faces and groups photos by faces that appear in them (GUI in the photos app and the memories app)
* 📷 🏔 Recognizes animals, landscapes, food, vehicles, buildings and other objects
* 📷 🗼 Recognizes landmarks and monuments
* 👂 🎵 Recognizes music genres
* 🎥 🤸 Recognizes human actions on video

⚡ Tagging works via Nextcloud's Collaborative Tags
* 👂 listen to your tagged music with the audioplayer app
* 📷 view your tagged photos and videos with the photos app

Model sizes:

* Object recognition: 1GB
* Landmark recognition: 300MB
* Video action recognition: 50MB
* Music genre recognition: 50MB

## Ethical AI Rating
### Rating for Photo object detection: 🟢

Positive:
* the software for training and inference of this model is open source
* the trained model is freely available, and thus can be run on-premises
* the training data is freely available, making it possible to check or correct for bias or optimise the performance and CO2 usage.

### Rating for Photo face recognition: 🟢

Positive:
* the software for training and inference of this model is open source
* the trained model is freely available, and thus can be run on-premises
* the training data is freely available, making it possible to check or correct for bias or optimise the performance and CO2 usage.

### Rating for Video action recognition: 🟢

Positive:
* the software for training and inferencing of this model is open source
* the trained model is freely available, and thus can be ran on-premises
* the training data is freely available, making it possible to check or correct for bias or optimise the performance and CO2 usage.

### Rating Music genre recognition: 🟡

Positive:
* the software for training and inference of this model is open source
* the trained model is freely available, and thus can be run on-premises

Negative:
* the training data is not freely available, limiting the ability of external parties to check and correct for bias or optimise the model’s performance and CO2 usage.

Learn more about the Nextcloud Ethical AI Rating [in our blog](https://nextcloud.com/blog/nextcloud-ethical-ai-rating/).

### Examples

![](https://github.com/marcelklehr/recognize/raw/master/screenshots/imagenet_examples.jpg)
(Screenshot by \_DigitalWriter\_)

### Privacy
This app does not send any sensitive data to cloud providers or similar services. All image processing is done on your nextcloud machine, using Tensorflow.js running in Node.js, which comes bundled with this app.

### Encryption
Note that end-to-end encrypted files are not possible to be processed by recognize, because the server by design cannot read them.

### Categories
This is the [list of recognized things and which categories they are currently mapped to](https://github.com/marcelklehr/recognize/blob/master/src/rules.yml). I'm happy to accept pull requests for this file to fine tune predictions.

## Behind the scenes
Recognize uses

 * a pre-trained [Efficient](https://github.com/google/automl/tree/master/efficientnetv2)[Net v2](https://tfhub.dev/google/collections/efficientnet_v2/1) model for ImageNet object detection.
 * a pre-trained [model trained on the Landmarks v1 dataset](https://tfhub.dev/google/collections/landmarks/1) for landmark recognition.
 * [face-api.js](https://github.com/justadudewhohacks/face-api.js) to extract and compare face features.
 * a [Musicnn](https://arxiv.org/abs/1909.06654) neural network architecture to classify audio files into music genres. Also see [the original musicnn repository](https://github.com/jordipons/musicnn).
 * a pre-trained [MoViNet](https://tfhub.dev/google/collections/movinet) model for video classification

[Learn more about what's going on behind the scenes in this wiki article](https://github.com/nextcloud/recognize/wiki/Behind-the-scenes) [and this forum post](https://help.nextcloud.com/t/ai-and-photos-2-0-in-depth-explanation-of-nextcloud-recognize-and-how-it-works/146767/3).

## Install

### Requirements

- php 8.0 and above
- App "collaborative tags" enabled
- For native speed:
  - Processor: x86 64-bit (with support for AVX instructions)
  - System with glibc (usually the norm on Linux; FreeBSD, Alpine linux and thus also Nextcloud AIO are *not* such systems)
- For sub-native speed (using WASM mode)
  - Processor: x86 64-bit, arm64, armv7l (no AVX needed)
  - System with glibc or musl (incl. Alpine linux and thus also Nextcloud AIO)
- ~4GB of free RAM (if you're cutting it close, make sure you have some swap available)

#### Tmp
This app temporarily stores files to be recognized in /tmp. If you're using docker, you might find
that adding an additional volume for /tmp speeds things up and eases the burden on your disk:

⚠️⚠️⚠️ Make sure that your RAM is big enough to store big files. Otherwise public uploads will fail.

`docker run`: Add `--mount type=tmpfs,destination=/tmp:exec` to command line.

`docker compose`: Add the following to the volume section `docker-compose.yml`:
```yaml
  app:
    image: nextcloud:26
    ...
    volumes:
      - type: tmpfs
        target: /tmp:exec
      ...
    ...
```

### One click

Go to "Apps" in your nextcloud, search for "recognize" and click install.

[Help: If one-click install fails](https://github.com/nextcloud/recognize/wiki/Manual-install)

### Configuration

Any configuration is done in Settings/Recognize of your Nextcloud instance.

#### Ignoring directories

If you want path/to/your/folder/* to be excluded from image recognition, add a file `path/to/your/folder/.noimage`. If you want to exclude it from music genre recognition, add a file `path/to/your/folder/.nomusic`. If you want to exclude it from video recognition, add a file `path/to/your/folder/.novideo`. If you want to exclude it from all recognition, add a file `path/to/your/folder/.nomedia`.

### Manual install

#### Dependencies

- make
- [git](https://git-scm.org/)
- [Node.js v16.x and npm](https://nodejs.org/)
- [php 8.0 or later](https://php.net/)
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

## 🛠️ State of maintenance

While there are some things that could be done to further improve this app, the app is currently maintained with **limited effort**. This means:

* The main functionality works for the majority of the use cases
* We will ensure that the app will continue to work like this for future releases and we will fix bugs that we classify as 'critical'
* We will not invest further development resources ourselves in advancing the app with new features
* We do review and enthusiastically welcome community PR's

We would be more than excited if you would like to collaborate with us. We will merge pull requests for new features and fixes. We also would love to welcome co-maintainers.

If you are a customer of Nextcloud and you have a strong business case for any development of this app, we will consider your wishes for our roadmap. Please contact your account manager to talk about the possibilities.

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

The recognize logo [Smart tag](https://thenounproject.com/term/smart-tag/1193284/) by Xinh Studio from [the Noun Project](https://thenounproject.com) is licensed under a Creative Commons Attribution license.
