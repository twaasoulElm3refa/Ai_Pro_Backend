<template>
    <header class="nb-hero" :class="{ 'nb-hero-compact': hideHeader }" :dir="currentDir">
        <!-- SEO / LAZY HERO MEDIA -->
        <template v-if="!hideHeader">
            <div class="nb-hero-media" ref="heroVideoCardRef" aria-hidden="true">
                <img class="nb-hero-bg" :src="heroBackground"
                    alt="" loading="lazy"
                    decoding="async" width="1920" height="900" />

                <video
                    v-if="shouldLoadHeroVideo"
                    class="nb-hero-video"
                    :class="{ 'is-ready': heroVideoReady }"
                    :src="heroVideo"
                    autoplay
                    muted
                    loop
                    playsinline
                    preload="none"
                    @loadeddata="heroVideoReady = true"
                ></video>
            </div>

            <div class="nb-hero-overlay"></div>
        </template>

        <div class="nb-shell">
            <div class="nb-inner">
                <!-- LOGO + LINKS -->
                <div class="nb-brand-zone">
                    <a class="nb-logo" :href="homeUrl" :aria-label="isArabic ? 'العودة إلى الرئيسية' : 'Back to home'">
                        <div class="nb-logo-mark">
                            <img src="/images/Ai_logo.png" alt="AiPro Logo" width="68" height="68" fetchpriority="high"
                                decoding="async" />
                        </div>
                    </a>

                    <button type="button" class="nb-links-toggle" :class="{ active: navLinksOpen }"
                        :aria-expanded="navLinksOpen"
                        :aria-label="isArabic ? 'فتح روابط التنقل' : 'Open navigation links'"
                        @click.stop="toggleNavLinks">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>

                    <nav class="nb-center-links" :class="{ open: navLinksOpen }"
                        :aria-label="isArabic ? 'روابط التنقل الرئيسية' : 'Main navigation links'">
                        <a :href="homeUrl" class="nb-nav-link">
                            <span>{{ t("navbar.home") }}</span>
                        </a>

                        <button
                            type="button"
                            class="nb-theme-toggle"
                            :title="themeToggleTitle"
                            :aria-label="themeToggleTitle"
                            :aria-pressed="currentTheme === 'dark'"
                            @click.stop="toggleTheme"
                        >
                            <i
                                class="bi"
                                :class="
                                    currentTheme === 'dark'
                                        ? 'bi-sun-fill'
                                        : 'bi-moon-stars-fill'
                                "
                                aria-hidden="true"
                            ></i>
                        </button>
                    </nav>
                </div>

                <div class="nb-social-center" :aria-label="isArabic ? 'روابط التواصل والمصدر' : 'Social and source links'">
                    <a
                        :href="whatsappUrl"
                        class="nb-social-link"
                        target="_blank"
                        rel="noopener noreferrer"
                        :aria-label="isArabic ? 'واتساب' : 'WhatsApp'"
                    >
                        <svg viewBox="0 0 16 16" aria-hidden="true">
                            <path d="M13.601 2.326A7.85 7.85 0 0 0 8.002 0C3.582 0 0 3.58 0 7.998c0 1.41.37 2.78 1.073 3.99L0 16l4.12-1.082a7.94 7.94 0 0 0 3.882.99h.004c4.417 0 7.998-3.58 7.998-7.998a7.95 7.95 0 0 0-2.403-5.584ZM8.006 14.55h-.003a6.61 6.61 0 0 1-3.37-.922l-.242-.144-2.445.641.653-2.384-.158-.244a6.55 6.55 0 0 1-1.006-3.499c0-3.612 2.94-6.552 6.555-6.552a6.51 6.51 0 0 1 4.64 1.925 6.51 6.51 0 0 1 1.93 4.638c0 3.613-2.94 6.54-6.554 6.54Zm3.594-4.904c-.197-.099-1.166-.575-1.347-.64-.18-.066-.312-.099-.443.099-.131.197-.509.64-.624.772-.115.131-.23.148-.427.05-.197-.1-.833-.307-1.587-.98-.586-.522-.982-1.168-1.097-1.365-.115-.197-.012-.304.087-.402.089-.089.197-.23.296-.345.099-.115.131-.197.197-.328.066-.132.033-.247-.016-.346-.05-.098-.443-1.068-.607-1.462-.16-.384-.322-.332-.443-.338l-.378-.007a.73.73 0 0 0-.526.247c-.18.197-.69.673-.69 1.64 0 .966.707 1.9.805 2.03.099.132 1.39 2.122 3.368 2.975.471.203.838.324 1.124.414.472.15.902.129 1.242.078.379-.057 1.166-.476 1.33-.936.164-.46.164-.854.115-.936-.05-.082-.18-.131-.377-.23Z" />
                        </svg>
                    </a>

                    <span class="nb-social-divider"></span>

                    <a
                        :href="originalNewsUrl"
                        class="nb-social-link"
                        target="_blank"
                        rel="noopener noreferrer"
                        :aria-label="isArabic ? 'الموقع الإخباري الأصلي' : 'Original news website'"
                    >
                        <svg viewBox="0 0 16 16" aria-hidden="true">
                            <path d="M0 2.5A1.5 1.5 0 0 1 1.5 1h11A1.5 1.5 0 0 1 14 2.5v.4h.5A1.5 1.5 0 0 1 16 4.4v8.1a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 0 12.5v-10Zm1.5-.5a.5.5 0 0 0-.5.5v10a.5.5 0 0 0 .5.5h11a.5.5 0 0 0 .5-.5v-10a.5.5 0 0 0-.5-.5h-11Zm12.5 1.9v8.6a1.49 1.49 0 0 1-.085.5h.585a.5.5 0 0 0 .5-.5V4.4a.5.5 0 0 0-.5-.5H14ZM2 3h10v2H2V3Zm0 3h4v6H2V6Zm5 0h5v1H7V6Zm0 2h5v1H7V8Zm0 2h5v1H7v-1Z" />
                        </svg>
                    </a>

                    <span class="nb-social-divider"></span>

                    <a
                        :href="twitterUrl"
                        class="nb-social-link"
                        target="_blank"
                        rel="noopener noreferrer"
                        :aria-label="isArabic ? 'منصة إكس' : 'X / Twitter'"
                    >
                        <svg viewBox="0 0 16 16" aria-hidden="true">
                            <path d="M12.6.75h2.454l-5.36 6.142L16 15.25h-4.937l-3.867-5.07-4.425 5.07H.316l5.733-6.57L0 .75h5.063l3.495 4.633L12.6.75Zm-.86 13.028h1.36L4.323 2.145H2.865l8.875 11.633Z" />
                        </svg>
                    </a>
                </div>

                <div class="nb-actions">
                    <!-- Language Dropdown -->
                    <div class="nb-lang-wrap" @click.stop>
                        <button class="nb-lang-btn" type="button" @click="toggleLangDropdown">
                            <i class="bi bi-globe2"></i>
                            <span>{{ currentLanguage.nativeName }}</span>
                            <span class="nb-chevron" :class="{ open: langDropdownOpen }">▼</span>
                        </button>

                        <div v-if="langDropdownOpen" class="nb-lang-dropdown">
                            <button v-for="language in languages" :key="language.code" type="button"
                                class="nb-lang-item" :class="{ active: language.code === currentLocale }"
                                @click="changeLanguage(language.code)">
                                <span>{{ language.nativeName }}</span>
                                <small>{{ language.label }}</small>
                            </button>
                        </div>
                    </div>

                    <template v-if="isLoggedIn">
                        <button class="nb-wallet-btn" type="button" @click="goToWallet" :title="t('navbar.wallet')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <rect x="2" y="6" width="20" height="14" rx="3" stroke="white" stroke-width="1.6" />
                                <path d="M16 13a1 1 0 1 0 2 0 1 1 0 0 0-2 0Z" fill="white" />
                                <path d="M2 10h20" stroke="white" stroke-width="1.6" />
                            </svg>

                            <span v-if="WalletBalance !== null" class="nb-badge">
                                {{ WalletBalance }}
                            </span>
                        </button>
                    </template>

                    <template v-if="isLoggedIn">
                        <div class="nb-user-btn" @click.stop="toggleDropdown">
                            <div class="nb-avatar">
                                {{ userName ? userName.charAt(0) : t("navbar.user").charAt(0) }}
                            </div>

                            <span class="nb-user-name">{{ userName }}</span>

                            <span class="nb-chevron" :class="{ open: dropdownOpen }">
                                ▼
                            </span>

                            <div v-if="dropdownOpen" class="nb-dropdown">
                                <div class="nb-dd-header">
                                    <div class="nb-dd-name">{{ userName }}</div>
                                    <div class="nb-dd-label">
                                        {{ t("navbar.personalAccount") }}
                                    </div>
                                </div>

                                <a :href="profileUrl" class="nb-dd-item">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                        <circle cx="12" cy="8" r="4" stroke="#154677" stroke-width="1.6" />
                                        <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" stroke="#154677" stroke-width="1.6"
                                            stroke-linecap="round" />
                                    </svg>

                                    {{ t("navbar.profile") }}
                                </a>

                                <div class="nb-dd-sep"></div>

                                <button class="nb-dd-item danger" type="button" @click="logout">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" stroke="#154677"
                                            stroke-width="1.6" stroke-linecap="round" />
                                        <polyline points="16 17 21 12 16 7" stroke="#154677" stroke-width="1.6"
                                            stroke-linecap="round" stroke-linejoin="round" />
                                        <line x1="21" y1="12" x2="9" y2="12" stroke="#154677" stroke-width="1.6"
                                            stroke-linecap="round" />
                                    </svg>

                                    {{ t("navbar.logout") }}
                                </button>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <a :href="authUrl" class="nb-login-btn">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" stroke="white" stroke-width="1.8"
                                    stroke-linecap="round" />
                                <polyline points="10 17 15 12 10 7" stroke="white" stroke-width="1.8"
                                    stroke-linecap="round" stroke-linejoin="round" />
                                <line x1="15" y1="12" x2="3" y2="12" stroke="white" stroke-width="1.8"
                                    stroke-linecap="round" />
                            </svg>

                            {{ t("navbar.login") }}
                        </a>
                    </template>
                </div>
            </div>
        </div>

        <section v-if="!hideHeader" class="nb-hero-content">
            <div class="nb-hero-copy">
                <span class="nb-hero-badge">
                    <i class="bi bi-stars"></i>
                    {{ isArabic ? "محتوى ذكي يوفر عليك الوقت" : "Smart content that saves time" }}
                </span>

                <h1>
                    <span>
                        {{ isArabic ? "خلّي محتواك يفتح لك" : "Let your content open" }}
                    </span>

                    <strong>
                        {{ isArabic ? "أبواب الفرص" : "new opportunities" }}
                    </strong>
                </h1>

                <p>
                    {{
                        isArabic
                            ? "من فكرة بسيطة إلى محتوى جاهز يجذب الانتباه ويقنع الجمهور بخطوات ذكية وسريعة."
                            : "From a simple idea to ready-to-use content that attracts attention and helps your audience take action."
                    }}
                </p>

                <div class="nb-hero-actions">
                    <a :href="toolsUrl" class="nb-primary-cta">
                        <span>{{ isArabic ? "جرّب الأدوات الآن" : "Try Tools Now" }}</span>
                        <i class="bi bi-arrow-left-short" v-if="isArabic"></i>
                        <i class="bi bi-arrow-right-short" v-else></i>
                    </a>

                    <a :href="authUrl" class="nb-secondary-cta">
                        {{ isArabic ? "لدي حساب بالفعل" : "I already have an account" }}
                    </a>
                </div>

                <div class="nb-hero-note">
                    <strong>{{ isArabic ? "ابدأ مجانًا" : "Start free" }}</strong>
                    <span>{{ isArabic ? "واكتب أول محتوى لك خلال دقائق." : "and create your first content in minutes."
                        }}</span>
                </div>
            </div>

        </section>
    </header>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useRouter } from "vue-router";
