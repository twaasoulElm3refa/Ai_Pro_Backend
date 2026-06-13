import test from "node:test";
import assert from "node:assert/strict";
import {
    cleanPromptEnhancerText,
    createPromptEnhancerState,
    extractPromptEnhancerResults,
    isPromptEnhancerResponse,
} from "../../resources/js/utils/promptEnhancerResults.js";

test("extracts only prompt enhancer result text", () => {
    const results = extractPromptEnhancerResults({
        type: "result",
        tool: "ai_prompt_enhancer",
        message: "Prompt enhanced successfully.",
        results: [
            { id: 1, text: "First improved prompt", title: "Ignored" },
            { id: 2, text: "Second improved prompt", subject: "Ignored" },
        ],
    });

    assert.deepEqual(results, [
        "First improved prompt",
        "Second improved prompt",
    ]);
    assert.equal(JSON.stringify(results).includes("Prompt enhanced successfully"), false);
    assert.equal(JSON.stringify(results).includes("Ignored"), false);
});

test("uses state.last_output when results are absent", () => {
    assert.deepEqual(
        extractPromptEnhancerResults({
            sub_tool_id: 10,
            state: { last_output: "Recovered improved prompt" },
        }),
        ["Recovered improved prompt"]
    );
});

test("removes control separators from displayed output", () => {
    assert.equal(
        cleanPromptEnhancerText("Add a call\u001eto\u001eaction."),
        "Add a call-to-action."
    );
});

test("recognizes tool 10 and exposes the requested empty state contract", () => {
    assert.equal(isPromptEnhancerResponse({ sub_tool_id: 10 }), true);
    assert.deepEqual(createPromptEnhancerState(), {
        original_prompt: null,
        target_ai_tool: null,
        language: null,
        enhancement_goal: null,
        tone: null,
        output_format: null,
        detail_level: null,
        preserve_intent: null,
        results_count: null,
        extra_options: [],
        last_output: null,
    });
});
