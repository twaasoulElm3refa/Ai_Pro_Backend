// Laravel's conversation payload is the source of truth for tool-to-catalog mapping.
export const getFreeAiCatalogSource = (conversation) => {
    const source = conversation?.catalog_source;
    return typeof source === "string" ? source.trim() || null : null;
};
