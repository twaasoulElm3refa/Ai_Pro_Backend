const FREE_AI_CATALOG_SOURCES = Object.freeze({
    "chat-writing": "general_chat",
});

export const getFreeAiCatalogSource = (toolSlug) =>
    FREE_AI_CATALOG_SOURCES[String(toolSlug || "").trim()] || null;

export default FREE_AI_CATALOG_SOURCES;
