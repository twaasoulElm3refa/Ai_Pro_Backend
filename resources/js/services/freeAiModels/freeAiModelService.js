import api from "@/services/ApiClient";

const unwrap = (response) => response.data;

const freeAiModelService = {
    async getModels() {
        return unwrap(await api.get("/free-ai-models"));
    },

    async getModel(slug) {
        return unwrap(await api.get(`/free-ai-models/${slug}`));
    },

    async createConversation(slug) {
        return unwrap(await api.post(`/free-ai-models/${slug}/conversations`));
    },

    async getConversation(slug, uuid) {
        return unwrap(await api.get(`/free-ai-models/${slug}/conversations/${uuid}`));
    },
};

export default freeAiModelService;
