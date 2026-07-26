import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), "utf8");

test("wallet charge uses the shared API client and a durable idempotency key", () => {
    const source = read("resources/js/views/home/user/charge.vue");

    assert.match(source, /ApiClient/);
    assert.match(source, /sessionStorage/);
    assert.match(source, /idempotency_key/);
    assert.match(source, /\/moyasar\/pay/);
    assert.match(source, /MOYASAR_DEPOSIT_ID_STORAGE_KEY/);
    assert.match(source, /POINTS_PER_SAR\s*=\s*1000000/);
    assert.match(source, /provider=paypal&order_id/);
    assert.match(source, /provider=moyasar&deposit_id/);
    assert.doesNotMatch(source, /\/wallet\/moyasar-status/);
    assert.doesNotMatch(source, /isMoyasarCallback|startMoyasarPolling|checkMoyasarStatus|moyasarStatus/);
    assert.doesNotMatch(source, /onBeforeUnmount\(stopMoyasarPolling\)/);
    assert.doesNotMatch(source, /AbortController/);
    assert.doesNotMatch(source, /setTimeout\s*\(/);
    assert.doesNotMatch(source, /setInterval\s*\(/);
    assert.doesNotMatch(source, /moyasar\.init|checkout\.moyasar|moyasar\.css/i);
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
    assert.match(source, /\/wallet\/order-status\/\$\{encodeURIComponent\(id\)\}/);
    assert.match(source, /\/wallet\/moyasar-status\/\$\{encodeURIComponent\(id\)\}/);
    assert.match(source, /provider\.value\s*===\s*["']moyasar["']/);
    assert.match(source, /deposit_id/);
    assert.match(source, /order_id/);
    assert.match(source, /refunded/);
    assert.match(source, /voided/);
    assert.doesNotMatch(source, /webhook/i);
});

test("success and cancel routes never fabricate a transaction identifier", () => {
    const success = read("resources/js/views/home/successDeposit.vue");
    const router = read("resources/js/router/index.js");

    assert.doesNotMatch(success, /Date\.now\s*\(/);
    assert.match(success, /order_id/);
    assert.match(success, /deposit_id/);
    assert.match(router, /deposit\/cancel/);
    assert.match(router, /CancelledDeposit/);
});
