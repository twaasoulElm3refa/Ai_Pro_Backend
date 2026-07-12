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
</script>

<template>
    <footer class="site-footer" :dir="footerDir">
        <div class="footer-bg-glow footer-glow-one"></div>
        <div class="footer-bg-glow footer-glow-two"></div>

        <div class="footer-container">
            <div class="footer-panel">

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
                            {{ t("user.footer.ctaButton") }}
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
                    <p class="copyright">
                        {{ t("user.footer.copyright") }}
                    </p>

                    <div class="legal-links">
                        <a href="#">{{ t("user.footer.privacyPolicy") }}</a>
                        <a href="#">{{ t("user.footer.termsOfService") }}</a>
                        <a href="#">{{ t("user.footer.copyrightRights") }}</a>
                    </div>
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
    opacity: 0.9;
    will-change: transform;
}

.footer-glow-one {
    width: 420px;
    height: 420px;
    top: -210px;
    inset-inline-end: -150px;
    background: radial-gradient(circle, rgba(43, 166, 222, 0.20), transparent 68%);
    animation: footerGlowFloatOne 9s ease-in-out infinite alternate;
}

.footer-glow-two {
    width: 360px;
    height: 360px;
    bottom: -180px;
    inset-inline-start: -140px;
    background: radial-gradient(circle, rgba(21, 70, 119, 0.14), transparent 70%);
    animation: footerGlowFloatTwo 11s ease-in-out infinite alternate;
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
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid rgba(21, 70, 119, 0.12);
    border-radius: 32px;
    padding: 34px;
    box-shadow:
        0 22px 60px rgba(21, 70, 119, 0.10),
        inset 0 1px 0 rgba(255, 255, 255, 0.75);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    animation: footerPanelReveal 0.75s cubic-bezier(0.22, 1, 0.36, 1) both;
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
    animation: footerPanelGlowMove 10s ease-in-out infinite alternate;
}

.footer-panel::after {
    content: "";
    position: absolute;
    inset: 0;
    z-index: 1;
    padding: 2px;
    border-radius: inherit;
    background:
        conic-gradient(
            from 0deg,
            transparent 0deg,
            rgba(43, 166, 222, 0.12) 38deg,
            rgba(43, 166, 222, 0.95) 76deg,
            rgba(255, 255, 255, 0.95) 112deg,
            rgba(21, 70, 119, 0.90) 156deg,
            rgba(43, 166, 222, 0.18) 208deg,
            transparent 260deg,
            rgba(43, 166, 222, 0.70) 320deg,
            transparent 360deg
        );
    -webkit-mask:
        linear-gradient(#fff 0 0) content-box,
        linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
    animation: footerBorderSpin 6.5s linear infinite;
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
}

.footer-brand-main {
    display: flex;
    align-items: center;
    gap: 18px;
    max-width: 680px;
}

.brand-mark {
    position: relative;
    isolation: isolate;
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
    animation: footerBrandFloat 4.8s ease-in-out infinite;
}

.brand-mark::after {
    content: "";
    position: absolute;
    inset: -6px;
    z-index: -1;
    border-radius: 28px;
    border: 1px solid rgba(43, 166, 222, 0.24);
    opacity: 0;
    transform: scale(0.92);
    animation: footerBrandPulse 2.8s ease-in-out infinite;
}

.brand-mark img {
    width: 54px;
    height: 54px;
    object-fit: contain;
    background: rgba(255, 255, 255, 0.92);
    border-radius: 16px;
    padding: 5px;
    position: relative;
    z-index: 1;
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
    animation: footerBadgeGlow 3.2s ease-in-out infinite;
}

.footer-cta-badge i {
    color: #2ba6de;
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
    top: -60%;
    bottom: -60%;
    width: 34px;
    inset-inline-start: -55px;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.52), transparent);
    transform: rotate(24deg);
    transition: inset-inline-start 0.55s ease;
    pointer-events: none;
}

.footer-cta-btn:hover::before {
    inset-inline-start: calc(100% + 55px);
}

.footer-cta-btn i {
    font-size: 20px;
}

