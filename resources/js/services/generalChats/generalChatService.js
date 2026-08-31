import api from "@/services/ApiClient";

const GENERAL_CHAT_TOOLS_ENDPOINT = "/general_tools/general-chat-tools";

const generalChatService = {
    async getTools() {
        const response = await api.get(GENERAL_CHAT_TOOLS_ENDPOINT);
        const payload = response?.data;

        if (Array.isArray(payload)) return payload;
        return Array.isArray(payload?.data) ? payload.data : [];
    },
};

export default generalChatService;
