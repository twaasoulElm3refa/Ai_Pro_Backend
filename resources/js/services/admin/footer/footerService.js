import api from "@/services/AdminApiClient";

const footerService = {
    async getFooter() {
        const response = await api.get("/admin/footer");
        return response.data;
    },

    async updateFooter(payload) {
        const response = await api.post("/admin/footer", payload, {
            headers: {
                "Content-Type": "multipart/form-data",
            },
        });
        return response.data;
    },
};

export default footerService;
