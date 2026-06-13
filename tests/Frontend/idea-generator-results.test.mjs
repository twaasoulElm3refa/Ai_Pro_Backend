import test from "node:test";
import assert from "node:assert/strict";
import {
    createIdeaGeneratorState,
    extractIdeaGeneratorResults,
    isIdeaGeneratorResponse,
} from "../../resources/js/utils/ideaGeneratorResults.js";

const ideas = Array.from({ length: 10 }, (_, index) => ({
    id: index + 1,
    title: `Idea ${index + 1}`,
    text: `Actionable description ${index + 1}`,
    subject: "AI tools",
    meta: {},
}));

test("extracts ten complete idea objects from an SSE response wrapper", () => {
    const results = extractIdeaGeneratorResults({
        type: "done",
        response: {
            type: "result",
            tool: "ai_idea_generator",
            message: "تم توليد الأفكار بنجاح.",
            results: ideas,
        },
    });

    assert.equal(results.length, 10);
    assert.deepEqual(results[0], ideas[0]);
    assert.equal(JSON.stringify(results).includes("تم توليد الأفكار بنجاح"), false);
});

test("extracts data-wrapped results and recognizes all tool identifiers", () => {
    const results = extractIdeaGeneratorResults({
        data: {
            sub_tool_id: 11,
            model_key: "idea_generator",
            results: ideas,
        },
    });

    assert.equal(results.length, 10);
    assert.equal(results[9].title, "Idea 10");
    assert.equal(isIdeaGeneratorResponse({ sub_tool_id: 11 }), true);
    assert.equal(isIdeaGeneratorResponse({ tool: "ai_idea_generator" }), true);
    assert.equal(isIdeaGeneratorResponse({ model_key: "idea_generator" }), true);
});

test("extracts results from response.data.response", () => {
    const results = extractIdeaGeneratorResults({
        data: {
            response: {
                sub_tool_id: 11,
                tool: "ai_idea_generator",
                results: ideas,
            },
        },
    });

    assert.equal(results.length, 10);
    assert.equal(results[0].text, "Actionable description 1");
});

test("exposes the requested idea generator state contract", () => {
    assert.deepEqual(createIdeaGeneratorState(), {
        topic: null,
        idea_type: null,
        industry: null,
        audience: null,
        language: null,
        tone: null,
        creativity_level: null,
        results_count: null,
        include_titles: null,
        include_descriptions: null,
        extra_options: [],
        last_output: null,
    });
});
