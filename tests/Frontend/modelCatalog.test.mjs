import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import { runInNewContext } from "node:vm";
import axios from "axios";
import { toApiRequestUrl } from "../../resources/js/utils/apiUrl.js";
import { getModelCatalogSource } from "../../resources/js/services/modelCatalog/modelCatalogSources.js";
import { getFreeAiCatalogSource } from "../../resources/js/services/freeAiModels/freeAiCatalogSources.js";
import { readSelectedCatalogModel, saveSelectedCatalogModel } from "../../resources/js/services/modelCatalog/selectedModelStorage.js";

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

test("uses the server catalog mapping without guessing from tool names or slugs", () => {
    assert.equal(getFreeAiCatalogSource({ catalog_source: "general_chat" }), "general_chat");
    assert.equal(getFreeAiCatalogSource({ catalog_source: "general_code" }), "general_code");
    assert.equal(getFreeAiCatalogSource({ model: { slug: "chat-writing" } }), null);
    assert.equal(getFreeAiCatalogSource({ catalog_source: null }), null);
    assert.equal(getFreeAiCatalogSource(null), null);
});

// Execute the real catalog service with only its browser HTTP dependency replaced.
async function catalogServiceWithApi(api) {
    const source = await readFile("resources/js/services/modelCatalog/modelCatalogService.js", "utf8");
    return runInNewContext(
        source.replace(/^import .*;\r?\n/gm, "")
            .replace("export default modelCatalogService;", "modelCatalogService;"),
        { api, getModelCatalogSource, normalizeCatalogModel, localStorage: { getItem: () => "en" } }
    );
}

test("both catalog sources use the Laravel proxy and reject unknown sources", () => {
    for (const source of ["general_chat", "general_code"]) {
        assert.equal(getModelCatalogSource(source).endpoint, `/model-catalogs/${source}`);
        assert.equal(getModelCatalogSource(source).usesServerProxy, true);
    }
    for (const source of ["missing", "constructor", "__proto__"]) {
        assert.throws(() => getModelCatalogSource(source), /Unknown model catalog source/);
    }
});

test("code items use shared normalization and stable sorting without execution metadata", async () => {
    const calls = [];
    const service = await catalogServiceWithApi({
        get: async (endpoint) => {
            calls.push(endpoint);
            return { data: { status: "success", data: {
                tool: "general_code",
                pagination: { next: "unused" },
                items: [
                    { id: 3, name: "Later", sort_order: 20 },
                    {
                        id: 1, provider: "fixture", provider_model_id: "fixture/code",
                        name: " Code fixture ", description: " Code description ",
                        tool_key: "general_code", operation: "code_generation", tier: "free",
                        is_free: "1", capabilities: ["code", null], is_available: "1",
                        is_recommended: "true", sort_order: "5",
                        parameter_schema: {}, recommended_parameters: {}, pricing: {},
                        pricing_updated_at: "unused", provider_updated_at: "unused",
                    },
                    { id: 2, name: "Unavailable", sort_order: 5, is_available: "0", is_recommended: true },
                ],
            } } };
        },
    });
    const result = await service.getModels("general_code");
    assert.deepEqual(calls, ["/model-catalogs/general_code"]);
    assert.equal(result.tool, "general_code");
    assert.deepEqual(Array.from(result.models, (model) => model.id), [1, 2, 3]);
    assert.deepEqual(result.models[0], {
        id: 1, provider: "fixture", providerModelId: "fixture/code", name: "Code fixture",
        description: "Code description", toolKey: "general_code", operation: "code_generation",
        tier: "free", isFree: true, capabilities: ["code"], isAvailable: true,
        isRecommended: true, sortOrder: 5,
    });
    assert.equal(result.models[1].isAvailable, false);
    assert.equal("pagination" in result, false);
    await service.getModels("general_code");
    assert.equal(calls.length, 1);
});

test("a failed code catalog cannot reuse a cached chat catalog and can retry its own source", async () => {
    const calls = [];
    let failCode = true;
    const service = await catalogServiceWithApi({
        get: async (endpoint) => {
            calls.push(endpoint);
            if (endpoint.endsWith("general_code") && failCode) throw new Error("Code upstream unavailable");
            return { data: { data: { tool: endpoint.split("/").pop(), items: [{ id: 1, name: endpoint }] } } };
        },
    });
    await service.getModels("general_chat");
    await assert.rejects(service.getModels("general_code"), /Code upstream unavailable/);
    failCode = false;
    const code = await service.getModels("general_code");
    assert.equal(code.tool, "general_code");
    assert.equal(code.models[0].name, "/model-catalogs/general_code");
    assert.deepEqual(calls, ["/model-catalogs/general_chat", "/model-catalogs/general_code", "/model-catalogs/general_code"]);
});

