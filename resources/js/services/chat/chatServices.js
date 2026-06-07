import api from "@/services/ApiClient";

const unwrap = (response) => response.data;

const chatServices = {
    async getConversations() {
        const response = await api.get("/conversation");
        return unwrap(response);
    },

    async getConversation(uuid) {
        const response = await api.get(`/conversation/${uuid}`);
        return unwrap(response);
    },

    async createConversation(slug) {
        const response = await api.post(`/conversation/${slug}`);
        return unwrap(response);
    },

    async deleteConversation(uuid) {
        const response = await api.delete(`/conversation/${uuid}`);
        return unwrap(response);
    },

    async sendMessage(payload) {
        console.log("[chatServices.sendMessage] request:", payload);
        const response = await api.post("/message/send", payload);
        return unwrap(response);
    },
};

export default chatServices;
