const storageKey = (sourceKey, pageSlug, operation = null) =>
    `free-ai:selected-model:${sourceKey}:${String(operation || "default")}:${pageSlug}`;

export const saveSelectedCatalogModel = (sourceKey, pageSlug, model, operation = null) => {
    if (!model) return;

    try {
        sessionStorage.setItem(storageKey(sourceKey, pageSlug, operation), JSON.stringify(model));
    } catch {
        // Selection remains available in component state when storage is unavailable.
    }
};

export const readSelectedCatalogModel = (sourceKey, pageSlug, operation = null) => {
    try {
        const model = JSON.parse(sessionStorage.getItem(storageKey(sourceKey, pageSlug, operation)) || "null");
        return model && typeof model === "object" ? model : null;
    } catch {
        return null;
    }
};
