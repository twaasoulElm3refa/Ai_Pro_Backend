<template>
    <div class="admin-tools-page" :data-theme="theme">

        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stat-item">
                <span class="stat-number">{{ pagination.total }}</span>
                <span class="stat-label">إجمالي الأدوات</span>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item">
                <span class="stat-number">{{ pagination.last_page }}</span>
                <span class="stat-label">عدد الصفحات</span>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item">
                <span class="stat-number">{{ pagination.per_page }}</span>
                <span class="stat-label">لكل صفحة</span>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading-state">
            <div class="spinner"></div>
            <p>جارٍ تحميل الأدوات...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="error-state">
            <span class="error-icon">⚠️</span>
            <p>{{ error }}</p>
            <button @click="fetchTools" class="retry-btn">إعادة المحاولة</button>
        </div>

        <!-- Tools Grid -->
        <div v-else class="tools-grid">
            <div v-for="tool in tools" :key="tool.id" class="tool-card" @mouseenter="hoveredId = tool.id"
                @mouseleave="hoveredId = null">
                <!-- Card Glow Effect -->
                <div class="card-glow" :class="{ active: hoveredId === tool.id }"></div>

                <!-- Tool Image -->
                <div class="tool-image-wrapper">
                    <img :src="`${BASE_URL}/storage/${tool.image}`" :alt="tool.name" class="tool-image"
                        @error="handleImageError($event)" />
                    <div class="image-overlay">
                        <span class="tool-id">#{{ tool.id }}</span>
                    </div>
                </div>

                <!-- Card Content -->
                <div class="card-content">
                    <h3 class="tool-name">{{ tool.name }}</h3>
                    <p class="tool-description">{{ tool.description }}</p>
                    <div class="tool-meta">
                        <span class="meta-date">📅 {{ formatDate(tool.created_at) }}</span>
                    </div>
                </div>

                <!-- Card Actions -->
                <div class="card-actions">
                    <button class="action-btn edit-btn" title="تعديل" @click="show">
                       عرض
                    </button>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="!loading && !error && pagination.last_page > 1" class="pagination">
            <button class="page-btn" :class="{ disabled: !pagination.prev_page_url }"
                :disabled="!pagination.prev_page_url" @click="changePage(pagination.current_page - 1)">
                ← السابق
            </button>

            <button v-for="page in pagination.last_page" :key="page" class="page-btn page-number"
                :class="{ active: page === pagination.current_page }" @click="changePage(page)">
                {{ page }}
            </button>

            <button class="page-btn" :class="{ disabled: !pagination.next_page_url }"
                :disabled="!pagination.next_page_url" @click="changePage(pagination.current_page + 1)">
                التالي →
            </button>
        </div>

    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'

// ─── Config ───────────────────────────────────────────────
const BASE_URL = 'http://127.0.0.1:8000'
const API_URL = `${BASE_URL}/api/admin/tools`

// ─── State ────────────────────────────────────────────────
const theme = ref(localStorage.getItem('theme') || 'dark')
const tools = ref([])
const loading = ref(true)
const error = ref(null)
const hoveredId = ref(null)

const pagination = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    next_page_url: null,
    prev_page_url: null,
})

// ─── Helpers ──────────────────────────────────────────────
const toggleTheme = () => {
    theme.value = theme.value === 'dark' ? 'light' : 'dark'
    localStorage.setItem('theme', theme.value)
}

const formatDate = (iso) => {
    return new Date(iso).toLocaleDateString('ar-EG', {
        year: 'numeric', month: 'short', day: 'numeric'
    })
}

const handleImageError = (e) => {
    e.target.src = 'https://placehold.co/400x220/1e293b/FBBF24?text=No+Image'
}