import api from "@/services/ApiClient";
import homeService from "@/services/home/homeService";

const props = defineProps({
    hideHeader: {
        type: Boolean,
        default: false,
    },
});

const { t, locale } = useI18n();
const router = useRouter();

const heroBackground = "/images/hero.png";
const heroVideo = "/images/video.mp4";
const whatsappUrl = "https://wa.me/";
const originalNewsUrl = "https://aiarabic.com";
const twitterUrl = "https://x.com/";

const isLoggedIn = ref(false);
const WalletBalance = ref(0);
const dropdownOpen = ref(false);
const langDropdownOpen = ref(false);
const navLinksOpen = ref(false);
const THEME_STORAGE_KEY = "theme";
const currentTheme = ref("light");

const heroVideoCardRef = ref(null);
const shouldLoadHeroVideo = ref(false);
const heroVideoReady = ref(false);

let heroVideoObserver = null;
let heroVideoTimer = null;

const PROFILE_CACHE_PREFIX = "navbar_profile_cache_v1";
const WALLET_CACHE_PREFIX = "navbar_wallet_cache_v1";
const NAV_CACHE_TTL = 60 * 1000;

const languages = [
    {
        code: "ar",
        label: "Arabic",
        nativeName: "العربية",
        dir: "rtl",
    },
    {
        code: "en",
        label: "English",
        nativeName: "English",
        dir: "ltr",
    },
    {
        code: "ru",
        label: "Russian",
        nativeName: "Русский",
        dir: "ltr",
    },
    {
        code: "fr",
        label: "French",
        nativeName: "Français",
        dir: "ltr",
    },
    {
        code: "zh",
        label: "Chinese",
        nativeName: "中文",
        dir: "ltr",
    },
];

const currentLocale = computed(() => locale.value || homeService.getLang() || "ar");

const isArabic = computed(() => String(currentLocale.value || "ar").toLowerCase() === "ar");

const currentLanguage = computed(() => {
    return languages.find((language) => language.code === currentLocale.value) || languages[0];
});

const currentDir = computed(() => {
    return currentLanguage.value.dir || "rtl";
});

const normalizeTheme = (value) => {
    return String(value || "")
        .trim()
        .toLowerCase() === "dark"
        ? "dark"
        : "light";
};

