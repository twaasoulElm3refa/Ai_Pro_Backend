import api from "@/services/ApiClient";
import { getModelCatalogSource } from "@/services/modelCatalog/modelCatalogSources";
import { normalizeCatalogModel } from "@/services/modelCatalog/modelCatalogNormalizer";

const catalogRequests = new Map();

const currentLanguage = () => String(localStorage.getItem("lang") || "en").toLowerCase();

const parseCatalogPayload = (response) => {
    const envelope = response?.data;
    const payload = envelope?.data ?? envelope;

    if (!payload || !Array.isArray(payload.items)) {
        throw new Error("Invalid model catalog response");
    }

    return {
        tool: String(payload.tool || ""),
        items: payload.items,
        pagination: payload.pagination
            && typeof payload.pagination === "object"
            && !Array.isArray(payload.pagination)
            ? payload.pagination
            : null,
    };
};

const resolveCatalogOperation = (source, requestedOperation) => {
    const requested = String(requestedOperation || "").trim();
    const supported = Array.isArray(source.operations) ? source.operations : [];

    if (!supported.length) {
        if (requested) throw new Error("This model catalog does not support operations");
        return null;
    }

    const operation = requested || String(source.defaultOperation || "").trim();
    if (!operation || !supported.includes(operation)) {
        throw new Error(`Unknown model catalog operation: ${operation || requested}`);
    }

    return operation;
};

const requestCatalog = (sourceKey, requestedOperation = null) => {
    const source = getModelCatalogSource(sourceKey);
    const operation = resolveCatalogOperation(source, requestedOperation);
    const cacheKey = `${sourceKey}:${operation || "default"}:${currentLanguage()}`;

    if (!catalogRequests.has(cacheKey)) {
        const request = api.get(source.endpoint, operation ? { params: { operation } } : undefined)
            .then(parseCatalogPayload)
            .catch((error) => {
                catalogRequests.delete(cacheKey);
                throw error;
            });

        catalogRequests.set(cacheKey, request);
    }

    return catalogRequests.get(cacheKey);
};

const modelCatalogService = {
    async getModels(sourceKey, options = {}) {
        const source = getModelCatalogSource(sourceKey);
        const operation = resolveCatalogOperation(source, options.operation);
        const catalog = await requestCatalog(sourceKey, operation);

        const models = catalog.items
            .map((item, index) => ({
                model: normalizeCatalogModel(item, options),
                index,
            }))
            .sort((left, right) =>
                left.model.sortOrder - right.model.sortOrder || left.index - right.index
            )
            .map(({ model }) => model);

        return {
            tool: catalog.tool || sourceKey,
            models,
            pagination: catalog.pagination,
            operation,
        };
    },
};

export default modelCatalogService;
