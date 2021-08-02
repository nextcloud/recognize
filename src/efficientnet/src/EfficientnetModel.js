"use strict";
var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
Object.defineProperty(exports, "__esModule", { value: true });
const tf = require("@tensorflow/tfjs-node");
const Jimp = require("jimp");
const cliProgress = require("cli-progress");
const EfficientNetLanguageProvider_1 = require("./EfficientNetLanguageProvider");
const EfficientNetResult_1 = require("./EfficientNetResult");
const NUM_OF_CHANNELS = 3;
class EfficientNetModel {
    constructor(modelPath, imageSize, local) {
        this.modelPath = modelPath;
        this.imageSize = imageSize;
        this.languageProvider = new EfficientNetLanguageProvider_1.EfficientNetLanguageProvider(local);
    }
    load() {
        return __awaiter(this, void 0, void 0, function* () {
            yield this.languageProvider.load();
            const bar = new cliProgress.SingleBar({}, cliProgress.Presets.shades_classic);
            bar.start(100, 0);
            const model = yield tf.loadGraphModel(this.modelPath, {
                onProgress: (p) => {
                    bar.update(p * 100);
                },
            });
            bar.stop();
            this.model = model;
        });
    }
    createTensor(image) {
        return __awaiter(this, void 0, void 0, function* () {
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
        });
    }
    cropAndResize(image) {
        return __awaiter(this, void 0, void 0, function* () {
            const width = image.bitmap.width;
            const height = image.bitmap.height;
            const cropPadding = 32;
            const paddedCenterCropSize = ((this.imageSize / (this.imageSize + cropPadding)) *
                Math.min(height, width)) >>
                0;
            const offsetHeight = ((height - paddedCenterCropSize + 1) / 2) >> 0;
            const offsetWidth = (((width - paddedCenterCropSize + 1) / 2) >> 0) + 1;
            yield image.crop(offsetWidth, offsetHeight, paddedCenterCropSize, paddedCenterCropSize);
            yield image.resize(this.imageSize, this.imageSize, Jimp.RESIZE_BICUBIC);
            return image;
        });
    }
    predict(tensor, topK) {
        return __awaiter(this, void 0, void 0, function* () {
            const objectArray = this.model.predict(tensor);
            const values = objectArray.dataSync();
            return new EfficientNetResult_1.default(values, topK, this.languageProvider);
        });
    }
    inference(imgPath, options) {
        return __awaiter(this, void 0, void 0, function* () {
            const { topK = NUM_OF_CHANNELS } = options || {};
            // @ts-ignore
            let image = yield Jimp.read(imgPath);
            image = yield this.cropAndResize(image);
            const tensor = yield this.createTensor(image);
            const prediction = this.predict(tensor, topK);
            tensor.dispose()
            return prediction
        });
    }
}
exports.default = EfficientNetModel;
