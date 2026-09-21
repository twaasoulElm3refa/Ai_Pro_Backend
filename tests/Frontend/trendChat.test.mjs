import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const read = (path) => readFile(new URL(`../../${path}`, import.meta.url), "utf8");

test("shared Trends chat maps every configured subtool to its dedicated endpoint", async () => {
    const [service, chat, router, toolPage] = await Promise.all([
        read("resources/js/services/chat/trendServices.js"),
        read("resources/js/views/home/chat7.vue"),
        read("resources/js/router/index.js"),
        read("resources/js/views/home/show.vue"),
    ]);

    assert.match(service, /28:[\s\S]*"cup-lifting-moment"[\s\S]*endpoint:\s*"\/tasks\/trends\/cup-lifting-moment"/);
    assert.match(service, /29:[\s\S]*"locker-room"[\s\S]*endpoint:\s*"\/tasks\/trends\/locker-room"/);
    assert.match(service, /30:[\s\S]*"players-tunnel"[\s\S]*endpoint:\s*"\/tasks\/trends\/players-tunnel"/);
    assert.match(service, /31:[\s\S]*"paparazzi"[\s\S]*endpoint:\s*"\/tasks\/trends\/paparazzi"/);
    assert.match(service, /32:[\s\S]*"80s-photo"[\s\S]*endpoint:\s*"\/tasks\/trends\/80s-photo"/);
    assert.match(service, /33:[\s\S]*"meet-past-self"[\s\S]*endpoint:\s*"\/tasks\/trends\/meet-past-self"/);
    assert.match(service, /api\.post\(trend\.endpoint/);
    assert.match(service, /formData\.append\("payload",\s*JSON\.stringify\(payload\)\)/);
    assert.match(service, /formData\.append\("file",\s*image\)/);
    assert.doesNotMatch(service + chat, /x-internal-api-key/i);
    assert.match(chat, /trendServices\.generate\(subtool\.value/);
    assert.match(chat, /const canSubmit = computed\(\(\) => !submitting\.value && selectedFile\.value !== null\)/);
    assert.doesNotMatch(chat, /if \(!text\)[\s\S]{0,120}promptRequired/);
    assert.match(chat, /sub_tool_id:\s*trend\.subtoolId/);
    assert.match(chat, /result\?\.success\s*!==\s*true[\s\S]*result\?\.type\s*!==\s*"result"[\s\S]*result\.files\[0\]\?\.download_url/);
    assert.doesNotMatch(chat, /EventSource|conversation\/.*\/stream/);
    assert.match(router, /subtool\/:slug\/chat7\/.*uuid/);
    assert.match(toolPage, /TREND_CHAT_SUB_TOOL_IDS\s*=\s*\[28,\s*29,\s*30,\s*31,\s*32,\s*33\]/);
});

test("every supported locale contains the complete Trends chat dictionary", async () => {
    const locales = ["ar", "en", "fr", "ru", "zh"];
    const required = [
        "conversations",
        "emptyTitle",
        "generatedImage",
        "download",
        "generating",
        "promptRequired",
        "imageRequired",
        "genericError",
        "insufficient",
        "unsupported",
    ];

    for (const locale of locales) {
        const messages = JSON.parse(await read(`resources/js/lang/${locale}.json`));
        const dictionary = messages.user?.trendChat;
        assert.ok(dictionary, `${locale} is missing user.trendChat`);
        for (const key of required) {
            assert.equal(typeof dictionary[key], "string", `${locale} is missing ${key}`);
            assert.ok(dictionary[key].trim(), `${locale}.${key} is empty`);
        }
    }
});