// ─── API ──────────────────────────────────────────────────
const fetchTools = async (page = 1) => {
    loading.value = true
    error.value = null
    try {
        const token = localStorage.getItem('auth_token') // adjust key as needed
        const res = await fetch(`${API_URL}?page=${page}`, {
            headers: {
                'Accept': 'application/json',
                'x-api-key': 'K7xP9mQ2vR8tL3sNf6GdJ1aB9zW4cH0y',
                ...(token ? { 'Authorization': `Bearer ${token}` } : {})
            }
        })
        if (!res.ok) throw new Error(`HTTP ${res.status}`)
        const json = await res.json()
        const d = json.data

        tools.value = d.data
        pagination.current_page = d.current_page
        pagination.last_page = d.last_page
        pagination.per_page = d.per_page
        pagination.total = d.total
        pagination.next_page_url = d.next_page_url
        pagination.prev_page_url = d.prev_page_url
    } catch (e) {
        error.value = 'فشل تحميل الأدوات: ' + e.message
    } finally {
        loading.value = false
    }
}

const changePage = (page) => {
    if (page < 1 || page > pagination.last_page) return
    fetchTools(page)
}

onMounted(() => fetchTools())

const show = () => {
    window.location.href = '/en/tool/show'
}
</script>

<style scoped>
/* ── Google Font ── */
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap');

/* ── Root ── */
.admin-tools-page {
    font-family: 'Cairo', sans-serif;
    direction: rtl;
    min-height: 100vh;
    padding: 2rem;
    transition: background 0.4s ease, color 0.4s ease;
}

/* ═══════════════════════════════════════
   THEME TOKENS
═══════════════════════════════════════ */
[data-theme='dark'] {
    --bg: #0F172A;
    --surface: rgba(30, 41, 59, 0.55);
    --surface-h: rgba(30, 41, 59, 0.80);
    --border: rgba(212, 175, 55, 0.30);
    --border-h: #FBBF24;
    --text: #e2e8f0;
    --text-muted: #94a3b8;
    --gold: #FBBF24;
    --gold-dim: rgba(212, 175, 55, 0.15);
    --gold-glow: rgba(251, 191, 36, 0.40);
    --accent: #FBBF24;
    --accent-text: #0F172A;
    --danger: #f87171;
    --badge-bg: rgba(251, 191, 36, 0.12);
    --badge-color: #FBBF24;
}

[data-theme='light'] {
    --bg: #f8fafc;
    --surface: rgba(255, 255, 255, 0.95);
    --surface-h: #ffffff;
    --border: #e2e8f0;
    --border-h: #111827;
    --text: #111827;
    --text-muted: #6b7280;
    --gold: #111827;
    --gold-dim: rgba(0, 0, 0, 0.05);
    --gold-glow: rgba(0, 0, 0, 0.12);
    --accent: #111827;
    --accent-text: #ffffff;
    --danger: #ef4444;
    --badge-bg: #f1f5f9;
    --badge-color: #334155;
}

.admin-tools-page {
    background: var(--bg);
    color: var(--text);
}

/* ═══════════════════════════════════════
   PAGE HEADER
═══════════════════════════════════════ */
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.75rem;
    flex-wrap: wrap;
}

.page-badge {
    display: inline-block;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    padding: 0.2rem 0.7rem;
    border-radius: 999px;
    background: var(--badge-bg);
    color: var(--badge-color);
    border: 1px solid var(--border);
    margin-bottom: 0.4rem;
}

.page-title {
    font-size: 2rem;
    font-weight: 900;
    margin: 0 0 0.25rem;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.page-subtitle {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin: 0;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
}

.theme-toggle {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 50%;
    width: 40px;
    height: 40px;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.theme-toggle:hover {
    border-color: var(--border-h);
    transform: rotate(20deg);
}

.add-btn {
    background: var(--accent);
    color: var(--accent-text);
    border: none;
    border-radius: 10px;
    padding: 0.55rem 1.25rem;
    font-family: 'Cairo', sans-serif;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    transition: all 0.3s;
}

.add-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px var(--gold-glow);
}

/* ═══════════════════════════════════════
   STATS BAR
═══════════════════════════════════════ */
.stats-bar {
    display: flex;
    align-items: center;
    gap: 0;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1rem 2rem;
    margin-bottom: 2rem;
    width: fit-content;
    backdrop-filter: blur(10px);
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0 1.5rem;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 900;
    color: var(--gold);
    line-height: 1;
}

.stat-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.2rem;
}

