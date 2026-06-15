import test from "node:test";
import assert from "node:assert/strict";
import {
    createHookGeneratorState,
    extractHookGeneratorResults,
    isHookGeneratorResponse,
} from "../../resources/js/utils/hookGeneratorResults.js";

const hooks = Array.from({ length: 10 }, (_, index) => ({
    id: index + 1,
    text: `Scroll-stopping hook ${index + 1}`,
    title: null,
    subject: null,
    meta: {},
}));

test("extracts ten hook texts from the SSE response", () => {
    const results = extractHookGeneratorResults({
        type: "done",
        response: {
            sub_tool_id: 12,
            tool: "ai_hook_generator",
            model_key: "hook_generator",
            message: "Hooks generated successfully.",
            results: hooks,
        },
    });

    assert.equal(results.length, 10);
    assert.deepEqual(results[0], hooks[0]);
    assert.equal(JSON.stringify(results).includes("Hooks generated successfully"), false);
});

test("parses nested JSON results and exposes only hook text cards", () => {
    const results = extractHookGeneratorResults({
        data: {
            tool: "ai_hook_generator",
            results: [{
                text: JSON.stringify({ results: hooks.slice(0, 2) }),
            }],
        },
    });

    assert.equal(results.length, 2);
    assert.equal(results[0].text, "Scroll-stopping hook 1");
    assert.equal(results[0].title, null);
    assert.equal(results[0].subject, null);
});

test("recognizes hook generator identifiers and state contract", () => {
    assert.equal(isHookGeneratorResponse({ sub_tool_id: 12 }), true);
    assert.equal(isHookGeneratorResponse({ tool: "ai_hook_generator" }), true);
    assert.equal(isHookGeneratorResponse({ model_key: "hook_generator" }), true);
    assert.deepEqual(createHookGeneratorState(), {
        topic: null,
        platform: null,
        content_type: null,
        language: null,
        tone: null,
        audience: null,
        hook_style: null,
        length: null,
        results_count: null,
        extra_options: [],
        last_output: null,
    });
});