const getInitialTheme = () => {
    const savedTheme = localStorage.getItem(
        THEME_STORAGE_KEY
    );

    if (
        savedTheme === "light" ||
        savedTheme === "dark"
    ) {
        return savedTheme;
    }

    return window.matchMedia?.(
        "(prefers-color-scheme: dark)"
    ).matches
        ? "dark"
        : "light";
};

const applyTheme = (
    theme,
    persist = true
) => {
    const normalizedTheme = normalizeTheme(theme);

    currentTheme.value = normalizedTheme;

    document.documentElement.setAttribute(
        "data-theme",
        normalizedTheme
    );

    document.documentElement.style.colorScheme =
        normalizedTheme;

    if (persist) {
        localStorage.setItem(
            THEME_STORAGE_KEY,
            normalizedTheme
        );
    }
};

const toggleTheme = () => {
    const nextTheme =
        currentTheme.value === "dark"
            ? "light"
            : "dark";

    applyTheme(nextTheme);
};

const themeToggleTitle = computed(() => {
    if (currentTheme.value === "dark") {
        return isArabic.value
            ? "ØªÙØ¹ÙŠÙ„ Ø§Ù„ÙˆØ¶Ø¹ Ø§Ù„ÙØ§ØªØ­"
            : "Switch to light mode";
    }

    return isArabic.value
        ? "ØªÙØ¹ÙŠÙ„ Ø§Ù„ÙˆØ¶Ø¹ Ø§Ù„Ø¯Ø§ÙƒÙ†"
        : "Switch to dark mode";
});

const homeUrl = computed(() => `/${currentLocale.value}`);
const contactUrl = computed(() => `/${currentLocale.value}/contact`);
const toolsUrl = computed(() => `/${currentLocale.value}/tool/كاتب-النصوص-الذكي`);
const profileUrl = computed(() => `/${currentLocale.value}/profile`);
const authUrl = computed(() => `/${currentLocale.value}/auth`);
const walletUrl = computed(() => `/${currentLocale.value}/wallet`);

const userName = ref(t("navbar.user"));

const scopedCacheKey = (prefix) => `${prefix}:${homeService.getLang()}`;

const clearNavbarCache = () => {
    for (let i = sessionStorage.length - 1; i >= 0; i -= 1) {
        const key = sessionStorage.key(i);

        if (!key) continue;

        if (key.startsWith(`${PROFILE_CACHE_PREFIX}:`) || key.startsWith(`${WALLET_CACHE_PREFIX}:`)) {
            sessionStorage.removeItem(key);
        }
    }
};

const syncHtmlDirection = () => {
    document.documentElement.setAttribute("lang", currentLocale.value);
    document.documentElement.setAttribute("dir", currentDir.value);
};

const replaceLocaleInPath = (newLocale) => {
    const currentPath = window.location.pathname;
    const supportedCodes = languages.map((language) => language.code);
    const pathParts = currentPath.split("/").filter(Boolean);

    if (pathParts.length && supportedCodes.includes(pathParts[0])) {
        pathParts[0] = newLocale;
        return `/${pathParts.join("/")}${window.location.search}`;
    }

    return `/${newLocale}${currentPath === "/" ? "" : currentPath}${window.location.search}`;
};

const changeLanguage = async (newLocale) => {
    if (newLocale === currentLocale.value) {
        langDropdownOpen.value = false;
        return;
    }

    const selectedLanguage = languages.find((language) => language.code === newLocale);

    if (!selectedLanguage) return;

    homeService.setLang(newLocale);
    locale.value = newLocale;
    document.documentElement.setAttribute("lang", newLocale);
    document.documentElement.setAttribute("dir", selectedLanguage.dir);

    langDropdownOpen.value = false;
    navLinksOpen.value = false;

    await router.push(replaceLocaleInPath(newLocale));

    clearNavbarCache();
    refreshUserState();
};

const toggleLangDropdown = () => {
    langDropdownOpen.value = !langDropdownOpen.value;
    dropdownOpen.value = false;
    navLinksOpen.value = false;
};

const toggleNavLinks = () => {
    navLinksOpen.value = !navLinksOpen.value;
    dropdownOpen.value = false;
    langDropdownOpen.value = false;
};

watch(locale, () => {
    syncHtmlDirection();

    if (!isLoggedIn.value) {
        userName.value = t("navbar.user");
    }
});

const readCache = (key) => {
    try {
        const raw = sessionStorage.getItem(key);

        if (!raw) return null;

        const parsed = JSON.parse(raw);

        if (!parsed?.expiresAt || parsed.expiresAt < Date.now()) {
            sessionStorage.removeItem(key);
            return null;
        }

        return parsed.value;
    } catch {
        return null;
    }
};

const writeCache = (key, value) => {
    try {
        sessionStorage.setItem(
            key,
            JSON.stringify({
                value,
                expiresAt: Date.now() + NAV_CACHE_TTL,
            })
        );
    } catch {
        // Ignore sessionStorage edge cases.
    }
};

const deferToIdle = (task) => {
    if ("requestIdleCallback" in window) {
        window.requestIdleCallback(task, { timeout: 800 });
        return;
    }

    setTimeout(task, 120);
};

const loadHeroVideo = () => {
    if (shouldLoadHeroVideo.value) return;

    shouldLoadHeroVideo.value = true;

    if (heroVideoObserver) {
        heroVideoObserver.disconnect();
        heroVideoObserver = null;
    }
};

const startHeroVideoLazyLoad = () => {
    if (props.hideHeader || shouldLoadHeroVideo.value) return;

    const target = heroVideoCardRef.value;

    if (!target) {
        heroVideoTimer = window.setTimeout(startHeroVideoLazyLoad, 300);
        return;
    }

    if ("IntersectionObserver" in window) {
        heroVideoObserver = new IntersectionObserver(
            (entries) => {
                const isVisible = entries.some((entry) => entry.isIntersecting);

                if (isVisible) {
                    loadHeroVideo();
                }
            },
            {
                root: null,
                rootMargin: "160px 0px",
                threshold: 0.05,
            }
        );

        heroVideoObserver.observe(target);
        return;
    }

    loadHeroVideo();
};

const scheduleHeroVideoLazyLoad = () => {
    if (props.hideHeader) return;

    deferToIdle(() => {
        heroVideoTimer = window.setTimeout(() => {
            startHeroVideoLazyLoad();
        }, 1800);
    });
};

const cleanupHeroVideoLazyLoad = () => {
    if (heroVideoTimer) {
        window.clearTimeout(heroVideoTimer);
        heroVideoTimer = null;
    }

    if (heroVideoObserver) {
        heroVideoObserver.disconnect();
        heroVideoObserver = null;
    }
};

const fetchWallet = async () => {
    if (!localStorage.getItem("auth_token")) return;

    const cachedBalance = readCache(scopedCacheKey(WALLET_CACHE_PREFIX));

    if (cachedBalance !== null) {
        WalletBalance.value = cachedBalance;
        return;
    }

    try {
        const res = await api.get("/users/wallet");

        if (res.data.status === "success") {
            WalletBalance.value = res.data.data?.balance ?? 0;
            writeCache(scopedCacheKey(WALLET_CACHE_PREFIX), WalletBalance.value);
        }
    } catch {
        // Ignore wallet fetch errors in navbar.
    }
};

const goToWallet = () => {
    window.location.href = walletUrl.value;
};

