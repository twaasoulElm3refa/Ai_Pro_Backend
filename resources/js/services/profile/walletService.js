import api from '../ApiClient';

const walletService = {
    // Get wallet data
    getWallet: async () => {
        try {
            const response = await api.get('/users/wallet');
            return response.data;
        } catch (error) {
            throw error;
        }
    },

    // Get wallet transactions (optional - for future use)
    getTransactions: async (page = 1) => {
        try {
            const response = await api.get(`/wallet/transactions?page=${page}`);
            return response.data;
        } catch (error) {
            throw error;
        }
    },

    // Get wallet balance only
    getBalance: async () => {
        try {
            const response = await api.get('/wallet/balance');
            return response.data;
        } catch (error) {
            throw error;
        }
    }
};

export default walletService;
