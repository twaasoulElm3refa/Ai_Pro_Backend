import api from "@/services/AdminApiClient";

const userService = {
    async getUsers(page = 1) {
        const response = await api.get(`/admin/users?page=${page}`);
        return response.data;
    },

    async getUser(id) {
        const response = await api.get(`/admin/users/${id}`);
        return response.data;
    },

    async storeUser(payload) {
        const response = await api.post(`/admin/users`, payload);
        return response.data;
    },

    async updateUser(id, formData) {
        const response = await api.post(`/admin/users/${id}`, formData, {
            headers: { "Content-Type": "multipart/form-data" },
        });
        return response.data;
    },

    async deleteUser(id) {
        const response = await api.delete(`/admin/users/${id}`);
        return response.data;
    },
};

export default userService;
