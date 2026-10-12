export const INSUFFICIENT_CREDITS = "INSUFFICIENT_CREDITS";

const creditNumber = (value) =>
    typeof value === "number" && Number.isFinite(value) && value >= 0 ? value : null;

export function normalizeInsufficientCredits(input) {
    const payload = input?.response?.data ?? input;
    if (!payload || typeof payload !== "object") return null;

    const candidates = [payload, payload.detail, payload.error, payload.metadata, payload.data, payload.data?.detail, payload.data?.error, payload.message?.metadata];
    const match = candidates.find((item) => item && typeof item === "object" && item.code === INSUFFICIENT_CREDITS);
    if (!match) return null;

    const available = creditNumber(match.available_credits);
    const required = creditNumber(match.required_credits);
    const shortfall = creditNumber(match.shortfall_credits);

    return {
        code: INSUFFICIENT_CREDITS,
        available_credits: available,
        required_credits: required,
        shortfall_credits: shortfall ?? (available !== null && required !== null ? Math.max(0, required - available) : null),
        message_id: match.message_id ?? payload.message_id ?? null,
    };
}

export function publishInsufficientCredits(input) {
    const error = normalizeInsufficientCredits(input);
    if (error && typeof window !== "undefined") {
        window.dispatchEvent(new CustomEvent("insufficient-credits", { detail: error }));
    }
    return error;
}
