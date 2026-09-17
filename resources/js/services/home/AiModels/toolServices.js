import api from "@/services/ApiClient";

export default {
    getAiTools() {
        return api.get("/tools/ai-tools");
    },

    getAiModels() {
        return this.getAiTools();
    },
};
