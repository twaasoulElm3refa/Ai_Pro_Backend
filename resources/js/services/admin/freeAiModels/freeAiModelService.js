import api from "@/services/AdminApiClient";

const isFileLike = (value) => value instanceof File || value instanceof Blob;

const buildPayload = (data = {}) => {
    const formData = new FormData();

    Object.entries(data).forEach(([key, value]) => {
        if (value === undefined || value === null || value === "") {
            if (key === "description" || key === "sort_order") {
                formData.append(key, "");
            }

            return;
        }

        if (isFileLike(value)) {
            formData.append(key, value);
            return;
        }

        if (typeof value === "boolean") {
            formData.append(key, value ? "1" : "0");
            return;
        }

        formData.append(key, value);
    });

    return formData;
};

const unwrap = (response) => response.data;

const freeAiModelService = {
    async getModels() {
        const response = await api.get("/admin/free-ai-models");
        return unwrap(response);
    },

    async getModel(id) {
        const response = await api.get(`/admin/free-ai-models/${id}`);
        return unwrap(response);
    },

    async createModel(data) {
        const response = await api.post("/admin/free-ai-models", buildPayload(data), {
            headers: {
                "Content-Type": "multipart/form-data",
            },
        });

        return unwrap(response);
    },

    async updateModel(id, data) {
        const response = await api.post(`/admin/free-ai-models/${id}`, buildPayload(data), {
            headers: {
                "Content-Type": "multipart/form-data",
            },
        });

        return unwrap(response);
    },

    async deleteModel(id) {
        const response = await api.delete(`/admin/free-ai-models/${id}`);
        return unwrap(response);
    },
};

export default freeAiModelService;
