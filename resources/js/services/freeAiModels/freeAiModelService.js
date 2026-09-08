import api from "@/services/ApiClient";

const unwrap = (response) => response.data;
const operationParams = (catalogOperation) => catalogOperation
    ? { params: { catalog_operation: catalogOperation } }
    : undefined;

const freeAiModelService = {
    async getModels() {
        return unwrap(await api.get("/free-ai-models"));
    },

    async getModel(slug) {
        return unwrap(await api.get(`/free-ai-models/${slug}`));
    },

    async getConversations(slug, catalogOperation = null) {
        return unwrap(await api.get(
            `/free-ai-models/${slug}/conversations`,
            operationParams(catalogOperation)
        ));
    },

    async createConversation(slug, selectedModel = null, catalogOperation = null) {
        const payload = selectedModel
            ? {
                  catalog_model_id: selectedModel.id,
                  provider_model_id: selectedModel.providerModelId || null,
              }
            : {};

        if (catalogOperation) payload.catalog_operation = catalogOperation;

        return unwrap(await api.post(`/free-ai-models/${slug}/conversations`, payload));
    },

    async getConversation(slug, uuid, catalogOperation = null) {
        return unwrap(await api.get(
            `/free-ai-models/${slug}/conversations/${uuid}`,
            operationParams(catalogOperation)
        ));
    },

    async updateConversationModel(slug, uuid, selectedModel, catalogOperation = null) {
        return unwrap(await api.patch(`/free-ai-models/${slug}/conversations/${uuid}/model`, {
            catalog_model_id: selectedModel.id,
            provider_model_id: selectedModel.providerModelId || null,
            ...(catalogOperation ? { catalog_operation: catalogOperation } : {}),
        }, operationParams(catalogOperation)));
    },

    async deleteConversation(slug, uuid, catalogOperation = null) {
        return unwrap(await api.delete(
            `/free-ai-models/${slug}/conversations/${uuid}`,
            operationParams(catalogOperation)
        ));
    },
};

export default freeAiModelService;