test("invalid code responses reject instead of yielding chat models", async () => {
    const service = await catalogServiceWithApi({ get: async () => ({ data: { data: { tool: "general_code" } } }) });
    await assert.rejects(service.getModels("general_code"), /Invalid model catalog response/);
});

test("remembered selections are isolated by catalog source and tool", (context) => {
    const entries = new Map();
    const previous = Object.getOwnPropertyDescriptor(globalThis, "sessionStorage");
    Object.defineProperty(globalThis, "sessionStorage", { configurable: true, value: {
        getItem: (key) => entries.get(key) ?? null,
        setItem: (key, value) => entries.set(key, value),
    } });
    context.after(() => {
        if (previous) Object.defineProperty(globalThis, "sessionStorage", previous);
        else delete globalThis.sessionStorage;
    });
    saveSelectedCatalogModel("general_chat", "test-tool", { id: 1, name: "Chat" });
    saveSelectedCatalogModel("general_code", "test-tool", { id: 1, name: "Code" });
    assert.equal(readSelectedCatalogModel("general_chat", "test-tool").name, "Chat");
    assert.equal(readSelectedCatalogModel("general_code", "test-tool").name, "Code");
    assert.equal(readSelectedCatalogModel("general_code", "another-tool"), null);
});

async function chatHarness({ route, api, catalogs, remembered = null }) {
    const page = await readFile("resources/js/views/home/free-ai-models/FreeAiModelChat.vue", "utf8");
    const script = page.match(/<script setup>([\s\S]*?)<\/script>/)[1];
    const watches = [];
    const saved = [];
    const state = runInNewContext(
        script.replace(/^import .*;\r?\n/gm, "") + `\n({
            loadConversation, loadCatalog, selectExecutionModel, defaultCatalogModel,
            conversation, catalogModels, catalogError, catalogLoading, selectedModel, modelSaving
        });`,
        {
            ref: (value) => ({ value }),
            computed: (getter) => ({ get value() { return getter(); } }),
            watch: (source, callback) => watches.push({ source, callback }),
            onMounted() {}, onBeforeUnmount() {}, useSeoMeta() {},
            useRoute: () => route, useRouter: () => ({ push: async () => {} }),
            useI18n: () => ({ t: (key) => key, locale: { value: "en" } }),
            homeService: { getLang: () => "en" },
            freeAiModelService: api, modelCatalogService: catalogs, getFreeAiCatalogSource,
            readSelectedCatalogModel: () => remembered,
            saveSelectedCatalogModel: (...args) => saved.push(args),
        }
    );
    return { ...state, saved, routeChanged: watches.find(({ source }) => Array.isArray(source)).callback };
}

test("the shared page loads the server-selected code source and persists a model through existing APIs", async () => {
    const route = { params: { slug: "test-tool", uuid: "test-conversation" } };
    const models = [
        normalizeCatalogModel({ id: 1, name: "Unavailable", is_available: false, is_recommended: true }),
        normalizeCatalogModel({ id: 2, name: "Recommended", provider_model_id: "fixture/recommended", is_recommended: true }),
        normalizeCatalogModel({ id: 3, name: "Alternative", provider_model_id: "fixture/alternative" }),
    ];
    const conversation = { uuid: "test-conversation", model: { slug: "test-tool" }, catalog_source: "general_code", selected_model: null };
    const calls = [];
    const page = await chatHarness({
        route,
        api: {
            getConversation: async () => ({ data: conversation }),
            updateConversationModel: async (slug, uuid, model) => {
                calls.push([slug, uuid, model.id, model.providerModelId]);
                return { data: { ...conversation, selected_model: { source: "general_code", id: model.id, provider_model_id: model.providerModelId, name: model.name } } };
            },
        },
        catalogs: { getModels: async (source) => {
            assert.equal(source, "general_code");
            return { tool: source, models };
        } },
    });
    await page.loadConversation();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(page.selectedModel.value.id, 2);
    await page.selectExecutionModel(models[0]);
    assert.equal(calls.length, 0);
    await page.selectExecutionModel(models[2]);
    assert.deepEqual(calls, [["test-tool", "test-conversation", 3, "fixture/alternative"]]);
    assert.equal(page.conversation.value.selected_model.source, "general_code");
    assert.equal(page.selectedModel.value.id, 3);
    assert.equal(page.saved[0][0], "general_code");
    assert.equal(page.saved[0][1], "test-tool");
    assert.equal(page.modelSaving.value, false);
});

