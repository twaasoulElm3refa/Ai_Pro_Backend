import api from "@/services/ApiClient";

const unwrap = (response) => response.data;

const homeService = {
    async fetchTools() {
        const response = await api.get("/tools");
        return unwrap(response);
    },
};

export default homeService;
