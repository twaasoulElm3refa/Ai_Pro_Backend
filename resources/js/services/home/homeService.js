import api from "@/services/ApiClient";

const unwrap = (response) => response.data;
const CACHE_TTL = 5 * 60 * 1000;
const memoryCache = new Map();
const pendingRequests = new Map();

const now = () => Date.now();

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
        // Ignore storage quota/private mode failures.
    }
};

const getCached = (key) => {
    const item = memoryCache.get(key);
    if (item && item.expiresAt > now()) return item.value;
    memoryCache.delete(key);
    return fromStorage(key);
};

const setCached = (key, value) => {
    memoryCache.set(key, { value, expiresAt: now() + CACHE_TTL });
    toStorage(key, value);
};

const cached = async (key, requestFn) => {
    const cachedValue = getCached(key);
    if (cachedValue !== null) return cachedValue;

    if (pendingRequests.has(key)) {
        return pendingRequests.get(key);
    }

    const req = requestFn()
        .then((response) => {
            setCached(key, response);
            return response;
        })
        .finally(() => {
            pendingRequests.delete(key);
        });

    pendingRequests.set(key, req);
    return req;
};

const homeService = {
    async fetchTools() {
        return cached("home:tools:index", async () => {
            const response = await api.get("/tools");
            return unwrap(response);
        });
    },

    async showTool(slug) {
        return cached(`home:tools:${slug}`, async () => {
            const response = await api.get(`/tools/${slug}`);
            return unwrap(response);
        });
    },

    async showSubtool(slug) {
        return cached(`home:subtools:${slug}`, async () => {
            const response = await api.get(`/tools/subtool/${slug}`);
            return unwrap(response);
        });
    },

    async sendChatMessage(slug, message, history = []) {
        const response = await api.post(`/subtool/${slug}/chat`, { message, history });
        return unwrap(response);
    },
};

export default homeService;
