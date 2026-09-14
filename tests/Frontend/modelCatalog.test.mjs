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
        parameter_schema: { temperature: { type: "number", default: 0.5 } },
        recommended_parameters: { temperature: 0.5 },
        pricing: { mode: "fixed" },
        pricing_updated_at: "2026-08-20T13:15:34",
        provider_updated_at: null,
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
        parameterSchema: { temperature: { type: "number", default: 0.5 } },
        recommendedParameters: { temperature: 0.5 },
        pricing: { mode: "fixed" },
        pricingUpdatedAt: "2026-08-20T13:15:34",
        providerUpdatedAt: null,
    });
});

test("keeps the execution-model selector in the chat composer and off the tool landing page", async () => {
    const [chat, show] = await Promise.all([
        readFile("resources/js/views/home/free-ai-models/FreeAiModelChat.vue", "utf8"),
        readFile("resources/js/views/home/free-ai-models/FreeAiModelShow.vue", "utf8"),
    ]);

    assert.match(chat, /<FreeAiModelSelector/);
    assert.match(chat, /class="composer-box"/);
    assert.match(chat, /:disabled="!conversation\?\.uuid \|\| modelSaving \|\| sendingMessage"/);
    assert.doesNotMatch(show, /FreeAiModelSelector|modelCatalogService/);
});

async function selectorHarness(props) {
    const component = await readFile("resources/js/components/free-ai-models/FreeAiModelSelector.vue", "utf8");
    const script = component.match(/<script setup>([\s\S]*?)<\/script>/)[1];
    const state = runInNewContext(
        script.replace(/^import .*;\r?\n/gm, "")
            + "\n({ availableModels, selectedAvailableModel, triggerLabel, handleTrigger, open });",
        {
            computed: (getter) => ({ get value() { return getter(); } }),
            ref: (value) => ({ value }),
            nextTick: async () => {},
            onMounted() {},
            onBeforeUnmount() {},
            defineProps: () => props,
            defineEmits: () => () => {},
            useI18n: () => ({ t: (key) => key }),
        }
    );

    return { component, ...state };
}

test("the shared selector hides unavailable models and never presents a stale unavailable selection", async () => {
    const unavailable = { id: 9, name: "S2.1 Pro Free", isAvailable: false };
    const selector = await selectorHarness({
        models: [
            { id: 56, name: "GPT-4o Mini TTS", isAvailable: true },
            unavailable,
            { id: 8, name: "Flux TTS Free", isAvailable: true },
        ],
        selectedModel: unavailable,
        loading: false,
        error: false,
        disabled: false,
    });

    assert.deepEqual(Array.from(selector.availableModels.value, (model) => model.name), [
        "GPT-4o Mini TTS",
        "Flux TTS Free",
    ]);
    assert.equal(selector.selectedAvailableModel.value, null);
    assert.equal(selector.triggerLabel.value, "freeAiModels.selectModel");
    assert.match(selector.component, /v-for="model in availableModels"/);
    assert.doesNotMatch(selector.component, /freeAiModels\.modelUnavailable/);
});

test("the shared selector shows a non-error empty state when every model is unavailable", async () => {
    const selector = await selectorHarness({
        models: [{ id: 9, name: "Unavailable", isAvailable: false }],
        selectedModel: null,
        loading: false,
        error: false,
        disabled: false,
    });

    assert.equal(selector.availableModels.value.length, 0);
    assert.equal(selector.triggerLabel.value, "freeAiModels.noAvailableModels");
    selector.handleTrigger();
    assert.equal(selector.open.value, false);
});

test("uses a dedicated media route while reusing the shared Free AI conversation shell", async () => {
    const [mediaPage, router, show] = await Promise.all([
        readFile("resources/js/views/home/free-ai-models/FreeAiModelMediaChat.vue", "utf8"),
        readFile("resources/js/router/index.js", "utf8"),
        readFile("resources/js/views/home/free-ai-models/FreeAiModelShow.vue", "utf8"),
    ]);

    assert.match(mediaPage, /<FreeAiModelChat\s*\/>/);
    assert.match(router, /path: "\/:lang\/free-ai\/:slug\/media\/:uuid"/);
    assert.match(router, /name: "free-ai-model\.media-chat"/);
    assert.match(router, /catalogSource: "general_media"/);
    assert.match(show, /source === "general_media"[\s\S]*?"free-ai-model\.media-chat"/);
});

