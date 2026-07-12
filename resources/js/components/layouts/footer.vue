<script setup>
import { computed } from "vue";
import { useI18n } from "vue-i18n";

const { t, locale } = useI18n();

const footerDir = computed(() => (locale.value === "ar" ? "rtl" : "ltr"));

const localePrefix = computed(() => `/${locale.value || "en"}`);

const localizedPath = (path = "") => {
    const cleanPath = path ? `/${path.replace(/^\/+/, "")}` : "";
    return `${localePrefix.value}${cleanPath}`;
};

const ctaIconClass = computed(() =>
    footerDir.value === "rtl" ? "bi bi-arrow-left-short" : "bi bi-arrow-right-short"
);

const whatsappUrl = "https://wa.me/";
const telegramUrl = "https://t.me/";
const xUrl = "https://x.com/";
const instagramUrl = "https://www.instagram.com/";
</script>

<template>
    <footer class="site-footer" :dir="footerDir">
        <div class="footer-bg-glow footer-glow-one"></div>
        <div class="footer-bg-glow footer-glow-two"></div>
        <div class="footer-bg-glow footer-glow-three"></div>

        <div class="footer-container">
            <div class="footer-panel">
                <!-- MOVING BORDER RUNNER -->
                <div class="footer-border-line" aria-hidden="true">
                    <span class="footer-border-runner footer-border-runner-one"></span>
                    <span class="footer-border-runner footer-border-runner-two"></span>
                    <span class="footer-border-dot footer-border-dot-one"></span>
                    <span class="footer-border-dot footer-border-dot-two"></span>
                </div>

                <!-- BRAND / CTA -->
                <div class="footer-brand-area">
                    <div class="footer-brand-main">
                        <div class="brand-mark">
                            <img
                                src="/images/Ai_logo.png"
                                :alt="t('user.footer.logoAlt')"
                                width="54"
                                height="54"
                                loading="lazy"
                                decoding="async"
                            />
                        </div>

                        <div>
                            <p class="brand-title">{{ t("user.footer.brandTitle") }}</p>
                            <p class="brand-copy">
                                {{ t("user.footer.brandCopy") }}
                            </p>
                        </div>
                    </div>

                    <div class="footer-cta">
                        <span class="footer-cta-badge">
                            <i class="bi bi-stars"></i>
                            {{ t("user.footer.ctaBadge") }}
                        </span>

                        <router-link :to="localizedPath()" class="footer-cta-btn">
                            <span>{{ t("user.footer.ctaButton") }}</span>
                            <i :class="ctaIconClass"></i>
                        </router-link>
                    </div>
                </div>

                <!-- LINKS -->
                <div class="footer-grid">
                    <div class="footer-col">
                        <h3>{{ t("user.footer.platformTitle") }}</h3>

                        <ul>
                            <li>
                                <router-link :to="localizedPath()">
                                    <i class="bi bi-house"></i>
                                    {{ t("user.footer.home") }}
                                </router-link>
                            </li>

                            <li>
                                <router-link :to="localizedPath('products')">
                                    <i class="bi bi-grid"></i>
                                    {{ t("user.footer.allTools") }}
                                </router-link>
                            </li>

                            <li>
                                <router-link :to="localizedPath('who')">
                                    <i class="bi bi-info-circle"></i>
                                    {{ t("user.footer.aboutUs") }}
                                </router-link>
                            </li>

                            <li>
                                <router-link :to="localizedPath('contact')">
                                    <i class="bi bi-envelope"></i>
                                    {{ t("user.footer.contactUs") }}
                                </router-link>
                            </li>
                        </ul>
                    </div>

                    <div class="footer-col">
                        <h3>{{ t("user.footer.supportTitle") }}</h3>

                        <ul>
                            <li>
                                <a href="#">
                                    <i class="bi bi-question-circle"></i>
                                    {{ t("user.footer.helpCenter") }}
                                </a>
                            </li>

                            <li>
                                <a href="#">
                                    <i class="bi bi-shield-check"></i>
                                    {{ t("user.footer.privacyPolicy") }}
                                </a>
                            </li>

                            <li>
                                <a href="#">
                                    <i class="bi bi-file-earmark-text"></i>
                                    {{ t("user.footer.termsOfService") }}
                                </a>
                            </li>

                            <li>
                                <a href="#">
                                    <i class="bi bi-c-circle"></i>
                                    {{ t("user.footer.copyrightRights") }}
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div class="footer-col footer-highlight">
                        <h3>{{ t("user.footer.fastExperienceTitle") }}</h3>

                        <p>
                            {{ t("user.footer.fastExperienceText") }}
                        </p>

                        <div class="footer-mini-stats">
                            <div>
                                <strong>{{ t("user.footer.statAi") }}</strong>
                                <span>{{ t("user.footer.statWriting") }}</span>
                            </div>

                            <div>
                                <strong>{{ t("user.footer.statAccessNumber") }}</strong>
                                <span>{{ t("user.footer.statAccess") }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BOTTOM -->
                <div class="footer-bottom">
                    <div class="legal-links">
                        <a href="#">{{ t("user.footer.privacyPolicy") }}</a>
                        <a href="#">{{ t("user.footer.termsOfService") }}</a>
                        <a href="#">{{ t("user.footer.copyrightRights") }}</a>
                    </div>

                    <div
                        class="footer-social-center"
                        :aria-label="footerDir === 'rtl' ? 'روابط الموقع والتواصل الاجتماعي' : 'Website and social links'"
                    >
                        <router-link
                            :to="localizedPath()"
                            class="footer-social-link"
                            :aria-label="footerDir === 'rtl' ? 'الموقع الأصلي' : 'Original website'"
                        >
                            <i class="bi bi-house-door"></i>
                        </router-link>

                        <a
                            :href="whatsappUrl"
                            class="footer-social-link"
                            target="_blank"
                            rel="noopener noreferrer"
                            :aria-label="footerDir === 'rtl' ? 'واتساب' : 'WhatsApp'"
                        >
                            <i class="bi bi-whatsapp"></i>
                        </a>

                        <a
                            :href="telegramUrl"
                            class="footer-social-link"
                            target="_blank"
                            rel="noopener noreferrer"
                            :aria-label="footerDir === 'rtl' ? 'تليجرام' : 'Telegram'"
                        >
                            <i class="bi bi-telegram"></i>
                        </a>

                        <a
                            :href="xUrl"
                            class="footer-social-link"
                            target="_blank"
                            rel="noopener noreferrer"
                            :aria-label="footerDir === 'rtl' ? 'منصة إكس' : 'X'"
                        >
                            <i class="bi bi-twitter-x"></i>
                        </a>

                        <a
                            :href="instagramUrl"
                            class="footer-social-link"
                            target="_blank"
                            rel="noopener noreferrer"
                            :aria-label="footerDir === 'rtl' ? 'إنستجرام' : 'Instagram'"
                        >
                            <i class="bi bi-instagram"></i>
                        </a>
                    </div>

                    <p class="copyright">
                        {{ t("user.footer.copyright") }}
                    </p>
                </div>
            </div>
        </div>
    </footer>
</template>

<style scoped>
.site-footer {
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(circle at 50% 0%, rgba(43, 166, 222, 0.14), transparent 34%),
        linear-gradient(180deg, #f4f8fb 0%, #eaf4fb 48%, #dcebf5 100%);
    color: #154677;
    padding: 76px 18px 28px;
    box-sizing: border-box;
}

.footer-bg-glow {
    position: absolute;
    border-radius: 999px;
    filter: blur(10px);
    pointer-events: none;
    will-change: transform;
}

.footer-glow-one {
    width: 420px;
    height: 420px;
    top: -210px;
    inset-inline-end: -150px;
    background: radial-gradient(circle, rgba(43, 166, 222, 0.20), transparent 68%);
    animation: footerGlowFloatOne 10s ease-in-out infinite alternate;
}

.footer-glow-two {
    width: 360px;
    height: 360px;
    bottom: -180px;
    inset-inline-start: -140px;
    background: radial-gradient(circle, rgba(21, 70, 119, 0.14), transparent 70%);
    animation: footerGlowFloatTwo 12s ease-in-out infinite alternate;
}

.footer-glow-three {
    width: 250px;
    height: 250px;
    top: 42%;
    inset-inline-start: 48%;
    background: radial-gradient(circle, rgba(43, 166, 222, 0.08), transparent 72%);
    animation: footerGlowFloatThree 14s ease-in-out infinite alternate;
}

.footer-container {
    position: relative;
    z-index: 2;
    width: min(1500px, 100%);
    margin: 0 auto;
}

.footer-panel {
    position: relative;
    overflow: hidden;
    isolation: isolate;
    background:
        linear-gradient(rgba(255, 255, 255, 0.86), rgba(255, 255, 255, 0.86)) padding-box,
        linear-gradient(135deg, rgba(21, 70, 119, 0.14), rgba(43, 166, 222, 0.18), rgba(21, 70, 119, 0.10)) border-box;
    border: 1px solid transparent;
    border-radius: 32px;
    padding: 34px;
    box-shadow:
        0 22px 60px rgba(21, 70, 119, 0.10),
        inset 0 1px 0 rgba(255, 255, 255, 0.75);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    animation: footerPanelEnter 0.75s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.footer-panel::before {
    content: "";
    position: absolute;
    inset: 0;
    z-index: 0;
    background:
        radial-gradient(circle at 82% 14%, rgba(43, 166, 222, 0.11), transparent 30%),
        radial-gradient(circle at 14% 82%, rgba(21, 70, 119, 0.08), transparent 32%);
    pointer-events: none;
    animation: footerSoftBgShift 12s ease-in-out infinite alternate;
}

/* BORDER THAT LOOKS LIKE A LIGHT WALKING ON THE FOOTER BORDER */
.footer-border-line {
    position: absolute;
    inset: 0;
    z-index: 1;
    border-radius: inherit;
    border: 1px solid rgba(21, 70, 119, 0.12);
    pointer-events: none;
    overflow: hidden;
}

.footer-border-line::before {
    content: "";
    position: absolute;
    inset: 1px;
    border-radius: calc(32px - 1px);
    border: 1px solid rgba(255, 255, 255, 0.54);
    pointer-events: none;
}

.footer-border-runner,
.footer-border-dot {
    position: absolute;
    left: 0;
    top: 0;
    pointer-events: none;
    will-change: left, top, transform, opacity;
}

.footer-border-runner {
    width: clamp(110px, 12vw, 190px);
    height: 3px;
    border-radius: 999px;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(43, 166, 222, 0.10) 10%,
        rgba(43, 166, 222, 0.92) 38%,
        rgba(255, 255, 255, 1) 50%,
        rgba(21, 70, 119, 0.92) 62%,
        rgba(43, 166, 222, 0.14) 90%,
        transparent 100%
    );
    box-shadow:
        0 0 10px rgba(43, 166, 222, 0.55),
        0 0 20px rgba(43, 166, 222, 0.34),
        0 0 36px rgba(21, 70, 119, 0.22);
    opacity: 0.95;
    animation: footerBorderWalk 6.8s linear infinite;
}

.footer-border-runner-two {
    animation-delay: -3.4s;
    opacity: 0.52;
    width: clamp(74px, 8vw, 126px);
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(43, 166, 222, 0.04) 12%,
        rgba(43, 166, 222, 0.68) 44%,
        rgba(255, 255, 255, 0.82) 50%,
        rgba(43, 166, 222, 0.58) 58%,
        transparent 100%
    );
}

.footer-border-dot {
    width: 9px;
    height: 9px;
    border-radius: 999px;
    background: #ffffff;
    box-shadow:
        0 0 8px rgba(255, 255, 255, 0.92),
        0 0 18px rgba(43, 166, 222, 0.72),
        0 0 30px rgba(43, 166, 222, 0.38);
    animation: footerBorderDotWalk 6.8s linear infinite;
}

.footer-border-dot-two {
    animation-delay: -3.4s;
    opacity: 0.65;
}

/* BRAND */
.footer-brand-area {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 28px;
    padding-bottom: 30px;
    margin-bottom: 30px;
    border-bottom: 1px solid rgba(21, 70, 119, 0.10);
    animation: footerFadeUp 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.12s both;
}

.footer-brand-main {
    display: flex;
    align-items: center;
    gap: 18px;
    max-width: 680px;
}

.brand-mark {
    width: 78px;
    height: 78px;
    display: grid;
    place-items: center;
    border-radius: 24px;
    background:
        linear-gradient(135deg, rgba(21, 70, 119, 0.98), rgba(43, 166, 222, 0.92));
    box-shadow:
        0 20px 38px rgba(21, 70, 119, 0.20),
        inset 0 1px 0 rgba(255, 255, 255, 0.28);
    flex-shrink: 0;
    animation: brandMarkFloat 4.2s ease-in-out infinite alternate;
}

.brand-mark img {
    width: 54px;
    height: 54px;
    object-fit: contain;
    background: rgba(255, 255, 255, 0.92);
    border-radius: 16px;
    padding: 5px;
}

.brand-title {
    margin: 0;
    font-size: 1.45rem;
    line-height: 1.2;
    font-weight: 950;
    color: #154677;
    letter-spacing: -0.03em;
}

.brand-copy {
    margin: 8px 0 0;
    color: #5f7288;
    line-height: 1.8;
    font-size: 0.95rem;
    font-weight: 600;
}

/* CTA */
.footer-cta {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.footer-cta-badge {
    min-height: 38px;
    padding: 0 15px;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    border-radius: 999px;
    background: rgba(43, 166, 222, 0.10);
    border: 1px solid rgba(43, 166, 222, 0.18);
    color: #154677;
    font-size: 12px;
    font-weight: 900;
    white-space: nowrap;
}

.footer-cta-badge i {
    color: #2ba6de;
    animation: footerStarPulse 1.8s ease-in-out infinite alternate;
}

.footer-cta-btn {
    position: relative;
    overflow: hidden;
    min-height: 42px;
    padding: 0 18px;
    border-radius: 999px;
    background: #154677;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 900;
    box-shadow: 0 14px 28px rgba(21, 70, 119, 0.18);
    transition:
        transform 0.22s ease,
        background 0.22s ease,
        box-shadow 0.22s ease;
}

.footer-cta-btn::before {
    content: "";
    position: absolute;
    top: -35%;
    bottom: -35%;
    left: -70%;
    width: 45%;
    transform: rotate(18deg);
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.46), transparent);
    transition: left 0.55s ease;
    pointer-events: none;
}

