import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import { normalizeInsufficientCredits } from "../../resources/js/utils/insufficientCredits.js";

const sample = { code: "INSUFFICIENT_CREDITS", available_credits: 9676, required_credits: 22000, shortfall_credits: 12324 };

test("normalizes FastAPI, Laravel, Axios and SSE credit errors without changing values", () => {
    for (const value of [{ detail: sample }, sample, { response: { data: sample } }, { type: "error", metadata: sample }]) {
        assert.deepEqual(
            { ...normalizeInsufficientCredits(value), message_id: null },
            { ...sample, message_id: null }
        );
    }
});

test("accepts zero and calculates only a missing shortfall", () => {
    assert.equal(normalizeInsufficientCredits({ code: sample.code, available_credits: 0, required_credits: 5 }).shortfall_credits, 5);
    assert.equal(normalizeInsufficientCredits({ code: sample.code, available_credits: 0 }).shortfall_credits, null);
    assert.equal(normalizeInsufficientCredits({ code: sample.code, available_credits: "0", required_credits: "5", shortfall_credits: "5" }).available_credits, null);
    assert.equal(normalizeInsufficientCredits({ code: "OTHER_ERROR", ...sample, code: "OTHER_ERROR" }), null);
});

test("all supported locales contain credit notice text and localized wallet route exists", async () => {
    for (const lang of ["ar", "en", "ru", "fr", "zh"]) {
        const messages = JSON.parse(await readFile(`resources/js/lang/${lang}.json`, "utf8"));
        for (const field of ["title", "description", "available", "required", "shortfall", "recharge", "close"]) {
            assert.ok(messages.errors.insufficientCredits[field]);
        }
    }
    const router = await readFile("resources/js/router/index.js", "utf8");
    assert.match(router, /path:\s*"\/:lang\/wallet"/);
});
