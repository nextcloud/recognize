import EfficientNetModel from "./EfficientnetModel";
import { EfficientNetCheckPoint } from "./EfficientNetCheckPoint";
import { EfficientNetLableLanguage } from "./EfficientNetLanguageProvider";
interface EfficientNetCheckPointFactoryOptions {
    localModelRootDirectory?: string;
    locale?: EfficientNetLableLanguage;
}
export default class EfficientNetCheckPointFactory {
    static create(checkPoint: EfficientNetCheckPoint, options?: EfficientNetCheckPointFactoryOptions): Promise<EfficientNetModel>;
}
export {};
