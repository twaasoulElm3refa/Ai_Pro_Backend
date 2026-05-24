import api from "@/services/ApiClient";

const unwrap = (response) => response.data;
const CACHE_TTL = 5 * 60 * 1000;
const LANG_KEY = "lang";
const SUPPORTED_LANGS = ["ar", "en", "ru", "fr", "zh"];

const memoryCache = new Map();
const pendingRequests = new Map();

const now = () => Date.now();

const normalizeLang = (value) => {
    const lang = String(value || "").toLowerCase();
    return SUPPORTED_LANGS.includes(lang) ? lang : "ar";
};

const getLang = () => normalizeLang(localStorage.getItem(LANG_KEY) || "en");

const buildKey = (prefix) => `${prefix}:${getLang()}`;

const fromStorage = (key) => {
    try {
        const raw = sessionStorage.getItem(key);
        if (!raw) return null;

        const parsed = JSON.parse(raw);
        if (!parsed?.expiresAt || parsed.expiresAt < now()) {
            sessionStorage.removeItem(key);
            return null;
        }

        return parsed.value;
    } catch {
        return null;
    }
};

const toStorage = (key, value) => {
    try {
        sessionStorage.setItem(
            key,
            JSON.stringify({
                value,
                expiresAt: now() + CACHE_TTL,
            })
        );
    } catch {
        // Ignore storage quota/availability edge cases.
    }
};

const getCached = (key) => {
    const item = memoryCache.get(key);
    if (item && item.expiresAt > now()) return item.value;

    memoryCache.delete(key);
    return fromStorage(key);
};

const setCached = (key, value) => {
    memoryCache.set(key, {
        value,
        expiresAt: now() + CACHE_TTL,
    });

    toStorage(key, value);
};

const clearAllCaches = () => {
    memoryCache.clear();
    pendingRequests.clear();
    sessionStorage.clear();
};

const cached = async (key, requestFn) => {
    const cachedValue = getCached(key);
    if (cachedValue !== null) return cachedValue;

    if (pendingRequests.has(key)) {
        return pendingRequests.get(key);
    }

    const request = requestFn()
        .then((response) => {
            setCached(key, response);
            return response;
        })
        .finally(() => {
            pendingRequests.delete(key);
        });

    pendingRequests.set(key, request);
    return request;
};

const syncApiLang = (lang) => {
    api.defaults.headers.common["Accept-Language"] = normalizeLang(lang);
};

const setLang = (nextLang) => {
    const lang = normalizeLang(nextLang);
    const previousLang = getLang();

    if (previousLang === lang) return;

    localStorage.setItem(LANG_KEY, lang);
    syncApiLang(lang);
    clearAllCaches();

    window.dispatchEvent(
        new CustomEvent("lang-changed", {
            detail: { lang, previousLang },
        })
    );
};

const initializeLangSync = () => {
    const lang = getLang();
    localStorage.setItem(LANG_KEY, lang);
    syncApiLang(lang);

    window.addEventListener("storage", (event) => {
        if (event.key === LANG_KEY && event.newValue) {
            const nextLang = normalizeLang(event.newValue);
            syncApiLang(nextLang);
            clearAllCaches();
            window.dispatchEvent(
                new CustomEvent("lang-changed", {
                    detail: { lang: nextLang },
                })
            );
        }
    });
};

initializeLangSync();

const homeService = {
    getLang,
    setLang,
    clearAllCaches,

    async fetchTools() {
        return cached(buildKey("home:tools:index"), async () => {
            const response = await api.get("/tools");
            return unwrap(response);
        });
    },

    async showTool(slug) {
        return cached(buildKey(`home:tools:${slug}`), async () => {
            const response = await api.get(`/tools/${slug}`);
            console.log(response);
            return unwrap(response);
        });
    },

    async showSubtool(slug) {
        return cached(buildKey(`home:subtools:${slug}`), async () => {
            const response = await api.get(`/tools/subtool/${slug}`);
            return unwrap(response);
        });
    },

    async getConversations() {
        return cached(buildKey("home:conversations:index"), async () => {
            const response = await api.get("/users/conversations");
            return unwrap(response);
        });
    },

    async sendChatMessage(slug, message, history = []) {
        const response = await api.post(`/subtool/${slug}/chat`, {
            message,
            history,
        });

        return unwrap(response);
    },
};

export default homeService;
