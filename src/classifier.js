const mobilenet = require('@tensorflow-models/mobilenet');
const tensorflow = require('@tensorflow/tfjs')
require('@tensorflow/tfjs-backend-wasm')
const jpeg = require('jpeg-js')
const fs = require('fs/promises');
const categories = require('./classes')

const classMapper = {}
for (const category in categories) {
    categories[category].forEach(label => {
        classMapper[label] = category
    })
}

async function readImageNative(path) {
    const imageBuffer = await fs.readFile(path)
    const tfimage = tensorflow.node.decodeImage(imageBuffer)
    return tfimage
}

async function readImageJs(path) {
    const imageBuffer = await fs.readFile(path)
    const imageData = jpeg.decode(imageBuffer, {useTArray: true, formatAsRGBA: false})
    return tensorflow.tensor(imageData.data, [imageData.height, imageData.width, 3])
}

async function classify(path) {
    const image = await readImageJs(path);
    const mobilenetModel = await mobilenet.load({version: 2, alpha: 1});
    return await mobilenetModel.classify(image);
}

if (process.argv.length < 3) throw new Error('Incorrect arguments: node classify.js ...<IMAGE_FILES>');

const paths = process.argv.slice(2)

async function main() {
    const results = []
    for (const path of paths) {
        const result = await classify(path)
        results.push(result.map(r => ({probability: r.probability, className: classMapper[r.className]})))
    }
    console.log(JSON.stringify(results))
}

tensorflow.setBackend('wasm')
    .then(() => main())
    .catch(e =>
        tensorflow.setBackend('cpu')
        .then(() => main())
    );
