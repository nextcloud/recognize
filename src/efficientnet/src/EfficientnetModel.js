const tf = require("@tensorflow/tfjs-node");
const Jimp = require("jimp");
const cliProgress = require("cli-progress");
const EfficientNetLanguageProvider_1 = require("./EfficientNetLanguageProvider");
const EfficientNetResult_1 = require("./EfficientNetResult");
const {IMAGENET_CLASSES} = require('./classes');
const NUM_OF_CHANNELS = 3;
class EfficientNetModel {
    constructor(modelPath, imageSize, local) {
        this.modelPath = modelPath;
        this.imageSize = imageSize;
        this.languageProvider = new EfficientNetLanguageProvider_1.EfficientNetLanguageProvider(local);
    }
    async load() {
        await this.languageProvider.load();
        const bar = new cliProgress.SingleBar({}, cliProgress.Presets.shades_classic);
        bar.start(100, 0);
        const model = await tf.loadGraphModel(this.modelPath, {
            onProgress: (p) => {
                bar.update(p * 100);
            },
        });
        bar.stop();
        this.model = model;
    }
    async createTensor(image) {
        const values = new Float32Array(this.imageSize * this.imageSize * NUM_OF_CHANNELS);
        let i = 0;
        image.scan(0, 0, image.bitmap.width, image.bitmap.height, (x, y) => {
            const pixel = Jimp.intToRGBA(image.getPixelColor(x, y));
            pixel.r = ((pixel.r - 1) / 127.0) >> 0;
            pixel.g = ((pixel.g - 1) / 127.0) >> 0;
            pixel.b = ((pixel.b - 1) / 127.0) >> 0;
            values[i * NUM_OF_CHANNELS + 0] = pixel.r;
            values[i * NUM_OF_CHANNELS + 1] = pixel.g;
            values[i * NUM_OF_CHANNELS + 2] = pixel.b;
            i++;
        });
        const outShape = [
            this.imageSize,
            this.imageSize,
            NUM_OF_CHANNELS,
        ];
        let imageTensor = tf.tensor3d(values, outShape, "float32");
        imageTensor = imageTensor.expandDims(0);
        return imageTensor;
    }
    async cropAndResize(image) {
        const width = image.bitmap.width;
        const height = image.bitmap.height;
        const cropPadding = 32;
        const paddedCenterCropSize = ((this.imageSize / (this.imageSize + cropPadding)) *
            Math.min(height, width)) >>
            0;
        const offsetHeight = ((height - paddedCenterCropSize + 1) / 2) >> 0;
        const offsetWidth = (((width - paddedCenterCropSize + 1) / 2) >> 0) + 1;
        await image.crop(offsetWidth, offsetHeight, paddedCenterCropSize, paddedCenterCropSize);
        await image.resize(this.imageSize, this.imageSize, Jimp.RESIZE_BICUBIC);
        return image;
    }
    async predict(tensor, topK) {
        const logits = this.model.predict(tensor);
        const results = await getTopKClasses(logits, topK)
        logits.dispose()
        return results
    }
    async inference(imgPath, options) {
        const { topK = NUM_OF_CHANNELS } = options || {};
        // @ts-ignore
        let image = await Jimp.read(imgPath);
        image = await this.cropAndResize(image);
        const tensor = await this.createTensor(image);
        const prediction = await this.predict(tensor, topK);
        tensor.dispose()
        return prediction
    }
}


async function getTopKClasses(logits, topK) {
    const softmax = tf.softmax(logits);
    const values = await softmax.data();
    softmax.dispose();

    const valuesAndIndices = [];
    for (let i = 0; i < values.length; i++) {
        valuesAndIndices.push({value: values[i], index: i});
    }
    valuesAndIndices.sort((a, b) => {
        return b.value - a.value;
    });
    const topkValues = new Float32Array(topK);
    const topkIndices = new Int32Array(topK);
    for (let i = 0; i < topK; i++) {
        topkValues[i] = valuesAndIndices[i].value;
        topkIndices[i] = valuesAndIndices[i].index;
    }
    const topClassesAndProbs = [];
    for (let i = 0; i < topkIndices.length; i++) {
        topClassesAndProbs.push({
            className: IMAGENET_CLASSES[topkIndices[i]],
            probability: topkValues[i]
        });
    }
    return topClassesAndProbs;
}

exports.default = EfficientNetModel;
