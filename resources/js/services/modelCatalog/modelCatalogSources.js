export const MODEL_CATALOG_SOURCES = Object.freeze({
    general_chat: Object.freeze({
        endpoint: "/model-catalogs/general_chat",
        usesServerProxy: true,
    }),
    general_code: Object.freeze({
        endpoint: "/model-catalogs/general_code",
        usesServerProxy: true,
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