const fetchProfile = async () => {
    if (!localStorage.getItem("auth_token")) {
        isLoggedIn.value = false;
        userName.value = t("navbar.user");
        WalletBalance.value = null;
        return;
    }

    const cachedProfile = readCache(scopedCacheKey(PROFILE_CACHE_PREFIX));

    if (cachedProfile?.name) {
        userName.value = cachedProfile.name;
        isLoggedIn.value = true;
        return;
    }

    try {
        const res = await api.get("/users/profile");

        if (res.data.status === "success") {
            userName.value = res.data.data.user.name || t("navbar.user");
            isLoggedIn.value = true;

            writeCache(scopedCacheKey(PROFILE_CACHE_PREFIX), {
                name: userName.value,
            });
        }
    } catch {
        // Ignore profile fetch errors in navbar.
    }
};

const logout = async () => {
    try {
        await api.post("/users/logout");
    } catch {
        // Ignore logout errors in navbar.
    }

    localStorage.removeItem("user_role");
    localStorage.removeItem("auth_token");

    clearNavbarCache();

    isLoggedIn.value = false;
    userName.value = t("navbar.user");
    WalletBalance.value = null;
    dropdownOpen.value = false;
    langDropdownOpen.value = false;
    navLinksOpen.value = false;

    window.location.href = homeUrl.value;
};

const toggleDropdown = () => {
    dropdownOpen.value = !dropdownOpen.value;
    langDropdownOpen.value = false;
    navLinksOpen.value = false;
};

const handleDocumentClick = (e) => {
    if (!e.target.closest(".nb-user-btn")) {
        dropdownOpen.value = false;
    }

    if (!e.target.closest(".nb-lang-wrap")) {
        langDropdownOpen.value = false;
    }

    if (!e.target.closest(".nb-brand-zone")) {
        navLinksOpen.value = false;
    }
};

const refreshUserState = () => {
    fetchProfile();

    deferToIdle(() => {
        fetchWallet();
    });
};

const handleLangChanged = () => {
    locale.value = homeService.getLang();
    syncHtmlDirection();
    clearNavbarCache();
    refreshUserState();
};

const handleThemeStorageChange = (event) => {
    if (event.key !== THEME_STORAGE_KEY) {
        return;
    }

    applyTheme(event.newValue, false);
};

onMounted(() => {
    applyTheme(getInitialTheme());

    syncHtmlDirection();

    refreshUserState();
    scheduleHeroVideoLazyLoad();

    window.addEventListener("storage", handleThemeStorageChange);
    window.addEventListener("login", refreshUserState);
    window.addEventListener("lang-changed", handleLangChanged);
    document.addEventListener("click", handleDocumentClick);
});

onBeforeUnmount(() => {
    cleanupHeroVideoLazyLoad();

    window.removeEventListener("storage", handleThemeStorageChange);
    window.removeEventListener("login", refreshUserState);
    window.removeEventListener("lang-changed", handleLangChanged);
    document.removeEventListener("click", handleDocumentClick);
});
</script>

<style scoped>
:global(:root) {
    --app-bg: #f4f8fb;
    --app-surface: #ffffff;
    --app-surface-soft: #f8fbfe;
    --app-text: #154677;
    --app-text-muted: #5b6f84;
    --app-border: rgba(21, 70, 119, 0.12);
}

:global(html[data-theme="dark"]) {
    --app-surface-soft: var(--theme-surface-secondary);
    --app-text: var(--theme-text-primary);
}

.nb-hero {
    --ai-dark: #154677;
    --ai-light: #2ba6de;

    position: relative;
    min-height: 760px;
    overflow: hidden;
    font-family: "Cairo", sans-serif;
    background: #06111d;
}

.nb-hero-compact {
    min-height: 118px;
    background:
        linear-gradient(135deg, rgba(21, 70, 119, 0.08), rgba(43, 166, 222, 0.12)),
        #f4f8fb;
    overflow: visible;
}

.nb-hero-media {
    position: absolute;
    inset: 0;
    z-index: 1;
    overflow: hidden;
    pointer-events: none;
}

.nb-hero-bg,
.nb-hero-video {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    display: block;
    pointer-events: none;
}

.nb-hero-bg {
    z-index: 1;
}

.nb-hero-video {
    z-index: 2;
    opacity: 0;
    transition: opacity 0.5s ease;
}

.nb-hero-video.is-ready {
    opacity: 1;
}

.nb-hero-overlay {
    position: absolute;
    inset: 0;
    z-index: 2;
    pointer-events: none;
    background:
        linear-gradient(
            180deg,
            rgba(5, 12, 20, 0.46) 0%,
            rgba(5, 12, 20, 0.58) 48%,
            rgba(5, 12, 20, 0.68) 100%
        );
}

/* NAVBAR */
.nb-shell {
    position: fixed;
    top: 24px;
    left: 0;
    right: 0;
    z-index: 5000;
    max-width: 1620px;
    margin: 0 auto;
    padding: 0 24px;
    pointer-events: none;
}

.nb-inner {
    position: relative;
    pointer-events: auto;
    min-height: 84px;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 0 16px;
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 20px 55px rgba(15, 23, 42, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
}

/* LOGO + LINKS */
.nb-brand-zone {
    position: relative;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.nb-logo {
    display: flex;
    align-items: center;
    text-decoration: none;
    flex-shrink: 0;
}

.nb-logo-mark {
    width: 138px;
    height: 50px;
    background: rgba(255, 255, 255, 0.96);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(21, 70, 119, 0.08);
    box-shadow: 0 10px 20px rgba(21, 70, 119, 0.08);
}

.nb-logo-mark img {
    width: 42px;
    height: 42px;
    object-fit: contain;
}

/* NAV LINKS */
.nb-center-links {
    position: relative;
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
    padding: 6px;
    border-radius: 999px;
    background:
        linear-gradient(180deg, rgba(21, 70, 119, 0.08), rgba(43, 166, 222, 0.06)),
        rgba(238, 243, 250, 0.92);
    border: 1px solid rgba(21, 70, 119, 0.10);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.82),
        0 10px 22px rgba(21, 70, 119, 0.06);
    isolation: isolate;
}

.nb-center-links::before,
.nb-center-links::after {
    content: "";
    position: absolute;
    top: 50%;
    width: 5px;
    height: 5px;
    border-radius: 999px;
    background: var(--ai-light);
    opacity: 0.55;
    transform: translateY(-50%);
    pointer-events: none;
}

.nb-center-links::before {
    inset-inline-start: 10px;
}

.nb-center-links::after {
    inset-inline-end: 10px;
}

.nb-nav-link {
    position: relative;
    z-index: 1;
    min-height: 38px;
    padding: 0 16px;
    border-radius: 999px;
    color: var(--ai-dark);
    text-decoration: none;
    font-size: 16px;
    font-weight: 950;
    letter-spacing: 0.01em;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    transition:
        color 0.22s ease,
        transform 0.22s ease,
        box-shadow 0.22s ease;
}

.nb-nav-link span {
    position: relative;
    z-index: 3;
}

.nb-nav-link::before {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(135deg, var(--ai-dark), var(--ai-light));
    opacity: 0;
    transform: scale(0.82);
    transition:
        opacity 0.25s ease,
        transform 0.25s ease;
}

.nb-nav-link::after {
    content: "";
    position: absolute;
    left: 18px;
    right: 18px;
    bottom: 7px;
    height: 2px;
    border-radius: 999px;
    background: #ffffff;
    opacity: 0;
    transform: scaleX(0);
    transform-origin: center;
    transition:
        opacity 0.25s ease,
        transform 0.25s ease;
}

.nb-nav-link:hover {
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 10px 20px rgba(21, 70, 119, 0.12);
}

.nb-nav-link:hover::before {
    opacity: 1;
    transform: scale(1);
}

.nb-nav-link:hover::after {
    opacity: 1;
    transform: scaleX(1);
}

.nb-theme-toggle {
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    padding: 0;
    border-radius: 12px;
    border: 1px solid rgba(21, 70, 119, 0.14);
    background: rgba(21, 70, 119, 0.05);
    color: #154677;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font: inherit;
    cursor: pointer;
    box-shadow: none;
    transition:
        transform 0.2s ease,
        background-color 0.2s ease,
        color 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.nb-theme-toggle i {
    font-size: 16px;
    line-height: 1;
}

.nb-theme-toggle:hover {
    transform: translateY(-1px);
    color: #ffffff;
    background: #154677;
    border-color: #154677;
    box-shadow: 0 9px 20px rgba(21, 70, 119, 0.17);
}

.nb-theme-toggle:active {
    transform: translateY(0);
}

.nb-theme-toggle:focus-visible {
    outline: 3px solid rgba(43, 166, 222, 0.25);
    outline-offset: 2px;
}

/* MOBILE LINKS TOGGLE */
.nb-links-toggle {
    display: none;
    width: 42px;
    height: 42px;
    border: none;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--ai-dark), var(--ai-light));
    cursor: pointer;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 5px;
    box-shadow: 0 12px 24px rgba(21, 70, 119, 0.16);
}

