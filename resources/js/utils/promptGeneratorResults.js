export const PROMPT_GENERATOR_SUB_TOOL_ID = 9;
export const PROMPT_GENERATOR_TOOL_KEY = "ai_prompt_generator";
export const PROMPT_GENERATOR_MODEL_KEY = "prompt_generator";

export const parsePromptGeneratorJson = (value) => {
    if (typeof value !== "string") return null;

    const text = value.trim();
    if (!text) return null;

    const cleaned = text
        .replace(/^```(?:json)?\s*/i, "")
        .replace(/\s*```$/i, "");

    try {
        const parsed = JSON.parse(cleaned);
        return parsed && typeof parsed === "object" ? parsed : null;
    } catch {
        return null;
    }
};

const unwrapPayload = (source) => {
    if (!source || typeof source !== "object") return {};
    return source.data && typeof source.data === "object" ? source.data : source;
};

const resultItemText = (item) => {
    if (typeof item === "string") return item.trim();
    if (!item || typeof item !== "object") return "";

    return typeof item.text === "string" ? item.text.trim() : "";
};

const textsFromResults = (results, unwrapNestedText = true) => {
    if (!Array.isArray(results)) return [];

    return results.flatMap((item) => {
        const text = resultItemText(item);
        if (!text) return [];

        if (!unwrapNestedText) return [text];

        const nestedPayload = parsePromptGeneratorJson(text);
        if (!Array.isArray(nestedPayload?.results)) return [text];

        const innerTexts = textsFromResults(nestedPayload.results, false);
        return innerTexts.length ? innerTexts : [];
    });
};

const mergePayloadMetadata = (source) => {
    const payload = unwrapPayload(source);
    const metadata = payload.metadata && typeof payload.metadata === "object"
        ? payload.metadata
        : {};

    return { ...payload, ...metadata };
};

export const isPromptGeneratorResponse = (source = {}, messageContent = "") => {
    const payload = mergePayloadMetadata(source);
    const parsedContent = parsePromptGeneratorJson(messageContent);

    return String(payload.tool || "").toLowerCase() === PROMPT_GENERATOR_TOOL_KEY
        || String(payload.model_key || "").toLowerCase() === PROMPT_GENERATOR_MODEL_KEY
        || Number(payload.sub_tool_id || 0) === PROMPT_GENERATOR_SUB_TOOL_ID
        || String(parsedContent?.tool || "").toLowerCase() === PROMPT_GENERATOR_TOOL_KEY
        || Array.isArray(parsedContent?.results);
};

export const extractPromptGeneratorTexts = (source = {}, messageContent = "") => {
    const payload = mergePayloadMetadata(source);
    const promptGeneratorResponse = isPromptGeneratorResponse(payload, messageContent);

    const directTexts = textsFromResults(payload.results);
    if (directTexts.length) return directTexts;

    // A normal /message/send acknowledgement must continue to SSE instead of
    // being rendered as an assistant result.
    if (!promptGeneratorResponse) return [];

    const parsedLastOutput = parsePromptGeneratorJson(payload.state?.last_output);
    const lastOutputTexts = textsFromResults(parsedLastOutput?.results);
    if (lastOutputTexts.length) return lastOutputTexts;

    const parsedMessageContent = parsePromptGeneratorJson(messageContent);
    const contentTexts = textsFromResults(parsedMessageContent?.results);
    if (contentTexts.length) return contentTexts;

    return typeof messageContent === "string"
        && messageContent.trim()
        && !parsedMessageContent
        ? [messageContent.trim()]
        : [];
};

export const normalizePromptGeneratorResults = extractPromptGeneratorTexts;
