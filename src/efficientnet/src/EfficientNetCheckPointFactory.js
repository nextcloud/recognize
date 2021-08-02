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
const EfficientnetModel_1 = require("./EfficientnetModel");
const defaultModelsUrl = "https://raw.githubusercontent.com/ntedgi/efficientnet-tensorflowjs-binaries/main/models/B";
const modelFileName = "model.json";
const inputLayerImageSize = [224, 240, 260, 300, 380, 456, 528, 600];
class EfficientNetCheckPointFactory {
    static create(checkPoint, options) {
        return __awaiter(this, void 0, void 0, function* () {
            const { localModelRootDirectory, locale } = options || {};
            let modelPath = `${defaultModelsUrl}${checkPoint}/${modelFileName}`;
            if (localModelRootDirectory) {
                modelPath = `file://${localModelRootDirectory}/${modelFileName}`;
            }
            const model = new EfficientnetModel_1.default(modelPath, inputLayerImageSize[checkPoint], locale);
            yield model.load();
            return model;
        });
    }
}
exports.default = EfficientNetCheckPointFactory;
