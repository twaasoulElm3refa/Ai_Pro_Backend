<template>
    <footer class="site-footer" dir="rtl">
        <div class="footer-container">
            <div class="footer-grid">
                <div class="footer-col theme-surface-soft">
                    <h3>روابط سريعة</h3>
                    <ul>
                        <li><router-link to="/en">الرئيسية</router-link></li>
                        <li><router-link to="/en/products">جميع النماذج</router-link></li>
                        <li><router-link to="/en/who">من نحن</router-link></li>
                        <li><router-link to="/en/contact">اتصل بنا</router-link></li>
                    </ul>
                </div>

                <div class="footer-col theme-surface-soft">
                    <h3>الدعم والمساعدة</h3>
                    <ul>
                        <li><a href="#">من نحن؟</a></li>
                        <li><a href="#">اتصل بنا</a></li>
                        <li><a href="#">حقوق الملكية</a></li>
                        <li><a href="#">شروط الخدمة</a></li>
                        <li><a href="#">سياسة الخصوصية</a></li>
                        <li><a href="#">مركز المساعدة</a></li>
                    </ul>
                </div>

                <div class="footer-col footer-brand theme-surface-soft">
                    <div class="brand-logo">
                        <div class="brand-mark">
                            <img src="/images/Ai_logo.png" alt="AiPro Logo" />
                        </div>
                        <div>
                            <p class="brand-title">AiPro</p>
                            <p class="brand-copy">منصة حديثة لتجارب الذكاء الاصطناعي بواجهة موحدة ونظيفة.</p>
                        </div>
                    </div>

                    <div class="app-buttons">
                        <a href="#" class="app-btn theme-button">تحميل من Google Play</a>
                        <a href="#" class="app-btn theme-button-soft">تحميل من App Store</a>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p class="copyright">© 2025 مؤسسة aiarabic - جميع الحقوق محفوظة</p>
                <div class="legal-links">
                    <a href="#">سياسة الخصوصية</a>
                    <a href="#">شروط الخدمة</a>
                    <a href="#">حقوق الملكية</a>
                </div>
            </div>
        </div>
    </footer>
</template>

<script setup>
</script>

<style scoped>
.site-footer {
    background: linear-gradient(180deg, #d7e1ee 0%, #eef7fc 100%);
    color: #154677;
    padding: 56px 24px 28px;
    box-sizing: border-box;
}

.footer-container {
    max-width: 1200px;
    margin: 0 auto;
}

.footer-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.footer-col {
    padding: 24px;
}

.footer-col h3 {
    margin: 0 0 18px;
    font-size: 1.05rem;
    font-weight: 700;
    color: #154677;
}

.footer-col ul {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    gap: 10px;
}

.footer-col ul li a {
    color: #5f7288;
    text-decoration: none;
    transition: color 0.2s ease, transform 0.2s ease;
}

.footer-col ul li a:hover {
    color: #2ba6de;
    transform: translateX(-2px);
}

.footer-brand {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 22px;
}

.brand-logo {
    display: flex;
    align-items: center;
    gap: 16px;
}

.brand-mark {
    width: 72px;
    height: 72px;
    display: grid;
    place-items: center;
    border-radius: 1.25rem;
    background: linear-gradient(135deg, #154677, #2ba6de);
    box-shadow: 0 18px 36px rgba(21, 70, 119, 0.16);
}

.brand-mark img {
    width: 54px;
    height: 54px;
    object-fit: contain;
}

.brand-title {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 800;
    color: #154677;
}

.brand-copy {
    margin: 6px 0 0;
    color: #5f7288;
    line-height: 1.7;
    font-size: 0.92rem;
}

.app-buttons {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.app-btn {
    min-height: 46px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 1rem;
    text-decoration: none;
    font-weight: 700;
}

.footer-bottom {
    border-top: 1px solid rgba(21, 70, 119, 0.1);
    padding-top: 24px;
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
}

.legal-links {
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
}

.legal-links a {
    color: #5f7288;
    text-decoration: none;
    font-size: 0.875rem;
    transition: color 0.2s ease;
}

.legal-links a:hover {
    color: #2ba6de;
}

@media (max-width: 768px) {
    .footer-bottom,
    .brand-logo {
        flex-direction: column;
        align-items: flex-start;
    }

    .legal-links {
        gap: 12px;
    }
}
</style>
