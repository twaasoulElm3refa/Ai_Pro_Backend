import assert from "node:assert/strict";
import test from "node:test";
import {
    normalizeGeneratedImages,
    normalizeImageGenerationMessage,
    unwrapApiData,
} from "../../resources/js/utils/imageGeneratorResults.js";

test("unwrapApiData unwraps Laravel response envelopes", () => {
    assert.deepEqual(
        unwrapApiData({ status: "success", data: { data: { success: true } } }),
        { success: true }
    );
});

test("normalizeGeneratedImages keeps local Laravel URLs only when complete", () => {
    const images = normalizeGeneratedImages([
        {
            id: "image-1",
            filename: "ai-image-1.png",
            content_type: "image/png",
            preview_url: "/api/v1/generated-images/image-1/preview",
            download_url: "/api/v1/generated-images/image-1/download",
        },
        {
            id: "incomplete",
            preview_url: "/preview",
        },
    ]);

    assert.equal(images.length, 1);
    assert.equal(images[0].id, "image-1");
    assert.match(images[0].preview_url, /^\/api\/v1\/generated-images\//);
});

test("normalizeImageGenerationMessage restores images and state from metadata", () => {
    const message = normalizeImageGenerationMessage({
        id: 10,
        role: "assistant",
        content: "Images generated successfully.",
        metadata: {
            state: { size: "1024x1536" },
            images: [{
                id: "image-1",
                filename: "ai-image-1.png",
                preview_url: "/api/v1/generated-images/image-1/preview",
                download_url: "/api/v1/generated-images/image-1/download",
            }],
        },
    });

    assert.equal(message.images.length, 1);
    assert.equal(message.state.size, "1024x1536");
    assert.equal(message.is_error, false);
});
