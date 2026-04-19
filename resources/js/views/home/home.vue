<template>
    <div class="min-vh-50 d-flex flex-column position-relative overflow-hidden">
        <!-- Animated Waves Overlay -->
        <div class="position-absolute w-100 h-100 waves"></div>
        <!-- Navbar -->
        <Navbar class="top-0 start-0 end-0 z-3 shadow-lg" />
    </div>

</template>

<script setup>
import Navbar from '@/components/layouts/Navbar.vue'

const categories = [
    { id: 1, icon: 'T', name: 'T-Shirts' },
    { id: 2, icon: 'S', name: 'Shirts' },
    { id: 3, icon: 'P', name: 'Pants' },
    { id: 4, icon: 'J', name: 'Jackets' },
    { id: 5, icon: 'S', name: 'Sweaters' },
    { id: 6, icon: 'C', name: 'Coats' },
];

const featuredProducts = [
    {
        id: 1,
        name: 'product Sale',
        image: '/images/categorey.png',
        price: 89.99
    },
    {
        id: 2,
        name: 'product Sale',
        image: '/images/categorey.png',
        price: 129.00
    },
    {
        id: 3,
        name: 'product Sale',
        image: '/images/categorey.png',
        price: 349.50
    },
    {
        id: 4,
        name: 'product Sale',
        image: '/images/categorey.png',
        price: 59.99
    },
];

const bestSellingProducts = [
    {
        id: 1,
        name: 'product Sale',
        image: '/images/categorey.png',
        price: 89.99
    },
    {
        id: 2,
        name: 'product Sale',
        image: '/images/categorey.png',
        price: 129.00
    },
    {
        id: 3,
        name: 'product Sale',
        image: '/images/categorey.png',
        price: 349.50
    },
    {
        id: 4,
        name: 'product Sale',
        image: '/images/categorey.png',
        price: 59.99
    },
];
</script>

<style scoped>
.z-3 {
    z-index: 1030 !important;
}

.overflow-auto {
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: rgba(100, 116, 139, 0.4) transparent;
}

.overflow-auto::-webkit-scrollbar {
    height: 8px;
}

.overflow-auto::-webkit-scrollbar-thumb {
    background: rgba(100, 116, 139, 0.4);
    border-radius: 10px;
}

/* Button base (will be overridden per theme) */
.btn-glow {
    border: none;
    font-weight: bold;
    transition: all 0.35s ease;
}

/* Text glow – only used in dark */
.text-glow {
    background: linear-gradient(90deg, #D4AF37, #FBBF24, #EAB308);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    filter: drop-shadow(0 0 12px rgba(212, 175, 55, 0.55));
}

/* Waves – very subtle in light mode */
.waves {
    background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23D4AF37" fill-opacity="0.06" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,138.7C960,139,1056,117,1152,122.7C1248,128,1344,160,1392,176L1440,192L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') bottom no-repeat;
    background-size: cover;
    animation: wave 28s linear infinite alternate;
    opacity: 0.35;
    transition: opacity 0.5s ease, filter 0.5s ease;
}

@keyframes wave {
    0% {
        transform: translateX(0);
    }

    100% {
        transform: translateX(-25%);
    }
}

.category-divider {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 100vw;
    transform: translateX(-50%);
    border: none;
    border-top: 3px solid #D4AF37;
    /* سماكة جيدة */
    box-shadow: 0 0 3px #D4AF37;
    /* ظل خفيف يبرز الخط */
    z-index: 10;
    /* خلي الخط فوق أي عناصر تانية */
    transition: border-color 0.4s ease;
}



/* Category card base */
.category-card {
    width: 130px;
    min-width: 120px;
    height: 110px;
    padding: 0.75rem 1rem;
    border: 1px solid;
    border-radius: 14px;
    box-shadow: 0 5px 18px rgba(0, 0, 0, 0.12), inset 0 0 12px rgba(0, 0, 0, 0.04);
    transition: all 0.35s ease;
    backdrop-filter: blur(8px);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    position: relative;
}

.category-icon {
    position: absolute;
    top: -28px;
    left: 50%;
    transform: translateX(-50%);
    width: 56px;
    height: 56px;
    border: 2px solid;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.9rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    opacity: 0;
    transition: all 0.35s ease;
    z-index: 3;
}

.category-card:hover .category-icon {
    opacity: 1;
    top: -32px;
    transform: translateX(-50%) scale(1.12);
}

/* Featured / Best-selling card base */
.featured-card {
    width: 240px;
    min-width: 220px;
    height: 320px;
    padding: 1.25rem;
    border: 1px solid;
    border-radius: 16px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1), inset 0 0 12px rgba(0, 0, 0, 0.03);
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    backdrop-filter: blur(8px);
    overflow: hidden;
    position: relative;
}

