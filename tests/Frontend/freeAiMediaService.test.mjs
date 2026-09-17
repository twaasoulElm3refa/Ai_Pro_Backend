import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const source = await readFile("resources/js/services/freeAiModels/freeAiMediaService.js", "utf8");

async function loadService(api, catalogs) {
    globalThis.__freeAiMediaApi = api;
    globalThis.__freeAiMediaCatalogs = catalogs;
    const moduleSource = source
        .replace('import api from "@/services/ApiClient";', "const api = globalThis.__freeAiMediaApi;")
        .replace('import modelCatalogService from "@/services/modelCatalog/modelCatalogService";', "const modelCatalogService = globalThis.__freeAiMediaCatalogs;");

    return import(`data:text/javascript,${encodeURIComponent(moduleSource)}#${Math.random()}`);
}

test("media models are fetched per operation without reusing the catalog cache", async () => {
    const calls = [];
    const module = await loadService({}, {
        getModels: async (...args) => {
            calls.push(args);
            return { tool: "general_media", models: [] };
        },
    });

    await module.default.getMediaModels("image_generation");
    await module.default.getMediaModels("image_generation");

    assert.equal(calls.length, 2);
    assert.deepEqual(calls[0], ["general_media", { force: true, operation: "image_generation" }]);
});

test("media execution sends one JSON payload field and an optional file as multipart data", async () => {
    const calls = [];
    const module = await loadService({
        post: async (...args) => {
            calls.push(args);
            return { data: { data: { assistant_message: { metadata: { files: [] } } } } };
        },
    }, {});
    const common = {
        slug: "images-video",
        conversationUuid: "conversation-uuid",
        requestId: "request-uuid",
        userId: 18,
        modelId: 4,
        selectedModelId: 41,
        parameters: { quality: "high" },
        userMessage: "Create a skyline",
    };

    await module.default.sendGeneralMedia({ ...common, operation: "image_generation" });
    const [url, body, config] = calls[0];
    assert.equal(url, "/free-ai-models/images-video/conversations/conversation-uuid/messages");
    assert.deepEqual([...body.keys()], ["payload"]);
    assert.deepEqual(JSON.parse(body.get("payload")), {
        user_id: 18,
        model_id: 4,
        selected_model_id: 41,
        conversation_uuid: "conversation-uuid",
        user_message: "Create a skyline",
        state: { operation: "image_generation", parameters: { quality: "high" } },
        debug: true,
        request_id: "request-uuid",
    });
    assert.equal(config.params.catalog_operation, "image_generation");
    assert.equal(config.timeout, 300000);

    const file = new Blob(["image"], { type: "image/png" });
    await module.default.sendGeneralMedia({ ...common, operation: "resize", file });
    assert.deepEqual([...calls[1][1].keys()], ["file", "payload"]);
});

test("file operations reject a missing upload before making an API request", async () => {
    let sent = false;
    const module = await loadService({ post: async () => { sent = true; } }, {});

    await assert.rejects(module.default.sendGeneralMedia({
        slug: "images-video",
        conversationUuid: "conversation-uuid",
        requestId: "request-uuid",
        userId: 18,
        modelId: 4,
        selectedModelId: 41,
        operation: "background_remove",
    }), /file is required/i);
    assert.equal(sent, false);
});

test("generated files are downloaded through the authenticated application endpoint", async () => {
    const calls = [];
    const blob = new Blob(["image"], { type: "image/webp" });
    const module = await loadService({
        get: async (...args) => {
            calls.push(args);
            return { data: blob };
        },
    }, {});

    const result = await module.default.downloadGeneratedFile(
        "/api/v1/free-ai-model-files/generated-file-id/content"
    );

    assert.equal(result, blob);
    assert.equal(calls[0][0], "/api/v1/free-ai-model-files/generated-file-id/content");
    assert.equal(calls[0][1].responseType, "blob");
    await assert.rejects(
        module.default.downloadGeneratedFile("https://api.aiarabic.com/tasks/generated-files/download/id"),
        /invalid generated media url/i
    );
});
