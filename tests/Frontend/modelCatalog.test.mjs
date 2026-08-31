import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

import {
    normalizeCatalogBoolean,
    normalizeCatalogModel,
} from "../../resources/js/services/modelCatalog/modelCatalogNormalizer.js";

test("normalizes catalog boolean variants without treating string zero as true", () => {
    assert.equal(normalizeCatalogBoolean(true), true);
    assert.equal(normalizeCatalogBoolean(false), false);
    assert.equal(normalizeCatalogBoolean(1), true);
    assert.equal(normalizeCatalogBoolean(0), false);
    assert.equal(normalizeCatalogBoolean("1"), true);
    assert.equal(normalizeCatalogBoolean("0"), false);
});

test("normalizes snake-case catalog models into the shared frontend contract", () => {
    const model = normalizeCatalogModel({
        id: 7,
        name: "GPT Test",
        description: null,
        tier: "advanced",
        is_free: "0",
        provider: "provider",
        provider_model_id: "provider/gpt-test",
        tool_key: "general_chat",
        operation: "text_generation",
        is_available: "1",
        is_recommended: 1,
        sort_order: "20",
        capabilities: ["chat", "reasoning"],
    }, { fallbackDescription: "Fallback description" });

    assert.deepEqual(model, {
        id: 7,
        name: "GPT Test",
        description: "Fallback description",
        tier: "advanced",
        isFree: false,
        provider: "provider",
        providerModelId: "provider/gpt-test",
        toolKey: "general_chat",
        operation: "text_generation",
        isAvailable: true,
        isRecommended: true,
        sortOrder: 20,
        capabilities: ["chat", "reasoning"],
    });
});

test("keeps the execution-model selector in the chat composer and off the tool landing page", async () => {
    const [chat, show] = await Promise.all([
        readFile("resources/js/views/home/free-ai-models/FreeAiModelChat.vue", "utf8"),
        readFile("resources/js/views/home/free-ai-models/FreeAiModelShow.vue", "utf8"),
    ]);

    assert.match(chat, /<FreeAiModelSelector/);
    assert.match(chat, /class="composer-box"/);
    assert.match(chat, /:disabled="!conversation\?\.uuid \|\| modelSaving"/);
    assert.doesNotMatch(show, /FreeAiModelSelector|modelCatalogService/);
});

test("maps a main Free AI tool to its catalog explicitly", async () => {
    const mapping = await readFile("resources/js/services/freeAiModels/freeAiCatalogSources.js", "utf8");

    assert.match(mapping, /"chat-writing": "general_chat"/);
    assert.doesNotMatch(mapping, /replace\(|split\(|includes\(/);
});
