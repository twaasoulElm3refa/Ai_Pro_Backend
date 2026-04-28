import api from '../ApiClient';

const walletService = {
    getWallet: async () => {
        const response = await api.get('/users/wallet');
        return response.data;
    },

    getTransactions: async (page = 1) => {
        const response = await api.get(`/users/wallet/transactions?page=${page}`);
        return response.data;
    },

    getTransactionDetails: async (slug) => {
        const response = await api.get(`/users/wallet/transaction/${slug}`);
        return response.data;
    },

    getBalance: async () => {
        const response = await api.get('/users/wallet');
        return response.data;
    }
};

export default walletService;
