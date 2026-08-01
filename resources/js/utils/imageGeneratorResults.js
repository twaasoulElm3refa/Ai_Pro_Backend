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
    const images = normalizeGeneratedImages(message?.images || metadata.images);

    return {
        id: message?.id || null,
        localKey: String(message?.id || `message-${index}-${Date.now()}`),
        role: message?.role === "assistant" ? "assistant" : "user",
        content: String(message?.content || metadata.message || ""),
        is_error: Boolean(message?.is_error || metadata.type === "error"),
        images,
        state: message?.state || metadata.state || null,
        metadata: {
            ...metadata,
            images,
        },
    };
};
