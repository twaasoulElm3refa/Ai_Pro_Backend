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
        .filter((image) =>
            image
            && typeof image === "object"
            && (image.id || image.file_id)
            && image.preview_url
            && image.download_url
        )
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

export const normalizeGeneratedFiles = (files) => {
    if (!Array.isArray(files)) return [];

    return files
        .filter((file) =>
            file
            && typeof file === "object"
            && (file.id || file.file_id)
            && file.preview_url
            && file.download_url
        )
        .map((file) => ({
            id: String(file.id || file.file_id),
            file_id: String(file.file_id || file.id),
            filename: String(file.filename || "generated-file"),
            content_type: String(file.content_type || "application/octet-stream").toLowerCase(),
            size_bytes: Number(file.size_bytes || 0),
            preview_url: String(file.preview_url || file.download_url || ""),
            download_url: String(file.download_url || file.preview_url || ""),
            source_file_id: file.source_file_id ? String(file.source_file_id) : null,
        }))
        .filter((file) => file.preview_url && file.download_url);
};

export const normalizeImageGenerationMessage = (message, index = 0) => {
    const metadata = message?.metadata && typeof message.metadata === "object"
        ? message.metadata
        : {};

    const rawImages = message?.images || metadata?.images || [];
    const rawFiles = message?.files || metadata?.files || [];
    const normalizedFiles = normalizeGeneratedFiles(rawFiles);
    const images = normalizeGeneratedImages([
        ...rawImages,
        ...normalizedFiles.filter((file) => file.content_type.startsWith("image/")),
    ]);
    const files = normalizedFiles.filter((file) => !file.content_type.startsWith("image/"));

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
        files,
        state: message?.state || metadata?.state || null,
        metadata: {
            ...metadata,
            images,
            files,
        },
    };
};
