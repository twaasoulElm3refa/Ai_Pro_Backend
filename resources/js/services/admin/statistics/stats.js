import api from "@/services/AdminApiClient";

const unwrap = (response) => response.data;

const statsService = {
    async getStats() {
        const response = await api.get("/admin/statistics");
        return unwrap(response);
    },
};

export default statsService;
