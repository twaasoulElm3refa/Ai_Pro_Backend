<template>
    <AdminLayout>
        <div class="container mt-4">

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>{{ product?.name || 'تفاصيل المنتج' }}</h2>

                <button class="btn btn-primary" @click="openEditModal">
                    <i class="bi bi-pencil-square"></i> Edit
                </button>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="text-center my-5">
                <div class="spinner-border text-primary"></div>
            </div>

            <!-- Product Info -->
            <div v-else-if="product" class="card p-4 shadow-sm">

                <div class="row">
                    <!-- شمال -->
                    <div class="col-md-6">
                        <p><b>Name:</b> {{ product.name }}</p>
                        <p><b>Description:</b> {{ product.description }}</p>
                        <p><b>Category:</b> {{ product?.category?.name }}</p>
                        <p><b>Brand:</b> {{ product?.brand?.name }}</p>
                        <p><b>Price:</b> {{ product.price }}</p>
                    </div>

                    <!-- يمين -->
                    <div class="col-md-6">
                        <p><b>Tax:</b> {{ product.tax }}%</p>
                        <p><b>SKU:</b> {{ product.sku }}</p>
                        <p><b>Active:</b> {{ product.is_active }}</p>
                        <p><b>Featured:</b> {{ product.is_featured }}</p>
                        <p><b>Return Policy:</b> {{ product.return_policy }}</p>
                    </div>
                </div>

                <!-- الصورة تحت في النص -->
                <div class="text-center mt-4">
                    <img :src="getImageUrl(product.image)" @error="handleImageError" class="img-fluid rounded"
                        style="max-height:200px; object-fit:cover" />

                </div>

            </div>

        </div>

        <!-- ================= MODAL ================= -->
        <div class="modal fade" tabindex="-1" ref="editModalRef">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5>Edit Product</h5>
                        <button class="btn-close" @click="closeModal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-3">

                            <div class="col-md-6">
                                <label>Category</label>
                                <select v-model="form.category_id" class="form-select">
                                    <option value="">Select Category</option>
                                    <option v-for="c in categories" :key="c.id" :value="c.id">
                                        {{ c.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label>Brand</label>
                                <select v-model="form.brand_id" class="form-select">
                                    <option value="">Select Brand</option>
                                    <option v-for="b in brands" :key="b.id" :value="b.id">
                                        {{ b.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label>Name</label>
                                <input v-model="form.name" class="form-control">
                            </div>

                            <div class="col-md-6">
                                <label>SKU</label>
                                <input v-model="form.sku" class="form-control">
                            </div>

                            <div class="col-md-6">
                                <label>Price</label>
                                <input type="number" v-model="form.price" class="form-control">
                            </div>

                            <div class="col-md-6">
                                <label>Tax</label>
                                <input type="number" v-model="form.tax" class="form-control">
                            </div>

                            <div class="col-12">
                                <label>Image</label>
                                <input type="file" class="form-control" @change="e => form.image = e.target.files[0]">
                            </div>

                            <div class="col-12">
                                <label>Description</label>
                                <textarea v-model="form.description" class="form-control"></textarea>
                            </div>

                            <div class="col-12">
                                <label>Meta Title</label>
                                <input v-model="form.meta_title" class="form-control">
                            </div>

                            <div class="col-12">
                                <label>Meta Description</label>
                                <textarea v-model="form.meta_description" class="form-control"></textarea>
                            </div>

                            <div class="col-12">
                                <label>Return Policy</label>
                                <textarea v-model="form.return_policy" class="form-control"></textarea>
                            </div>

                            <div class="col-md-6 form-check mt-3">
                                <input type="checkbox" v-model="form.is_active" class="form-check-input">
                                <label>Active</label>
                            </div>

                            <div class="col-md-6 form-check mt-3">
                                <input type="checkbox" v-model="form.is_featured" class="form-check-input">
                                <label>Featured</label>
                            </div>

                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-secondary" @click="closeModal">
                            Cancel
                        </button>

                        <button class="btn btn-success" :disabled="updating" @click="updateProduct">

                            <span v-if="updating" class="spinner-border spinner-border-sm me-2"></span>
                            Save
                        </button>
                    </div>

                </div>
            </div>
        </div>

    </AdminLayout>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import axios from 'axios'
import { useRoute } from 'vue-router'
import AdminLayout from '@/layouts/AdminLayout.vue'
import * as bootstrap from 'bootstrap'

const route = useRoute()
const productId = route.params.id

const product = ref(null)
const loading = ref(true)
const updating = ref(false)

const categories = ref([])
const brands = ref([])

const editModalRef = ref(null)
let modalInstance = null

const form = ref({
    category_id: null,
    brand_id: null,
    name: '',
    image: null,
    description: '',
    sku: '',
    tax: 0,
    is_active: true,
    price: '',
    is_featured: false,
    meta_title: '',
    meta_description: '',
    return_policy: ''
})

const fetchProduct = async () => {
    const token = localStorage.getItem('auth_token')

    const res = await axios.get(
        `http://localhost:8000/api/v1/products/${productId}`,
        { headers: { Authorization: `Bearer ${token}` } }
    )

    product.value = res.data.data
    Object.assign(form.value, product.value)
    loading.value = false
}
const placeholder =
    "https://developers.elementor.com/docs/assets/img/elementor-placeholder-image.png"

const getImageUrl = (img) => {
    if (!img) return placeholder

    // لو Laravel storage path
    if (!img.startsWith('http')) {
        return `http://localhost:8000/storage/${img}`
    }

    return img
}

const handleImageError = (e) => {
    e.target.src = placeholder
}

const fetchDropdowns = async () => {
    try {
        const token = localStorage.getItem('auth_token')

        const headers = {
            Authorization: `Bearer ${token}`
        }

        const [catRes, brandRes] = await Promise.all([
            axios.get('/v1/categories/all/categories', { headers }),
            axios.get('/v1/brands/all/brands', { headers })
        ])

        if (catRes.data?.success) {
            categories.value = catRes.data.data || []
        } else {
            categories.value = []
        }

        if (brandRes.data?.success) {
            brands.value = brandRes.data.data || []
        } else {
            brands.value = []
        }

    } catch (err) {
        console.error('Dropdown Fetch Error:', err)
        categories.value = []
        brands.value = []
    }
}


const openEditModal = async () => {
    Object.assign(form.value, product.value)

    await nextTick()
    modalInstance = new bootstrap.Modal(editModalRef.value)
    modalInstance.show()
}

const closeModal = () => modalInstance.hide()

const updateProduct = async () => {
    updating.value = true

    try {
        const token = localStorage.getItem('auth_token')
        const data = new FormData()

        Object.keys(form.value).forEach(key => {
            let value = form.value[key]

            // معالجة checkboxes boolean
            if (key === 'is_active' || key === 'is_featured') {
                value = value ? 1 : 0
            }

            data.append(key, value ?? '')
        })

        await axios.post(
            `/v1/products/${productId}`,
            data,
            {
                headers: {
                    Authorization: `Bearer ${token}`,
                    'Content-Type': 'multipart/form-data'
                }
            }
        )

        await fetchProduct()
        closeModal()
        alert('تم التعديل ')

    } catch (err) {
        console.error('Full Axios Error:', err)
        console.error('Response data:', err.response?.data)
        console.error('Response status:', err.response?.status)
        console.error('Response headers:', err.response?.headers)

        if (err.response?.data?.errors) {
            console.error('Validation Errors:', err.response.data.errors)
        }

        alert(err.response?.data?.message || 'Update failed')
    }

    updating.value = false
}


onMounted(async () => {
    await fetchProduct()
    await fetchDropdowns()
})
</script>