.footer-cta-btn:hover {
    background: #2ba6de;
    transform: translateY(-2px);
    box-shadow: 0 18px 34px rgba(43, 166, 222, 0.24);
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
    animation: footerCardReveal 0.72s cubic-bezier(0.22, 1, 0.36, 1) both;
    transition:
        transform 0.25s ease,
        border-color 0.25s ease,
        box-shadow 0.25s ease,
        background 0.25s ease;
}

.footer-col:nth-child(1) {
    animation-delay: 0.12s;
}

.footer-col:nth-child(2) {
    animation-delay: 0.22s;
}

.footer-col:nth-child(3) {
    animation-delay: 0.32s;
}

.footer-col:not(.footer-highlight):hover {
    transform: translateY(-4px);
    border-color: rgba(43, 166, 222, 0.24);
    background: rgba(255, 255, 255, 0.78);
    box-shadow: 0 16px 34px rgba(21, 70, 119, 0.08);
}

.footer-col h3 {
    margin: 0 0 16px;
    font-size: 1rem;
    font-weight: 950;
    color: #154677;
}

.footer-col ul {
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
        color 0.2s ease;
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
}

/* HIGHLIGHT */
.footer-highlight {
    position: relative;
    overflow: hidden;
    isolation: isolate;
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
    top: -90px;
    inset-inline-start: -120px;
    width: 170px;
    height: 340px;
    z-index: 0;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.20), transparent);
    transform: rotate(22deg);
    pointer-events: none;
    animation: footerHighlightSweep 5.6s ease-in-out infinite;
}

.footer-highlight h3,
.footer-highlight p,
.footer-mini-stats {
    position: relative;
    z-index: 1;
}

.footer-highlight h3 {
    color: #ffffff;
}

.footer-highlight p {
    margin: 0;
    color: rgba(255, 255, 255, 0.84);
    line-height: 1.8;
    font-size: 0.95rem;
    font-weight: 700;
}

.footer-mini-stats {
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
    background: rgba(255, 255, 255, 0.17);
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
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

.copyright {
    margin: 0;
    font-size: 0.875rem;
    color: #5f7288;
    font-weight: 700;
}

.legal-links {
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
        background 0.2s ease;
}

.legal-links a:hover {
    color: #ffffff;
    background: #154677;
}


@keyframes footerPanelReveal {
    from {
        opacity: 0;
        transform: translateY(24px) scale(0.985);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes footerBorderSpin {
    to {
        transform: rotate(360deg);
    }
}

@keyframes footerPanelGlowMove {
    0% {
        transform: translate3d(0, 0, 0) scale(1);
    }

    100% {
        transform: translate3d(10px, -8px, 0) scale(1.02);
    }
}

@keyframes footerGlowFloatOne {
    0% {
        transform: translate3d(0, 0, 0) scale(1);
    }

    100% {
        transform: translate3d(-18px, 18px, 0) scale(1.05);
    }
}

@keyframes footerGlowFloatTwo {
    0% {
        transform: translate3d(0, 0, 0) scale(1);
    }

    100% {
        transform: translate3d(18px, -14px, 0) scale(1.04);
    }
}

@keyframes footerBrandFloat {
    0%,
    100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-5px);
    }
}

@keyframes footerBrandPulse {
    0%,
    100% {
        opacity: 0;
        transform: scale(0.92);
    }

    45% {
        opacity: 1;
        transform: scale(1.04);
    }
}

@keyframes footerBadgeGlow {
    0%,
    100% {
        box-shadow: 0 0 0 rgba(43, 166, 222, 0);
    }

    50% {
        box-shadow: 0 0 0 6px rgba(43, 166, 222, 0.09);
    }
}

@keyframes footerCardReveal {
    from {
        opacity: 0;
        transform: translateY(18px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes footerHighlightSweep {
    0% {
        inset-inline-start: -160px;
        opacity: 0;
    }

    22% {
        opacity: 0.95;
    }

    55% {
        inset-inline-start: calc(100% + 80px);
        opacity: 0;
    }

    100% {
        inset-inline-start: calc(100% + 80px);
        opacity: 0;
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
        align-items: flex-start;
        flex-direction: column;
    }

    .legal-links {
        width: 100%;
    }

    .legal-links a {
        flex: 1;
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
