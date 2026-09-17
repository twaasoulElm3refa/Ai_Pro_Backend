export const MODEL_CATALOG_SOURCES = Object.freeze({
    general_chat: Object.freeze({
        endpoint: "/model-catalogs/general_chat",
        usesServerProxy: true,
    }),
    general_code: Object.freeze({
        endpoint: "/model-catalogs/general_code",
        usesServerProxy: true,
    }),
    general_translation: Object.freeze({
        endpoint: "/model-catalogs/general_translation",
        usesServerProxy: true,
    }),
    general_media: Object.freeze({
        endpoint: "/model-catalogs/general_media",
        usesServerProxy: true,
        defaultOperation: "image_generation",
        operations: Object.freeze([
            "image_generation",
            "background_remove",
            "image_upscale",
            "image_edit",
            "remove_element",
            "restore",
            "outpaint",
            "resize",
            "video_generation",
        ]),
    }),
    general_audio: Object.freeze({
        endpoint: "/model-catalogs/general_audio",
        usesServerProxy: true,
        defaultOperation: "speech_to_text",
        operations: Object.freeze(["speech_to_text", "text_to_speech"]),
    }),
});

export const getModelCatalogSource = (sourceKey) => {
    const source = Object.hasOwn(MODEL_CATALOG_SOURCES, sourceKey)
        ? MODEL_CATALOG_SOURCES[sourceKey]
        : null;

    if (!source) {
        throw new Error(`Unknown model catalog source: ${sourceKey}`);
    }

    return source;
};