.nb-links-toggle span {
    width: 18px;
    height: 2px;
    border-radius: 999px;
    background: #ffffff;
    transition:
        transform 0.22s ease,
        opacity 0.22s ease;
}

.nb-links-toggle.active span:nth-child(1) {
    transform: translateY(7px) rotate(45deg);
}

.nb-links-toggle.active span:nth-child(2) {
    opacity: 0;
}

.nb-links-toggle.active span:nth-child(3) {
    transform: translateY(-7px) rotate(-45deg);
}


/* CENTER SOCIAL ICONS */
.nb-social-center {
    position: absolute;
    left: 50%;
    top: 50%;
    z-index: 3;
    transform: translate(-50%, -50%);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 18px;
    pointer-events: auto;
}

.nb-social-link {
    width: 58px;
    height: 58px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--ai-dark);
    text-decoration: none;
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(239, 247, 253, 0.96));
    border: 1px solid rgba(21, 70, 119, 0.14);
    box-shadow:
        0 12px 26px rgba(21, 70, 119, 0.10),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
    transition:
        transform 0.22s ease,
        box-shadow 0.22s ease,
        color 0.22s ease,
        border-color 0.22s ease,
        background 0.22s ease;
}

.nb-social-link svg {
    width: 27px;
    height: 27px;
    display: block;
    fill: currentColor;
}

.nb-social-link:hover {
    color: var(--ai-light);
    transform: translateY(-2px);
    border-color: rgba(43, 166, 222, 0.42);
    background:
        linear-gradient(180deg, #ffffff, rgba(232, 247, 255, 0.98));
    box-shadow:
        0 18px 34px rgba(43, 166, 222, 0.18),
        inset 0 1px 0 rgba(255, 255, 255, 1);
}

.nb-social-divider {
    width: 1.5px;
    height: 38px;
    border-radius: 999px;
    background: linear-gradient(
        180deg,
        transparent,
        rgba(43, 166, 222, 0.72),
        transparent
    );
    flex: 0 0 auto;
}

/* ACTIONS */
.nb-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
    margin-inline-start: auto;
}

.nb-lang-wrap {
    position: relative;
}

.nb-lang-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #1f2937;
    background: #eef3fa;
    border: 1px solid rgba(21, 70, 119, 0.08);
    font-size: 13px;
    font-weight: 900;
    padding: 10px 14px;
    border-radius: 999px;
    white-space: nowrap;
    cursor: pointer;
    transition:
        transform 0.2s ease,
        background-color 0.2s ease,
        color 0.2s ease;
}

.nb-lang-btn i {
    color: var(--ai-dark);
    font-size: 15px;
}

.nb-lang-btn:hover {
    background: #f4fbff;
    color: var(--ai-dark);
    transform: translateY(-1px);
}

.nb-lang-dropdown {
    position: absolute;
    top: calc(100% + 12px);
    min-width: 180px;
    background: rgba(255, 255, 255, 0.98);
    border-radius: 1rem;
    box-shadow: 0 24px 50px rgba(21, 70, 119, 0.16);
    border: 1px solid rgba(21, 70, 119, 0.1);
    overflow: hidden;
    z-index: 9999;
}

.nb-hero[dir="rtl"] .nb-lang-dropdown {
    left: 0;
    right: auto;
}

.nb-hero[dir="ltr"] .nb-lang-dropdown {
    right: 0;
    left: auto;
}

.nb-lang-item {
    width: 100%;
    border: none;
    background: transparent;
    padding: 12px 14px;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    gap: 3px;
    color: var(--ai-dark);
    font-weight: 800;
    transition: background 0.2s ease;
}

.nb-hero[dir="rtl"] .nb-lang-item {
    text-align: right;
    align-items: flex-end;
}

.nb-hero[dir="ltr"] .nb-lang-item {
    text-align: left;
    align-items: flex-start;
}

.nb-lang-item small {
    font-size: 11px;
    color: #5f7288;
    font-weight: 600;
}

.nb-lang-item:hover,
.nb-lang-item.active {
    background: #f4fbff;
}

.nb-wallet-btn {
    position: relative;
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--ai-dark), var(--ai-light));
    border: 1px solid rgba(21, 70, 119, 0.16);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 12px 24px rgba(21, 70, 119, 0.18);
    transition:
        transform 0.2s ease,
        background-color 0.2s ease,
        box-shadow 0.2s ease;
}

.nb-wallet-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 16px 28px rgba(43, 166, 222, 0.24);
}

.nb-badge {
    position: absolute;
    top: -7px;
    left: -7px;
    min-width: 21px;
    height: 21px;
    background: #ffffff;
    color: var(--ai-dark);
    font-size: 10px;
    font-weight: 900;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
    border: 2px solid var(--ai-light);
}

.nb-user-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 13px;
    border-radius: 999px;
    background: #eef3fa;
    border: 1px solid rgba(21, 70, 119, 0.08);
    color: var(--ai-dark);
    cursor: pointer;
    font-size: 14px;
    font-weight: 800;
    position: relative;
    white-space: nowrap;
    transition:
        transform 0.2s ease,
        background-color 0.2s ease;
}

.nb-user-btn:hover {
    background: #f4fbff;
    transform: translateY(-1px);
}

