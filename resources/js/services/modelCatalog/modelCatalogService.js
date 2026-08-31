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
    };
};

const requestCatalog = (sourceKey) => {
    const source = getModelCatalogSource(sourceKey);
    const cacheKey = `${sourceKey}:${currentLanguage()}`;

    if (!catalogRequests.has(cacheKey)) {
        const request = api.get(source.endpoint)
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
        const catalog = await requestCatalog(sourceKey);

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
        };
    },
};

export default modelCatalogService;
