import api from "@/services/AdminApiClient";

const unwrap = (response) => response.data;

const paymentService = {
    async getPayments(page = 1) {
        const response = await api.get(`/admin/payments?page=${page}`);
        return unwrap(response);
    },

    async getPayment(id) {
        const response = await api.get(`/admin/payments/${id}`);
        return unwrap(response);
    },

    async updatePayment(id, payload) {
        const response = await api.post(`/admin/payments/${id}`, payload);
        return unwrap(response);
    },

    async deletePayment(id) {
        const response = await api.delete(`/admin/payments/${id}`);
        return unwrap(response);
    },
};

export default paymentService;
