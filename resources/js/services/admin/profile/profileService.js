import api from "@/services/AdminApiClient";

const unwrap = (response) => response.data;

const profileService = {
    async getProfile() {
        const response = await api.get("/admin/profile");
        return unwrap(response);
    },

    async updateProfile(data) {
        const response = await api.post("/admin/profile", data);
        return unwrap(response);
    },

    async updatePassword(data) {
        const response = await api.post("/admin/password", data);
        return unwrap(response);
    },

    async logout() {
        const response = await api.post("/admin/logout");
        return unwrap(response);
    },
};

export default profileService;
