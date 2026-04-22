import api from "@/services/AdminApiClient";

const contactService = {
    async getContacts(page = 1) {
        const response = await api.get(`/admin/contact?page=${page}`);
        return response.data;
    },
};

export default contactService;
