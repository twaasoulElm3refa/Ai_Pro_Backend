import api from "@/services/ApiClient";

const unwrap = (response) => response.data;

const homeService = {
    async fetchTools() {
        const response = await api.get("/tools");
        return unwrap(response);
    },

    async showTool(slug) {
        const response = await api.get(`/tools/${slug}`);
        return unwrap(response);
    },

    async showSubtool(slug) {
        const response = await api.get(`/tools/subtool/${slug}`);
        return unwrap(response);
    },

    async sendChatMessage(slug, message, history = []) {
        const response = await api.post(`/subtool/${slug}/chat`, { message, history });
        return unwrap(response);
    },
};

export default homeService;
