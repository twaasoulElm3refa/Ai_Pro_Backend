export const unwrapApiData = (payload) => {
    let value = payload;

    while (value && typeof value === "object" && !Array.isArray(value) && "data" in value) {
        value = value.data;
    }

    return value;
};

export const normalizeGeneratedImages = (images) => {
    if (!Array.isArray(images)) return [];

    return images
        .filter((image) => image && typeof image === "object" && (image.id || image.file_id))
        .map((image) => ({
            id: String(image.id || image.file_id),
            filename: String(image.filename || "generated-image.png"),
            content_type: String(image.content_type || "image/png"),
            size_bytes: Number(image.size_bytes || 0),
            preview_url: String(image.preview_url || image.download_url || ""),
            download_url: String(image.download_url || image.preview_url || ""),
            source_file_id: image.source_file_id ? String(image.source_file_id) : null,
        }))
        .filter((image) => image.preview_url && image.download_url);
};

export const normalizeImageGenerationMessage = (message, index = 0) => {
    const metadata = message?.metadata && typeof message.metadata === "object"
        ? message.metadata
        : {};

    const rawImages = message?.images || metadata?.images || message?.files || metadata?.files || [];
    
    // Convert any raw files to Image Generator compatible structures so it works seamlessly on reload
    const normalizedRaw = rawImages.map(f => {
        if (!f || typeof f !== "object") return f;
        return {
            ...f,
            id: String(f.id || f.file_id || Date.now()),
            preview_url: String(f.preview_url || f.download_url || ""),
            download_url: String(f.download_url || f.preview_url || "")
        };
    });

    const images = normalizeGeneratedImages(normalizedRaw);

    let content = message?.content || metadata?.message || "";
    if (typeof content === "object" && content !== null) {
        content = content.message || content.text || "";
    }

    return {
        id: message?.id || null,
        localKey: String(message?.id || `message-${index}-${Date.now()}`),
        role: message?.role === "assistant" ? "assistant" : "user",
        content: String(content),
        is_error: Boolean(message?.is_error || metadata?.type === "error"),
        images,
        state: message?.state || metadata?.state || null,
        metadata: {
            ...metadata,
            images,
        },
    };
};

