export declare enum EfficientNetLableLanguage {
    ENGLISH = 0,
    CHINESE = 1,
    SPANISH = 2
}
export declare class EfficientNetLanguageProvider {
    private filePath;
    private labelsMap;
    constructor(language: EfficientNetLableLanguage | undefined);
    load(): Promise<void>;
    get(value: number): string | undefined;
}
