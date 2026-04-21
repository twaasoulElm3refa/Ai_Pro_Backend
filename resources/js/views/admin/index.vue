<template>
    <AdminLayout>
        <h1>Admin Panel</h1>

    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/layouts/AdminLayout.vue'
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { toast } from 'vue3-toastify';

// ===== Theme =====
const isDark = computed(
    () => document.documentElement.getAttribute('data-theme') === 'dark'
)
const productCount = ref(0)
const users = ref([])
const loadingUsers = ref(false)
const deletingUserId = ref(null)
const userCount = ref(0)
const brandCount = ref(0)
const categoryCount = ref(0)

onMounted(async () => {
    await fetchUserCount()
    await fetchUsers()
})


const fetchUserCount = async () => {
    const token = localStorage.getItem('auth_token')
    if (!token) return

    try {
        const res = await axios.get('http://localhost:8000/api/v1/users/User/count', {
            headers: { Authorization: `Bearer ${token}` }
        })
        if (res.data.success) {
            userCount.value = res.data.data
        }
    } catch (err) {
        console.error('Failed to fetch user count', err)
    }
}

const fetchUsers = async () => {
    loadingUsers.value = true
    const token = localStorage.getItem('auth_token')
    if (!token) {
        console.error('No token found, redirect to login')
        return
    }
    try {
        const res = await axios.get('http://localhost:8000/api/v1/users', {
            headers: { Authorization: `Bearer ${token}` }
        })
        users.value = res.data.data
    } catch (err) {
        console.error('Failed to fetch users', err)
    } finally {
        loadingUsers.value = false
    }
}

const editUser = async (user) => {
    const newName = prompt("Enter new name:", user.name)
    if (!newName || newName === user.name) return

    const token = localStorage.getItem('auth_token')
    if (!token) return

    try {
        const res = await axios.post(`http://localhost:8000/api/v1/users/${user.id}`, {
            name: newName
        }, {
            headers: { Authorization: `Bearer ${token}` }
        })

        if (res.data.success) {
            toast.success("User updated successfully")
            const index = users.value.findIndex(u => u.id === user.id)
            if (index !== -1) {
                users.value[index].name = newName
            }
        }
    } catch (err) {
        console.error('Update failed', err)
        toast.error("Failed to update user")
    }
}

const deleteUser = async (id) => {
    if (!confirm("Are you sure you want to delete this user?")) return

    deletingUserId.value = id
    const token = localStorage.getItem('auth_token')
    if (!token) return

    try {
        const res = await axios.delete(`http://localhost:8000/api/v1/users/${id}`, {
            headers: { Authorization: `Bearer ${token}` }
        })

        if (res.data.success) {
            toast.success("User deleted successfully")
            users.value = users.value.filter(u => u.id !== id)
            userCount.value -= 1
        }
    } catch (err) {
        console.error('Delete failed', err)
        toast.error("Failed to delete user")
    } finally {
        deletingUserId.value = null
    }
}

</script>

<style scoped>
/* ===== Cards ===== */
.stat-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

/* Light theme colors */
[data-theme='light'] .bg-users-light {
    background: #0d6efd !important;
}

[data-theme='light'] .bg-brands-light {
    background: #198754 !important;
}

[data-theme='light'] .bg-categories-light {
    background: #6f42c1 !important;
}

[data-theme='light'] .bg-products-light {
    background: #dc3545 !important;
}

[data-theme='light'] .bg-colors-light {
    background: #0dcaf0 !important;
}

[data-theme='light'] .bg-sizes-light {
    background: #ffc107 !important;
}

[data-theme='light'] .bg-orders-light {
    background: #6c757d !important;
}

/* Dark theme colors */
[data-theme='dark'] .bg-users-dark {
    background: #0a58ca !important;
}

[data-theme='dark'] .bg-brands-dark {
    background: #146c43 !important;
}

[data-theme='dark'] .bg-categories-dark {
    background: #5a32a3 !important;
}

[data-theme='dark'] .bg-products-dark {
    background: #b02a37 !important;
}

[data-theme='dark'] .bg-colors-dark {
    background: #0aa2c0 !important;
}

[data-theme='dark'] .bg-sizes-dark {
    background: #cc9a06 !important;
}

[data-theme='dark'] .bg-orders-dark {
    background: #495057 !important;
}

/* Table Cards */
.table-card {
    border-radius: 12px;
    overflow: hidden;
}

[data-theme='light'] .table-card {
    background: #fff;
    border: 1px solid #dee2e6;
}

[data-theme='dark'] .table-card {
    background: #1e293b;
    border: 1px solid #334155;
}

[data-theme='light'] .card-header {
    background: #f8f9fa;
    color: #212529;
    border-bottom: 1px solid #dee2e6;
}

[data-theme='dark'] .card-header {
    background: #0f172a;
    color: #e2e8f0;
    border-bottom: 1px solid #334155;
}

[data-theme='light'] .table thead th {
    background: #f1f3f5;
    color: #495057;
}

[data-theme='dark'] .table thead th {
    background: #0f172a;
    color: #94a3b8;
}

[data-theme='dark'] .table {
    --bs-table-color: #e2e8f0;
    --bs-table-bg: transparent;
    --bs-table-striped-bg: rgba(30, 41, 59, 0.4);
    --bs-table-hover-bg: rgba(45, 55, 72, 0.6);
}

/* Icons color in dark mode */
[data-theme='dark'] .bi {
    opacity: 0.9;
}
</style>
