<template>
    <AdminLayout>
        <div class="container mt-4">
            <h1 class="mb-4">categorey Products</h1>

            <!-- Loading -->
            <div v-if="loading" class="text-center my-5">
                <div class="spinner-border text-primary"></div>
            </div>

            <!-- Error -->
            <div v-if="error" class="alert alert-danger">{{ error }}</div>

            <!-- Products Grid -->
            <div v-else-if="products.length" class="row g-4">
                <div v-for="product in products" :key="product.id" class="col-md-4">
                    <div class="card h-100 shadow-sm">
                        <img :src="getImageUrl(product.image)" @error="handleImageError" class="card-img-top"
                            style="max-height:200px; object-fit:cover" />
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">{{ product.name }}</h5>
                            <p class="card-text text-truncate">{{ product.description }}</p>
                            <p class="mb-1"><b>Price:</b> {{ product.price }}</p>
                            <p class="mb-1"><b>Tax:</b> {{ product.tax }}%</p>
                            <div class="mt-auto">
                                <button class="btn btn-sm btn-info text-white w-100" @click="goToProduct(product.id)">
                                    Show Product
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- No Products -->
            <div v-else class="text-center text-muted my-5">
                No products found for this categorey.
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import AdminLayout from '@/layouts/AdminLayout.vue'

const route = useRoute()
const router = useRouter()
const categoreyId = route.params.id

const products = ref([])
const loading = ref(false)
const error = ref(null)

// Placeholder image
const placeholder = "https://developers.elementor.com/docs/assets/img/elementor-placeholder-image.png"

// Get image URL
const getImageUrl = (img) => img ? img : placeholder
const handleImageError = (e) => e.target.src = placeholder

// Fetch categorey products
const fetchcategoreyProducts = async () => {
    loading.value = true
    error.value = null
    try {
        const response = await axios.get(`/v1/categories/${categoreyId}/products`)
        if (response.data.success) {
            console.log(response.data.data)
            products.value = response.data.data
        } else {
            console.warn('API returned success: false')
            products.value = []
            error.value = response.data.message || 'Failed to fetch products'
        }
    } catch (err) {
        console.error(err)
        error.value = 'Failed to fetch products. Please try again.'
    } finally {
        loading.value = false
    }
}

// Navigate to product
const goToProduct = (id) => {
    router.push(`/admin/products/${id}`)
}

onMounted(() => {
    fetchcategoreyProducts()
})
</script>

<style scoped>
.card-text {
    max-height: 3em;
    overflow: hidden;
}
</style>
