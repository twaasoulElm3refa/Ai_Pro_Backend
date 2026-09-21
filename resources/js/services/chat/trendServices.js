import api from "@/services/ApiClient";

const unwrap = (response) => response.data?.data ?? response.data;

const trends = Object.freeze({
    28: Object.freeze({
        subtoolId: 28,
        slugs: Object.freeze(["cup-lifting-moment", "cup-lift"]),
        endpoint: "/tasks/trends/cup-lifting-moment",
        selectedModelId: 46,
    }),
    29: Object.freeze({
        subtoolId: 29,
        slugs: Object.freeze(["locker-room"]),
        endpoint: "/tasks/trends/locker-room",
        selectedModelId: 46,
    }),
    30: Object.freeze({
        subtoolId: 30,
        slugs: Object.freeze(["players-tunnel"]),
        endpoint: "/tasks/trends/players-tunnel",
        selectedModelId: 46,
    }),
    31: Object.freeze({
        subtoolId: 31,
        slugs: Object.freeze(["paparazzi"]),
        endpoint: "/tasks/trends/paparazzi",
        selectedModelId: 46,
    }),
    32: Object.freeze({
        subtoolId: 32,
        slugs: Object.freeze(["80s-photo", "the-eighties"]),
        endpoint: "/tasks/trends/80s-photo",
        selectedModelId: 46,
    }),
    33: Object.freeze({
        subtoolId: 33,
        slugs: Object.freeze(["interview-your-past-self", "meet-past-self"]),
        endpoint: "/tasks/trends/meet-past-self",
        selectedModelId: 46,
    }),
});

const resolveTrend = (subtool) => {
    const trend = trends[Number(subtool?.id)];
    const slug = String(subtool?.slug || "").trim().toLowerCase();

    if (!trend || !trend.slugs.includes(slug)) {
        throw new Error("Unsupported Trends subtool.");
    }

    return trend;
};

const trendServices = {
    resolveTrend,

    async generate(subtool, payload, image) {
        const trend = resolveTrend(subtool);
        const formData = new FormData();

        formData.append("payload", JSON.stringify(payload));
        formData.append("file", image);

        const response = await api.post(trend.endpoint, formData, {
            timeout: 390000,
            suppressGlobalErrorToast: true,
        });

        return unwrap(response);
    },

    async fetchProtectedImage(url) {
        const response = await api.get(url, {
            responseType: "blob",
            suppressGlobalErrorToast: true,
        });

        return response.data;
    },
};

export default trendServices;
