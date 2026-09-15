import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const source = await readFile("resources/js/services/audioChatStorage.js", "utf8");
const storageModule = await import(`data:text/javascript,${encodeURIComponent(source)}`);

function fakeStorage() {
    const values = new Map();
    return {
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, String(value)),
        removeItem: (key) => values.delete(key),
    };
}

test("audio chat persistence stores only lightweight message and file metadata", () => {
    const originalStorage = globalThis.localStorage;
    globalThis.localStorage = fakeStorage();
    try {
        const saved = storageModule.saveAudioChatHistory("conversation-uuid", [
            { id: "runtime-user", role: "user", content: "hello", request_id: "request-id" },
            {
                id: "runtime-audio",
                role: "assistant",
                type: "audio",
                content: "Speech generated successfully.",
                filename: "generated-speech.mp3",
                download_url: "/tasks/generated-files/download/file-id",
                file_id: "file-id",
                content_type: "audio/mpeg",
                url: "blob:http://localhost/temporary",
                blob: new Blob(["audio bytes"], { type: "audio/mpeg" }),
            },
        ]);

        assert.equal(saved, true);
        const raw = globalThis.localStorage.getItem(storageModule.audioChatStorageKey("conversation-uuid"));
        assert.doesNotMatch(raw, /blob:/);
        assert.deepEqual(JSON.parse(raw), {
            conversation_uuid: "conversation-uuid",
            messages: [
                { role: "user", type: "text", content: "hello" },
                {
                    role: "assistant",
                    type: "audio",
                    content: "Speech generated successfully.",
                    filename: "generated-speech.mp3",
                    download_url: "/tasks/generated-files/download/file-id",
                    file_id: "file-id",
                    content_type: "audio/mpeg",
                },
            ],
        });
    } finally {
        globalThis.localStorage = originalStorage;
    }
});

test("audio chat history restores only for its conversation and can be cleared", () => {
    const originalStorage = globalThis.localStorage;
    globalThis.localStorage = fakeStorage();
    try {
        storageModule.saveAudioChatHistory("conversation-uuid", [
            { role: "user", type: "text", content: "hello" },
        ]);
        storageModule.saveAudioChatHistory("second-conversation", [
            { role: "user", type: "text", content: "second chat" },
        ]);
        assert.deepEqual(storageModule.readAudioChatHistory("conversation-uuid"), [
            { role: "user", type: "text", content: "hello" },
        ]);
        assert.deepEqual(storageModule.readAudioChatHistory("different-uuid"), []);

        storageModule.clearAudioChatHistory("different-uuid");
        assert.equal(globalThis.localStorage.getItem(storageModule.audioChatStorageKey("conversation-uuid")) !== null, true);
        storageModule.clearAudioChatHistory("conversation-uuid");
        assert.equal(globalThis.localStorage.getItem(storageModule.audioChatStorageKey("conversation-uuid")), null);
    } finally {
        globalThis.localStorage = originalStorage;
    }
});