.footer-cta-btn span,
.footer-cta-btn i {
    position: relative;
    z-index: 1;
}

.footer-cta-btn i {
    font-size: 20px;
    transition: transform 0.22s ease;
}

.footer-cta-btn:hover {
    background: #2ba6de;
    transform: translateY(-2px);
    box-shadow: 0 18px 34px rgba(43, 166, 222, 0.24);
}

.footer-cta-btn:hover::before {
    left: 125%;
}

.site-footer[dir="rtl"] .footer-cta-btn:hover i {
    transform: translateX(-3px);
}

.site-footer[dir="ltr"] .footer-cta-btn:hover i {
    transform: translateX(3px);
}

/* LINKS */
.footer-grid {
    position: relative;
    z-index: 2;
    display: grid;
    grid-template-columns: 1fr 1fr 1.2fr;
    gap: 22px;
}

.footer-col {
    position: relative;
    overflow: hidden;
    padding: 22px;
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.58);
    border: 1px solid rgba(21, 70, 119, 0.08);
    transition:
        transform 0.24s ease,
        border-color 0.24s ease,
        box-shadow 0.24s ease,
        background 0.24s ease;
    animation: footerFadeUp 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.footer-col:nth-child(1) {
    animation-delay: 0.22s;
}

.footer-col:nth-child(2) {
    animation-delay: 0.32s;
}

