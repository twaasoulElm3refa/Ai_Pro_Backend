import api from "@/services/AdminApiClient";

const unwrap = (response) => response.data;

const costService = {
    async index(params = {}) {
        const response = await api.get("/admin/cost", { params });
        return unwrap(response);
    },

    async today(params = {}) {
        const response = await api.get("/admin/cost/today", { params });
        return unwrap(response);
    },

    async show(id) {
        const response = await api.get(`/admin/cost/show/${id}`);
        return unwrap(response);
    },

    async destroy(id) {
        const response = await api.delete(`/admin/cost/${id}`);
        return unwrap(response);
    },
};

export default costService;
