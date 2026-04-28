const COLORS = {
    /* ---------- LIGHT MODE ---------- */
    BG: "#F8FBFF",
    PRIMARY: "#FFFFFF",
    SECONDARY: "#154677",
    TEXT: "#154677",
    TEXT_MUTED: "#5F7288",
    CARD_BORDER: "rgba(21, 70, 119, 0.12)",

    INPUT: "#FFFFFF",
    ACCENT: "#2BA6DE",
    BTN_TEXT: "#FFFFFF",

    SUCCESS: "#2BA6DE",
    LINK_HOVER: "#2BA6DE",

    /* ---------- DARK MODE ---------- */
    BG_DARK: "#EEF5FB",
    CARD_DARK: "#154677",
    INPUT_DARK: "#FFFFFF",
    TEXT_DARK: "#FFFFFF",
};

/* ---------- rgba helper ---------- */
export const rgba = (hex, alpha = 1) => {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
};

export default COLORS;
