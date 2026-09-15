const STORAGE_KEY_PREFIX = "ai-pro:text-to-speech-chat:";
const AUDIO_SUCCESS_MESSAGE = "Speech generated successfully.";
const DOWNLOAD_PATH = /^\/tasks\/generated-files\/download\/[A-Za-z0-9-]+$/;

function storage() {
    try {
        return globalThis.localStorage || null;
    } catch {
        return null;
    }
}

function cleanString(value) {
    return typeof value === "string" ? value.trim() : "";
}

function storageKey(conversationUuid) {
    const uuid = cleanString(conversationUuid);
    return uuid ? `${STORAGE_KEY_PREFIX}${uuid}` : "";
}

export function toStoredAudioChatMessage(message) {
    if (!message || (message.role !== "user" && message.role !== "assistant")) return null;

    if (message.role === "assistant" && message.type === "audio") {
        const filename = cleanString(message.filename).split(/[\\/]/).pop();
        const downloadUrl = cleanString(message.download_url);
        if (!filename || !DOWNLOAD_PATH.test(downloadUrl)) return null;

        return {
            role: "assistant",
            type: "audio",
            content: cleanString(message.content) || AUDIO_SUCCESS_MESSAGE,
            filename,
            download_url: downloadUrl,
            file_id: cleanString(message.file_id),
            content_type: cleanString(message.content_type || message.mimeType) || "audio/mpeg",
        };
    }

    const content = typeof message.content === "string" ? message.content : "";
    if (!content) return null;

    return {
        role: message.role,
        type: message.type === "error" ? "error" : "text",
        content,
    };
}

export function saveAudioChatHistory(conversationUuid, messages) {
    const target = storage();
    const uuid = cleanString(conversationUuid);
    if (!target || !uuid || !Array.isArray(messages)) return false;

    const lightweightMessages = messages.map(toStoredAudioChatMessage).filter(Boolean);
    try {
        target.setItem(storageKey(uuid), JSON.stringify({
            conversation_uuid: uuid,
            messages: lightweightMessages,
        }));
        return true;
    } catch {
        return false;
    }
}

export function readAudioChatHistory(conversationUuid) {
    const target = storage();
    const uuid = cleanString(conversationUuid);
    if (!target || !uuid) return [];

    try {
        const saved = JSON.parse(target.getItem(storageKey(uuid)) || "null");
        if (saved?.conversation_uuid !== uuid || !Array.isArray(saved.messages)) return [];
        return saved.messages.map(toStoredAudioChatMessage).filter(Boolean);
    } catch {
        return [];
    }
}

export function clearAudioChatHistory(conversationUuid) {
    const target = storage();
    const key = storageKey(conversationUuid);
    if (!target || !key) return;

    try {
        const saved = JSON.parse(target.getItem(key) || "null");
        if (saved?.conversation_uuid === cleanString(conversationUuid)) target.removeItem(key);
    } catch {
        target.removeItem(key);
    }
}

export { STORAGE_KEY_PREFIX as AUDIO_CHAT_STORAGE_KEY_PREFIX, storageKey as audioChatStorageKey };
