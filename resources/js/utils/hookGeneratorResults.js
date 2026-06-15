export const HOOK_GENERATOR_SUB_TOOL_ID = 12;
export const HOOK_GENERATOR_TOOL_KEY = "ai_hook_generator";
export const HOOK_GENERATOR_MODEL_KEY = "hook_generator";

export const createHookGeneratorState = () => ({
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

const cleanText = (value = "") =>
    String(value || "")
        .replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]+/gu, " ")
        .trim();

const asObject = (value) =>
    value && typeof value === "object" && !Array.isArray(value) ? value : {};

const parseJson = (value) => {
    if (typeof value !== "string") return null;

    const text = value.trim()
        .replace(/^```(?:json)?\s*/i, "")
        .replace(/\s*```$/i, "");

    try {
        const parsed = JSON.parse(text);
        return parsed && typeof parsed === "object" ? parsed : null;
    } catch {
        return null;
    }
};

const payloadLayers = (source = {}) => {
    const root = asObject(source);
    const data = asObject(root.data);
    const response = asObject(root.response);
    const dataResponse = asObject(data.response);
    const metadata = asObject(root.metadata);

    return [
        root,
        data,
        response,
        dataResponse,
        metadata,
        asObject(data.metadata),
        asObject(response.metadata),
        asObject(dataResponse.metadata),
    ];
};

const statusMessages = new Set([
    "hook generated successfully",
    "hooks generated successfully",
    "تم توليد الهوكات بنجاح",
    "generated successfully",
    "success",
]);

const isStatusText = (value) => {
    const normalized = cleanText(value)
        .toLowerCase()
        .replace(/[.!؟]+$/gu, "");

    return !normalized || statusMessages.has(normalized);
};

const normalizeHook = (item, index) => {
    if (typeof item === "string") {
        const parsed = parseJson(item);

        if (Array.isArray(parsed?.results)) {
            return parsed.results.flatMap(normalizeHook);
        }

        const text = cleanText(item);
        return text && !isStatusText(text)
            ? [{ id: index + 1, text, title: null, subject: null, meta: {} }]
            : [];
    }

    if (!item || typeof item !== "object") return [];

    const nested = parseJson(item.text);
    if (Array.isArray(nested?.results)) {
        return nested.results.flatMap(normalizeHook);
    }

    const text = cleanText(item.text);
    if (!text || isStatusText(text)) return [];

    return [{
        id: item.id ?? index + 1,
        text,
        title: null,
        subject: null,
        meta: asObject(item.meta),
    }];
};

export const isHookGeneratorResponse = (source = {}) =>
    payloadLayers(source).some((payload) =>
        Number(payload.sub_tool_id || 0) === HOOK_GENERATOR_SUB_TOOL_ID
        || String(payload.tool || "").toLowerCase() === HOOK_GENERATOR_TOOL_KEY
        || String(payload.model_key || "").toLowerCase() === HOOK_GENERATOR_MODEL_KEY
    );

export const extractHookGeneratorResults = (source = {}, fallbackText = "") => {
    for (const payload of payloadLayers(source)) {
        if (!Array.isArray(payload.results)) continue;

        const results = payload.results.flatMap(normalizeHook);
        if (results.length) return results;
    }

    for (const payload of payloadLayers(source)) {
        const lastOutput = payload.state?.last_output;
        const parsed = parseJson(lastOutput);

        if (Array.isArray(parsed?.results)) {
            const results = parsed.results.flatMap(normalizeHook);
            if (results.length) return results;
        }

        const text = cleanText(lastOutput);
        if (text && !isStatusText(text)) {
            return [{ id: 1, text, title: null, subject: null, meta: {} }];
        }
    }

    const fallback = cleanText(fallbackText);

    return fallback && !isStatusText(fallback)
        ? [{ id: 1, text: fallback, title: null, subject: null, meta: {} }]
        : [];
};
