<template>
    <div v-if="open" class="sidebar-backdrop" @click="$emit('close')"></div>

    <aside class="admin-sidebar overflow-auto" :class="{ 'is-open': open }">

        <!-- Top Content -->
        <div class="sidebar-top">
            <!-- Logo -->
            <div class="sidebar-logo">
                <img src="/images/ai_logo.png" class="w-50" alt="Site Logo" />
                <span class="logo-text">AI PRO</span>
            </div>

            <div class="sidebar-spacer"></div>

            <!-- Dashboard -->
            <RouterLink to="/admin" class="sidebar-btn" @click="$emit('close')">
                <span>Dashboard</span>
            </RouterLink>

            <!-- USERS -->
            <div class="sidebar-group">
                <button class="sidebar-btn dropdown-toggle"
                    :class="{ activeParent: usersActive }"
                    @click="toggle('users')">
                    <span>Users</span>
                    <span class="arrow" :class="{ open: openMenus.users }"></span>
                </button>

                <div v-if="openMenus.users" class="dropdown">
                    <RouterLink to="/admin/users" class="sidebar-btn dropdown-item mt-2" @click="$emit('close')">
                        <span>All Users</span>
                    </RouterLink>
                    <RouterLink to="/admin/users/add" class="sidebar-btn dropdown-item" @click="$emit('close')">
                        <span>Add User</span>
                    </RouterLink>
                </div>
            </div>

            <!-- SETTINGS -->
            <div class="sidebar-group">
                <button class="sidebar-btn dropdown-toggle"
                    :class="{ activeParent: settingsActive }"
                    @click="toggle('settings')">
                    <span>Settings</span>
                    <span class="arrow" :class="{ open: openMenus.settings }"></span>
                </button>

                <div v-if="openMenus.settings" class="dropdown">
                    <RouterLink to="/admin/contacts" class="sidebar-btn dropdown-item mt-2" @click="$emit('close')">
                        <span>Contacts</span>
                    </RouterLink>
                    <RouterLink to="/admin/footer" class="sidebar-btn dropdown-item" @click="$emit('close')">
                        <span>Footer</span>
                    </RouterLink>
                </div>
            </div>

            <!-- ADMIN SETTINGS -->
            <div class="sidebar-group">
                <button class="sidebar-btn dropdown-toggle"
                    :class="{ activeParent: adminSettingsActive }"
                    @click="toggle('adminSettings')">
                    <span>Admin Settings</span>
                    <span class="arrow" :class="{ open: openMenus.adminSettings }"></span>
                </button>

                <div v-if="openMenus.adminSettings" class="dropdown">
                    <RouterLink to="/admin/profile" class="sidebar-btn dropdown-item mt-2" @click="$emit('close')">
                        <span>Profile</span>
                    </RouterLink>
                    <RouterLink to="/admin/profile/edit" class="sidebar-btn dropdown-item" @click="$emit('close')">
                        <span>Update Profile</span>
                    </RouterLink>
                    <RouterLink to="/admin/password" class="sidebar-btn dropdown-item" @click="$emit('close')">
                        <span>Change Password</span>
                    </RouterLink>
                </div>
            </div>
        </div>

        <!-- Bottom Logout -->
        <div class="sidebar-bottom">
            <RouterLink to="/admin/logout" class="sidebar-btn danger-item logout-btn" @click="$emit('close')">
                <span>Logout</span>
            </RouterLink>
        </div>

    </aside>
</template>

<script setup>
import { reactive, computed } from "vue"
import { useRoute } from "vue-router"

defineProps({
    open: {
        type: Boolean,
        default: false,
    },
})

defineEmits(["close"])

const route = useRoute()

const openMenus = reactive({
    users: route.path.startsWith('/admin/users'),
    settings: route.path.startsWith('/admin/contacts') || route.path.startsWith('/admin/footer'),
    adminSettings: route.path.startsWith('/admin/profile') || route.path.startsWith('/admin/password'),
})

const toggle = (key) => {
    openMenus[key] = !openMenus[key]
}

const usersActive = computed(() =>
    route.path.startsWith('/admin/users')
)

const settingsActive = computed(() =>
    route.path.startsWith('/admin/contacts') ||
    route.path.startsWith('/admin/footer')
)

const adminSettingsActive = computed(() =>
    route.path.startsWith('/admin/profile') ||
    route.path.startsWith('/admin/password') ||
    route.path.startsWith('/admin/logout')
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
    justify-content: space-between;
    gap: 12px;
    transition: 0.4s ease;
    z-index: 40;
}

.sidebar-top {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.sidebar-bottom {
    margin-top: auto;
    padding-top: 12px;
}

.sidebar-backdrop {
    position: fixed;
    inset: 0;
    z-index: 35;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(3px);
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
    color: #000000;
    font-weight: bold;
    font-size: 1.6rem;
}

.sidebar-spacer {
    height: 4px;
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
    text-decoration: none;
    gap: 10px;
}

.dropdown-toggle {
    width: 100%;
}

.dropdown-toggle span:first-child {
    flex: 1;
    text-align: left;
}

/* Active link */

.router-link-active {
    background: rgba(212, 175, 55, 0.25) !important;
    color: #000000 !important;
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
    justify-content: flex-start;
}

/* Arrow */

.arrow {
    width: 8px;
    height: 8px;
    flex-shrink: 0;
    margin-left: auto;
    border-right: 2px solid currentColor;
    border-bottom: 2px solid currentColor;
    transform: rotate(45deg);
    transition: transform 0.3s ease;
}

.arrow.open {
    transform: rotate(225deg);
}

.danger-item {
    color: #dc2626 !important;
}

.logout-btn {
    font-weight: 700;
}

@media (max-width: 1023px) {
    .admin-sidebar {
        position: fixed;
        left: 0;
        top: 0;
        transform: translateX(-100%);
        box-shadow: 16px 0 40px rgba(15, 23, 42, 0.28);
    }

    .admin-sidebar.is-open {
        transform: translateX(0);
    }
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

[data-theme="dark"] .danger-item {
    background: rgba(127, 29, 29, 0.18) !important;
    color: #f87171 !important;
}

[data-theme='dark'] .activeParent {
    background: rgba(212, 175, 55, 0.35) !important;
    color: #D4AF37 !important;
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

[data-theme="light"] .danger-item {
    background: #fef2f2 !important;
    color: #dc2626 !important;
}

[data-theme='light'] .activeParent {
    background: #111 !important;
    color: #fff !important;
}
.dropdown-toggle::after {
    display: none !important;
    content: none !important;
}
</style>