.nb-avatar {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    background: linear-gradient(135deg, var(--ai-dark), var(--ai-light));
    color: #ffffff;
    font-size: 13px;
    font-weight: 900;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nb-user-name {
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.nb-chevron {
    font-size: 10px;
    opacity: 0.75;
    transition: transform 0.2s ease;
}

.nb-chevron.open {
    transform: rotate(180deg);
}

.nb-dropdown {
    position: absolute;
    top: calc(100% + 12px);
    left: 0;
    min-width: 220px;
    background: rgba(255, 255, 255, 0.98);
    border-radius: 1.25rem;
    box-shadow: 0 24px 50px rgba(21, 70, 119, 0.16);
    border: 1px solid rgba(21, 70, 119, 0.1);
    overflow: hidden;
    z-index: 9999;
}

.nb-dd-header {
    padding: 16px 18px 12px;
    border-bottom: 1px solid rgba(21, 70, 119, 0.08);
}

.nb-dd-name {
    font-size: 14px;
    font-weight: 800;
    color: var(--ai-dark);
}

.nb-dd-label {
    font-size: 11px;
    color: #5f7288;
    margin-top: 4px;
}

.nb-dd-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 18px;
    font-size: 13px;
    color: var(--ai-dark);
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    border: none;
    background: none;
    width: 100%;
    text-align: right;
    transition:
        background 0.2s ease,
        color 0.2s ease;
}

.nb-dd-item:hover {
    background: #f4fbff;
    color: var(--ai-light);
}

.nb-dd-item.danger {
    color: var(--ai-dark);
}

.nb-dd-item.danger:hover {
    background: rgba(43, 166, 222, 0.1);
    color: var(--ai-light);
}

.nb-dd-sep {
    height: 1px;
    background: rgba(21, 70, 119, 0.08);
    margin: 2px 0;
}

.nb-login-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 11px 18px;
    border-radius: 999px;
    background: linear-gradient(135deg, var(--ai-dark), var(--ai-light));
    color: #ffffff;
    font-size: 14px;
    font-weight: 900;
    text-decoration: none;
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease,
        background-color 0.2s ease;
    white-space: nowrap;
    box-shadow: 0 12px 24px rgba(21, 70, 119, 0.18);
}

.nb-login-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 16px 30px rgba(43, 166, 222, 0.24);
}

/* HERO CONTENT */
.nb-hero-content {
    position: relative;
    z-index: 5;
    width: 100%;
    max-width: 1500px;
    margin: 0 auto;
    padding: 170px 28px 90px;
    min-height: 760px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.nb-hero-copy {
    position: relative;
    z-index: 5;
    width: min(900px, 100%);
    margin: 0 auto;
    color: #ffffff;
    text-align: center;
}

.nb-hero[dir="rtl"] .nb-hero-copy {
    text-align: center;
}

.nb-hero[dir="ltr"] .nb-hero-copy {
    text-align: center;
}

.nb-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.10);
    color: #ffffff;
    padding: 9px 18px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 900;
    border: 1px solid rgba(255, 255, 255, 0.18);
    box-shadow: 0 18px 36px rgba(0, 0, 0, 0.18);
    backdrop-filter: blur(10px);
}

.nb-hero-badge i {
    color: var(--ai-light);
}

.nb-hero-copy h1 {
    margin: 26px 0 0;
    font-size: clamp(48px, 5.5vw, 82px);
    line-height: 1.05;
    font-weight: 950;
    letter-spacing: 0;
    color: #ffffff;
}

.nb-hero-copy h1 span {
    display: block;
}

.nb-hero-copy h1 strong {
    display: block;
    font-weight: 950;
    background: linear-gradient(90deg, #62c8f0 0%, #2ba6de 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.nb-hero-copy p {
    margin: 26px 0 0;
    max-width: 760px;
    margin-inline: auto;
    font-size: 19px;
    line-height: 1.9;
    color: rgba(255, 255, 255, 0.82);
    font-weight: 500;
}

.nb-hero-actions {
    margin-top: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    flex-wrap: wrap;
}

.nb-primary-cta,
.nb-secondary-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 58px;
    padding: 0 34px;
    border-radius: 999px;
    font-size: 16px;
    font-weight: 900;
    text-decoration: none;
    transition:
        transform 0.22s ease,
        box-shadow 0.22s ease,
        background 0.22s ease,
        color 0.22s ease;
}

.nb-primary-cta {
    gap: 8px;
    background: linear-gradient(135deg, var(--ai-dark) 0%, var(--ai-light) 100%);
    color: #ffffff;
    box-shadow: 0 20px 42px rgba(21, 70, 119, 0.28);
}

.nb-primary-cta i {
    font-size: 24px;
    line-height: 1;
}

.nb-primary-cta:hover {
    background: linear-gradient(135deg, var(--ai-light) 0%, var(--ai-dark) 100%);
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 24px 48px rgba(43, 166, 222, 0.32);
}

.nb-secondary-cta {
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.32);
    box-shadow: 0 16px 32px rgba(0, 0, 0, 0.16);
    backdrop-filter: blur(8px);
}

.nb-secondary-cta:hover {
    transform: translateY(-2px);
    background: rgba(255, 255, 255, 0.14);
    color: #ffffff;
    box-shadow: 0 20px 38px rgba(0, 0, 0, 0.20);
}

.nb-hero-note {
    margin-top: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    color: rgba(255, 255, 255, 0.72);
    font-size: 14px;
}

.nb-hero-note strong {
    color: #62c8f0;
    font-weight: 950;
}

html[data-theme="dark"] .nb-hero,
html[data-theme="dark"] .nb-hero-compact {
    background:
        radial-gradient(
            circle at 18% 18%,
            rgba(43, 166, 222, 0.08),
            transparent 30%
        ),
        radial-gradient(
            circle at 80% 18%,
            rgba(21, 70, 119, 0.10),
            transparent 34%
        ),
        var(--theme-bg);
    color: var(--theme-text-primary);
}

html[data-theme="dark"] .nb-hero-overlay {
    background:
        linear-gradient(
            180deg,
            rgba(5, 12, 20, 0.50) 0%,
            rgba(5, 12, 20, 0.60) 48%,
            rgba(5, 12, 20, 0.70) 100%
        );
}

html[data-theme="dark"] .nb-inner {
    background: rgba(27, 31, 35, 0.96);
    border-color: var(--theme-border);
    box-shadow: 0 20px 55px var(--theme-shadow);
}

html[data-theme="dark"] .nb-logo-mark {
    background: var(--theme-surface-secondary);
    border-color: var(--theme-border);
    box-shadow: 0 10px 20px var(--theme-shadow);
}

html[data-theme="dark"] .nb-center-links {
    background: var(--theme-surface-secondary);
    border-color: var(--theme-border);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
}

html[data-theme="dark"] .nb-nav-link {
    color: var(--theme-text-secondary);
}

html[data-theme="dark"] .nb-nav-link:hover,
html[data-theme="dark"] .nb-nav-link.active {
    color: var(--theme-accent);
    background: rgba(43, 166, 222, 0.10);
    box-shadow: none;
}

html[data-theme="dark"] .nb-nav-link::after {
    background: var(--theme-accent);
}

html[data-theme="dark"] .nb-theme-toggle,
html[data-theme="dark"] .nb-lang-btn,
html[data-theme="dark"] .nb-user-btn {
    color: var(--theme-text-primary);
    background: var(--theme-surface-secondary);
    border-color: var(--theme-border);
}

