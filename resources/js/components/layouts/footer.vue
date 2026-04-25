<template>
    <footer class="site-footer" dir="rtl">
        <div class="footer-container">
            <div class="footer-grid">
                <!-- العمود الأول: روابط سريعة -->
                <div class="footer-col">
                    <h3>روابط سريعة</h3>
                    <ul>
                        <li><router-link to="/en">الرئيسية</router-link></li>
                        <li><router-link to="/en/products">جميع النماذج</router-link></li>
                        <li><router-link to="/en/who">من نحن</router-link></li>
                        <li><router-link to="/en/contact">اتصل بنا</router-link></li>
                    </ul>
                </div>

                <!-- العمود الثاني: الدعم والمساعدة -->
                <div class="footer-col">
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

                <div class="footer-col">
                    <div class="brand-logo">
                        <img src="/images/ai_logo.png" class="w-30 bg-white rounded-full" alt="AiPro Logo" />
                        <!-- <span>AiPro</span> -->
                    </div>

                    <div class="app-buttons">
                        <a href="#" class="app-btn">تحميل من Google Play</a>
                        <a href="#" class="app-btn">تحميل من App Store</a>
                    </div>
                </div>
            </div>

            <!-- الجزء السفلي: الحقوق والروابط القانونية -->
            <div class="footer-bottom">
                <p class="copyright">© 2025 مؤسسة aiarabic — جميع الحقوق محفوظة</p>
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
// لا يحتاج إلى منطق إضافي، مجرد قالب عرض ثابت
</script>

<style scoped>
/* 🎨 تم تعديل الألوان والخطوط حسب طلبك */
.site-footer {
    --bg-color: #074377;
    /* ✅ اللون الجديد للخلفية */
    --text-color: #e2e8f0;
    --muted-color: #b3c7d9;
    /* ✅ تم تعديله ليتناسق مع الخلفية الزرقاء */
    --accent-color: #60a5fa;
    --border-color: #0a5a99;
    --hover-color: #ffffff;

    background-color: var(--bg-color);
    color: var(--text-color);
    padding: 48px 24px 24px;
    font-family: inherit;
    box-sizing: border-box;
}

.footer-container {
    max-width: 1200px;
    margin: 0 auto;
}

.footer-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 32px;
    margin-bottom: 40px;
}

.footer-col h3 {
    color: var(--hover-color);
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 16px;
}

.footer-col ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-col ul li {
    margin-bottom: 10px;
}

.footer-col ul li a {
    color: var(--muted-color);
    text-decoration: none;
    transition: color 0.2s ease;
}

.footer-col ul li a:hover {
    color: var(--hover-color);
}

.brand-logo {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--accent-color);
    margin-bottom: 16px;
    letter-spacing: 1px;
}

.app-buttons {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.app-btn {
    color: var(--muted-color);
    text-decoration: none;
    border: 1px solid var(--border-color);
    padding: 10px 14px;
    border-radius: 8px;
    transition: all 0.2s ease;
    text-align: center;
}

.app-btn:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: var(--hover-color);
    border-color: var(--accent-color);
}

.footer-bottom {
    border-top: 1px solid var(--border-color);
    padding-top: 24px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

/* ✅ تطبيق خط Year of Camels على نص الحقوق */
.copyright {
    color: #cfe6f5;
    margin: 0;
    font-size: 0.875rem;
    font-family: "Year of Camels", "Tajawal", "Cairo", "Almarai", sans-serif;
}

.legal-links {
    display: flex;
    gap: 20px;
}

.legal-links a {
    color: #b3c7d9;
    text-decoration: none;
    font-size: 0.875rem;
    transition: color 0.2s ease;
}

.legal-links a:hover {
    color: var(--hover-color);
}

@media (max-width: 768px) {
    .footer-bottom {
        flex-direction: column;
        text-align: center;
    }

    .legal-links {
        justify-content: center;
    }
}
</style>
