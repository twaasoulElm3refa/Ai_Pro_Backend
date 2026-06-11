import test from "node:test";
import assert from "node:assert/strict";
import {
    extractPromptGeneratorDisplayItems,
    isPromptGeneratorStatusText,
} from "../../resources/js/utils/promptGeneratorResults.js";

test("returns only response.results text values", () => {
    const results = extractPromptGeneratorDisplayItems({
        type: "result",
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
            {
                id: 1,
                title: null,
                subject: null,
                text: "Actual prompt here...",
            },
        ],
    });

    const results = extractPromptGeneratorDisplayItems({
        success: true,
        type: "result",
        tool: "ai_prompt_generator",
        message: "Prompt generated successfully.",
        results: [{ id: 99, text: rawJson }],
    });

    assert.deepEqual(results, ["Actual prompt here..."]);
    assert.equal(results.includes(rawJson), false);
    assert.equal(results.includes("Prompt generated successfully."), false);
    assert.equal(JSON.stringify(results).includes("title"), false);
    assert.equal(JSON.stringify(results).includes("subject"), false);
    assert.equal(JSON.stringify(results).includes("null"), false);
});

test("extracts nested result text from a data-wrapped API response", () => {
    const rawJson = JSON.stringify({
        results: [
            {
                title: null,
                subject: null,
                text: "ACTUAL PROMPT",
            },
        ],
    });

    const results = extractPromptGeneratorDisplayItems({
        success: true,
        message: "Prompt generated successfully.",
        data: {
            tool: "ai_prompt_generator",
            type: "result",
            message: "Prompt generated successfully.",
            results: [{ text: rawJson }],
        },
    });

    assert.deepEqual(results, ["ACTUAL PROMPT"]);
    assert.equal(JSON.stringify(results).includes("Prompt generated successfully"), false);
    assert.equal(JSON.stringify(results).includes("title"), false);
    assert.equal(JSON.stringify(results).includes("subject"), false);
    assert.equal(JSON.stringify(results).includes("null"), false);
    assert.equal(JSON.stringify(results).includes('{"results"'), false);
});

test("parses state.last_output JSON only when direct results are absent", () => {
    const results = extractPromptGeneratorDisplayItems({
        type: "result",
        tool: "ai_prompt_generator",
        state: {
            last_output: JSON.stringify({
                results: [{ text: "Prompt recovered from state" }],
            }),
        },
    });

    assert.deepEqual(results, ["Prompt recovered from state"]);
});

test("rejects Prompt Generator status messages as plain-text fallbacks", () => {
    assert.deepEqual(
        extractPromptGeneratorDisplayItems(
            { type: "result", tool: "ai_prompt_generator" },
            "Prompt generated successfully."
        ),
        []
    );
    assert.deepEqual(
        extractPromptGeneratorDisplayItems(
            { type: "result", sub_tool_id: 9 },
            "تم توليد البرومبت بنجاح."
        ),
        []
    );
    assert.deepEqual(
        extractPromptGeneratorDisplayItems(
            { type: "result", sub_tool_id: 9 },
            "Generated successfully."
        ),
        []
    );
    assert.deepEqual(
        extractPromptGeneratorDisplayItems(
            { type: "result", sub_tool_id: 9 },
            "Success"
        ),
        []
    );
    assert.equal(isPromptGeneratorStatusText("Prompt generated successfully."), true);
    assert.equal(
        isPromptGeneratorStatusText("\u062a\u0645 \u062a\u0648\u0644\u064a\u062f \u0627\u0644\u0628\u0631\u0648\u0645\u0628\u062a \u0628\u0646\u062c\u0627\u062d."),
        true
    );
    assert.equal(isPromptGeneratorStatusText("تم توليد البرومبت بنجاح."), true);
});

test("returns the AI message for a question response", () => {
    const items = extractPromptGeneratorDisplayItems({
        success: true,
        type: "question",
        tool: "ai_prompt_generator",
        message: "What task or idea should I create a prompt for?",
        results: [],
    });

    assert.deepEqual(items, ["What task or idea should I create a prompt for?"]);
});

test("does not use the success message for a result response", () => {
    const items = extractPromptGeneratorDisplayItems({
        success: true,
        type: "result",
        tool: "ai_prompt_generator",
        message: "Prompt generated successfully.",
        results: [{ text: "ACTUAL PROMPT TEXT HERE" }],
    });

    assert.deepEqual(items, ["ACTUAL PROMPT TEXT HERE"]);
    assert.equal(items.includes("Prompt generated successfully."), false);
});

test("uses plain message content only when no result text exists", () => {
    const results = extractPromptGeneratorDisplayItems(
        { tool: "ai_prompt_generator" },
        "Plain saved assistant response"
    );

    assert.deepEqual(results, ["Plain saved assistant response"]);
});

test("keeps non Prompt Generator legacy messages out of result cards", () => {
    const results = extractPromptGeneratorDisplayItems(
        { tool: "legacy_writer", message: "Legacy assistant response" },
        "Legacy assistant response"
    );

    assert.deepEqual(results, []);
});
