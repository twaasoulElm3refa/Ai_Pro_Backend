export const normalizeCatalogBoolean = (value, fallback = false) => {
    if (typeof value === "boolean") return value;
    if (typeof value === "number") return value === 1;

    if (typeof value === "string") {
        const normalized = value.trim().toLowerCase();

        if (["1", "true", "yes", "on"].includes(normalized)) return true;
        if (["0", "false", "no", "off", ""].includes(normalized)) return false;
    }

    return fallback;
};

const normalizeSortOrder = (value) => {
    const order = Number(value);
    return Number.isFinite(order) ? order : Number.MAX_SAFE_INTEGER;
};

const normalizeCatalogObject = (value) => {
    if (!value || typeof value !== "object" || Array.isArray(value)) return null;
    return { ...value };
};

const normalizeNullableString = (value) => {
    if (value === null || value === undefined) return null;
    const normalized = String(value).trim();
    return normalized || null;
};

export const normalizeCatalogModel = (model = {}, options = {}) => {
    const description = typeof model.description === "string" ? model.description.trim() : "";
    const tier = String(model.tier || "standard").trim().toLowerCase();

    return {
        id: model.id ?? null,
        name: String(model.name || model.provider_model_id || "AI Model").trim(),
        description: description || String(options.fallbackDescription || "").trim(),
        tier: ["free", "standard", "advanced"].includes(tier) ? tier : "standard",
        isFree: normalizeCatalogBoolean(model.is_free),
        provider: String(model.provider || "").trim(),
        providerModelId: String(model.provider_model_id || "").trim(),
        toolKey: String(model.tool_key || "").trim(),
        operation: String(model.operation || "").trim(),
        isAvailable: normalizeCatalogBoolean(model.is_available, true),
        isRecommended: normalizeCatalogBoolean(model.is_recommended),
        sortOrder: normalizeSortOrder(model.sort_order),
        capabilities: Array.isArray(model.capabilities)
            ? model.capabilities.filter((capability) => capability !== null && capability !== undefined)
            : [],
        parameterSchema: normalizeCatalogObject(model.parameter_schema),
        recommendedParameters: normalizeCatalogObject(model.recommended_parameters),
        pricing: normalizeCatalogObject(model.pricing),
        pricingUpdatedAt: normalizeNullableString(model.pricing_updated_at),
        providerUpdatedAt: normalizeNullableString(model.provider_updated_at),
    };
};
