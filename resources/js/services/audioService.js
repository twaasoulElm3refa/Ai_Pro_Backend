const AUDIO_API_ORIGIN = "https://api.aiarabic.com";
const DOWNLOAD_PATH = /^\/tasks\/generated-files\/download\/[A-Za-z0-9-]+$/;

export class AudioServiceError extends Error {
    constructor(code) {
        super(code);
        this.name = "AudioServiceError";
        this.code = code;
    }
}

function apiKey() {
    const key = String("L5W9R2Qx1T7p4Z8Vn6Hj3KcDmBaDsEUy" || "L5W9R2Qx1T7p4Z8Vn6Hj3KcDmBaDsEUy").trim();
    if (!key) throw new AudioServiceError("missing_key");
    return key;
}

function checkResponse(response, failureCode) {
    if (response.status === 401 || response.status === 403) {
        throw new AudioServiceError("unauthorized");
    }
    if (!response.ok) throw new AudioServiceError(failureCode);
}

export async function generateSpeech({ userId, modelId, selectedModelId, conversationUuid, message }) {
    const payload = {
        user_id: userId,
        model_id: modelId,
        selected_model_id: selectedModelId,
        conversation_uuid: conversationUuid,
        user_message: message,
        state: { operation: "text_to_speech", parameters: {} },
        debug: true,
    };
    const formData = new FormData();
    formData.append("payload", JSON.stringify(payload));

    let response;
    try {
        response = await fetch(`${AUDIO_API_ORIGIN}/tasks/general-audio`, {
            method: "POST",
            headers: { "x-internal-api-key": apiKey() },
            body: formData,
        });
    } catch (error) {
        if (error instanceof AudioServiceError) throw error;
        throw new AudioServiceError("generation_failed");
    }
    checkResponse(response, "generation_failed");

    let result;
    try {
        result = await response.json();
    } catch {
        throw new AudioServiceError("invalid_response");
    }
    if (!result || typeof result !== "object" || result.success !== true) {
        throw new AudioServiceError("generation_failed");
    }
    if (result.type !== "result" || result.tool !== "general_audio" || !Array.isArray(result.files)) {
        throw new AudioServiceError("invalid_response");
    }
    if (!result.files.length) throw new AudioServiceError("missing_files");
    const file = result.files[0];
    if (!file || typeof file.download_url !== "string" || !DOWNLOAD_PATH.test(file.download_url)
        || typeof file.filename !== "string" || !file.filename.trim()
        || typeof file.content_type !== "string" || !file.content_type.startsWith("audio/")) {
        throw new AudioServiceError("invalid_response");
    }
    return file;
}

export async function downloadAudioFile(file) {
    if (!file || !DOWNLOAD_PATH.test(file.download_url || "")) {
        throw new AudioServiceError("invalid_response");
    }
    const downloadUrl = new URL(file.download_url, AUDIO_API_ORIGIN).href;
    let response;
    try {
        response = await fetch(downloadUrl, {
            headers: { "x-internal-api-key": apiKey() },
        });
    } catch (error) {
        if (error instanceof AudioServiceError) throw error;
        throw new AudioServiceError("download_failed");
    }
    checkResponse(response, "download_failed");
    let blob;
    try {
        blob = await response.blob();
    } catch {
        throw new AudioServiceError("download_failed");
    }
    if (!blob.size || (blob.type && !blob.type.startsWith("audio/") && blob.type !== "application/octet-stream")) {
        throw new AudioServiceError("download_failed");
    }
    return blob.type.startsWith("audio/") ? blob : new Blob([blob], { type: file.content_type });
}
