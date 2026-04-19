<template>
    <AdminLayout>
        <div :class="['wrapper', theme]" :data-theme="theme">
            <div class="header">
                <h1 class="title">Brands</h1>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="loading">Loading brands...</div>

            <!-- Error -->
            <div v-if="error" class="alert error-bg">
                {{ error }}
            </div>

            <!-- Brands List -->
            <div v-else-if="brands.length" class="brands-grid">
                <div v-for="brand in brands" :key="brand.id" class="brand-card">
                    <div class="brand-image">
                        <img v-if="brand.image" :src="getImageUrl(brand.image)" alt="Brand logo" class="image"
                            @error="handleImageError($event)" />
                        <div v-else class="no-image">No Image</div>
                    </div>

                    <div class="brand-info">
                        <h3 class="brand-name">{{ brand.name }}</h3>
                        <div class="brand-actions d-flex gap-2">
                            <button class="btn btn-sm btn-primary" @click="editBrand(brand)">Edit</button>
                            <button class="btn btn-sm btn-danger" @click="deleteBrand(brand.id)">Delete</button>
                            <!-- زرار Show -->
                            <button class="btn btn-sm btn-info text-white" @click="goToBrand(brand.id)">
                                Show
                            </button>
                        </div>
                    </div>
                </div>
            </div>



            <div v-else class="no-data">No brands found.</div>

            <!-- Pagination -->
            <div v-if="pagination.last_page > 1" class="pagination">
                <button class="page-btn" :disabled="!pagination.prev_page_url" @click="changePage(currentPage - 1)">
                    ← Previous
                </button>

                <button v-for="page in visiblePages" :key="page" class="page-btn"
                    :class="{ active: page === currentPage }" @click="changePage(page)">
                    {{ page }}
                </button>

                <button class="page-btn" :disabled="!pagination.next_page_url" @click="changePage(currentPage + 1)">
                    Next →
                </button>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const theme = localStorage.getItem('theme') || 'light'

const brands = ref([])
const pagination = ref({})
const currentPage = ref(1)
const loading = ref(false)
const error = ref(null)

// Fetch brands
const fetchBrands = async (page = 1) => {
    loading.value = true
    error.value = null
    try {
        const response = await axios.get(`/v1/brands?page=${page}`)
        const responseData = response.data.data

        brands.value = responseData.data // array of brands

        // pagination
        pagination.value = {
            current_page: responseData.current_page,
            last_page: responseData.last_page,
            prev_page_url: responseData.prev_page_url,
            next_page_url: responseData.next_page_url,
            total: responseData.total,
            per_page: responseData.per_page,
        }
        currentPage.value = responseData.current_page
    } catch (err) {
        error.value = 'Failed to load brands. Please try again.'
        console.error(err)
    } finally {
        loading.value = false
    }
}

// Handle image error
const handleImageError = (e) => {
    e.target.style.display = 'none'
    e.target.parentNode.innerHTML = '<div class="no-image">Image Failed</div>'
}

const getImageUrl = (path) => {
    return path ? `/storage/${path}` : null
}


// Pagination logic: show up to 5 pages
const visiblePages = computed(() => {
    const pages = []
    const last = pagination.value.last_page || 1
    const current = currentPage.value
    let start = current - 2
    let end = current + 2
    if (start < 1) {
        end += 1 - start
        start = 1
    }
    if (end > last) {
        start -= end - last
        end = last
    }
    if (start < 1) start = 1
    for (let i = start; i <= end; i++) pages.push(i)
    return pages
})


const goToBrand = (id) => {
    router.push(`/admin/brands/${id}`)
}
// Change page
const changePage = (page) => {
    if (page < 1 || page > pagination.value.last_page || page === currentPage.value) return
    fetchBrands(page)
}

// Edit brand (example: update name)
const editBrand = async (brand) => {
    const newName = prompt('Enter new brand name:', brand.name)
    if (!newName || newName.trim() === '') return

    try {
        await axios.post(`/v1/brands/${brand.id}`, { name: newName })
        // Update locally
        brand.name = newName
        alert('Brand updated successfully!')
    } catch (err) {
        console.error(err)
        alert('Failed to update brand.')
    }
}

// Delete brand
const deleteBrand = async (id) => {
    if (!confirm('Are you sure you want to delete this brand?')) return
    try {
        await axios.delete(`/v1/brands/${id}`)
        // Remove from local array
        brands.value = brands.value.filter((b) => b.id !== id)
        alert('Brand deleted successfully!')
    } catch (err) {
        console.error(err)
        alert('Failed to delete brand.')
    }
}

onMounted(() => {
    fetchBrands(1)
})
</script>

<style scoped>
.wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 30px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.title {
    font-size: 28px;
    font-weight: bold;
}

.brands-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 24px;
}

.brand-card {
    background: var(--card-bg);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: transform 0.2s, box-shadow 0.2s;
}

.brand-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.brand-image {
    height: 160px;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    /* مهم لتجنب خروج الصورة خارج الكارد */
    position: relative;
}

.brand-image img.image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    /* <-- هذا هو المفتاح */
    object-position: center;
    /* يركّز الصورة في الوسط */
}


[data-theme='dark'] .brand-image {
    background: #1f2937;
}

.image {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.no-image {
    color: #9ca3af;
    font-weight: 500;
    font-size: 1.1rem;
}

.brand-info {
    padding: 16px;
    text-align: center;
}

.brand-name {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0 0 8px;
}

.brand-slug {
    color: #6b7280;
    font-size: 0.9rem;
    margin: 0 0 12px;
}

.brand-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.edit-btn {
    background: #3b82f6;
    color: white;
    border: none;
}

.edit-btn:hover {
    background: #2563eb;
}

.delete-btn {
    background: #ef4444;
    color: white;
    border: none;
}

.delete-btn:hover {
    background: #dc2626;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    margin-top: 40px;
    flex-wrap: wrap;
}

.page-btn {
    padding: 8px 16px;
    border-radius: 8px;
    background: var(--btn-bg);
    color: var(--btn-text);
    border: 1px solid var(--border);
    cursor: pointer;
    transition: all 0.2s;
}

.page-btn:hover:not(:disabled) {
    background: var(--btn-hover);
}

.page-btn.active {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.loading,
.no-data {
    text-align: center;
    padding: 60px 0;
    color: #6b7280;
    font-size: 1.2rem;
}

/* Light / Dark variables */
[data-theme='light'] {
    --card-bg: #ffffff;
    --btn-bg: #f3f4f6;
    --btn-text: #1f2937;
    --btn-hover: #e5e7eb;
    --border: #d1d5db;
}

[data-theme='dark'] {
    --card-bg: #1f2937;
    --btn-bg: #374151;
    --btn-text: #f3f4f6;
    --btn-hover: #4b5563;
    --border: #4b5563;
}
</style>
