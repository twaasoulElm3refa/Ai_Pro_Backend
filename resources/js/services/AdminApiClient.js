import axios from "axios";
import toastr from "toastr";

toastr.options = {
    closeButton: true,
    progressBar: true,
    positionClass: "toast-top-right",
    timeOut: "3000",
};

let redirectingToLogin = false;

const api = axios.create({
    baseURL: "https://pro.aiarabic.com/api",
    headers: {
        Accept: "application/json",
        'x-api-key': 'L5W9R2Qx1T7p4Z8Vn6Hj3KcDmBaDsEUy'
    },
});

api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem("auth_token");

        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        return config;
    },
    (error) => Promise.reject(error)
);

api.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        if (!error.response) {
            toastr.error("في مشكلة في الاتصال بالسيرفر");
            return Promise.reject(error);
        }

        switch (status) {
            case 400:
                toastr.warning("طلب غير صحيح");
                break;

            case 401:
                toastr.error("يجب تسجيل الدخول");

                localStorage.removeItem("auth_token");
                localStorage.removeItem("user_role");
                localStorage.removeItem("user_data");
                if (!redirectingToLogin) {
                    redirectingToLogin = true;
                    setTimeout(() => {
                        window.location.href = "/login";
                    }, 1500);
                }
                break;

            case 403:
                toastr.error("غير مسموح لك بالوصول");
                break;

            case 404:
                toastr.error("المورد غير موجود");
                break;

            case 422:
                const errors = error.response.data.errors;

                if (errors) {
                    Object.values(errors).forEach((fieldErrors) => {
                        fieldErrors.forEach((msg) => toastr.error(msg));
                    });
                } else {
                    toastr.error("بيانات غير صالحة");
                }
                break;

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

export default api;
