<template>
    <AdminLayout>
        <div class="p-6 admin-products-container">
            <h1 class="text-2xl font-bold mb-6 title">All Products</h1>

            <!-- Loading -->
            <div v-if="loading" class="text-center py-12">
                <p class="loading-text">جاري تحميل المنتجات...</p>
            </div>

            <!-- Error -->
            <div v-else-if="error" class="text-center py-12 error-message">
                {{ error }}
            </div>

            <!-- Table -->
            <div v-else-if="products.length" class="overflow-x-auto table-container">
                <table class="min-w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="th">ID</th>
                            <th class="th">الصورة</th>
                            <th class="th">الاسم</th>
                            <th class="th">السعر</th>
                            <th class="th">الضريبة</th>
                            <th class="th">التصنيف</th>
                            <th class="th">الماركة</th>
                            <th class="th">نشط</th>
                            <th class="th">مميز</th>
                            <th class="th">رقم التشغيلة</th>
                            <th class="th">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="product in products" :key="product.id" class="hover-row">
                            <td class="td">{{ product.id }}</td>
                            <td class="td">
                                <img :src="product.image || defaultPlaceholder"
                                    class="h-12 w-12 object-cover rounded-md shadow-sm" @error="setPlaceholder" />
                            </td>
                            <td class="td font-medium">{{ product.name }}</td>
                            <td class="td">{{ Number(product.price).toFixed(2) }} ج.م</td>
                            <td class="td">{{ Number(product.tax).toFixed(2) }}%</td>
                            <td class="td">{{ product.categories_id }}</td>
                            <td class="td">{{ product.brands_id }}</td>
                            <td class="td">
                                <span :class="product.is_active ? 'status-active' : 'status-inactive'">
                                    {{ product.is_active ? 'نعم' : 'لا' }}
                                </span>
                            </td>
                            <td class="td">
                                <span :class="product.is_featured ? 'status-featured' : 'status-not-featured'">
                                    {{ product.is_featured ? 'نعم' : 'لا' }}
                                </span>
                            </td>
                            <td class="td">{{ product.sku ? product.sku : 'لا يوجد' }}</td>
                            <td class="td flex gap-2 justify-center">
                                <!-- زرار العرض الجديد -->
                                <button class="view-btn" @click="goToProductDetails(product.id)">
                                    عرض
                                </button>
                                <button class="delete-btn" @click="deleteProduct(product.id)">حذف</button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="mt-8 flex flex-col sm:flex-row justify-between items-center gap-5">
                    <div class="text-sm showing-text">
                        عرض {{ meta.from || 0 }}–{{ meta.to || 0 }} من {{ meta.total || 0 }} منتج
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button @click="changePage(currentPage - 1)" :disabled="currentPage === 1"
                            class="pagination-btn">
                            السابق
                        </button>
                        <button v-for="page in visiblePages" :key="page" @click="changePage(page)"
                            :class="['pagination-btn', { 'active-page': currentPage === page }]">
                            {{ page }}
                        </button>
                        <button @click="changePage(currentPage + 1)" :disabled="currentPage === meta.last_page"
                            class="pagination-btn">
                            التالي
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-12 empty-text">
                لا توجد منتجات
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'   // ← أضفنا ده
import axios from 'axios'
import AdminLayout from '@/layouts/AdminLayout.vue'

const router = useRouter()   // ← للتوجيه

const products = ref([])
const meta = ref({ current_page: 1, last_page: 1, from: 0, to: 0, total: 0 })
const loading = ref(false)
const error = ref(null)
const currentPage = ref(1)

const defaultPlaceholder = 'https://placehold.co/48x48/e5e7eb/6b7280?text=No+Image'

const setPlaceholder = (e) => {
    e.target.src = defaultPlaceholder
    e.target.onerror = null
}

const visiblePages = computed(() => {
    const pages = []
    const maxVisible = 7
    const last = meta.value.last_page || 1
    let start = Math.max(1, currentPage.value - Math.floor(maxVisible / 2))
    let end = Math.min(last, start + maxVisible - 1)
    if (end - start + 1 < maxVisible) start = Math.max(1, end - maxVisible + 1)
    for (let i = start; i <= end; i++) pages.push(i)
    return pages
})

