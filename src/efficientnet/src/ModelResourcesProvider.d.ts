import { EfficientNetCheckPoint } from "./EfficientNetCheckPoint";
export default class ModelResourcesProvider {
    private static downloadUri;
    private static download;
    static get(checkPoint: EfficientNetCheckPoint): Promise<string>;
}
