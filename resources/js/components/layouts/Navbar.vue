<template>
    <header
        class="nb-hero"
        :class="{ 'nb-hero-compact': hideHeader }"
        :dir="currentDir"
    >
        <!-- SEO / LAZY HERO IMAGE -->
        <template v-if="!hideHeader">
            <img
                class="nb-hero-bg"
                :src="heroBackground"
                :alt="isArabic ? 'خلفية ذكاء اصطناعي لأدوات AiPro' : 'AI tools background for AiPro'"
                loading="lazy"
                decoding="async"
                width="1920"
                height="900"
            />

            <div class="nb-hero-overlay"></div>
        </template>

        <div class="nb-shell">
            <div class="nb-inner">
                <!-- LOGO + LINKS -->
                <div class="nb-brand-zone">
                    <a
                        class="nb-logo"
                        :href="homeUrl"
                        :aria-label="isArabic ? 'العودة إلى الرئيسية' : 'Back to home'"
                    >
                        <div class="nb-logo-mark">
                            <img
                                src="/images/Ai_logo.png"
                                alt="AiPro Logo"
                                width="42"
                                height="42"
                                fetchpriority="high"
                                decoding="async"
                            />
                        </div>
                    </a>

                    <button
                        type="button"
                        class="nb-links-toggle"
                        :class="{ active: navLinksOpen }"
                        :aria-expanded="navLinksOpen"
                        :aria-label="isArabic ? 'فتح روابط التنقل' : 'Open navigation links'"
                        @click.stop="toggleNavLinks"
                    >
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>

                    <nav
                        class="nb-center-links"
                        :class="{ open: navLinksOpen }"
                        :aria-label="isArabic ? 'روابط التنقل الرئيسية' : 'Main navigation links'"
                    >
                        <a :href="homeUrl" class="nb-nav-link">
                            <span>{{ t("navbar.home") }}</span>
                        </a>

                        <!-- <a :href="toolsUrl" class="nb-nav-link">
                            <span>{{ isArabic ? "الأدوات" : "Tools" }}</span>
                        </a>

                        <a :href="contactUrl" class="nb-nav-link">
                            <span>{{ isArabic ? "تواصل معنا" : "Contact Us" }}</span>
                        </a> -->
                    </nav>
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
                            <button
                                v-for="language in languages"
                                :key="language.code"
                                type="button"
                                class="nb-lang-item"
                                :class="{ active: language.code === currentLocale }"
                                @click="changeLanguage(language.code)"
                            >
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
                                        <path
                                            d="M4 20c0-4 3.6-7 8-7s8 3 8 7"
                                            stroke="#154677"
                                            stroke-width="1.6"
                                            stroke-linecap="round"
                                        />
                                    </svg>

                                    {{ t("navbar.profile") }}
                                </a>

                                <div class="nb-dd-sep"></div>

                                <button class="nb-dd-item danger" type="button" @click="logout">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                        <path
                                            d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"
                                            stroke="#154677"
                                            stroke-width="1.6"
                                            stroke-linecap="round"
                                        />
                                        <polyline
                                            points="16 17 21 12 16 7"
                                            stroke="#154677"
                                            stroke-width="1.6"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                        <line
                                            x1="21"
                                            y1="12"
                                            x2="9"
                                            y2="12"
                                            stroke="#154677"
                                            stroke-width="1.6"
                                            stroke-linecap="round"
                                        />
                                    </svg>

                                    {{ t("navbar.logout") }}
                                </button>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <a :href="authUrl" class="nb-login-btn">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                                <path
                                    d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"
                                    stroke="white"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                />
                                <polyline
                                    points="10 17 15 12 10 7"
                                    stroke="white"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <line
                                    x1="15"
                                    y1="12"
                                    x2="3"
                                    y2="12"
                                    stroke="white"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                />
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
                    {{ isArabic ? "منصة أدوات ذكاء اصطناعي" : "AI Tools Platform" }}
                </span>

                <h1>
                    {{ isArabic ? "أدوات ذكية لإنجاز أسرع" : "Smart AI Tools for Faster Work" }}
                </h1>

                <p>
                    {{
                        isArabic
                            ? "اكتب، لخص، حرّر، ونظّم مهامك من مكان واحد بتجربة بسيطة وسريعة."
                            : "Write, summarize, edit, and organize your tasks from one simple workspace."
                    }}
                </p>

                <div class="nb-hero-actions">
                    <a :href="toolsUrl" class="nb-primary-cta">
                        {{ isArabic ? "استكشف الأدوات" : "Explore Tools" }}
                    </a>

                    <a :href="authUrl" class="nb-secondary-cta">
                        {{ isArabic ? "ابدأ الآن" : "Get Started" }}
                    </a>
                </div>
            </div>
        </section>
    </header>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed, watch } from "vue";
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

const isLoggedIn = ref(false);
const WalletBalance = ref(0);
const dropdownOpen = ref(false);
const langDropdownOpen = ref(false);
const navLinksOpen = ref(false);

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
        // Ignore logout request errors and continue local cleanup.
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

onMounted(() => {
    syncHtmlDirection();

    refreshUserState();

    window.addEventListener("login", refreshUserState);
    window.addEventListener("lang-changed", handleLangChanged);
    document.addEventListener("click", handleDocumentClick);
});

onBeforeUnmount(() => {
    window.removeEventListener("login", refreshUserState);
    window.removeEventListener("lang-changed", handleLangChanged);
    document.removeEventListener("click", handleDocumentClick);
});
</script>

<style scoped>
.nb-hero {
    position: relative;
    min-height: 760px;
    overflow: hidden;
    font-family: "Cairo", sans-serif;
    background: #154677;
}

