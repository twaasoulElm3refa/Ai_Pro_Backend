import api from "@/services/ApiClient";

export default {
    getTools() {
        return api.get("/tools");
    },
};
