import api from "../../AdminApiClient";

const adminAuthService = {
    async login(email, password) {
        const response = await api.post("/admin/login", { email, password });

        const data = response.data.data;
        const token = data.token;
        const user = data.user;
        if (token) {
            localStorage.setItem("auth_token", token);
        }
        if (user?.role) {
            localStorage.setItem("user_role", user.role);
        }
        localStorage.setItem("user_data", JSON.stringify(user));

        return { token, user };
    },
    logout() {
        localStorage.removeItem("auth_token");
        localStorage.removeItem("user_role");
        localStorage.removeItem("user_data");

        window.location.href = "/admin/auth";
    },

    isAuthenticated() {
        return !!localStorage.getItem("auth_token");
    },
};

export default adminAuthService;
