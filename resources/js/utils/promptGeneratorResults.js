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

const normalizeBasicResult = (item, index) => {
    if (typeof item === "string") {
        const text = item.trim();
        return text ? {
            id: index + 1,
            title: null,
            subject: null,
            text,
            meta: {},
        } : null;
    }

    if (!item || typeof item !== "object") return null;

    const text = item.text ?? item.prompt ?? item.output ?? item.result ?? item.content;
    if (text === null || text === undefined || String(text).trim() === "") return null;

    return {
        ...item,
        id: item.id ?? index + 1,
        title: item.title ?? item.name ?? null,
        subject: item.subject ?? null,
        text: String(text).trim(),
        meta: item.meta && typeof item.meta === "object" ? item.meta : {},
    };
};

const normalizeResultCollection = (candidate, expandNestedText = true) => {
    if (candidate === null || candidate === undefined || candidate === "") return [];

    if (typeof candidate === "string") {
        const parsed = parsePromptGeneratorJson(candidate);
        return parsed
            ? normalizeResultCollection(parsed, expandNestedText)
            : [normalizeBasicResult(candidate, 0)].filter(Boolean);
    }

    if (Array.isArray(candidate)) {
        return candidate.flatMap((item, index) => {
            const normalized = normalizeBasicResult(item, index);
            if (!normalized) return [];

            if (!expandNestedText) return [normalized];

            const nested = parsePromptGeneratorJson(normalized.text);
            if (!nested) return [normalized];

            const nestedResults = normalizeResultCollection(
                nested.results ?? nested.result ?? nested,
                false
            );

            return nestedResults.length ? nestedResults : [normalized];
        });
    }

    if (typeof candidate === "object") {
        if (Array.isArray(candidate.results)) {
            return normalizeResultCollection(candidate.results, expandNestedText);
        }

        if (candidate.result !== undefined) {
            return normalizeResultCollection(candidate.result, expandNestedText);
        }

        const normalized = normalizeBasicResult(candidate, 0);
        return normalized ? [normalized] : [];
    }

    return [];
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

export const normalizePromptGeneratorResults = (source = {}, messageContent = "") => {
    const payload = mergePayloadMetadata(source);
    const promptGeneratorResponse = isPromptGeneratorResponse(payload, messageContent);

    const directResults = normalizeResultCollection(payload.results);
    if (directResults.length) return directResults;

    // A normal /message/send acknowledgement must continue to SSE instead of
    // being rendered as an assistant result.
    if (!promptGeneratorResponse) return [];

    const fallbackCandidates = [
        payload.result,
        payload.message,
        payload.state?.last_output,
        messageContent,
    ];

    for (const candidate of fallbackCandidates) {
        const results = normalizeResultCollection(candidate);
        if (results.length) return results;
    }

    return [];
};