.featured-card:hover {
    transform: translateY(-12px) scale(1.06);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.18);
}

.product-image-wrapper {
    width: 100%;
    height: 220px;
    overflow: hidden;
    border-radius: 12px;
    background: rgba(0, 0, 0, 0.06);
}

.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.featured-card:hover .product-image {
    transform: scale(1.10);
}

/* Hero image hover */
.hero-image {
    transition: transform 0.6s ease;
}

.hero-image:hover {
    transform: scale(1.03);
}

/* ==========================================================================
   DARK MODE (default – your original look)
   ========================================================================== */

[data-theme='dark'] .waves {
    opacity: 0.35;
}

[data-theme='dark'] .category-divider {
    border-top-color: rgba(251, 191, 36, 0.35);
}

.exclusive-picks {
    text-align: center;
    margin-top: 2rem;
    margin-bottom: 0.5rem;
    font-size: 1.25rem;
    /* fs-4 */
    transition: color 0.3s ease;
}

/* Light mode */
[data-theme='light'] .exclusive-picks {
    color: #ffffff;
    /* light black / gray-900 */
}

/* Dark mode */
[data-theme='dark'] .exclusive-picks {
    color: #FBBF24;
    /* gold */
}

[data-theme='dark'] .category-card {
    position: relative;
    /* تأكد إنه موجود */
    z-index: 5;

    background: rgba(30, 41, 59, 0.38);
    border-color: rgba(212, 175, 55, 0.45);
    box-shadow: 0 5px 18px rgba(212, 175, 55, 0.22), inset 0 0 12px rgba(212, 175, 55, 0.07);
}

[data-theme='dark'] .category-card:hover {
    z-index: 15;
    transform: translateY(-8px) scale(1.06);
    box-shadow: 0 16px 40px rgba(212, 175, 55, 0.48), inset 0 0 18px rgba(212, 175, 55, 0.18);
    border-color: #FBBF24;
    background: rgba(30, 41, 59, 0.55);
}

[data-theme='dark'] .category-icon {
    background: rgba(212, 175, 55, 0.18);
    border-color: rgba(212, 175, 55, 0.55);
    color: #FBBF24;
}

[data-theme='dark'] .category-card:hover .category-icon {
    background: rgba(212, 175, 55, 0.38);
    border-color: #FBBF24;
    box-shadow: 0 0 20px rgba(251, 191, 36, 0.65);
}

[data-theme='dark'] .category-name {
    color: #e2e8f0;
}

[data-theme='dark'] .featured-card {
    position: relative;
    z-index: 5;
    background: rgba(30, 41, 59, 0.35);
    border-color: rgba(212, 175, 55, 0.4);
}

.add-to-cart-btn {
    font-weight: bold;
    padding: 0.5rem 1rem;
    /* px-4 py-2 */
    border: none;
    border-radius: 6px;
    transition: all 0.35s ease;
    cursor: pointer;
}

/* Light Mode */
[data-theme='light'] .add-to-cart-btn {
    background: #111827;
    /* أسود */
    color: #ffffff;
}

[data-theme='light'] .add-to-cart-btn:hover {
    transform: scale(1.08);
    background: #000000;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
}

/* Dark Mode */
[data-theme='dark'] .add-to-cart-btn {
    background: #FBBF24;
    /* gold */
    color: #0F172A;
    /* dark text on gold */
}

[data-theme='dark'] .add-to-cart-btn:hover {
    transform: scale(1.08);
    background: #FFD700;
    /* brighter gold */
    box-shadow: 0 8px 20px rgba(255, 223, 0, 0.4);
}

[data-theme='dark'] .featured-card:hover {
    position: relative;
    z-index: 5;
    border-color: #FBBF24;
    background: rgba(30, 41, 59, 0.5);
}