test("a late chat catalog cannot overwrite the code page's unavailable state", async () => {
    const route = { params: { slug: "chat-writing", uuid: "chat-uuid" } };
    let resolveChat;
    const pendingChat = new Promise((resolve) => { resolveChat = resolve; });
    const calls = [];
    const page = await chatHarness({
        route,
        api: {
            getConversation: async (slug, uuid) => ({ data: {
                uuid, model: { slug }, catalog_source: slug === "chat-writing" ? "general_chat" : "general_code",
            } }),
            getConversations: async () => ({ data: [] }),
        },
        catalogs: { getModels: async (source) => {
            calls.push(source);
            if (source === "general_chat") return pendingChat;
            throw new Error("Code unavailable");
        } },
    });
    await page.loadConversation();
    route.params = { slug: "test-code-tool", uuid: "code-uuid" };
    page.routeChanged(["test-code-tool", "code-uuid"], ["chat-writing", "chat-uuid"]);
    await new Promise((resolve) => setImmediate(resolve));
    resolveChat({ tool: "general_chat", models: [normalizeCatalogModel({ id: 1, name: "Chat only" })] });
    await new Promise((resolve) => setImmediate(resolve));
    assert.deepEqual(calls, ["general_chat", "general_code"]);
    assert.equal(page.catalogError.value, true);
    assert.equal(page.catalogLoading.value, false);
    assert.equal(page.catalogModels.value.length, 0);
    assert.equal(page.selectedModel.value, null);
    assert.equal(page.conversation.value.uuid, "code-uuid");
});

test("missing server mapping reproduces unavailable before any catalog HTTP request", async () => {
    const page = await chatHarness({
        route: { params: { slug: "programming-technology", uuid: "fixture-uuid" } },
        api: { getConversation: async () => ({ data: {
            uuid: "fixture-uuid", model: { slug: "programming-technology" }, catalog_source: null,
        } }) },
        catalogs: { getModels: async () => assert.fail("An unmapped tool never reaches the proxy") },
    });
    await page.loadConversation();
    assert.equal(page.catalogError.value, true);
    assert.equal(page.catalogModels.value.length, 0);
});

test("the real seven-model sample reaches the composer's selector state and survives selection reload", async () => {
    const sample = JSON.parse(await readFile("tests/Fixtures/general-code-catalog.json", "utf8"));
    const requests = [];
    const catalogs = await catalogServiceWithApi({ get: async (endpoint) => {
        // Same envelope returned by ModelCatalogController; Axios adds the outer data.
        requests.push(axios.getUri({ baseURL: "/api/v1", url: toApiRequestUrl(endpoint, "/api/v1") }));
        return { data: { status: "success", message: "", data: sample } };
    } });
    const route = { params: { slug: "programming-technology", uuid: "fixture-uuid" } };
    let persisted = { uuid: route.params.uuid, model: { slug: route.params.slug }, catalog_source: "general_code", selected_model: null };
    const api = {
        getConversation: async () => ({ data: structuredClone(persisted) }),
        updateConversationModel: async (slug, uuid, model) => {
            assert.equal(slug, "programming-technology");
            assert.equal(uuid, "fixture-uuid");
            persisted = { ...persisted, selected_model: {
                source: "general_code", id: model.id, provider_model_id: model.providerModelId, name: model.name,
            } };
            return { data: structuredClone(persisted) };
        },
    };
    const page = await chatHarness({ route, api, catalogs });
    await page.loadConversation();
    await new Promise((resolve) => setImmediate(resolve));
    assert.deepEqual(requests, ["/api/v1/model-catalogs/general_code"]);
    assert.equal(page.catalogError.value, false);
    assert.equal(page.catalogLoading.value, false);
    assert.equal(page.catalogModels.value.length, 7);
    assert.deepEqual(Array.from(page.catalogModels.value, (model) => model.id), [3, 16, 17, 18, 19, 20, 21]);
    assert.equal(page.selectedModel.value.name, "Qwen3 Coder Next");
    assert.equal(page.selectedModel.value.description, "freeAiModels.modelDescriptionFallback");
    for (const model of page.catalogModels.value) {
        for (const [field, type] of Object.entries({
            id: "number", providerModelId: "string", name: "string", description: "string",
            tier: "string", isFree: "boolean", isAvailable: "boolean", isRecommended: "boolean", sortOrder: "number",
        })) assert.equal(typeof model[field], type, `${model.name}: ${field}`);
        assert.equal(model.isAvailable, true);
        assert.equal(model.toolKey, "general_code");
        for (const field of ["parameter_schema", "recommended_parameters", "pricing"]) assert.equal(field in model, false);
    }
    const template = await readFile("resources/js/views/home/free-ai-models/FreeAiModelChat.vue", "utf8");
    assert.match(template, /<FreeAiModelSelector[\s\S]*?:models="catalogModels"/);
    await page.selectExecutionModel(page.catalogModels.value.find((model) => model.id === 17));
    const reloaded = await chatHarness({ route, api, catalogs });
    await reloaded.loadConversation();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(reloaded.catalogError.value, false);
    assert.equal(reloaded.selectedModel.value.id, 17);
    assert.equal(reloaded.selectedModel.value.name, "North Mini Code Free");
});