test("uses an authenticated dedicated audio route with the shared Free AI conversation shell", async () => {
    const [audioPage, router, show] = await Promise.all([
        readFile("resources/js/views/home/free-ai-models/FreeAiSpeechToTextChat.vue", "utf8"),
        readFile("resources/js/router/index.js", "utf8"),
        readFile("resources/js/views/home/free-ai-models/FreeAiModelShow.vue", "utf8"),
    ]);

    assert.match(audioPage, /<FreeAiModelChat\s*\/>/);
    assert.match(router, /path: "\/:lang\/free-ai\/:slug\/audio\/:uuid"/);
    assert.match(router, /name: "free-ai-model\.audio-chat"/);
    assert.match(router, /component: FreeAiSpeechToTextChat,[\s\S]*?requiresUserAuth: true,[\s\S]*?catalogSource: "general_audio",[\s\S]*?catalogOperation: "speech_to_text"/);
    assert.match(show, /source === "general_audio"[\s\S]*?"free-ai-model\.audio-chat"/);
});

test("Voice & Audio exposes two localized choices and a dedicated authenticated TTS route", async () => {
    const [ttsPage, router, show] = await Promise.all([
        readFile("resources/js/views/home/free-ai-models/FreeAiTextToSpeechChat.vue", "utf8"),
        readFile("resources/js/router/index.js", "utf8"),
        readFile("resources/js/views/home/free-ai-models/FreeAiModelShow.vue", "utf8"),
    ]);

    assert.match(ttsPage, /<FreeAiModelChat\s*\/>/);
    assert.match(router, /path: "\/:lang\/free-ai\/:slug\/text-to-speech\/:uuid"/);
    assert.match(router, /name: "free-ai-model\.text-to-speech-chat"/);
    assert.match(router, /component: FreeAiTextToSpeechChat,[\s\S]*?requiresUserAuth: true,[\s\S]*?catalogSource: "general_audio",[\s\S]*?catalogOperation: "text_to_speech"/);
    assert.match(show, /v-if="isVoiceAudio" class="audio-operation-picker"/);
    assert.match(show, /@click="startChat\('speech_to_text'\)"/);
    assert.match(show, /@click="startChat\('text_to_speech'\)"/);
    assert.match(show, /isVoiceAudio = computed\(\(\) => model\.value\.slug === "audio-voice"\)/);
    assert.match(show, /createConversation\([\s\S]*?route\.params\.slug,[\s\S]*?null,[\s\S]*?catalogOperation/);
    assert.match(show, /<div v-else class="model-actions">/);
});

async function showHarness({ authenticated = true, responseOperation = "speech_to_text" } = {}) {
    const page = await readFile("resources/js/views/home/free-ai-models/FreeAiModelShow.vue", "utf8");
    const script = page.match(/<script setup>([\s\S]*?)<\/script>/)[1];
    const pushes = [];
    const creates = [];
    const state = runInNewContext(
        script.replace(/^import .*;\r?\n/gm, "") + "\n({ startChat, model, startingOperation, isVoiceAudio });",
        {
            ref: (value) => ({ value }),
            computed: (getter) => ({ get value() { return getter(); } }),
            watch() {}, onMounted() {}, useSeoMeta() {},
            useRoute: () => ({ params: { slug: "audio-voice" } }),
            useRouter: () => ({ push: async (target) => pushes.push(target) }),
            useI18n: () => ({ t: (key) => key, locale: { value: "en" } }),
            homeService: { getLang: () => "en" },
            localStorage: { getItem: () => authenticated ? "token" : null },
            freeAiModelService: {
                createConversation: async (...args) => {
                    creates.push(args);
                    return { data: {
                        uuid: `${responseOperation}-uuid`,
                        catalog_source: "general_audio",
                        catalog_operation: responseOperation,
                    } };
                },
            },
        }
    );
    state.model.value = { slug: "audio-voice", name: "Voice & Audio" };
    return { ...state, pushes, creates };
}

test("Voice & Audio creates no conversation until an operation is selected", async () => {
    const undecided = await showHarness();
    await undecided.startChat();
    assert.equal(undecided.creates.length, 0);

    const speech = await showHarness({ responseOperation: "speech_to_text" });
    await speech.startChat("speech_to_text");
    assert.deepEqual(speech.creates[0], ["audio-voice", null, "speech_to_text"]);
    assert.equal(speech.pushes[0].name, "free-ai-model.audio-chat");

    const voice = await showHarness({ responseOperation: "text_to_speech" });
    await voice.startChat("text_to_speech");
    assert.deepEqual(voice.creates[0], ["audio-voice", null, "text_to_speech"]);
    assert.equal(voice.pushes[0].name, "free-ai-model.text-to-speech-chat");

    const guest = await showHarness({ authenticated: false });
    await guest.startChat("text_to_speech");
    assert.equal(guest.creates.length, 0);
    assert.equal(guest.pushes[0], "/en/auth");
});

test("all supported locales include the Voice & Audio operation labels", async () => {
    const keys = [
        "chooseAudioOperation", "speechToText", "speechToTextDescription", "audioToText",
        "textToSpeech", "textToSpeechDescription", "textToAudio",
    ];

    for (const locale of ["en", "ar", "fr", "ru", "zh"]) {
        const messages = JSON.parse(await readFile(`resources/js/lang/${locale}.json`, "utf8"));
        for (const key of keys) {
            assert.equal(typeof messages.freeAiModels[key], "string", `${locale}.${key}`);
            assert.ok(messages.freeAiModels[key].trim(), `${locale}.${key}`);
        }
    }
});

test("all supported locales include the selector's no-available-models label", async () => {
    for (const locale of ["en", "ar", "fr", "ru", "zh"]) {
        const messages = JSON.parse(await readFile(`resources/js/lang/${locale}.json`, "utf8"));
        assert.equal(typeof messages.freeAiModels.noAvailableModels, "string", `${locale}.noAvailableModels`);
        assert.ok(messages.freeAiModels.noAvailableModels.trim(), `${locale}.noAvailableModels`);
    }
});

test("uses the server catalog mapping without guessing from tool names or slugs", () => {
    assert.equal(getFreeAiCatalogSource({ catalog_source: "general_chat" }), "general_chat");
    assert.equal(getFreeAiCatalogSource({ catalog_source: "general_code" }), "general_code");
    assert.equal(getFreeAiCatalogSource({ catalog_source: "general_translation" }), "general_translation");
    assert.equal(getFreeAiCatalogSource({ catalog_source: "general_media" }), "general_media");
    assert.equal(getFreeAiCatalogSource({ catalog_source: "general_audio" }), "general_audio");
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

test("all catalog sources use the Laravel proxy and reject unknown sources", () => {
    for (const source of ["general_chat", "general_code", "general_translation", "general_media", "general_audio"]) {
        assert.equal(getModelCatalogSource(source).endpoint, `/model-catalogs/${source}`);
        assert.equal(getModelCatalogSource(source).usesServerProxy, true);
    }
    for (const source of ["missing", "constructor", "__proto__"]) {
        assert.throws(() => getModelCatalogSource(source), /Unknown model catalog source/);
    }
});

test("code items use shared normalization, stable sorting, and preserved execution metadata", async () => {
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
        parameterSchema: {}, recommendedParameters: {}, pricing: {},
        pricingUpdatedAt: "unused", providerUpdatedAt: "unused",
    });
    assert.equal(result.models[1].isAvailable, false);
    assert.deepEqual(result.pagination, { next: "unused" });
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

test("the real translation fixture normalizes all models, nullable descriptions, flags, and stable order", async () => {
    const sample = JSON.parse(await readFile("tests/Fixtures/general-translation-catalog.json", "utf8"));
    const calls = [];
    const service = await catalogServiceWithApi({ get: async (endpoint) => {
        calls.push(endpoint);
        return { data: { status: "success", data: sample } };
    } });

    const result = await service.getModels("general_translation", { fallbackDescription: "Fallback description" });
    assert.deepEqual(calls, ["/model-catalogs/general_translation"]);
    assert.equal(result.tool, "general_translation");
    assert.equal(result.models.length, 7);
    assert.deepEqual(Array.from(result.models, (model) => model.sortOrder), [10, 10, 20, 30, 40, 50, 60]);
    assert.deepEqual(Array.from(result.models, (model) => model.id), [4, 22, 23, 24, 25, 26, 27]);

    const router = result.models.find((model) => model.name === "Free Translation Router");
    assert.ok(router);
    assert.equal(router.description, "Fallback description");
    assert.equal(router.isFree, true);
    assert.equal(router.isAvailable, true);
    assert.equal(router.isRecommended, false);
    assert.equal(result.models.find((model) => model.isRecommended)?.name, "Qwen3 Max");
    assert.equal(result.models.some((model) => model.isFree), true);
    assert.equal(result.models.some((model) => !model.isFree), true);
    for (const item of sample.items) {
        const model = result.models.find((candidate) => candidate.id === item.id);
        assert.ok(model);
        assert.equal(model.isFree, item.is_free);
        assert.equal(model.isAvailable, item.is_available);
        assert.equal(model.isRecommended, item.is_recommended);
        assert.equal(model.sortOrder, item.sort_order);
        assert.equal(model.toolKey, "general_translation");
        assert.deepEqual(model.parameterSchema, item.parameter_schema);
        assert.deepEqual(model.recommendedParameters, item.recommended_parameters);
        assert.deepEqual(model.pricing, item.pricing);
        assert.equal(model.pricingUpdatedAt, item.pricing_updated_at);
        assert.equal(model.providerUpdatedAt, item.provider_updated_at);
        for (const field of ["parameter_schema", "recommended_parameters", "pagination"]) {
            assert.equal(field in model, false);
        }
    }
});

test("normalizes the real media catalog without losing image-generation metadata or pagination", async () => {
    const sample = JSON.parse(await readFile("tests/Fixtures/general-media-catalog.json", "utf8"));
    const calls = [];
    const service = await catalogServiceWithApi({ get: async (endpoint) => {
        calls.push(endpoint);
        return { data: { status: "success", data: sample } };
    } });

    const result = await service.getModels("general_media");
    assert.deepEqual(calls, ["/model-catalogs/general_media"]);
    assert.equal(result.tool, "general_media");
    assert.equal(result.models.length, 2);
    assert.deepEqual(result.pagination, sample.pagination);
    assert.deepEqual(Array.from(result.models, (model) => model.id), [7, 28]);

    const recommended = result.models[0];
    assert.equal(recommended.provider, "runware");
    assert.equal(recommended.providerModelId, "runware:400@4");
    assert.equal(recommended.operation, "image_generation");
    assert.deepEqual(recommended.capabilities, ["text_to_image"]);
    assert.deepEqual(recommended.parameterSchema, sample.items[0].parameter_schema);
    assert.deepEqual(recommended.recommendedParameters, sample.items[0].recommended_parameters);
    assert.deepEqual(recommended.pricing, sample.items[0].pricing);
    assert.equal(recommended.isRecommended, true);
});

test("normalizes the real audio catalog with stable sorting and complete speech metadata", async () => {
    const sample = JSON.parse(await readFile("tests/Fixtures/general-audio-catalog.json", "utf8"));
    const calls = [];
    const service = await catalogServiceWithApi({ get: async (endpoint, config) => {
        calls.push([endpoint, config?.params?.operation]);
        return { data: { status: "success", data: sample } };
    } });

    const result = await service.getModels("general_audio", { fallbackDescription: "Fallback description" });
    assert.deepEqual(calls, [["/model-catalogs/general_audio", "speech_to_text"]]);
    assert.equal(result.tool, "general_audio");
    assert.equal(result.operation, "speech_to_text");
    assert.equal(result.models.length, 8);
    assert.deepEqual(result.pagination, sample.pagination);
    assert.deepEqual(Array.from(result.models, (model) => model.id), [5, 34, 6, 35, 36, 37, 38, 39]);
    assert.deepEqual(Array.from(result.models, (model) => model.name), [
        "Whisper Large V3",
        "Nemotron 3.5 ASR Streaming 0.6B",
        "GPT-4o Mini Transcribe",
        "Qwen3 ASR 0.6B",
        "Whisper Large V3 Turbo",
        "Qwen3 ASR 1.7B",
        "GPT Transcribe",
        "Chirp 3",
    ]);

    for (const item of sample.items) {
        const model = result.models.find((candidate) => candidate.id === item.id);
        assert.ok(model);
        assert.equal(model.provider, item.provider);
        assert.equal(model.providerModelId, item.provider_model_id);
        assert.equal(model.operation, "speech_to_text");
        assert.equal(model.isFree, item.is_free);
        assert.equal(model.isAvailable, item.is_available);
        assert.equal(model.isRecommended, item.is_recommended);
        assert.equal(model.sortOrder, item.sort_order);
        assert.deepEqual(model.capabilities, item.capabilities);
        assert.deepEqual(model.parameterSchema, item.parameter_schema);
        assert.deepEqual(model.recommendedParameters, item.recommended_parameters);
        assert.deepEqual(model.pricing, item.pricing);
    }

    assert.equal(result.models[0].description, "Fallback description");
    assert.equal(result.models[0].isRecommended, true);
    assert.equal(result.models[1].isFree, true);
    assert.equal(result.models.find((model) => model.id === 6).parameterSchema.language.nullable, true);
    assert.equal(result.models.find((model) => model.id === 36).pricing.audio_per_hour, 0.04);
    assert.equal(result.models.find((model) => model.id === 35).pricing.audio_per_second, 0.000003);
    assert.equal(result.models.find((model) => model.id === 38).pricing.audio_per_minute, 0.0045);
});

test("loads a separate text-to-speech catalog cache and preserves its real metadata", async () => {
    const speechSample = JSON.parse(await readFile("tests/Fixtures/general-audio-catalog.json", "utf8"));
    const voiceSample = JSON.parse(await readFile("tests/Fixtures/general-audio-text-to-speech-catalog.json", "utf8"));
    const calls = [];
    const service = await catalogServiceWithApi({ get: async (endpoint, config) => {
        const operation = config?.params?.operation;
        calls.push([endpoint, operation]);
        return { data: { status: "success", data: operation === "text_to_speech" ? voiceSample : speechSample } };
    } });

    const speech = await service.getModels("general_audio", { operation: "speech_to_text" });
    const voice = await service.getModels("general_audio", { operation: "text_to_speech" });
    assert.deepEqual(calls, [
        ["/model-catalogs/general_audio", "speech_to_text"],
        ["/model-catalogs/general_audio", "text_to_speech"],
    ]);
    assert.equal(speech.models[0].name, "Whisper Large V3");
    assert.equal(voice.operation, "text_to_speech");
    assert.deepEqual(Array.from(voice.models, (model) => model.id), [56, 8, 9, 40, 41, 42, 43]);

    const recommended = voice.models[0];
    assert.equal(recommended.name, "GPT-4o Mini TTS");
    assert.equal(recommended.providerModelId, "openai/gpt-4o-mini-tts-2025-12-15");
    assert.equal(recommended.operation, "text_to_speech");
    assert.deepEqual(recommended.capabilities, ["text_to_speech", "multilingual", "speed_control"]);
    assert.deepEqual(recommended.parameterSchema, voiceSample.items[0].parameter_schema);
    assert.deepEqual(recommended.recommendedParameters, voiceSample.items[0].recommended_parameters);
    assert.deepEqual(recommended.pricing, voiceSample.items[0].pricing);
    assert.equal(voice.models.find((model) => model.id === 8).isFree, true);
    assert.equal(voice.models.find((model) => model.id === 9).isAvailable, false);
    assert.equal(voice.models.find((model) => model.id === 41).pricing.per_million_characters, 15);

    await service.getModels("general_audio", { operation: "speech_to_text" });
    await service.getModels("general_audio", { operation: "text_to_speech" });
    assert.equal(calls.length, 2);
    await assert.rejects(
        service.getModels("general_audio", { operation: "voice_conversion" }),
        /Unknown model catalog operation/
    );
});

test("a failed translation catalog cannot reuse another source cache", async () => {
    const calls = [];
    const service = await catalogServiceWithApi({ get: async (endpoint) => {
        calls.push(endpoint);
        if (endpoint.endsWith("general_translation")) throw new Error("Translation unavailable");
        return { data: { data: { tool: "general_chat", items: [{ id: 1, name: "Chat only" }] } } };
    } });

    await service.getModels("general_chat");
    await assert.rejects(service.getModels("general_translation"), /Translation unavailable/);
    assert.deepEqual(calls, ["/model-catalogs/general_chat", "/model-catalogs/general_translation"]);
});

test("remembered selections are isolated by catalog source, operation, and tool", (context) => {
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
    saveSelectedCatalogModel("general_audio", "audio-voice", { id: 5, name: "Speech" }, "speech_to_text");
    saveSelectedCatalogModel("general_audio", "audio-voice", { id: 56, name: "Voice" }, "text_to_speech");
    assert.equal(readSelectedCatalogModel("general_chat", "test-tool").name, "Chat");
    assert.equal(readSelectedCatalogModel("general_code", "test-tool").name, "Code");
    assert.equal(readSelectedCatalogModel("general_code", "another-tool"), null);
    assert.equal(readSelectedCatalogModel("general_audio", "audio-voice", "speech_to_text").name, "Speech");
    assert.equal(readSelectedCatalogModel("general_audio", "audio-voice", "text_to_speech").name, "Voice");
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

test("the shared page loads, switches, and restores translation catalog models", async () => {
    const sample = JSON.parse(await readFile("tests/Fixtures/general-translation-catalog.json", "utf8"));
    const catalogs = await catalogServiceWithApi({ get: async (endpoint) => {
        assert.equal(endpoint, "/model-catalogs/general_translation");
        return { data: { status: "success", data: sample } };
    } });
    const route = { params: { slug: "translation", uuid: "translation-uuid" } };
    let persisted = {
        uuid: route.params.uuid,
        model: { slug: route.params.slug },
        catalog_source: "general_translation",
        selected_model: null,
    };
    const api = {
        getConversation: async () => ({ data: structuredClone(persisted) }),
        updateConversationModel: async (slug, uuid, model) => {
            assert.equal(slug, "translation");
            assert.equal(uuid, "translation-uuid");
            persisted = { ...persisted, selected_model: {
                source: "general_translation",
                id: model.id,
                provider_model_id: model.providerModelId,
                name: model.name,
            } };
            return { data: structuredClone(persisted) };
        },
    };

    const page = await chatHarness({ route, api, catalogs });
    await page.loadConversation();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(page.catalogError.value, false);
    assert.equal(page.catalogModels.value.length, 7);
    assert.equal(page.selectedModel.value.name, "Qwen3 Max");

    const switched = page.catalogModels.value.find((model) => model.name === "Free Translation Router");
    await page.selectExecutionModel(switched);
    assert.equal(page.selectedModel.value.id, switched.id);
    assert.equal(page.saved[0][0], "general_translation");
    assert.equal(page.saved[0][1], "translation");

    const reloaded = await chatHarness({ route, api, catalogs });
    await reloaded.loadConversation();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(reloaded.catalogError.value, false);
    assert.equal(reloaded.selectedModel.value.id, switched.id);
    assert.equal(reloaded.selectedModel.value.name, "Free Translation Router");

    const selector = await readFile("resources/js/components/free-ai-models/FreeAiModelSelector.vue", "utf8");
    assert.match(selector, /@click="handleTrigger"/);
    assert.match(selector, /v-for="model in availableModels"/);
    assert.match(selector, /@click="choose\(model\)"/);
});

test("the speech-to-text page loads API models and restores the conversation selection", async () => {
    const sample = JSON.parse(await readFile("tests/Fixtures/general-audio-catalog.json", "utf8"));
    const catalogs = await catalogServiceWithApi({ get: async (endpoint, config) => {
        assert.equal(endpoint, "/model-catalogs/general_audio");
        assert.equal(config.params.operation, "speech_to_text");
        return { data: { status: "success", data: sample } };
    } });
    const route = {
        name: "free-ai-model.audio-chat",
        params: { slug: "speech-catalog-test-tool", uuid: "audio-uuid" },
        meta: { catalogSource: "general_audio", catalogOperation: "speech_to_text" },
    };
    let persisted = {
        uuid: route.params.uuid,
        model: { slug: route.params.slug },
        catalog_source: "general_audio",
        catalog_operation: "speech_to_text",
        selected_model: null,
    };
    const api = {
        getConversation: async (slug, uuid, operation) => {
            assert.equal(operation, "speech_to_text");
            return { data: structuredClone(persisted) };
        },
        updateConversationModel: async (slug, uuid, model, operation) => {
            assert.equal(slug, "speech-catalog-test-tool");
            assert.equal(uuid, "audio-uuid");
            assert.equal(operation, "speech_to_text");
            persisted = { ...persisted, selected_model: {
                source: "general_audio",
                id: model.id,
                provider_model_id: model.providerModelId,
                name: model.name,
            } };
            return { data: structuredClone(persisted) };
        },
    };

    const page = await chatHarness({ route, api, catalogs });
    await page.loadConversation();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(page.catalogError.value, false);
    assert.equal(page.catalogModels.value.length, 8);
    assert.equal(page.selectedModel.value.name, "Whisper Large V3");
    for (const name of [
        "Whisper Large V3", "GPT-4o Mini Transcribe", "Whisper Large V3 Turbo",
        "Nemotron 3.5 ASR Streaming 0.6B", "Qwen3 ASR 0.6B", "Qwen3 ASR 1.7B",
        "GPT Transcribe", "Chirp 3",
    ]) assert.ok(page.catalogModels.value.some((model) => model.name === name), name);

    const qwen = page.catalogModels.value.find((model) => model.id === 35);
    await page.selectExecutionModel(qwen);
    assert.equal(page.saved[0][0], "general_audio");
    assert.equal(page.saved[0][1], "speech-catalog-test-tool");
    assert.equal(page.saved[0][3], "speech_to_text");

    const reloaded = await chatHarness({ route, api, catalogs });
    await reloaded.loadConversation();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(reloaded.selectedModel.value.id, 35);
    assert.equal(reloaded.selectedModel.value.name, "Qwen3 ASR 0.6B");
});

test("the text-to-speech page loads only TTS models and restores its own selection", async () => {
    const sample = JSON.parse(await readFile("tests/Fixtures/general-audio-text-to-speech-catalog.json", "utf8"));
    const catalogs = await catalogServiceWithApi({ get: async (endpoint, config) => {
        assert.equal(endpoint, "/model-catalogs/general_audio");
        assert.equal(config.params.operation, "text_to_speech");
        return { data: { status: "success", data: sample } };
    } });
    const route = {
        name: "free-ai-model.text-to-speech-chat",
        params: { slug: "audio-voice", uuid: "voice-uuid" },
        meta: { catalogSource: "general_audio", catalogOperation: "text_to_speech" },
    };
    let persisted = {
        uuid: route.params.uuid,
        model: { slug: route.params.slug },
        catalog_source: "general_audio",
        catalog_operation: "text_to_speech",
        selected_model: null,
    };
    const api = {
        getConversation: async (slug, uuid, operation) => {
            assert.equal(operation, "text_to_speech");
            return { data: structuredClone(persisted) };
        },
        updateConversationModel: async (slug, uuid, model, operation) => {
            assert.equal(slug, "audio-voice");
            assert.equal(uuid, "voice-uuid");
            assert.equal(operation, "text_to_speech");
            persisted = { ...persisted, selected_model: {
                source: "general_audio",
                id: model.id,
                provider_model_id: model.providerModelId,
                name: model.name,
            } };
            return { data: structuredClone(persisted) };
        },
    };

    const page = await chatHarness({ route, api, catalogs });
    await page.loadConversation();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(page.catalogError.value, false);
    assert.equal(page.catalogModels.value.length, 7);
    assert.equal(page.catalogModels.value.every((model) => model.operation === "text_to_speech"), true);
    assert.equal(page.selectedModel.value.id, 56);

    const flux = page.catalogModels.value.find((model) => model.id === 8);
    const unavailable = page.catalogModels.value.find((model) => model.id === 9);
    await page.selectExecutionModel(unavailable);
    assert.equal(page.selectedModel.value.id, 56);
    await page.selectExecutionModel(flux);
    assert.equal(page.saved[0][3], "text_to_speech");

    const reloaded = await chatHarness({ route, api, catalogs });
    await reloaded.loadConversation();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(reloaded.selectedModel.value.id, 8);
    assert.equal(reloaded.selectedModel.value.name, "Flux TTS Free");
});

test("an unavailable persisted model falls back to the source-scoped session model", async () => {
    const models = [
        normalizeCatalogModel({ id: 5, name: "Unavailable persisted", provider_model_id: "audio/old", is_available: false }),
        normalizeCatalogModel({ id: 34, name: "Remembered audio", provider_model_id: "audio/remembered", is_available: true }),
        normalizeCatalogModel({ id: 6, name: "Recommended audio", provider_model_id: "audio/recommended", is_available: true, is_recommended: true }),
    ];
    const page = await chatHarness({
        route: {
            name: "free-ai-model.audio-chat",
            params: { slug: "speech-catalog-test-tool", uuid: "audio-uuid" },
            meta: { catalogSource: "general_audio", catalogOperation: "speech_to_text" },
        },
        api: { getConversation: async () => ({ data: {
            uuid: "audio-uuid",
            catalog_source: "general_audio",
            catalog_operation: "speech_to_text",
            selected_model: { id: 5, provider_model_id: "audio/old", name: "Unavailable persisted" },
        } }) },
        catalogs: { getModels: async () => ({ tool: "general_audio", models }) },
        remembered: { id: 34, providerModelId: "audio/remembered", name: "Remembered audio" },
    });

    await page.loadConversation();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(page.selectedModel.value.id, 34);
});

test("the shared page loads the server-selected code source and persists a model through existing APIs", async () => {
    const route = { params: { slug: "test-tool", uuid: "test-conversation" } };
    const models = [
        normalizeCatalogModel({ id: 1, name: "Unavailable", tool_key: "general_code", operation: "text_generation", is_available: false, is_recommended: true }),
        normalizeCatalogModel({ id: 4, name: "Wrong tool", tool_key: "general_chat", operation: "text_generation", provider_model_id: "fixture/chat", is_recommended: true }),
        normalizeCatalogModel({ id: 2, name: "Recommended", tool_key: "general_code", operation: "text_generation", provider_model_id: "fixture/recommended", is_recommended: true }),
        normalizeCatalogModel({ id: 3, name: "Alternative", tool_key: "general_code", operation: "text_generation", provider_model_id: "fixture/alternative" }),
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
    await page.selectExecutionModel(models.find((model) => model.id === 3));
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
        assert.deepEqual(model.parameterSchema, sample.items.find((item) => item.id === model.id).parameter_schema);
        assert.deepEqual(model.recommendedParameters, sample.items.find((item) => item.id === model.id).recommended_parameters);
        assert.deepEqual(model.pricing, sample.items.find((item) => item.id === model.id).pricing);
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
