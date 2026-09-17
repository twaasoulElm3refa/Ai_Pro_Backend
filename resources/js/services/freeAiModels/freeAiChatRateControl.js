const env = import.meta.env || {};

function positiveMilliseconds(value, fallback) {
    const milliseconds = Number(value);
    return Number.isSafeInteger(milliseconds) && milliseconds > 0 ? milliseconds : fallback;
}

export const MESSAGE_COOLDOWN_MS = positiveMilliseconds(env.VITE_FREE_AI_CHAT_MESSAGE_COOLDOWN_MS, 3000);
export const MEDIA_MESSAGE_COOLDOWN_MS = positiveMilliseconds(env.VITE_FREE_AI_MEDIA_MESSAGE_COOLDOWN_MS, 5000);
export const DUPLICATE_WINDOW_MS = positiveMilliseconds(env.VITE_FREE_AI_CHAT_DUPLICATE_WINDOW_MS, 5000);
export const RATE_LIMIT_FALLBACK_MS = 30000;
export const recentChatRequests = new Map();

export function messageRequestSignature(conversationUuid, userMessage, selectedModelId) {
    return JSON.stringify([String(conversationUuid), String(userMessage).trim(), String(selectedModelId)]);
}

export function wasRecentlySent(requests, signature, now = Date.now()) {
    for (const [key, sentAt] of requests) {
        if (now - sentAt >= DUPLICATE_WINDOW_MS) requests.delete(key);
    }

    const sentAt = requests.get(signature);
    return sentAt !== undefined && now - sentAt < DUPLICATE_WINDOW_MS;
}

export function rememberSentRequest(requests, signature, now = Date.now()) {
    requests.set(signature, now);
}

export function retryAfterMilliseconds(headers, now = Date.now()) {
    const value = typeof headers?.get === "function"
        ? headers.get("retry-after")
        : headers?.["retry-after"] ?? headers?.["Retry-After"];
    if (value == null || String(value).trim() === "") return null;

    const text = String(value).trim();
    if (/^\d+(?:\.\d+)?$/.test(text)) {
        const milliseconds = Number(text) * 1000;
        return Number.isSafeInteger(Math.ceil(milliseconds)) ? Math.ceil(milliseconds) : null;
    }

    const date = Date.parse(text);
    return Number.isFinite(date) && Number.isSafeInteger(date - now) ? Math.max(0, date - now) : null;
}
