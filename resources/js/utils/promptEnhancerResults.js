export const PROMPT_ENHANCER_SUB_TOOL_ID = 10;
export const PROMPT_ENHANCER_TOOL_KEY = "ai_prompt_enhancer";
export const PROMPT_ENHANCER_MODEL_KEY = "prompt_enhancer";

export const createPromptEnhancerState = () => ({
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

export const cleanPromptEnhancerText = (value = "") =>
    String(value || "")
        .replace(/([\p{L}\p{N}])[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]+(?=[\p{L}\p{N}])/gu, "$1-")
        .replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]+/gu, " ")
        .trim();

const asObject = (value) =>
    value && typeof value === "object" && !Array.isArray(value) ? value : {};

const payloadLayers = (source = {}) => {
    const root = asObject(source);
    const data = asObject(root.data);
    const metadata = asObject(root.metadata);
    const dataMetadata = asObject(data.metadata);

    return [root, data, metadata, dataMetadata];
};

export const isPromptEnhancerResponse = (source = {}) =>
    payloadLayers(source).some((payload) =>
        Number(payload.sub_tool_id || 0) === PROMPT_ENHANCER_SUB_TOOL_ID
        || String(payload.tool || "").toLowerCase() === PROMPT_ENHANCER_TOOL_KEY
        || String(payload.model_key || "").toLowerCase() === PROMPT_ENHANCER_MODEL_KEY
    );

const parseNestedResults = (text) => {
    try {
        const parsed = JSON.parse(text);
        return Array.isArray(parsed?.results) ? parsed.results : null;
    } catch {
        return null;
    }
};

const extractResultTexts = (results, unwrapNested = true) => {
    if (!Array.isArray(results)) return [];

    return results.flatMap((item) => {
        const text = cleanPromptEnhancerText(
            typeof item === "string" ? item : item?.text
        );
        if (!text) return [];

        const nestedResults = unwrapNested ? parseNestedResults(text) : null;
        return nestedResults
            ? extractResultTexts(nestedResults, false)
            : [text];
    });
};

export const extractPromptEnhancerResults = (source = {}, fallbackText = "") => {
    for (const payload of payloadLayers(source)) {
        const results = extractResultTexts(payload.results);
        if (results.length) return results;
    }

    for (const payload of payloadLayers(source)) {
        const lastOutput = cleanPromptEnhancerText(payload.state?.last_output);
        if (!lastOutput) continue;

        const nestedResults = parseNestedResults(lastOutput);
        return nestedResults
            ? extractResultTexts(nestedResults, false)
            : [lastOutput];
    }

    const fallback = cleanPromptEnhancerText(fallbackText);
    return fallback ? [fallback] : [];
};

export const normalizePromptEnhancerState = (state = {}) => ({
    ...createPromptEnhancerState(),
    ...asObject(state),
    extra_options: Array.isArray(state?.extra_options)
        ? state.extra_options.map(cleanPromptEnhancerText).filter(Boolean)
        : [],
});