html[data-theme="dark"] .nb-theme-toggle:hover,
html[data-theme="dark"] .nb-lang-btn:hover,
html[data-theme="dark"] .nb-user-btn:hover {
    color: var(--theme-accent);
    background: var(--theme-hover);
    border-color: rgba(43, 166, 222, 0.34);
}

html[data-theme="dark"] .nb-lang-btn i {
    color: var(--theme-accent);
}

html[data-theme="dark"] .nb-lang-dropdown,
html[data-theme="dark"] .nb-dropdown {
    background: var(--theme-surface-elevated);
    border-color: var(--theme-border-strong);
    box-shadow: 0 24px 54px rgba(0, 0, 0, 0.34);
}

html[data-theme="dark"] .nb-lang-item,
html[data-theme="dark"] .nb-dd-item {
    color: var(--theme-text-secondary);
}

html[data-theme="dark"] .nb-lang-item:hover,
html[data-theme="dark"] .nb-lang-item.active,
html[data-theme="dark"] .nb-dd-item:hover {
    color: var(--theme-text-primary);
    background: var(--theme-hover);
}

html[data-theme="dark"] .nb-dd-item svg [stroke] {
    stroke: currentColor;
}

html[data-theme="dark"] .nb-dd-name,
html[data-theme="dark"] .nb-user-name {
    color: var(--theme-text-primary);
}

html[data-theme="dark"] .nb-dd-label,
html[data-theme="dark"] .nb-lang-item small {
    color: var(--theme-text-muted);
}

html[data-theme="dark"] .nb-dd-sep,
html[data-theme="dark"] .nb-social-divider {
    background: var(--theme-border-strong);
}

html[data-theme="dark"] .nb-social-link {
    color: var(--theme-text-secondary);
    background: var(--theme-surface-secondary);
    border-color: var(--theme-border);
    box-shadow: 0 12px 26px var(--theme-shadow);
}

html[data-theme="dark"] .nb-social-link:hover,
html[data-theme="dark"] .nb-social-link:focus-visible {
    color: var(--theme-accent);
    background: var(--theme-hover);
    border-color: rgba(43, 166, 222, 0.40);
    box-shadow: 0 18px 34px var(--theme-shadow);
}

html[data-theme="dark"] .nb-wallet-btn,
html[data-theme="dark"] .nb-login-btn {
    background: linear-gradient(135deg, #154677, #2ba6de);
    color: #ffffff;
}

html[data-theme="dark"] .nb-badge {
    background: var(--theme-surface-elevated);
    color: var(--theme-text-primary);
    border-color: var(--theme-accent);
}

html[data-theme="dark"] .nb-hero-copy,
html[data-theme="dark"] .nb-hero-copy h1 {
    color: #ffffff;
}

html[data-theme="dark"] .nb-hero-copy p,
html[data-theme="dark"] .nb-hero-note {
    color: rgba(255, 255, 255, 0.82);
}

html[data-theme="dark"] .nb-hero-badge,
html[data-theme="dark"] .nb-secondary-cta {
    background: rgba(255, 255, 255, 0.10);
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.24);
    box-shadow: 0 16px 32px rgba(0, 0, 0, 0.20);
}

html[data-theme="dark"] .nb-secondary-cta:hover {
    background: rgba(255, 255, 255, 0.14);
    color: #ffffff;
}

/* RESPONSIVE */

@media (max-width: 768px) {
    .nb-theme-toggle {
        width: 36px;
        height: 36px;
        flex-basis: 36px;
    }
}

@media (max-width: 1280px) {
    .nb-social-center {
        gap: 13px;
    }

    .nb-social-link {
        width: 52px;
        height: 52px;
    }

    .nb-social-link svg {
        width: 24px;
        height: 24px;
    }

    .nb-social-divider {
        height: 32px;
    }
}

@media (max-width: 1180px) {
    .nb-nav-link {
        font-size: 12px;
        padding-inline: 13px;
    }

    .nb-user-name {
        display: none;
    }
}

@media (max-width: 920px) {
    .nb-social-center {
        display: none;
    }

    .nb-shell {
        top: 14px;
        padding: 0 14px;
        max-width: 100%;
    }

    .nb-inner {
        min-height: 72px;
        padding: 10px 12px;
        flex-wrap: nowrap;
        border-radius: 20px;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        width: 100%;
    }

    .nb-brand-zone {
        gap: 8px;
        min-width: 0;
        flex: 0 1 auto;
    }

    .nb-logo-mark {
        width: 92px;
        height: 46px;
    }

    .nb-center-links {
        position: absolute;
        top: calc(100% + 12px);
        inset-inline-start: 0;
        display: grid;
        grid-template-columns: 1fr;
        gap: 8px;
        min-width: 190px;
        padding: 10px;
        border-radius: 20px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-8px) scale(0.98);
        transform-origin: top;
        transition:
            opacity 0.22s ease,
            visibility 0.22s ease,
            transform 0.22s ease;
    }

    .nb-center-links.open {
        opacity: 1;
        visibility: visible;
        transform: translateY(0) scale(1);
    }

    .nb-center-links::before,
    .nb-center-links::after {
        display: none;
    }

    .nb-nav-link {
        justify-content: flex-start;
        width: 100%;
        min-height: 42px;
        padding-inline: 16px;
    }

    .nb-links-toggle {
        display: inline-flex;
        flex: 0 0 42px;
    }

    .nb-actions {
        margin-inline-start: auto;
        min-width: 0;
        flex: 0 0 auto;
    }

    .nb-hero-content {
        padding: 155px 18px 90px;
        text-align: center;
    }

    .nb-hero-copy {
        margin: 0 auto;
    }

    .nb-hero-copy p {
        margin-inline: auto;
    }

    .nb-hero-actions,
    .nb-hero-note {
        justify-content: center;
    }

    .nb-hero-compact {
        min-height: 104px;
    }
}

