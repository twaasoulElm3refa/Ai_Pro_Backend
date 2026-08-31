import api from "@/services/ApiClient";

const unwrap = (response) => response.data;

const freeAiModelService = {
    async getModels() {
        return unwrap(await api.get("/free-ai-models"));
    },

    async getModel(slug) {
        return unwrap(await api.get(`/free-ai-models/${slug}`));
    },

    async getConversations(slug) {
        return unwrap(await api.get(`/free-ai-models/${slug}/conversations`));
    },

    async createConversation(slug, selectedModel = null) {
        const payload = selectedModel
            ? {
                  catalog_model_id: selectedModel.id,
                  provider_model_id: selectedModel.providerModelId || null,
              }
            : {};

        return unwrap(await api.post(`/free-ai-models/${slug}/conversations`, payload));
    },

    async getConversation(slug, uuid) {
        return unwrap(await api.get(`/free-ai-models/${slug}/conversations/${uuid}`));
    },

    async updateConversationModel(slug, uuid, selectedModel) {
        return unwrap(await api.patch(`/free-ai-models/${slug}/conversations/${uuid}/model`, {
            catalog_model_id: selectedModel.id,
            provider_model_id: selectedModel.providerModelId || null,
        }));
    },

    async deleteConversation(slug, uuid) {
        return unwrap(await api.delete(`/free-ai-models/${slug}/conversations/${uuid}`));
    },
};

export default freeAiModelService;