.footer-col:nth-child(3) {
    animation-delay: 0.42s;
}

.footer-col:not(.footer-highlight)::before {
    content: "";
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 82% 12%, rgba(43, 166, 222, 0.10), transparent 34%);
    opacity: 0;
    transition: opacity 0.24s ease;
    pointer-events: none;
}

.footer-col:not(.footer-highlight):hover {
    transform: translateY(-4px);
    border-color: rgba(43, 166, 222, 0.24);
    background: rgba(255, 255, 255, 0.76);
    box-shadow: 0 16px 34px rgba(21, 70, 119, 0.08);
}

.footer-col:not(.footer-highlight):hover::before {
    opacity: 1;
}

.footer-col h3 {
    position: relative;
    z-index: 1;
    margin: 0 0 16px;
    font-size: 1rem;
    font-weight: 950;
    color: #154677;
}

.footer-col ul {
    position: relative;
    z-index: 1;
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    gap: 9px;
}

.footer-col ul li a {
    min-height: 36px;
    color: #5f7288;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 9px;
    font-size: 0.92rem;
    font-weight: 700;
    transition:
        color 0.2s ease,
        transform 0.2s ease;
}

.footer-col ul li a i {
    width: 28px;
    height: 28px;
    border-radius: 10px;
    background: rgba(43, 166, 222, 0.08);
    color: #154677;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    transition:
        background 0.2s ease,
        color 0.2s ease,
        transform 0.2s ease;
}

