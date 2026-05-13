import api from "@/services/AdminApiClient";

const isFileLike = (value) => value instanceof File || value instanceof Blob;

const buildPayload = (data = {}) => {
    const formData = new FormData();

    Object.entries(data).forEach(([key, value]) => {
        if (value === undefined || value === null || value === "") {
            if (["description", "prompt_placeholder", "endpoint"].includes(key)) {
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
const subToolService = {
    async index(mainToolId) {
        const response = await api.get(`/admin/subtools/${mainToolId}`);
        return unwrap(response);
    },

    async show(id) {
        const response = await api.get(`/admin/subtools/show/${id}`);
        return unwrap(response);
    },

    async store(mainToolId, payload) {
        const response = await api.post(`/admin/subtools/${mainToolId}`, buildPayload(payload), {
            headers: {
                "Content-Type": "multipart/form-data",
            },
        });

        return unwrap(response);
    },

    async update(id, payload) {
        const response = await api.post(`/admin/subtools/update/${id}`, buildPayload(payload), {
            headers: {
                "Content-Type": "multipart/form-data",
            },
        });

        return unwrap(response);
    },

    async destroy(id) {
        const response = await api.delete(`/admin/subtools/delete/${id}`);
        return unwrap(response);
    },
};

export default subToolService;
