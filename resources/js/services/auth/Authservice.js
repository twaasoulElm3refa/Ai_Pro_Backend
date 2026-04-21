import api from "../ApiClient";
import router from "@/router";

// ─────────────────────────────────────────────
//  Helpers
// ─────────────────────────────────────────────

const saveSession = (token, role) => {
    if (!token) throw new Error("No token received");

    const normalizedRole = (role ?? "user").toString().toLowerCase().trim();

    localStorage.setItem("auth_token", token);
    localStorage.setItem("user_role", normalizedRole);

    api.defaults.headers.common["Authorization"] = `Bearer ${token}`;

    return normalizedRole;
};

const clearSession = () => {
    localStorage.removeItem("auth_token");
    localStorage.removeItem("user_role");
    delete api.defaults.headers.common["Authorization"];
};

const redirectByRole = (role) => {
    router.push(role === "admin" ? "/admin" : "/");
};

// ─────────────────────────────────────────────
//  Auth Service
// ─────────────────────────────────────────────

const authService = {
    // ── Login ──────────────────────────────────
    async login(credentials) {
        const { data } = await api.post("/users/login", credentials);

        const token = data.data?.token;
        const role  = data.data?.user?.role;

        const normalizedRole = saveSession(token, role);
        redirectByRole(normalizedRole);
    },

    // ── Register ────────────────────────────────
    async sendOtp(email) {
        await api.post("/users/send-otp", { email });
    },

    async verifyOtp(email, otp) {
        await api.post("/users/verify-otp", { email, otp });
    },

    async register(formData) {
        /**
         * @param {Object} formData - { name, email, password, password_confirmation, phone?, image? }
         */
        const payload = new FormData();

        payload.append("name",                  formData.name);
        payload.append("email",                 formData.email);
        payload.append("password",              formData.password);
        payload.append("password_confirmation", formData.password_confirmation);

        if (formData.phone) payload.append("phone", formData.phone);
        if (formData.image) payload.append("image", formData.image);

        const { data } = await api.post("/users/register", payload, {
            headers: { "Content-Type": "multipart/form-data" },
        });

        const token = data.data?.token;
        const role  = data.data?.user?.role;

        const normalizedRole = saveSession(token, role);
        redirectByRole(normalizedRole);
    },

    // ── Forgot Password ─────────────────────────
    async forgotPassword(email) {
        await api.post("/users/forgot-password", { email });
    },

    // ── Reset Password ──────────────────────────
    async resetPassword(payload) {
        /**
         * @param {Object} payload - { email, otp, password, password_confirmation }
         */
        await api.post("/users/reset-password", payload);
    },

    // ── Google Login ────────────────────────────
    async getGoogleAuthUrl() {
        const { data } = await api.get("/users/google-login");
        return data.url;
    },

    async handleGoogleCallback() {
        /**
         * يُستدعى في onMounted لو في token في الـ URL
         * (بعد redirect من Google Callback)
         */
        const urlParams   = new URLSearchParams(window.location.search);
        const token       = urlParams.get("token");
        const roleFromUrl = urlParams.get("role");
        const errorMsg    = urlParams.get("error");

        if (errorMsg) throw new Error(decodeURIComponent(errorMsg));
        if (!token)   return false; // مفيش Google callback، تجاهل

        // نظّف الـ URL
        const cleanUrl = new URL(window.location.href);
        cleanUrl.search = "";
        window.history.replaceState({}, document.title, cleanUrl.toString());

        // احفظ الـ token مبدئياً عشان نقدر نكلم الـ API
        localStorage.setItem("auth_token", token);
        api.defaults.headers.common["Authorization"] = `Bearer ${token}`;

        // جيب الـ role من الـ profile (أو fallback للـ URL param)
        try {
            const { data } = await api.get("/users/profile");
            const role = data.data?.role ?? data.data?.user?.role ?? roleFromUrl ?? "user";
            const normalizedRole = saveSession(token, role);
            redirectByRole(normalizedRole);
        } catch {
            const normalizedRole = saveSession(token, roleFromUrl ?? "user");
            redirectByRole(normalizedRole);
        }

        return true;
    },

    // ── Logout ──────────────────────────────────
    logout() {
        clearSession();
        router.push("/login");
    },

    // ── Getters ─────────────────────────────────
    getToken()    { return localStorage.getItem("auth_token"); },
    getRole()     { return localStorage.getItem("user_role"); },
    isLoggedIn()  { return !!this.getToken(); },
    isAdmin()     { return this.getRole() === "admin"; },
};

export default authService;
