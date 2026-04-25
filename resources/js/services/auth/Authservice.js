import api from "../ApiClient";
import router from "@/router";
import toastr from "toastr";

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

    // ── Register (مرة واحدة بدون OTP) ──────────
    async register(formData) {
        const payload = new FormData();
        payload.append("name",                  formData.name);
        payload.append("email",                 formData.email);
        payload.append("password",              formData.password);
        payload.append("password_confirmation", formData.password_confirmation);
        if (formData.phone) payload.append("phone", formData.phone);
        if (formData.image) payload.append("image", formData.image);

        await api.post("/users/register", payload, {
            headers: { "Content-Type": "multipart/form-data" },
        });

        // مفيش token هنا، الحساب not verified
        // الـ component هيتكلم بعدها بـ toastr ويروح login
        toastr.success("تم إنشاء الحساب! سجّل دخولك لتفعيل إيميلك");
    },

    // ── Login ───────────────────────────────────
    async login(credentials) {
        const { data } = await api.post("/users/login", credentials);

        // ── الحساب محتاج تحقق → رجّع flag للـ component
        if (data.data?.requires_verification) {
            toastr.info("تم إرسال كود التحقق على إيميلك");
            return {
                requiresVerification: true,
                email: data.data.email,
            };
        }

        // ── Verified → سجل دخول على طول
        const token          = data.data?.token;
        const role           = data.data?.user?.role;
        const normalizedRole = saveSession(token, role);

        toastr.success("تم تسجيل الدخول بنجاح");
        redirectByRole(normalizedRole);

        return { requiresVerification: false };
    },

    // ── Verify OTP (بعد Login لو not verified) ──
    async verifyLoginOtp(email, otp) {
        const { data } = await api.post("/users/verify-otp", { email, otp });

        const token          = data.data?.token;
        const role           = data.data?.user?.role;
        const normalizedRole = saveSession(token, role);

        toastr.success("تم التحقق وتسجيل الدخول بنجاح!");
        redirectByRole(normalizedRole);
    },

    // ── Resend OTP ───────────────────────────────
    async resendOtp(email) {
        await api.post("/users/resend-otp", { email });
        toastr.info("تم إعادة إرسال الكود");
    },

    // ── Forgot Password ──────────────────────────
    async forgotPassword(email) {
        await api.post("/users/forgot-password", { email });
    },

    // ── Reset Password ───────────────────────────
    async resetPassword(payload) {
        await api.post("/users/reset-password", payload);
    },

    // ── Google Login ─────────────────────────────
    async getGoogleAuthUrl() {
        const { data } = await api.get("/users/google-login");
        return data.url;
    },

    async handleGoogleCallback() {
        const urlParams = new URLSearchParams(window.location.search);
        const token     = urlParams.get("token");
        const roleFromUrl = urlParams.get("role");
        const errorMsg  = urlParams.get("error");

        if (errorMsg) throw new Error(decodeURIComponent(errorMsg));
        if (!token)   return false;

        // نظّف الـ URL
        const cleanUrl = new URL(window.location.href);
        cleanUrl.search = "";
        window.history.replaceState({}, document.title, cleanUrl.toString());

        localStorage.setItem("auth_token", token);
        api.defaults.headers.common["Authorization"] = `Bearer ${token}`;

        try {
            const { data }   = await api.get("/users/profile");
            const role       = data.data?.role ?? data.data?.user?.role ?? roleFromUrl ?? "user";
            const normalizedRole = saveSession(token, role);
            redirectByRole(normalizedRole);
        } catch {
            const normalizedRole = saveSession(token, roleFromUrl ?? "user");
            redirectByRole(normalizedRole);
        }

        return true;
    },

    // ── Logout ───────────────────────────────────
    logout() {
        clearSession();
        toastr.info("تم تسجيل الخروج");
        router.push("/login");
    },

    // ── Getters ──────────────────────────────────
    getToken()   { return localStorage.getItem("auth_token"); },
    getRole()    { return localStorage.getItem("user_role"); },
    isLoggedIn() { return !!this.getToken(); },
    isAdmin()    { return this.getRole() === "admin"; },
};

export default authService;
