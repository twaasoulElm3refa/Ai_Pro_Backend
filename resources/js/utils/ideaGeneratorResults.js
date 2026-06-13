export const IDEA_GENERATOR_SUB_TOOL_ID = 11;
export const IDEA_GENERATOR_TOOL_KEY = "ai_idea_generator";
export const IDEA_GENERATOR_MODEL_KEY = "idea_generator";

export const createIdeaGeneratorState = () => ({
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

const cleanText = (value = "") =>
    String(value || "")
        .replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]+/gu, " ")
        .trim();

const asObject = (value) =>
    value && typeof value === "object" && !Array.isArray(value) ? value : {};

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
    "idea generated successfully",
    "ideas generated successfully",
    "تم توليد الأفكار بنجاح",
    "generated successfully",
    "success",
]);

const isStatusText = (value) => {
    const normalized = cleanText(value)
        .toLowerCase()
        .replace(/[.!؟]+$/gu, "");

    return !normalized || statusMessages.has(normalized);
};

const parseJson = (value) => {
    if (typeof value !== "string") return null;

    try {
        const parsed = JSON.parse(value.trim());
        return parsed && typeof parsed === "object" ? parsed : null;
    } catch {
        return null;
    }
};

const normalizeIdea = (item, index) => {
    if (typeof item === "string") {
        const parsed = parseJson(item);

        if (Array.isArray(parsed?.results)) {
            return parsed.results.flatMap(normalizeIdea);
        }

        const text = cleanText(item);
        return text && !isStatusText(text)
            ? [{ id: index + 1, title: "", text, subject: "", meta: {} }]
            : [];
    }

    if (!item || typeof item !== "object") return [];

    const nestedText = parseJson(item.text);
    if (Array.isArray(nestedText?.results)) {
        return nestedText.results.flatMap(normalizeIdea);
    }

    const text = cleanText(item.text);
    const title = cleanText(item.title);
    const subject = cleanText(item.subject);

    if ((!text && !title) || isStatusText(text || title)) return [];

    return [{
        id: item.id ?? index + 1,
        title,
        text,
        subject,
        meta: asObject(item.meta),
    }];
};

export const isIdeaGeneratorResponse = (source = {}) =>
    payloadLayers(source).some((payload) =>
        Number(payload.sub_tool_id || 0) === IDEA_GENERATOR_SUB_TOOL_ID
        || String(payload.tool || "").toLowerCase() === IDEA_GENERATOR_TOOL_KEY
        || String(payload.model_key || "").toLowerCase() === IDEA_GENERATOR_MODEL_KEY
    );

export const extractIdeaGeneratorResults = (source = {}, fallbackText = "") => {
    for (const payload of payloadLayers(source)) {
        if (!Array.isArray(payload.results)) continue;

        const results = payload.results.flatMap(normalizeIdea);
        if (results.length) return results;
    }

    for (const payload of payloadLayers(source)) {
        const lastOutput = payload.state?.last_output;
        const parsed = parseJson(lastOutput);

        if (Array.isArray(parsed?.results)) {
            const results = parsed.results.flatMap(normalizeIdea);
            if (results.length) return results;
        }

        const text = cleanText(lastOutput);
        if (text && !isStatusText(text)) {
            return [{ id: 1, title: "", text, subject: "", meta: {} }];
        }
    }

    const fallback = cleanText(fallbackText);

    return fallback && !isStatusText(fallback)
        ? [{ id: 1, title: "", text: fallback, subject: "", meta: {} }]
        : [];
};