.site-footer[dir="rtl"] .footer-col ul li a:hover {
    color: #154677;
    transform: translateX(-3px);
}

.site-footer[dir="ltr"] .footer-col ul li a:hover {
    color: #154677;
    transform: translateX(3px);
}

.footer-col ul li a:hover i {
    background: #154677;
    color: #ffffff;
    transform: scale(1.06) rotate(-4deg);
}

/* HIGHLIGHT */
.footer-highlight {
    background:
        radial-gradient(circle at 20% 10%, rgba(43, 166, 222, 0.16), transparent 36%),
        linear-gradient(135deg, rgba(21, 70, 119, 0.96), rgba(43, 166, 222, 0.88));
    color: #ffffff;
    border: none;
    box-shadow: 0 18px 40px rgba(21, 70, 119, 0.18);
}

.footer-highlight::before {
    content: "";
    position: absolute;
    inset: -40%;
    background: linear-gradient(115deg, transparent 35%, rgba(255, 255, 255, 0.12) 50%, transparent 65%);
    transform: translateX(-55%) rotate(8deg);
    animation: highlightShine 5s ease-in-out infinite;
    pointer-events: none;
}

.footer-highlight h3 {
    color: #ffffff;
}

.footer-highlight p {
    position: relative;
    z-index: 1;
    margin: 0;
    color: rgba(255, 255, 255, 0.84);
    line-height: 1.8;
    font-size: 0.95rem;
    font-weight: 700;
}

