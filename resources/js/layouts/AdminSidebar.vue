<template>
    <aside class="admin-sidebar overflow-auto">

        <!-- Logo -->
        <div class="sidebar-logo">
            <img src="/images/logo.png" alt="Site Logo" />
            <span class="logo-text">Admin</span>
        </div>

        <div class="sidebar-spacer"></div>

        <!-- Dashboard -->
        <RouterLink to="/admin" class="sidebar-btn">
            Dashboard
        </RouterLink>

        <!-- USERS -->
        <div class="sidebar-group">
            <button class="sidebar-btn dropdown-toggle" :class="{ activeParent: usersActive }" @click="toggle('users')">
                Users
                <span :class="['arrow', { open: open.users }]"></span>
            </button>


            <div v-if="open.users" class="dropdown">
                <RouterLink to="/admin/users" class="sidebar-btn dropdown-item mt-2">All Users</RouterLink>
                <RouterLink to="/admin/users/add" class="sidebar-btn dropdown-item">Add User</RouterLink>
            </div>
        </div>

        <!-- CATEGORIES -->
        <div class="sidebar-group">
            <button class="sidebar-btn dropdown-toggle" :class="{ activeParent: categoriesActive }"
                @click="toggle('categories')">
                Categories
                <span :class="['arrow', { open: open.categories }]"></span>
            </button>


            <div v-if="open.categories" class="dropdown">
                <RouterLink to="/admin/categories" class="sidebar-btn dropdown-item mt-2">All Categories</RouterLink>
                <RouterLink to="/admin/categories/create" class="sidebar-btn dropdown-item">Add Category</RouterLink>
            </div>
        </div>

        <!-- BRANDS -->
        <div class="sidebar-group">
            <button class="sidebar-btn dropdown-toggle" :class="{ activeParent: brandsActive }"
                @click="toggle('brands')">
                Brands
                <span :class="['arrow', { open: open.brands }]"></span>
            </button>


            <div v-if="open.brands" class="dropdown">
                <RouterLink to="/admin/brands" class="sidebar-btn dropdown-item mt-2">All Brands</RouterLink>
                <RouterLink to="/admin/brands/create" class="sidebar-btn dropdown-item">Add Brand</RouterLink>
            </div>
        </div>

        <!-- PRODUCTS -->
        <div class="sidebar-group">
            <button class="sidebar-btn dropdown-toggle" :class="{ activeParent: productsActive }"
                @click="toggle('products')">
                Products
                <span :class="['arrow', { open: open.products }]"></span>
            </button>


            <div v-if="open.products" class="dropdown">
                <RouterLink to="/admin/products" class="sidebar-btn dropdown-item mt-2">All Products</RouterLink>
                <RouterLink to="/admin/products/create" class="sidebar-btn dropdown-item">Add Product</RouterLink>
            </div>
        </div>

        <!-- Settings -->
        <RouterLink to="/admin/settings" class="sidebar-btn " :class="{ activeParent: settingsActive }">
            Settings
        </RouterLink>


    </aside>
</template>

<script setup>
import { reactive, computed } from "vue"
import { useRoute } from "vue-router"

const route = useRoute()

const open = reactive({
    users: route.path.startsWith('/admin/users'),
    categories: route.path.startsWith('/admin/categories'),
    brands: route.path.startsWith('/admin/brands'),
    products: route.path.startsWith('/admin/products'),
})

const toggle = (key) => {
    open[key] = !open[key]
}

/* Parent Active States */
const usersActive = computed(() =>
    route.path.startsWith('/admin/users')
)

const categoriesActive = computed(() =>
    route.path.startsWith('/admin/categories')
)

const brandsActive = computed(() =>
    route.path.startsWith('/admin/brands')
)

const productsActive = computed(() =>
    route.path.startsWith('/admin/products')
)

const settingsActive = computed(() =>
    route.path.startsWith('/admin/settings')
)

</script>

<style scoped>
/* ================= SIDEBAR ================= */

.admin-sidebar {
    width: 250px;
    min-height: 100vh;
    padding: 20px 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    transition: 0.4s ease;
}

/* ===== Logo ===== */

.sidebar-logo {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 15px;
}

.sidebar-logo img {
    width: 48px;
}

.logo-text {
    color: #D4AF37;
    font-weight: bold;
    font-size: 1.3rem;
}

/* ===== Buttons ===== */

.sidebar-btn {
    width: 100%;
    text-align: left;
    padding: 12px 16px;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
}

/* Active link */

.router-link-active {
    background: rgba(212, 175, 55, 0.25) !important;
    color: #D4AF37 !important;
}

/* Dropdown */

.dropdown {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding-left: 10px;
}

.dropdown-item {
    padding-left: 26px;
    font-weight: 500;
}

/* Arrow */

.arrow {
    transition: 0.4s;
}

.arrow.open {
    transform: rotate(180deg);
}

/* ================= DARK THEME ================= */

[data-theme="dark"] .admin-sidebar {
    background: linear-gradient(180deg,
            rgba(2, 6, 23, 0.98),
            rgba(15, 23, 42, 0.98));
}

[data-theme="dark"] .sidebar-btn {
    background: rgba(30, 41, 59, 0.5);
    color: #e5e7eb;
}

[data-theme="dark"] .sidebar-btn:hover {
    background: rgba(212, 175, 55, 0.18);
    color: #D4AF37;
    transform: translateX(5px);
}

[data-theme="dark"] .dropdown-item {
    background: rgba(30, 41, 59, 0.35);
}

/* ================= LIGHT THEME ================= */

[data-theme="light"] .admin-sidebar {
    background: #ffffff;
    border-right: 1px solid #eee;
}

[data-theme="light"] .sidebar-btn {
    background: #f8fafc;
    color: #111;
}

[data-theme="light"] .sidebar-btn:hover {
    background: #111;
    color: #fff;
    transform: translateX(5px);
}

[data-theme="light"] .dropdown-item {
    background: #f1f5f9;
}

[data-theme='dark'] .activeParent {
    background: rgba(212, 175, 55, 0.35) !important;
    color: #D4AF37 !important;
}

[data-theme='light'] .activeParent {
    background: #111 !important;
    color: #fff !important;
}
</style>
