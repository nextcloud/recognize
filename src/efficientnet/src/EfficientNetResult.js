"use strict";
const tf = require('@tensorflow/tfjs-node')
Object.defineProperty(exports, "__esModule", { value: true });
class EfficientNetResult {
    constructor(values, languageProvider) {
        this.result = values.forEach((value, index) => {
            this.result.push({
                label: languageProvider.get(index),
                precision: value,
            });
        });
    }
}
exports.default = EfficientNetResult;