// Fetch Products
const fetchProducts = async (page = 1) => {
    loading.value = true
    error.value = null
    try {
        const res = await axios.get(`/v1/products?page=${page}`, {
            headers: {
                Authorization: `Bearer ${localStorage.getItem('auth_token') || ''}`,
                Accept: 'application/json',
            },
        })
        if (res.data.success) {
            products.value = res.data.data?.data || res.data.data || []
            meta.value = { ...meta.value, ...res.data.data, current_page: page }
            currentPage.value = page
        } else {
            error.value = res.data.message || 'حدث خطأ أثناء جلب المنتجات'
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'حدث خطأ في الاتصال بالخادم'
    } finally {
        loading.value = false
    }
}

// الانتقال لصفحة تفاصيل المنتج
const goToProductDetails = (id) => {
    router.push(`/admin/products/${id}`)
}

// Delete Product
const deleteProduct = async (id) => {
    if (!confirm('متأكد من حذف المنتج نهائياً؟')) return
    try {
        const res = await axios.delete(`/v1/products/${id}`, {
            headers: {
                Authorization: `Bearer ${localStorage.getItem('auth_token') || ''}`,
                Accept: 'application/json',
            },
        })
        if (res.data.success) {
            alert('تم حذف المنتج بنجاح')
            fetchProducts(currentPage.value)
        } else {
            alert(res.data.message || 'فشل الحذف')
        }
    } catch (err) {
        alert(err.response?.data?.message || 'حدث خطأ أثناء الحذف')
    }
}

// Pagination
const changePage = (page) => {
    if (page < 1 || page > meta.value.last_page || page === currentPage.value) return
    fetchProducts(page)
}

onMounted(() => {
    fetchProducts(1)
})
</script>

<style scoped>
/* Buttons */
.view-btn {
    background: #10b981;
    /* أخضر مناسب */
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    font-weight: 500;
}

.view-btn:hover {
    background: #059669;
}

.edit-btn {
    background: #2563eb;
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
}

.delete-btn {
    background: #dc2626;
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
}

/* Container */
.admin-products-container {
    transition: all 0.3s ease;
}

.title {
    color: #111827;
}

[data-theme='dark'] .title {
    color: #f3f4f6;
}

/* Text States */
.loading-text,
.empty-text {
    color: #6b7280;
}

[data-theme='dark'] .loading-text,
[data-theme='dark'] .empty-text {
    color: #9ca3af;
}

.error-message {
    color: #dc2626;
}

[data-theme='dark'] .error-message {
    color: #f87171;
}

/* Table */
.table-container {
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
    background: white;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

[data-theme='dark'] .table-container {
    border-color: #374151;
    background: #1e293b;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
}

th.th {
    padding: 0.875rem 1.5rem;
    text-align: center;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    color: #4b5563;
    background: #f3f4f6;
    border-bottom: 1px solid #e5e7eb;
}

[data-theme='dark'] th.th {
    color: #d1d5db;
    background: #334155;
    border-bottom-color: #475569;
}

td.td {
    padding: 1rem 1.5rem;
    text-align: center;
    font-size: 0.875rem;
    color: #111827;
}

[data-theme='dark'] td.td {
    color: #e5e7eb;
}

.hover-row:hover {
    background: #f9fafb;
}

[data-theme='dark'] .hover-row:hover {
    background: #334155;
}

/* Status */
.status-active {
    color: #16a34a;
    font-weight: 500;
}

[data-theme='dark'] .status-active {
    color: #4ade80;
}

.status-inactive {
    color: #dc2626;
    font-weight: 500;
}

[data-theme='dark'] .status-inactive {
    color: #f87171;
}

.status-featured {
    color: #9333ea;
    font-weight: 500;
}

[data-theme='dark'] .status-featured {
    color: #c084fc;
}

.status-not-featured {
    color: #6b7280;
    font-weight: 500;
}

[data-theme='dark'] .status-not-featured {
    color: #9ca3af;
}

/* Pagination */
.showing-text {
    color: #4b5563;
}

[data-theme='dark'] .showing-text {
    color: #d1d5db;
}

.pagination-btn {
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    transition: all 0.15s;
    background: #f3f4f6;
    color: #374151;
    border: none;
    cursor: pointer;
}

.pagination-btn:hover:not(:disabled) {
    background: #e5e7eb;
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

[data-theme='dark'] .pagination-btn {
    background: #334155;
    color: #e5e7eb;
}

[data-theme='dark'] .pagination-btn:hover:not(:disabled) {
    background: #475569;
}

.active-page {
    background: #2563eb;
    color: white;
}

.active-page:hover {
    background: #1d4ed8;
}

[data-theme='dark'] .active-page {
    background: #3b82f6;
}

[data-theme='dark'] .active-page:hover {
    background: #60a5fa;
}
</style>
