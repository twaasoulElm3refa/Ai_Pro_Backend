import { createI18n } from "vue-i18n";

import en from "./lang/en.json";
import ar from "./lang/ar.json";
import ru from "./lang/ru.json";
import fr from "./lang/fr.json";
import zh from "./lang/zh.json";

const LANG_KEY = "lang";
const FALLBACK_LANG = "ar";
const SUPPORTED_LANGS = ["ar", "en", "ru", "fr", "zh"];

const normalizeLang = (value) => {
    const lang = String(value || "").toLowerCase();
    return SUPPORTED_LANGS.includes(lang) ? lang : FALLBACK_LANG;
};

const getStoredLang = () => normalizeLang(localStorage.getItem(LANG_KEY));

const applyHtmlLang = (lang) => {
    document.documentElement.setAttribute("lang", lang);
    document.documentElement.setAttribute("dir", lang === "ar" ? "rtl" : "ltr");
};

const initialLang = getStoredLang();
localStorage.setItem(LANG_KEY, initialLang);
applyHtmlLang(initialLang);

const i18n = createI18n({
    legacy: false,
    globalInjection: true,
    locale: initialLang,
    fallbackLocale: "en",
    messages: {
        en,
        ar,
        ru,
        fr,
        zh,
    },
});

const syncI18nLang = (lang) => {
    const normalized = normalizeLang(lang);
    if (i18n.global.locale.value !== normalized) {
        i18n.global.locale.value = normalized;
    }
    localStorage.setItem(LANG_KEY, normalized);
    applyHtmlLang(normalized);
};

window.addEventListener("lang-changed", (event) => {
    syncI18nLang(event?.detail?.lang || getStoredLang());
});

window.addEventListener("storage", (event) => {
    if (event.key === LANG_KEY && event.newValue) {
        syncI18nLang(event.newValue);
    }
});

export default i18n;
