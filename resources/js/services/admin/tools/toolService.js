import api from "@/services/AdminApiClient";

const isFileLike = (value) => value instanceof File || value instanceof Blob;

const buildToolPayload = (data = {}) => {
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

        formData.append(key, value);
    });

    return formData;
};

const unwrap = (response) => response.data;

const toolService = {
    async getTools() {
        const response = await api.get("/admin/tools");
        return unwrap(response);
    },

    async getTool(id) {
        const response = await api.get(`/admin/tools/${id}`);
        return unwrap(response);
    },

    async createTool(data) {
        const response = await api.post("/admin/tools", buildToolPayload(data), {
            headers: {
                "Content-Type": "multipart/form-data",
            },
        });

        return unwrap(response);
    },

    async updateTool(id, data) {
        const response = await api.post(`/admin/tools/${id}`, buildToolPayload(data), {
            headers: {
                "Content-Type": "multipart/form-data",
            },
        });

        return unwrap(response);
    },

    async deleteTool(id) {
        const response = await api.delete(`/admin/tools/${id}`);
        return unwrap(response);
    },
};

export default toolService;
