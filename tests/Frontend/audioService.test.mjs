import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import { runInNewContext } from "node:vm";

const source = await readFile("resources/js/services/audioService.js", "utf8");

async function serviceWithKey(key) {
    const moduleSource = source.replace("import.meta.env.VITE_INTERNAL_API_KEY", JSON.stringify(key));
    return import(`data:text/javascript,${encodeURIComponent(moduleSource)}`);
}

const request = {
    userId: 18,
    modelId: 4,
    selectedModelId: 41,
    conversationUuid: "conversation-uuid",
    message: "Hello from the chat",
};
const file = {
    file_id: "file-id",
    filename: "generated-speech.mp3",
    content_type: "audio/mpeg",
    download_url: "/tasks/generated-files/download/file-id",
};

test("speech generation sends one JSON-string payload field as multipart data", async () => {
    const service = await serviceWithKey("test-internal-key");
    const originalFetch = globalThis.fetch;
    let sent;
    globalThis.fetch = async (url, options) => {
        sent = { url, options };
        return new Response(JSON.stringify({ success: true, type: "result", tool: "general_audio", files: [file] }), {
            headers: { "Content-Type": "application/json" },
        });
    };
    try {
        assert.deepEqual(await service.generateSpeech(request), file);
        assert.equal(sent.url, "https://api.aiarabic.com/tasks/general-audio");
        assert.equal(sent.options.method, "POST");
        assert.equal(sent.options.headers["x-internal-api-key"], "test-internal-key");
        assert.equal(sent.options.headers["Content-Type"], undefined);
        assert.ok(sent.options.body instanceof FormData);
        assert.deepEqual([...sent.options.body.keys()], ["payload"]);
        assert.deepEqual(JSON.parse(sent.options.body.get("payload")), {
            user_id: 18,
            model_id: 4,
            selected_model_id: 41,
            conversation_uuid: "conversation-uuid",
            user_message: "Hello from the chat",
            state: { operation: "text_to_speech", parameters: {} },
            debug: true,
        });
    } finally {
        globalThis.fetch = originalFetch;
    }
});

test("audio download includes the internal key and returns a Blob", async () => {
    const service = await serviceWithKey("test-internal-key");
    const originalFetch = globalThis.fetch;
    let sent;
    globalThis.fetch = async (url, options) => {
        sent = { url, options };
        return new Response(new Blob(["audio bytes"], { type: "audio/mpeg" }));
    };
    try {
        const blob = await service.downloadAudioFile(file);
        assert.equal(sent.url, "https://api.aiarabic.com/tasks/generated-files/download/file-id");
        assert.equal(sent.options.headers["x-internal-api-key"], "test-internal-key");
        assert.ok(blob instanceof Blob);
        assert.equal(blob.type, "audio/mpeg");
    } finally {
        globalThis.fetch = originalFetch;
    }
});

test("speech failures distinguish authorization, missing files, invalid response, and download errors", async () => {
    const service = await serviceWithKey("test-internal-key");
    const originalFetch = globalThis.fetch;
    try {
        globalThis.fetch = async () => new Response("", { status: 401 });
        await assert.rejects(service.generateSpeech(request), { code: "unauthorized" });
        globalThis.fetch = async () => new Response(JSON.stringify({ success: true, type: "result", tool: "general_audio", files: [] }));
        await assert.rejects(service.generateSpeech(request), { code: "missing_files" });
        globalThis.fetch = async () => new Response("not JSON");
        await assert.rejects(service.generateSpeech(request), { code: "invalid_response" });
        globalThis.fetch = async () => new Response("", { status: 502 });
        await assert.rejects(service.downloadAudioFile(file), { code: "download_failed" });
        await assert.rejects(service.downloadAudioFile({ ...file, download_url: "https://other.example/file" }), { code: "invalid_response" });
    } finally {
        globalThis.fetch = originalFetch;
    }
});

test("a missing client key fails before sending a request", async () => {
    const service = await serviceWithKey("");
    const originalFetch = globalThis.fetch;
    globalThis.fetch = () => { throw new Error("fetch must not be called"); };
    try {
        await assert.rejects(service.generateSpeech(request), { code: "missing_key" });
    } finally {
        globalThis.fetch = originalFetch;
    }
});

test("the TTS chat sends its selected IDs and adds a playable assistant attachment or a chat error", async () => {
    const page = await readFile("resources/js/views/home/free-ai-models/FreeAiModelChat.vue", "utf8");
    const script = page.match(/<script setup>([\s\S]*?)<\/script>/)[1].replace(/^import .*;\r?\n/gm, "");
    const calls = [];
    let failDownload = false;
    const state = runInNewContext(`${script}\n({ conversation, selectedModel, loadingConversation, messageDraft, messages, cooldownUntil, canChat, sendMessage })`, {
        ref: (value) => ({ value }),
        computed: (getter) => ({ get value() { return getter(); } }),
        watch() {}, onMounted() {}, onBeforeUnmount() {}, useSeoMeta() {},
        nextTick: async () => {},
        useRoute: () => ({ params: { slug: "audio-voice", uuid: "conversation-uuid" }, meta: { catalogOperation: "text_to_speech", catalogSource: "general_audio" } }),
        useRouter: () => ({ push: async () => {} }),
        useI18n: () => ({ t: (key) => key, locale: { value: "en" } }),
        getFreeAiCatalogSource: (conversation) => conversation?.catalog_source,
        freeAiModelService: {},
        uuidv4: () => "request-uuid",
        messageRequestSignature: () => "signature",
        recentChatRequests: new Map(),
        wasRecentlySent: () => false,
        rememberSentRequest() {},
        generateSpeech: async (input) => { calls.push(input); return file; },
        downloadAudioFile: async () => {
            if (failDownload) throw { code: "download_failed" };
            return new Blob(["audio bytes"], { type: "audio/mpeg" });
        },
        URL: { createObjectURL: () => "blob:generated", revokeObjectURL() {} },
        window: { innerWidth: 1200, setInterval: () => 1, clearInterval() {} },
    });
    state.conversation.value = {
        uuid: "conversation-uuid", model_id: 4, user: { id: 18 },
        catalog_source: "general_audio", catalog_operation: "text_to_speech",
        selected_model: { id: 41 },
    };
    state.selectedModel.value = { id: 41, isAvailable: true };
    state.loadingConversation.value = false;
    assert.equal(state.canChat.value, true);
    state.messageDraft.value = "Speak this";
    await state.sendMessage();
    assert.equal(calls.length, 1);
    assert.equal(calls[0].userId, 18);
    assert.equal(calls[0].modelId, 4);
    assert.equal(calls[0].selectedModelId, 41);
    assert.equal(state.messages.value[1].type, "audio");
    assert.equal(state.messages.value[1].role, "assistant");
    assert.equal(state.messages.value[1].url, "blob:generated");
    assert.equal(state.messages.value[1].filename, "generated-speech.mp3");

    failDownload = true;
    state.cooldownUntil.value = 0;
    state.messageDraft.value = "Another phrase";
    await state.sendMessage();
    assert.equal(state.messages.value.at(-1).type, "error");
    assert.equal(state.messages.value.at(-1).role, "assistant");
    assert.equal(state.messages.value.at(-1).content, "freeAiModels.speechDownloadFailed");
});
