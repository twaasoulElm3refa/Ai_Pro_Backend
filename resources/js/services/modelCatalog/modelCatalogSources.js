export const MODEL_CATALOG_SOURCES = Object.freeze({
    general_chat: Object.freeze({
        endpoint: "/model-catalogs/general_chat",
        usesServerProxy: true,
    }),
});

export const getModelCatalogSource = (sourceKey) => {
    const source = MODEL_CATALOG_SOURCES[sourceKey];

    if (!source) {
        throw new Error(`Unknown model catalog source: ${sourceKey}`);
    }

    return source;
};
