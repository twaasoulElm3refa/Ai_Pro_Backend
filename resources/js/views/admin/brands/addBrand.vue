<template>
    <AdminLayout>
        <div class="wrapper">
            <h1 class="title">Create New Brand</h1>

            <!-- Error -->
            <div v-if="error" class="alert error-bg">{{ error }}</div>

            <!-- Success -->
            <div v-if="success" class="alert success-bg">{{ success }}</div>

            <form @submit.prevent="submitForm" class="brand-form" enctype="multipart/form-data">
                <!-- Name -->
                <div class="form-group">
                    <label for="name">Brand Name</label>
                    <input type="text" id="name" v-model="form.name" placeholder="Enter brand name" required />
                </div>

                <!-- Image -->
                <div class="form-group">
                    <label for="image">Brand Image</label>
                    <input type="file" id="image" @change="handleFileChange" accept="image/*" />
                </div>

                <button type="submit" class="btn" :disabled="loading">
                    {{ loading ? 'Creating...' : 'Create Brand' }}
                </button>
            </form>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import AdminLayout from '@/layouts/AdminLayout.vue'

const form = ref({
    name: '',
    image: null,
})

const loading = ref(false)
const error = ref(null)
const success = ref(null)

// Handle file selection
const handleFileChange = (e) => {
    form.value.image = e.target.files[0]
}

// Submit form
const submitForm = async () => {
    error.value = null
    success.value = null

    if (!form.value.name) {
        error.value = 'Brand name is required.'
        return
    }

    loading.value = true

    try {
        const formData = new FormData()
        formData.append('name', form.value.name)
        if (form.value.image) {
            formData.append('image', form.value.image)
        }

        const response = await axios.post('/v1/brands/create', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        })

        success.value = 'Brand created successfully!'
        // Reset form
        form.value.name = ''
        form.value.image = null
        document.getElementById('image').value = null

    } catch (err) {
        console.error(err)
        error.value = err.response?.data?.message || 'Failed to create brand.'
    } finally {
        loading.value = false
    }
}
</script>

<style scoped>
.wrapper {
    max-width: 600px;
    margin: 0 auto;
    padding: 30px;
}

.title {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 20px;
}

.brand-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

label {
    margin-bottom: 6px;
    font-weight: 500;
}

input[type='text'],
input[type='file'] {
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 1rem;
}

.btn {
    padding: 10px 16px;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.2s;
}

.btn:hover:not(:disabled) {
    background: #2563eb;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.alert {
    padding: 12px 16px;
    border-radius: 6px;
    font-weight: 500;
}

.error-bg {
    background: #fee2e2;
    color: #b91c1c;
}

.success-bg {
    background: #dcfce7;
    color: #166534;
}
</style>
