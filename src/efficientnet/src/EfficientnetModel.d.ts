/// <reference types="node" />
import * as tf from "@tensorflow/tfjs-node";
import { io } from "@tensorflow/tfjs-core";
import { EfficientNetLableLanguage, EfficientNetLanguageProvider } from "./EfficientNetLanguageProvider";
import EfficientNetResult from "./EfficientNetResult";
interface EfficientNetModelInferenceOptions {
    topK?: number;
}
export default class EfficientNetModel {
    modelPath: string | io.IOHandler;
    imageSize: number;
    model: tf.GraphModel | undefined;
    languageProvider: EfficientNetLanguageProvider;
    constructor(modelPath: string | io.IOHandler, imageSize: number, local: EfficientNetLableLanguage | undefined);
    load(): Promise<void>;
    private createTensor;
    private cropAndResize;
    private predict;
    inference(imgPath: string | Buffer, options?: EfficientNetModelInferenceOptions): Promise<EfficientNetResult>;
}
export {};
