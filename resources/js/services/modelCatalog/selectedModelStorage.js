const storageKey = (sourceKey, pageSlug) => `free-ai:selected-model:${sourceKey}:${pageSlug}`;

export const saveSelectedCatalogModel = (sourceKey, pageSlug, model) => {
    if (!model) return;

    try {
        sessionStorage.setItem(storageKey(sourceKey, pageSlug), JSON.stringify(model));
    } catch {
        // Selection remains available in component state when storage is unavailable.
    }
};

export const readSelectedCatalogModel = (sourceKey, pageSlug) => {
    try {
        const model = JSON.parse(sessionStorage.getItem(storageKey(sourceKey, pageSlug)) || "null");
        return model && typeof model === "object" ? model : null;
    } catch {
        return null;
    }
};
