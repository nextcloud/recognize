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
exports.EfficientNetLanguageProvider = exports.EfficientNetLableLanguage = void 0;
const fs = require("fs");
const path = require("path");
var EfficientNetLableLanguage;
(function (EfficientNetLableLanguage) {
    EfficientNetLableLanguage[EfficientNetLableLanguage["ENGLISH"] = 0] = "ENGLISH";
    EfficientNetLableLanguage[EfficientNetLableLanguage["CHINESE"] = 1] = "CHINESE";
    EfficientNetLableLanguage[EfficientNetLableLanguage["SPANISH"] = 2] = "SPANISH";
})(EfficientNetLableLanguage = exports.EfficientNetLableLanguage || (exports.EfficientNetLableLanguage = {}));
class EfficientNetLanguageProvider {
    constructor(language) {
        this.filePath = "misc/en.json";
        this.labelsMap = null;
        let fileName = null;
        if (language) {
            language;
            switch (+language) {
                case EfficientNetLableLanguage.CHINESE:
                    fileName = "zh";
                    break;
                case EfficientNetLableLanguage.ENGLISH:
                    fileName = "en";
                    break;
                case EfficientNetLableLanguage.SPANISH:
                    fileName = "es";
                    break;
            }
        }
        this.filePath = fileName ? `misc/${fileName}.json` : this.filePath;
    }
    load() {
        return __awaiter(this, void 0, void 0, function* () {
            const jsonFile = path.join(__dirname, this.filePath);
            const translationFile = yield fs.readFileSync(jsonFile, "utf8");
            this.labelsMap = JSON.parse(translationFile);
        });
    }
    get(value) {
        var _a;
        if (!this.labelsMap)
            throw "EfficientNetLanguageProvider error faild loading translation file.";
        return (_a = this.labelsMap) === null || _a === void 0 ? void 0 : _a[value];
    }
}
exports.EfficientNetLanguageProvider = EfficientNetLanguageProvider;
