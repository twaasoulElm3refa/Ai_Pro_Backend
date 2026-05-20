import axios from "axios";
import toastr from "toastr";
import FingerprintJS from "@fingerprintjs/fingerprintjs";

let fingerprintPromise = null;

const LANG_KEY = "lang";
const FINGERPRINT_KEY = "device_fingerprint";

const SUPPORTED_LANGS = ["ar", "en", "ru", "fr", "zh"];

const getLang = () => {
    const lang = String(localStorage.getItem(LANG_KEY) || "").toLowerCase();

    return SUPPORTED_LANGS.includes(lang) ? lang : "ar";
};

const getDeviceFingerprint = async () => {
    const cachedFingerprint = localStorage.getItem(FINGERPRINT_KEY);

    if (cachedFingerprint) {
        return cachedFingerprint;
    }

    if (!fingerprintPromise) {
        fingerprintPromise = FingerprintJS.load()
            .then((fp) => fp.get())
            .then((result) => result.visitorId);
    }

    const visitorId = await fingerprintPromise;

    if (visitorId) {
        localStorage.setItem(FINGERPRINT_KEY, visitorId);
    }

    return visitorId || "";
};

toastr.options = {
    closeButton: true,
    progressBar: true,
    positionClass: "toast-top-right",
    timeOut: "3000",
};

const api = axios.create({
    baseURL: "https://pro.aiarabic.com/api/v1",
    headers: {
        Accept: "application/json",
        "Accept-Language": getLang(),
        "x-api-key": "L5W9R2Qx1T7p4Z8Vn6Hj3KcDmBaDsEUy",
    },
});

const syncAcceptLanguageHeader = () => {
    api.defaults.headers.common["Accept-Language"] = getLang();
};

syncAcceptLanguageHeader();

window.addEventListener("lang-changed", (event) => {
    const lang = event?.detail?.lang || getLang();

    api.defaults.headers.common["Accept-Language"] = lang;
});

window.addEventListener("storage", (event) => {
    if (event.key === LANG_KEY && event.newValue) {
        api.defaults.headers.common["Accept-Language"] = event.newValue;
    }
});

const isPublicAuthUrl = (url = "") => {
    return (
        String(url).includes("/users/google-login") ||
        String(url).includes("/users/google-callback") ||
        String(url).includes("/users/login") ||
        String(url).includes("/users/register") ||
        String(url).includes("/users/forgot-password") ||
        String(url).includes("/users/reset-password")
    );
};

api.interceptors.request.use(
    async (config) => {
        const token = localStorage.getItem("auth_token");
        const lang = getLang();
        const requestUrl = String(config.url || "");

        config.headers = config.headers || {};
        config.headers["Accept-Language"] = lang;

        if (token && !isPublicAuthUrl(requestUrl)) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        try {
            const fingerprint = await getDeviceFingerprint();

            if (fingerprint) {
                config.headers["X-Device-Fingerprint"] = fingerprint;
            }
        } catch (error) {
            console.warn("Unable to resolve device fingerprint", error);
        }

        return config;
    },
    (error) => Promise.reject(error)
);

api.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        const requestUrl = String(error.config?.url || "");

        if (!error.response) {
            toastr.error("في مشكلة في الاتصال بالسيرفر");

            return Promise.reject(error);
        }

        switch (status) {
            case 400:
                toastr.warning("طلب غير صحيح");
                break;

            case 401:
                if (isPublicAuthUrl(requestUrl)) {
                    return Promise.reject(error);
                }

                toastr.error("يجب تسجيل الدخول");

                localStorage.removeItem("auth_token");

                setTimeout(() => {
                    window.location.href = `/${getLang()}/auth`;
                }, 1500);
                break;

            case 403:
                toastr.error("غير مسموح لك بالوصول");
                break;

            case 404:
                toastr.error("المورد غير موجود");
                break;

            case 422: {
                const errors = error.response.data.errors;

                if (errors) {
                    Object.values(errors).forEach((fieldErrors) => {
                        fieldErrors.forEach((msg) => toastr.error(msg));
                    });
                } else {
                    toastr.error("بيانات غير صالحة");
                }

                break;
            }

            case 429:
                toastr.warning("عدد محاولات كبير، حاول لاحقًا");
                break;

            case 500:
                toastr.error("خطأ في السيرفر");
                break;

            case 502:
            case 503:
            case 504:
                toastr.error("السيرفر غير متاح حاليًا");
                break;

            default:
                toastr.error("حدث خطأ غير متوقع");
        }

        return Promise.reject(error);
    }
);

export const redirectToGoogleLogin = () => {
    localStorage.removeItem("auth_token");

    window.location.href = "https://pro.aiarabic.com/api/v1/users/google-login";
};

export default api;
