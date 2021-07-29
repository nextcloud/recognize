import { EfficientNetLanguageProvider } from "./EfficientNetLanguageProvider";
interface Prediction {
    label: string;
    precision: number;
}
export default class EfficientNetResult {
    result: Prediction[];
    constructor(values: Float32Array, topK: number, languageProvider: EfficientNetLanguageProvider);
}
export {};
