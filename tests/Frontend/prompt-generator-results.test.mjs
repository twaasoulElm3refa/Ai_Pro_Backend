import test from "node:test";
import assert from "node:assert/strict";
import {
    normalizePromptGeneratorResults,
} from "../../resources/js/utils/promptGeneratorResults.js";

test("uses response.results as the primary display source", () => {
    const results = normalizePromptGeneratorResults({
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

    assert.deepEqual(results, [
        {
            id: 1,
            title: "SEO Prompt",
            subject: "Marketing",
            text: "Generated prompt text here...",
            meta: {},
        },
    ]);
    assert.equal(results[0].text.includes('{"results"'), false);
});

test("unwraps one nested JSON results payload from a result text", () => {
    const rawJson = JSON.stringify({
        results: [
            { id: 1, text: "Inner prompt one" },
            { id: 2, text: "Inner prompt two" },
        ],
    });

    const results = normalizePromptGeneratorResults({
        tool: "ai_prompt_generator",
        results: [{ id: 99, text: rawJson }],
    });

    assert.deepEqual(
        results.map((item) => item.text),
        ["Inner prompt one", "Inner prompt two"]
    );
    assert.equal(results.some((item) => item.text === rawJson), false);
});

test("parses state.last_output JSON only when direct results are absent", () => {
    const results = normalizePromptGeneratorResults({
        tool: "ai_prompt_generator",
        state: {
            last_output: JSON.stringify({
                results: [{ text: "Prompt recovered from state" }],
            }),
        },
    });

    assert.equal(results[0].text, "Prompt recovered from state");
});

test("keeps non Prompt Generator legacy messages out of result cards", () => {
    const results = normalizePromptGeneratorResults(
        { tool: "legacy_writer", message: "Legacy assistant response" },
        "Legacy assistant response"
    );

    assert.deepEqual(results, []);
});
