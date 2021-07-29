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
const fs = require("fs");
const nodeFetch = require("node-fetch");
const workspaceDir = "./workspace";
class ModelResourcesProvider {
    static download(url, outputFilePath) {
        return __awaiter(this, void 0, void 0, function* () {
            const response = yield nodeFetch.default(url);
            const buffer = yield response.buffer();
            yield fs.writeFileSync(outputFilePath, buffer);
        });
    }
    static get(checkPoint) {
        return __awaiter(this, void 0, void 0, function* () {
            const modelDir = `${workspaceDir}/B${checkPoint}/model.tgz`;
            if (!fs.existsSync(workspaceDir)) {
                fs.mkdirSync(workspaceDir);
                yield this.download(this.downloadUri(checkPoint), modelDir);
            }
            return "";
        });
    }
}
exports.default = ModelResourcesProvider;
ModelResourcesProvider.downloadUri = (checkPoint) => `https://tfhub.dev/tensorflow/efficientnet/b${checkPoint}/classification/1?tf-hub-format=compressed`;
