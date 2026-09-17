import api from "@/services/ApiClient";

export default {
    getAiModels() {
        return api.get("/tools/ai-models");
    }
};
