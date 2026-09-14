import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import { runInNewContext } from "node:vm";
import test from "node:test";
import MarkdownIt from "markdown-it";
import {
    MESSAGE_COOLDOWN_MS,
    messageRequestSignature,
    recentChatRequests,
    rememberSentRequest,
    retryAfterMilliseconds,
    wasRecentlySent,
} from "../../resources/js/services/freeAiModels/freeAiChatRateControl.js";
import { PROGRAMMING_LANGUAGES, PROGRAMMING_FRAMEWORKS, programmingLanguageValue } from "../../resources/js/services/freeAiModels/freeAiCodeOptions.js";

test("duplicate signature is scoped to conversation, text and selected model", () => {
    const requests = new Map();
    const signature = messageRequestSignature("conversation-a", " hello ", 1);
    rememberSentRequest(requests, signature, 1000);

    assert.equal(wasRecentlySent(requests, messageRequestSignature("conversation-a", "hello", 1), 1001), true);
    assert.equal(wasRecentlySent(requests, messageRequestSignature("conversation-b", "hello", 1), 1001), false);
    assert.equal(wasRecentlySent(requests, messageRequestSignature("conversation-a", "hello", 2), 1001), false);
    assert.equal(wasRecentlySent(requests, messageRequestSignature("conversation-a", "different", 1), 1001), false);
    assert.equal(wasRecentlySent(requests, signature, 6000), false);
    assert.equal(requests.size, 0);
});

test("Retry-After accepts seconds and HTTP dates", () => {
    const now = Date.parse("2026-09-14T12:00:00Z");
    assert.equal(retryAfterMilliseconds({ "retry-after": "7" }, now), 7000);
    assert.equal(retryAfterMilliseconds({ get: () => "1.5" }, now), 1500);
    assert.equal(retryAfterMilliseconds({ "Retry-After": "Mon, 14 Sep 2026 12:00:09 GMT" }, now), 9000);
    assert.equal(retryAfterMilliseconds({ "Retry-After": "invalid" }, now), null);
    assert.equal(retryAfterMilliseconds({}, now), null);
});

async function chatHarness(
    sendRequest,
    getMessages = async () => ({ data: { items: [], next_cursor: null } }),
    { source = "general_chat", sendCodeRequest = undefined } = {}
) {
    recentChatRequests.clear();
    const page = await readFile("resources/js/views/home/free-ai-models/FreeAiModelChat.vue", "utf8");
    const script = page.match(/<script setup>([\s\S]*?)<\/script>/)[1];
    const cleanup = [];
    const route = { params: { slug: source === "general_code" ? "programming-technology" : "chat-writing", uuid: "conversation-a" }, meta: {}, name: "free-ai-model.chat" };
    const state = runInNewContext(
        script.replace(/^import .*;\r?\n/gm, "") + "\n({ sendMessage, conversation, selectedModel, loadingConversation, messageDraft, messages, sendingMessage, canSend, cooldownSeconds, cooldownUntil, cooldownClock, sendError, selectedCodeLanguage, selectedCodeFramework, programmingLanguage, isGeneralCode, renderCodeMarkdown });",
        {
            ref: (value) => ({ value }),
            computed: (getter) => ({ get value() { return getter(); } }),
            watch() {}, onMounted() {}, onBeforeUnmount: (callback) => cleanup.push(callback),
            useSeoMeta() {}, nextTick: () => Promise.resolve(),
            useRoute: () => route, useRouter: () => ({ push: async () => {} }),
            useI18n: () => ({ t: (key) => key, locale: { value: "en" } }),
            getFreeAiCatalogSource: () => source,
            freeAiModelService: {
                getWallet: async () => ({ data: { balance: 100, payback_balance: 0 } }),
                getMessages,
                sendMessage: sendRequest,
                sendGeneralCodeMessage: sendCodeRequest,
            },
            messageRequestSignature, rememberSentRequest, retryAfterMilliseconds, wasRecentlySent,
            recentChatRequests,
            PROGRAMMING_LANGUAGES, PROGRAMMING_FRAMEWORKS, programmingLanguageValue,
            MarkdownIt, DOMPurify: { sanitize: (html) => html },
            MESSAGE_COOLDOWN_MS, RATE_LIMIT_FALLBACK_MS: 30000,
            uuidv4: () => "request-id",
            TextEncoder, CustomEvent: class { constructor(name, options) { this.type = name; this.detail = options.detail; } },
            window: { innerWidth: 1200, setInterval, clearInterval, dispatchEvent() {}, removeEventListener() {} },
            document: { body: { style: {} } },
            localStorage: { setItem() {} },
        }
    );
    state.conversation.value = { uuid: "conversation-a", selected_model: { id: 1 }, model: { name: "Chat" } };
    state.selectedModel.value = { id: 1, isAvailable: true };
    state.loadingConversation.value = false;
    return { state, cleanup: () => cleanup.forEach((callback) => callback()) };
}