.footer-mini-stats {
    position: relative;
    z-index: 1;
    margin-top: 22px;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.footer-mini-stats div {
    min-height: 76px;
    border-radius: 18px;
    padding: 15px;
    background: rgba(255, 255, 255, 0.13);
    border: 1px solid rgba(255, 255, 255, 0.18);
    display: flex;
    flex-direction: column;
    justify-content: center;
    transition:
        transform 0.22s ease,
        background 0.22s ease,
        border-color 0.22s ease;
}

.footer-mini-stats div:hover {
    transform: translateY(-3px);
    background: rgba(255, 255, 255, 0.18);
    border-color: rgba(255, 255, 255, 0.30);
}

.footer-mini-stats strong {
    color: #ffffff;
    font-size: 1.25rem;
    font-weight: 950;
    line-height: 1;
}

.footer-mini-stats span {
    margin-top: 7px;
    color: rgba(255, 255, 255, 0.78);
    font-size: 0.78rem;
    font-weight: 800;
}

/* BOTTOM */
.footer-bottom {
    position: relative;
    z-index: 2;
    border-top: 1px solid rgba(21, 70, 119, 0.10);
    margin-top: 30px;
    padding-top: 22px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
    align-items: center;
    gap: 18px;
    direction: ltr;
    animation: footerFadeUp 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.52s both;
}

.footer-social-center {
    justify-self: center;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 18px;
}

.footer-social-link {
    position: relative;
    overflow: hidden;
    width: 62px;
    height: 62px;
    border-radius: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #154677;
    text-decoration: none;
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.82), rgba(236, 247, 254, 0.72));
    border: 1px solid rgba(43, 166, 222, 0.24);
    box-shadow:
        0 14px 30px rgba(21, 70, 119, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.92);
    transition:
        transform 0.24s ease,
        color 0.24s ease,
        border-color 0.24s ease,
        box-shadow 0.24s ease,
        background 0.24s ease;
    animation: socialIconPop 0.65s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.footer-social-link:nth-child(1) {
    animation-delay: 0.62s;
}