.nb-hero-compact {
    min-height: 118px;
    background: #f4f8fb;
    overflow: visible;
}

.nb-hero-bg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    z-index: 1;
    transform: scale(1.02);
}

.nb-hero-overlay {
    position: absolute;
    inset: 0;
    z-index: 2;
    background:
        linear-gradient(180deg, rgba(21, 70, 119, 0.28) 0%, rgba(21, 70, 119, 0.42) 52%, rgba(248, 250, 252, 1) 100%),
        radial-gradient(circle at 70% 20%, rgba(43, 166, 222, 0.22), transparent 34%);
}

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
    background: #2ba6de;
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
    color: #154677;
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
    background: linear-gradient(135deg, #154677, #2ba6de);
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

/* MOBILE LINKS TOGGLE */
.nb-links-toggle {
    display: none;
    width: 42px;
    height: 42px;
    border: none;
    border-radius: 14px;
    background: #154677;
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
    transition: transform 0.2s ease, background-color 0.2s ease;
}

.nb-lang-btn i {
    color: #154677;
    font-size: 15px;
}

.nb-lang-btn:hover {
    background: #f4fbff;
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
    color: #154677;
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
    background: #154677;
    border: 1px solid rgba(21, 70, 119, 0.16);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 12px 24px rgba(21, 70, 119, 0.18);
    transition: transform 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
}

.nb-wallet-btn:hover {
    background: #2ba6de;
    transform: translateY(-1px);
    box-shadow: 0 16px 28px rgba(21, 70, 119, 0.22);
}

.nb-badge {
    position: absolute;
    top: -7px;
    left: -7px;
    min-width: 21px;
    height: 21px;
    background: #ffffff;
    color: #154677;
    font-size: 10px;
    font-weight: 900;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
    border: 2px solid #2ba6de;
}

.nb-user-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 13px;
    border-radius: 999px;
    background: #eef3fa;
    border: 1px solid rgba(21, 70, 119, 0.08);
    color: #154677;
    cursor: pointer;
    font-size: 14px;
    font-weight: 800;
    position: relative;
    white-space: nowrap;
    transition: transform 0.2s ease, background-color 0.2s ease;
}

.nb-user-btn:hover {
    background: #f4fbff;
    transform: translateY(-1px);
}

.nb-avatar {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    background: #154677;
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
    color: #154677;
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
    color: #154677;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    border: none;
    background: none;
    width: 100%;
    text-align: right;
    transition: background 0.2s ease;
}

.nb-dd-item:hover {
    background: #f4fbff;
}

.nb-dd-item.danger {
    color: #154677;
}

.nb-dd-item.danger:hover {
    background: rgba(43, 166, 222, 0.1);
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
    background: #154677;
    color: #ffffff;
    font-size: 14px;
    font-weight: 900;
    text-decoration: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    white-space: nowrap;
    box-shadow: 0 12px 24px rgba(21, 70, 119, 0.18);
}

.nb-login-btn:hover {
    background: #2ba6de;
    transform: translateY(-1px);
    box-shadow: 0 16px 30px rgba(21, 70, 119, 0.2);
}

/* HERO CONTENT */
.nb-hero-content {
    position: relative;
    z-index: 5;
    max-width: 1620px;
    margin: 0 auto;
    padding: 190px 24px 145px;
    display: flex;
    align-items: center;
}

.nb-hero-copy {
    width: min(680px, 100%);
    color: #ffffff;
}

.nb-hero-badge {
    margin-top: 5%;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.94);
    color: #154677;
    padding: 8px 15px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 900;
    box-shadow: 0 12px 25px rgba(15, 23, 42, 0.14);
}

.nb-hero-badge i {
    color: #2ba6de;
}

.nb-hero-copy h1 {
    margin: 22px 0 0;
    font-size: clamp(36px, 5vw, 74px);
    line-height: 1.08;
    font-weight: 900;
    letter-spacing: -0.04em;
    text-shadow: 0 18px 40px rgba(15, 23, 42, 0.22);
}

.nb-hero-copy p {
    margin: 18px 0 0;
    max-width: 600px;
    font-size: 17px;
    line-height: 1.8;
    color: rgba(255, 255, 255, 0.9);
    text-shadow: 0 12px 28px rgba(15, 23, 42, 0.2);
}

.nb-hero-actions {
    margin-top: 28px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.nb-primary-cta,
.nb-secondary-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 22px;
    border-radius: 999px;
    font-size: 14px;
    font-weight: 900;
    text-decoration: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.nb-primary-cta {
    background: #154677;
    color: #ffffff;
    box-shadow: 0 14px 28px rgba(21, 70, 119, 0.28);
}

.nb-primary-cta:hover {
    background: #2ba6de;
    transform: translateY(-2px);
}

.nb-secondary-cta {
    background: rgba(255, 255, 255, 0.94);
    color: #154677;
    box-shadow: 0 14px 28px rgba(15, 23, 42, 0.16);
}

.nb-secondary-cta:hover {
    transform: translateY(-2px);
    background: #ffffff;
}

/* RESPONSIVE */
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
        padding: 170px 18px 120px;
    }

    .nb-hero-compact {
        min-height: 104px;
    }
}

@media (max-width: 640px) {
    .nb-hero {
        min-height: 610px;
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
        padding-top: 160px;
    }

    .nb-hero-copy h1 {
        font-size: 38px;
    }

    .nb-hero-copy p {
        font-size: 15px;
    }

    .nb-primary-cta,
    .nb-secondary-cta {
        width: 100%;
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
</style>