test("chat allows only one in-flight send and applies cooldown after success", async () => {
    let resolveRequest;
    let sends = 0;
    const { state, cleanup } = await chatHarness(() => {
        sends++;
        return new Promise((resolve) => { resolveRequest = resolve; });
    });

    try {
        state.messageDraft.value = "hello";
        const first = state.sendMessage();
        await state.sendMessage();
        await new Promise((resolve) => setImmediate(resolve));
        assert.equal(sends, 1);
        assert.equal(state.sendingMessage.value, true);
        assert.equal(state.canSend.value, false);

        resolveRequest({ data: {
            user_message: { id: 1, role: "user", content: "hello" },
            assistant_message: { id: 2, role: "assistant", content: "reply" },
            wallet: { balance: 98, payback_balance: 0 },
        } });
        await first;
        assert.equal(state.sendingMessage.value, false);
        assert.equal(state.messages.value.at(-1).content, "reply");
        assert.ok(state.cooldownSeconds.value > 0);
        state.messageDraft.value = "another message";
        await state.sendMessage();
        assert.equal(sends, 1);

        state.cooldownUntil.value = 0;
        state.cooldownClock.value = Date.now();
        state.messageDraft.value = "hello";
        await state.sendMessage();
        assert.equal(sends, 1);
        assert.equal(state.sendError.value, "freeAiModels.duplicateRequest");
    } finally {
        cleanup();
    }
});

test("chat waits for Retry-After after a 429 and clears loading", async () => {
    let sends = 0;
    const { state, cleanup } = await chatHarness(async () => {
        sends++;
        throw { response: { status: 429, headers: { "retry-after": "4" } } };
    }, () => new Promise(() => {}));

    try {
        state.messageDraft.value = "hello";
        await state.sendMessage();
        assert.equal(sends, 1);
        assert.equal(state.sendingMessage.value, false);
        assert.equal(state.sendError.value, "freeAiModels.rateLimited");
        assert.ok(state.cooldownSeconds.value >= 3);
        state.messageDraft.value = "new message";
        await state.sendMessage();
        assert.equal(sends, 1);
    } finally {
        cleanup();
    }
});

test("general code requires options, sends them through its service, and renders code blocks", async () => {
    const calls = [];
    const result = { data: {
        user_message: { id: 1, role: "user", content: "build an API" },
        assistant_message: { id: 2, role: "assistant", content: "```ts\nconst app = express();\n```" },
        wallet: { balance: 98, payback_balance: 0 },
    } };
    const { state, cleanup } = await chatHarness(
        () => { throw new Error("general_chat must not be called"); },
        undefined,
        { source: "general_code", sendCodeRequest: async (...args) => { calls.push(args); return result; } }
    );

    try {
        state.messageDraft.value = "build an API";
        assert.equal(state.isGeneralCode.value, true);
        assert.equal(state.canSend.value, false);
        await state.sendMessage();
        assert.equal(calls.length, 0);

        state.selectedCodeLanguage.value = "TypeScript";
        state.selectedCodeFramework.value = "Express.js";
        assert.equal(state.programmingLanguage.value, "TypeScript / Express.js");
        assert.equal(state.canSend.value, true);
        await state.sendMessage();
        assert.equal(calls.length, 1);
        assert.equal(calls[0][4], "TypeScript / Express.js");
        assert.equal(state.messages.value.at(-1).content, result.data.assistant_message.content);
        assert.match(state.renderCodeMarkdown(result.data.assistant_message.content), /<pre><code class="language-ts">/);
        assert.doesNotMatch(state.renderCodeMarkdown("<script>alert(1)</script>"), /<script>/);
    } finally {
        cleanup();
    }
});