.footer-social-link:nth-child(2) {
    animation-delay: 0.70s;
}

.footer-social-link:nth-child(3) {
    animation-delay: 0.78s;
}

.footer-social-link:nth-child(4) {
    animation-delay: 0.86s;
}

.footer-social-link:nth-child(5) {
    animation-delay: 0.94s;
}

.footer-social-link::before {
    content: "";
    position: absolute;
    inset: -35%;
    background: linear-gradient(115deg, transparent 35%, rgba(255, 255, 255, 0.72) 50%, transparent 65%);
    transform: translateX(-120%) rotate(18deg);
    transition: transform 0.58s ease;
    pointer-events: none;
}

.footer-social-link::after {
    content: "";
    position: absolute;
    inset: 8px;
    border-radius: 14px;
    background: radial-gradient(circle, rgba(43, 166, 222, 0.16), transparent 68%);
    opacity: 0;
    transform: scale(0.86);
    transition:
        opacity 0.24s ease,
        transform 0.24s ease;
    pointer-events: none;
}

.footer-social-link i {
    position: relative;
    z-index: 1;
    font-size: 24px;
    line-height: 1;
    transition: transform 0.24s ease;
}

.footer-social-link:hover,
.footer-social-link:focus-visible {
    color: #2ba6de;
    transform: translateY(-5px) scale(1.035);
    border-color: rgba(43, 166, 222, 0.48);
    background:
        linear-gradient(180deg, #ffffff, rgba(230, 247, 255, 0.96));
    box-shadow:
        0 20px 38px rgba(43, 166, 222, 0.18),
        0 0 0 5px rgba(43, 166, 222, 0.07),
        inset 0 1px 0 rgba(255, 255, 255, 1);
    outline: none;
}

.footer-social-link:hover::before,
.footer-social-link:focus-visible::before {
    transform: translateX(120%) rotate(18deg);
}

.footer-social-link:hover::after,
.footer-social-link:focus-visible::after {
    opacity: 1;
    transform: scale(1);
}

.footer-social-link:hover i,
.footer-social-link:focus-visible i {
    transform: scale(1.08) rotate(-3deg);
}

.copyright {
    justify-self: end;
    margin: 0;
    font-size: 0.875rem;
    color: #5f7288;
    font-weight: 700;
    text-align: end;
}

.site-footer[dir="rtl"] .copyright,
.site-footer[dir="rtl"] .legal-links {
    direction: rtl;
}

.site-footer[dir="ltr"] .copyright,
.site-footer[dir="ltr"] .legal-links {
    direction: ltr;
}

.legal-links {
    justify-self: start;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.legal-links a {
    color: #5f7288;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 800;
    padding: 8px 12px;
    border-radius: 999px;
    background: rgba(21, 70, 119, 0.04);
    transition:
        color 0.2s ease,
        background 0.2s ease,
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.legal-links a:hover {
    color: #ffffff;
    background: #154677;
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(21, 70, 119, 0.12);
}

/* ANIMATIONS */
@keyframes footerBorderWalk {
    0% {
        left: -190px;
        top: 0;
        transform: translateY(-50%) rotate(0deg);
    }

    24.99% {
        left: 100%;
        top: 0;
        transform: translateY(-50%) rotate(0deg);
    }

    25% {
        left: 100%;
        top: 0;
        transform: translate(-50%, 0) rotate(90deg);
    }

    49.99% {
        left: 100%;
        top: 100%;
        transform: translate(-50%, -100%) rotate(90deg);
    }

    50% {
        left: 100%;
        top: 100%;
        transform: translate(-100%, -50%) rotate(180deg);
    }

    74.99% {
        left: 0;
        top: 100%;
        transform: translate(0, -50%) rotate(180deg);
    }

    75% {
        left: 0;
        top: 100%;
        transform: translate(-50%, -100%) rotate(270deg);
    }

    99.99% {
        left: 0;
        top: 0;
        transform: translate(-50%, 0) rotate(270deg);
    }

    100% {
        left: -190px;
        top: 0;
        transform: translateY(-50%) rotate(0deg);
    }
}

@keyframes footerBorderDotWalk {
    0% {
        left: -10px;
        top: 0;
        transform: translate(-50%, -50%);
    }

    25% {
        left: 100%;
        top: 0;
        transform: translate(-50%, -50%);
    }

    50% {
        left: 100%;
        top: 100%;
        transform: translate(-50%, -50%);
    }

    75% {
        left: 0;
        top: 100%;
        transform: translate(-50%, -50%);
    }

    100% {
        left: 0;
        top: 0;
        transform: translate(-50%, -50%);
    }
}

@keyframes footerPanelEnter {
    from {
        opacity: 0;
        transform: translateY(22px) scale(0.985);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes footerFadeUp {
    from {
        opacity: 0;
        transform: translateY(18px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes footerGlowFloatOne {
    from {
        transform: translate3d(0, 0, 0) scale(1);
    }

    to {
        transform: translate3d(-18px, 16px, 0) scale(1.05);
    }
}

@keyframes footerGlowFloatTwo {
    from {
        transform: translate3d(0, 0, 0) scale(1);
    }

    to {
        transform: translate3d(20px, -14px, 0) scale(1.07);
    }
}

@keyframes footerGlowFloatThree {
    from {
        transform: translate3d(-8px, 8px, 0) scale(1);
    }

    to {
        transform: translate3d(14px, -10px, 0) scale(1.08);
    }
}

@keyframes footerSoftBgShift {
    from {
        transform: translate3d(0, 0, 0);
    }

    to {
        transform: translate3d(8px, -8px, 0);
    }
}

@keyframes brandMarkFloat {
    from {
        transform: translateY(0);
    }

    to {
        transform: translateY(-4px);
    }
}

@keyframes footerStarPulse {
    from {
        transform: scale(1) rotate(0deg);
    }

    to {
        transform: scale(1.12) rotate(7deg);
    }
}

@keyframes highlightShine {
    0%,
    62% {
        transform: translateX(-58%) rotate(8deg);
        opacity: 0;
    }

    72% {
        opacity: 1;
    }

    100% {
        transform: translateX(58%) rotate(8deg);
        opacity: 0;
    }
}

@keyframes socialIconPop {
    from {
        opacity: 0;
        transform: translateY(14px) scale(0.86);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* RESPONSIVE */
@media (max-width: 980px) {
    .footer-brand-area {
        align-items: flex-start;
        flex-direction: column;
    }

    .footer-grid {
        grid-template-columns: 1fr;
    }

    .footer-highlight {
        order: -1;
    }
}

@media (max-width: 640px) {
    .site-footer {
        padding: 52px 10px 22px;
    }

    .footer-panel {
        padding: 22px;
        border-radius: 26px;
    }

    .footer-border-line::before {
        border-radius: calc(26px - 1px);
    }

    .footer-border-runner {
        width: 96px;
        height: 2.5px;
    }

    .footer-border-runner-two {
        width: 70px;
    }

    .footer-brand-main {
        align-items: flex-start;
        flex-direction: column;
    }

    .brand-mark {
        width: 70px;
        height: 70px;
        border-radius: 22px;
    }

    .brand-title {
        font-size: 1.25rem;
    }

    .footer-cta {
        width: 100%;
        align-items: stretch;
        flex-direction: column;
    }

    .footer-cta-badge,
    .footer-cta-btn {
        width: 100%;
        justify-content: center;
    }

    .footer-col {
        padding: 18px;
    }

    .footer-bottom {
        grid-template-columns: 1fr;
        gap: 18px;
        justify-items: stretch;
    }

    .footer-social-center {
        order: -1;
        width: 100%;
        gap: 10px;
    }

    .footer-social-link {
        width: 50px;
        height: 50px;
        border-radius: 16px;
    }

    .footer-social-link i {
        font-size: 20px;
    }

    .legal-links {
        width: 100%;
        justify-self: stretch;
    }

    .legal-links a {
        flex: 1;
        text-align: center;
    }

    .copyright {
        justify-self: stretch;
        width: 100%;
        text-align: center;
    }
}

@media (prefers-reduced-motion: reduce) {
    .site-footer *,
    .site-footer *::before,
    .site-footer *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}
</style>