[data-theme='dark'] .btn-glow {
    background: linear-gradient(135deg, #FBBF64, #f59e0b);
    color: #0f172a;
    box-shadow: 0 10px 30px rgba(251, 191, 100, 0.25);
}

[data-theme='dark'] .btn-glow:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 35px rgba(251, 191, 100, 0.5);
    background: linear-gradient(135deg, #fcd34d, #fbbf24);
}

/* ==========================================================================
   LIGHT MODE – pure black & white + soft grays
   ========================================================================== */

[data-theme='light'] .waves {
    opacity: 0.08;
    filter: brightness(0.4) grayscale(1);
}

[data-theme='light'] .category-divider {
    border-top-color: rgba(0, 0, 0, 0.12);
}

[data-theme='light'] .category-card {
    background: rgba(255, 255, 255, 0.92);
    border-color: #d1d5db;
    /* gray-300 */
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

[data-theme='light'] .category-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12);
    border-color: #111827;
    /* gray-900 */
}

[data-theme='light'] .category-icon {
    background: #f3f4f6;
    /* gray-100 */
    border-color: #9ca3af;
    /* gray-400 */
    color: #111827;
}

[data-theme='light'] .category-card:hover .category-icon {
    background: #e5e7eb;
    /* gray-200 */
    border-color: #111827;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
}

[data-theme='light'] .category-name {
    color: #1f2937;
}

/* gray-800 */

[data-theme='light'] .featured-card {
    background: white;
    border-color: #e5e7eb;
}

[data-theme='light'] .featured-card:hover {
    border-color: #111827;
}

[data-theme='light'] .product-image-wrapper {
    background: #f9fafb;
}

[data-theme='light'] .text-glow,
[data-theme='light'] .text-glow * {
    background: none !important;
    -webkit-background-clip: unset !important;
    -webkit-text-fill-color: #111827 !important;
    /* 👈 السطر المهم */
    color: #111827 !important;
    filter: none !important;
    text-shadow: none !important;
}


/* Light mode button – black */
[data-theme='light'] .btn-glow {
    background: #111827;
    color: white;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
}

[data-theme='light'] .btn-glow:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.15);
    background: #1f2937;
    /* gray-800 hover */
}

/* Text colors in light mode */
[data-theme='light'] .text-light {
    color: #111827 !important;
}

.must-have-styles {
    text-align: center;
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
    font-weight: bold;
    font-size: 3.5rem;
    /* display-5 */
    transition: color 0.3s ease;
}

.top-trending-styles {
    text-align: center;
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
    font-weight: bold;
    font-size: 3.5rem;
    /* display-5 */
    transition: color 0.3s ease;
}

/* Light mode */
[data-theme='light'] .top-trending-styles {
    color: #111827;
    /* أسود فاتح */
    background: none !important;
    /* نتأكد إن text-glow ما يأثرش */
    -webkit-background-clip: unset !important;
    -webkit-text-fill-color: unset !important;
    filter: none !important;
}

/* Dark mode – يفضل text-glow كما هو */

/* Light mode */
[data-theme='light'] .must-have-styles {
    color: #111827;
    /* أسود فاتح */
    background: none !important;
    /* نتأكد إن text-glow ما يأثرش */
    -webkit-background-clip: unset !important;
    -webkit-text-fill-color: unset !important;
    filter: none !important;
}

/* Dark mode – نتركه زي ما هو (text-glow) */


[data-theme='light'] h1,
[data-theme='light'] h2,
[data-theme='light'] h3 {
    color: #111827;
}

[data-theme='light'] p {
    color: #374151;
}

/* gray-700 */

[data-theme='light'] .text-muted {
    color: #6b7280;
    /* gray-500 */
}

[data-theme='light'] .text-muted a {
    color: #6b7280;
    /* gray-500 */

}

.favorites-picks {
    text-align: center;
    margin-top: 2rem;
    margin-bottom: 0.5rem;
    font-size: 1.25rem;
    /* fs-4 */
    transition: color 0.3s ease;
}

/* Light mode */
[data-theme='light'] .favorites-picks {
    color: #111827;
    /* light black / gray-900 */
}

/* Dark mode */
[data-theme='dark'] .favorites-picks {
    color: #FBBF24;
    /* gold */
}
</style>
