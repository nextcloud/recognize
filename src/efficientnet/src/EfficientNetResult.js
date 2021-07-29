"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
class EfficientNetResult {
    constructor(values, topK, languageProvider) {
        this.result = [];
        const arr = Array.from(values);
        const topValues = values
            .sort((a, b) => b - a)
            .slice(0, topK);
        const indexes = topValues.map((e) => arr.indexOf(e));
        const sum = topValues.reduce((a, b) => {
            return a + b;
        }, 0);
        indexes.forEach((value, index) => {
            this.result.push({
                label: languageProvider.get(value),
                precision: (topValues[index] / sum) * 100,
            });
        });
    }
}
exports.default = EfficientNetResult;
