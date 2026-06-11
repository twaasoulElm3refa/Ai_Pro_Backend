import test from "node:test";
import assert from "node:assert/strict";
import {
    extractPromptGeneratorTexts,
} from "../../resources/js/utils/promptGeneratorResults.js";

test("returns only response.results text values", () => {
    const results = extractPromptGeneratorTexts({
        tool: "ai_prompt_generator",
        result: "This fallback must not win.",
        message: "This message must not win.",
        state: {
            last_output: "This state fallback must not win.",
        },
        results: [
            {
                id: 1,
                title: "SEO Prompt",
                subject: "Marketing",
                text: "Generated prompt text here...",
                meta: {},
            },
        ],
    });

    assert.deepEqual(results, ["Generated prompt text here..."]);
    assert.equal(JSON.stringify(results).includes("SEO Prompt"), false);
    assert.equal(JSON.stringify(results).includes("Marketing"), false);
    assert.equal(JSON.stringify(results).includes("null"), false);
});

test("unwraps one nested JSON results payload from a result text", () => {
    const rawJson = JSON.stringify({
        results: [
            { id: 1, text: "Inner prompt one" },
            { id: 2, text: "Inner prompt two" },
        ],
    });

    const results = extractPromptGeneratorTexts({
        tool: "ai_prompt_generator",
        results: [{ id: 99, text: rawJson }],
    });

    assert.deepEqual(results, ["Inner prompt one", "Inner prompt two"]);
    assert.equal(results.includes(rawJson), false);
});

test("parses state.last_output JSON only when direct results are absent", () => {
    const results = extractPromptGeneratorTexts({
        tool: "ai_prompt_generator",
        state: {
            last_output: JSON.stringify({
                results: [{ text: "Prompt recovered from state" }],
            }),
        },
    });

    assert.deepEqual(results, ["Prompt recovered from state"]);
});

test("uses plain message content only when no result text exists", () => {
    const results = extractPromptGeneratorTexts(
        { tool: "ai_prompt_generator" },
        "Plain saved assistant response"
    );

    assert.deepEqual(results, ["Plain saved assistant response"]);
});

test("keeps non Prompt Generator legacy messages out of result cards", () => {
    const results = extractPromptGeneratorTexts(
        { tool: "legacy_writer", message: "Legacy assistant response" },
        "Legacy assistant response"
    );

    assert.deepEqual(results, []);
});
