<template>
    <footer class="site-footer" dir="rtl">
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
                                alt="AiPro Logo"
                                width="54"
                                height="54"
                                loading="lazy"
                                decoding="async"
                            />
                        </div>

                        <div>
                            <p class="brand-title">AiPro</p>
                            <p class="brand-copy">
                                أدوات ذكاء اصطناعي تساعدك على الكتابة، التلخيص، والتحرير بسرعة واحترافية.
                            </p>
                        </div>
                    </div>

                    <div class="footer-cta">
                        <span class="footer-cta-badge">
                            <i class="bi bi-stars"></i>
                            AI Tools
                        </span>

                        <router-link to="/en" class="footer-cta-btn">
                            ابدأ الآن
                            <i class="bi bi-arrow-left-short"></i>
                        </router-link>
                    </div>
                </div>

                <!-- LINKS -->
                <div class="footer-grid">
                    <div class="footer-col">
                        <h3>المنصة</h3>

                        <ul>
                            <li>
                                <router-link to="/en">
                                    <i class="bi bi-house"></i>
                                    الرئيسية
                                </router-link>
                            </li>

                            <li>
                                <router-link to="/en/products">
                                    <i class="bi bi-grid"></i>
                                    جميع الأدوات
                                </router-link>
                            </li>

                            <li>
                                <router-link to="/en/who">
                                    <i class="bi bi-info-circle"></i>
                                    من نحن
                                </router-link>
                            </li>

                            <li>
                                <router-link to="/en/contact">
                                    <i class="bi bi-envelope"></i>
                                    تواصل معنا
                                </router-link>
                            </li>
                        </ul>
                    </div>

                    <div class="footer-col">
                        <h3>الدعم</h3>

                        <ul>
                            <li>
                                <a href="#">
                                    <i class="bi bi-question-circle"></i>
                                    مركز المساعدة
                                </a>
                            </li>

                            <li>
                                <a href="#">
                                    <i class="bi bi-shield-check"></i>
                                    سياسة الخصوصية
                                </a>
                            </li>

                            <li>
                                <a href="#">
                                    <i class="bi bi-file-earmark-text"></i>
                                    شروط الخدمة
                                </a>
                            </li>

                            <li>
                                <a href="#">
                                    <i class="bi bi-c-circle"></i>
                                    حقوق الملكية
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div class="footer-col footer-highlight">
                        <h3>تجربة أسرع</h3>

                        <p>
                            افتح أدواتك المفضلة وابدأ بإنشاء محتوى ذكي خلال ثوانٍ.
                        </p>

                        <div class="footer-mini-stats">
                            <div>
                                <strong>AI</strong>
                                <span>Writing</span>
                            </div>

                            <div>
                                <strong>24/7</strong>
                                <span>Access</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BOTTOM -->
                <div class="footer-bottom">
                    <p class="copyright">
                        © 2025 مؤسسة aiarabic - جميع الحقوق محفوظة
                    </p>

                    <div class="legal-links">
                        <a href="#">سياسة الخصوصية</a>
                        <a href="#">شروط الخدمة</a>
                        <a href="#">حقوق الملكية</a>
                    </div>
                </div>

            </div>
        </div>
    </footer>
</template>

<script setup>
</script>

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
}

.footer-glow-one {
    width: 420px;
    height: 420px;
    top: -210px;
    inset-inline-end: -150px;
    background: radial-gradient(circle, rgba(43, 166, 222, 0.20), transparent 68%);
}

.footer-glow-two {
    width: 360px;
    height: 360px;
    bottom: -180px;
    inset-inline-start: -140px;
    background: radial-gradient(circle, rgba(21, 70, 119, 0.14), transparent 70%);
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
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid rgba(21, 70, 119, 0.12);
    border-radius: 32px;
    padding: 34px;
    box-shadow:
        0 22px 60px rgba(21, 70, 119, 0.10),
        inset 0 1px 0 rgba(255, 255, 255, 0.75);
    backdrop-filter: blur(16px);
}

.footer-panel::before {
    content: "";
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 82% 14%, rgba(43, 166, 222, 0.11), transparent 30%),
        radial-gradient(circle at 14% 82%, rgba(21, 70, 119, 0.08), transparent 32%);
    pointer-events: none;
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
}

.footer-cta-btn {
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
    padding: 22px;
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.58);
    border: 1px solid rgba(21, 70, 119, 0.08);
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

.footer-col ul li a:hover {
    color: #154677;
    transform: translateX(-3px);
}

.footer-col ul li a:hover i {
    background: #154677;
    color: #ffffff;
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
</style>