@media (max-width: 640px) {
    .nb-hero {
        min-height: 680px;
    }

    .nb-hero-compact {
        min-height: 90px;
    }

    .nb-shell {
        top: 10px;
        padding: 0 8px;
        max-width: 100%;
    }

    .nb-inner {
        min-height: 64px;
        padding: 8px 10px;
        gap: 8px;
        border-radius: 18px;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: space-between;
        width: 100%;
    }

    .nb-brand-zone {
        gap: 6px;
        min-width: 0;
        flex: 0 1 auto;
    }

    .nb-logo-mark {
        width: 68px;
        height: 40px;
        border-radius: 12px;
    }

    .nb-logo-mark img {
        width: 32px;
        height: 32px;
    }

    .nb-links-toggle {
        width: 40px;
        height: 40px;
        border-radius: 13px;
        flex: 0 0 40px;
    }

    .nb-links-toggle span {
        width: 17px;
    }

    .nb-actions {
        gap: 6px;
        margin-inline-start: 0;
        flex: 0 0 auto;
        min-width: 0;
    }

    .nb-lang-btn {
        width: 40px;
        height: 40px;
        padding: 0;
        justify-content: center;
        border-radius: 13px;
        gap: 0;
    }

    .nb-lang-btn span:not(.nb-chevron) {
        display: none;
    }

    .nb-lang-btn .nb-chevron {
        display: none;
    }

    .nb-wallet-btn {
        width: 40px;
        height: 40px;
        border-radius: 13px;
        flex: 0 0 40px;
    }

    .nb-wallet-btn svg {
        width: 17px;
        height: 17px;
    }

    .nb-badge {
        top: -6px;
        left: -5px;
        min-width: 18px;
        height: 18px;
        font-size: 9px;
        padding: 0 4px;
        border-width: 1.5px;
    }

    .nb-user-btn {
        width: 40px;
        height: 40px;
        padding: 0;
        border-radius: 13px;
        justify-content: center;
        gap: 0;
    }

    .nb-avatar {
        width: 30px;
        height: 30px;
        font-size: 12px;
    }

    .nb-user-name,
    .nb-user-btn > .nb-chevron {
        display: none;
    }

    .nb-login-btn {
        width: 40px;
        height: 40px;
        padding: 0;
        border-radius: 13px;
        justify-content: center;
        font-size: 0;
        gap: 0;
        flex: 0 0 40px;
    }

    .nb-login-btn svg {
        width: 17px;
        height: 17px;
    }

    .nb-center-links {
        top: calc(100% + 10px);
        inset-inline-start: 0;
        min-width: min(220px, calc(100vw - 32px));
        max-width: calc(100vw - 32px);
    }

    .nb-lang-dropdown {
        top: calc(100% + 10px);
        min-width: 150px;
        max-width: calc(100vw - 24px);
    }

    .nb-hero[dir="rtl"] .nb-lang-dropdown {
        left: 0;
        right: auto;
    }

    .nb-hero[dir="ltr"] .nb-lang-dropdown {
        right: 0;
        left: auto;
    }

    .nb-dropdown {
        top: calc(100% + 10px);
        left: auto;
        right: 0;
        min-width: 190px;
        max-width: calc(100vw - 24px);
    }

    .nb-hero[dir="rtl"] .nb-dropdown {
        left: 0;
        right: auto;
    }

    .nb-hero[dir="ltr"] .nb-dropdown {
        right: 0;
        left: auto;
    }

    .nb-hero-content {
        min-height: 680px;
        padding: 135px 20px 60px;
    }

    .nb-hero-copy h1 {
        font-size: clamp(36px, 11vw, 52px);
        letter-spacing: 0;
    }

    .nb-hero-copy p {
        font-size: 15px;
        line-height: 1.8;
    }

    .nb-primary-cta,
    .nb-secondary-cta {
        width: 100%;
        min-height: 54px;
    }

    .nb-hero-note {
        flex-direction: column;
        gap: 4px;
        font-size: 13px;
    }

}

@media (max-width: 420px) {
    .nb-shell {
        padding: 0 7px;
    }

    .nb-inner {
        padding: 7px 8px;
        gap: 6px;
        border-radius: 16px;
    }

    .nb-logo-mark {
        width: 60px;
        height: 38px;
    }

    .nb-logo-mark img {
        width: 29px;
        height: 29px;
    }

    .nb-links-toggle,
    .nb-lang-btn,
    .nb-wallet-btn,
    .nb-user-btn,
    .nb-login-btn {
        width: 38px;
        height: 38px;
        border-radius: 12px;
    }

    .nb-actions {
        gap: 5px;
    }

    .nb-badge {
        min-width: 17px;
        height: 17px;
        font-size: 8px;
    }
}

/* =========================
   PREMIUM NAVBAR MOTION
========================= */
@keyframes nbNavEnter {
    from {
        opacity: 0;
        transform: translateY(-18px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes nbSocialEnter {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.82);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes nbDividerEnter {
    from {
        opacity: 0;
        transform: scaleY(0.4);
    }

    to {
        opacity: 0.72;
        transform: scaleY(1);
    }
}

@keyframes nbHeroFadeUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.nb-inner {
    animation: nbNavEnter 0.65s cubic-bezier(0.22, 1, 0.36, 1) backwards;
}

.nb-social-link {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    animation: nbSocialEnter 0.48s cubic-bezier(0.22, 1, 0.36, 1) backwards;
}

.nb-social-link:nth-of-type(1) { animation-delay: 0.25s; }
.nb-social-link:nth-of-type(2) { animation-delay: 0.36s; }
.nb-social-link:nth-of-type(3) { animation-delay: 0.47s; }

.nb-social-link::before {
    content: "";
    position: absolute;
    z-index: 0;
    top: -30%;
    bottom: -30%;
    left: -78%;
    width: 46%;
    background: linear-gradient(
        110deg,
        transparent 0%,
        rgba(255, 255, 255, 0.24) 30%,
        rgba(43, 166, 222, 0.18) 52%,
        rgba(255, 255, 255, 0.42) 72%,
        transparent 100%
    );
    transform: skewX(-16deg);
    transition: left 0.58s cubic-bezier(0.22, 1, 0.36, 1);
    pointer-events: none;
}

.nb-social-link svg {
    position: relative;
    z-index: 1;
    transition: transform 0.26s cubic-bezier(0.22, 1, 0.36, 1);
}

.nb-social-link:hover,
.nb-social-link:focus-visible {
    color: var(--ai-light);
    transform: translateY(-4px) scale(1.08);
    border-color: rgba(43, 166, 222, 0.48);
    background: linear-gradient(180deg, #ffffff, rgba(232, 247, 255, 0.98));
    box-shadow:
        0 19px 38px rgba(43, 166, 222, 0.22),
        0 0 0 6px rgba(43, 166, 222, 0.06),
        inset 0 1px 0 #ffffff;
}

.nb-social-link:focus-visible {
    outline: 3px solid rgba(43, 166, 222, 0.24);
    outline-offset: 3px;
}

.nb-social-link:hover::before,
.nb-social-link:focus-visible::before {
    left: 132%;
}

.nb-social-link:hover svg,
.nb-social-link:focus-visible svg {
    transform: rotate(3deg) scale(1.04);
}

.nb-social-divider {
    opacity: 0.72;
    transform-origin: center;
    animation: nbDividerEnter 0.46s 0.54s cubic-bezier(0.22, 1, 0.36, 1) backwards;
    transition:
        opacity 0.25s ease,
        filter 0.25s ease;
}

.nb-social-center:hover .nb-social-divider {
    opacity: 1;
    filter: drop-shadow(0 0 5px rgba(43, 166, 222, 0.40));
}

.nb-hero-badge,
.nb-hero-copy h1,
.nb-hero-copy p,
.nb-hero-actions,
.nb-hero-note {
    animation: nbHeroFadeUp 0.62s cubic-bezier(0.22, 1, 0.36, 1) backwards;
}

.nb-hero-badge { animation-delay: 0.18s; }
.nb-hero-copy h1 { animation-delay: 0.28s; }
.nb-hero-copy p { animation-delay: 0.38s; }
.nb-hero-actions { animation-delay: 0.48s; }
.nb-hero-note { animation-delay: 0.58s; }

@media (prefers-reduced-motion: reduce) {
    .nb-inner,
    .nb-social-link,
    .nb-social-divider,
    .nb-hero-badge,
    .nb-hero-copy h1,
    .nb-hero-copy p,
    .nb-hero-actions,
    .nb-hero-note,
    .nb-hero-overlay {
        animation: none !important;
        transition-duration: 0.01ms !important;
    }
}


</style>