.stat-divider {
    width: 1px;
    height: 36px;
    background: var(--border);
}

/* ═══════════════════════════════════════
   LOADING / ERROR
═══════════════════════════════════════ */
.loading-state,
.error-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding: 4rem;
    color: var(--text-muted);
}

.spinner {
    width: 48px;
    height: 48px;
    border: 3px solid var(--border);
    border-top-color: var(--gold);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.error-icon {
    font-size: 2.5rem;
}

.retry-btn {
    background: var(--accent);
    color: var(--accent-text);
    border: none;
    border-radius: 8px;
    padding: 0.5rem 1.5rem;
    font-family: 'Cairo', sans-serif;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
}

/* ═══════════════════════════════════════
   TOOLS GRID
═══════════════════════════════════════ */
.tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
}

/* ── Card ── */
.tool-card {
    position: relative;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 18px;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    backdrop-filter: blur(10px);
}

.tool-card:hover {
    transform: translateY(-8px) scale(1.02);
    border-color: var(--border-h);
    background: var(--surface-h);
    box-shadow: 0 20px 50px var(--gold-glow);
}

/* Glow */
.card-glow {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 50% 0%, var(--gold-dim) 0%, transparent 70%);
    opacity: 0;
    transition: opacity 0.4s;
    pointer-events: none;
    z-index: 0;
}

.card-glow.active {
    opacity: 1;
}

/* Image */
.tool-image-wrapper {
    position: relative;
    width: 100%;
    height: 200px;
    overflow: hidden;
    background: var(--gold-dim);
}

.tool-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.tool-card:hover .tool-image {
    transform: scale(1.08);
}

.image-overlay {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
}

.tool-id {
    background: var(--badge-bg);
    color: var(--badge-color);
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: 0.15rem 0.6rem;
    font-size: 0.72rem;
    font-weight: 700;
    backdrop-filter: blur(8px);
}

/* Content */
.card-content {
    position: relative;
    z-index: 1;
    padding: 1.1rem 1.25rem 0.75rem;
}

.tool-name {
    font-size: 1.05rem;
    font-weight: 700;
    margin: 0 0 0.4rem;
    color: var(--text);
    line-height: 1.4;
}

.tool-description {
    font-size: 0.83rem;
    color: var(--text-muted);
    margin: 0 0 0.75rem;
    line-height: 1.6;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.tool-meta {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.meta-date {
    font-size: 0.75rem;
    color: var(--text-muted);
}

/* Actions */
.card-actions {
    position: relative;
    z-index: 1;
    display: flex;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem 1.1rem;
    border-top: 1px solid var(--border);
    margin-top: 0.25rem;
}

.action-btn {
    flex: 1;
    padding: 0.45rem 0.75rem;
    border-radius: 8px;
    font-family: 'Cairo', sans-serif;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid;
    transition: all 0.3s;
}

.edit-btn {
    background: var(--gold-dim);
    color: var(--gold);
    border-color: var(--border);
}

.edit-btn:hover {
    background: var(--gold);
    color: var(--accent-text);
    border-color: var(--gold);
    transform: translateY(-2px);
}

.delete-btn {
    background: transparent;
    color: var(--danger);
    border-color: var(--border);
}

.delete-btn:hover {
    background: rgba(248, 113, 113, 0.12);
    border-color: var(--danger);
    transform: translateY(-2px);
}

/* ═══════════════════════════════════════
   PAGINATION
═══════════════════════════════════════ */
.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2.5rem;
    flex-wrap: wrap;
}

.page-btn {
    background: var(--surface);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.45rem 1rem;
    font-family: 'Cairo', sans-serif;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.page-btn:hover:not(.disabled):not(.active) {
    border-color: var(--border-h);
    transform: translateY(-2px);
}

.page-btn.active {
    background: var(--accent);
    color: var(--accent-text);
    border-color: var(--accent);
}

.page-btn.disabled {
    opacity: 0.35;
    cursor: not-allowed;
}
</style>
