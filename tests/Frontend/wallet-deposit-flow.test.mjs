import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), "utf8");

test("wallet charge uses the shared API client and a durable idempotency key", () => {
    const source = read("resources/js/views/home/user/charge.vue");

    assert.match(source, /ApiClient/);
    assert.match(source, /sessionStorage/);
    assert.match(source, /idempotency_key/);
    assert.doesNotMatch(source, /https:\/\/api\.ai-pro\.pro/);
    assert.doesNotMatch(source, /X-API-KEY['"]?\s*:/i);
});

test("waiting page polls sequentially and has terminal stop conditions", () => {
    const source = read("resources/js/views/home/WaitingDeposit.vue");

    assert.doesNotMatch(source, /setInterval\s*\(/);
    assert.match(source, /setTimeout\s*\(/);
    assert.match(source, /AbortController/);
    assert.match(source, /MAX_ATTEMPTS/);
    assert.match(source, /router\.replace/);
    assert.match(source, /status\s*===\s*429/);
    assert.match(source, /viewState\.value\s*=\s*["']timeout["']/);
});

test("success and cancel routes never fabricate a transaction identifier", () => {
    const success = read("resources/js/views/home/successDeposit.vue");
    const router = read("resources/js/router/index.js");

    assert.doesNotMatch(success, /Date\.now\s*\(/);
    assert.match(success, /order_id/);
    assert.match(router, /deposit\/cancel/);
    assert.match(router, /CancelledDeposit/);
});
