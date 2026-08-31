import assert from "node:assert/strict";
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
