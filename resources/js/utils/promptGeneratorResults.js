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

const asObject = (value) =>
    value && typeof value === "object" && !Array.isArray(value) ? value : {};

const payloadLayers = (source) => {
    const root = asObject(source);
    const data = asObject(root.data);
    const metadata = asObject(root.metadata);
    const dataMetadata = asObject(data.metadata);

    return [root, data, metadata, dataMetadata];
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
    const [root, data, metadata, dataMetadata] = payloadLayers(source);
    return { ...root, ...data, ...metadata, ...dataMetadata };
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

const normalizeStatusText = (value) =>
    String(value || "")
        .trim()
        .toLowerCase()
        .replace(/[.!؟]+$/g, "")
        .replace(/\s+/g, " ");

export const isPromptGeneratorStatusText = (value) => {
    const normalized = normalizeStatusText(value);

    return normalized === "prompt generated successfully"
        || normalized === "تم توليد البرومبت بنجاح";
};

export const extractPromptGeneratorTexts = (source = {}, messageContent = "") => {
    const payload = mergePayloadMetadata(source);
    const promptGeneratorResponse = isPromptGeneratorResponse(payload, messageContent);

    for (const layer of payloadLayers(source)) {
        const directTexts = textsFromResults(layer.results);
        if (directTexts.length) return directTexts;
    }

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
        && !isPromptGeneratorStatusText(messageContent)
        ? [messageContent.trim()]
        : [];
};

export const normalizePromptGeneratorResults = extractPromptGeneratorTexts;
