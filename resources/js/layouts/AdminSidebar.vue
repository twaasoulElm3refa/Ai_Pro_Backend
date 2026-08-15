<template>
    <div v-if="open" class="sidebar-backdrop" @click="$emit('close')"></div>

    <aside class="admin-sidebar overflow-auto" :class="{ 'is-open': open }">

        <!-- Top Content -->
        <div class="sidebar-top">

            <!-- Logo -->
            <div class="sidebar-logo">
                <img src="/images/ai_logo.png" class="w-50" alt="Site Logo" width="96" height="96" loading="eager"
                    decoding="async" />
                <span class="logo-text">AI PRO</span>
            </div>

            <div class="sidebar-spacer"></div>

            <!-- Dashboard -->
            <RouterLink v-if="sidebarItems.dashboard" to="/admin" class="sidebar-btn" @click="$emit('close')">
                <span class="sidebar-link-content">
                    <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none">
                        <path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z"
                            stroke="currentColor" stroke-width="1.8" />
                    </svg>
                    <span>Dashboard</span>
                </span>
            </RouterLink>

            <!-- USERS -->
            <div v-if="sidebarItems.users" class="sidebar-group">

                <button class="sidebar-btn dropdown-toggle" :class="{ activeParent: usersActive }"
                    @click="toggle('users')">

                    <span class="sidebar-link-content">
                        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none">
                            <path d="M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0Z" stroke="currentColor" stroke-width="1.8" />
                            <path d="M4 20a8 8 0 0 1 16 0" stroke="currentColor" stroke-width="1.8" />
                        </svg>
                        <span>Users</span>
                    </span>

                    <span class="arrow" :class="{ open: openMenus.users }"></span>
                </button>

                <div v-if="openMenus.users" class="dropdown">
                    <RouterLink to="/admin/users" class="sidebar-btn dropdown-item mt-2" @click="$emit('close')">
                        All Users
                    </RouterLink>
                    <RouterLink to="/admin/users/add" class="sidebar-btn dropdown-item" @click="$emit('close')">
                        Add User
                    </RouterLink>
                </div>
            </div>

            <!-- TOOLS -->
            <RouterLink v-if="sidebarItems.tools" to="/admin/tools" class="sidebar-btn" @click="$emit('close')">
                <span class="sidebar-link-content">
                    <!-- AI Icon --> <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"> <!-- Brain outline -->
                        <path
                            d="M9 3a4 4 0 0 0-4 4v1a3 3 0 0 0 0 6v1a4 4 0 0 0 4 4h1v-2H9a2 2 0 0 1-2-2v-1h1v-2H7a1 1 0 0 1 0-2h1V8H7V7a2 2 0 0 1 2-2h1V3H9Z"
                            stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                        <path
                            d="M15 3a4 4 0 0 1 4 4v1a3 3 0 0 1 0 6v1a4 4 0 0 1-4 4h-1v-2h1a2 2 0 0 0 2-2v-1h-1v-2h1a1 1 0 0 0 0-2h-1V8h1V7a2 2 0 0 0-2-2h-1V3h1Z"
                            stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                        <!-- Nodes -->
                        <circle cx="12" cy="8" r="1" fill="currentColor" />
                        <circle cx="10" cy="12" r="1" fill="currentColor" />
                        <circle cx="14" cy="12" r="1" fill="currentColor" />
                        <circle cx="12" cy="16" r="1" fill="currentColor" /> <!-- Connections -->
                        <path d="M12 9v2M10.5 12h3M12 13v2" stroke="currentColor" stroke-width="1.4"
                            stroke-linecap="round" />
                    </svg>
                    <span>Tools</span>
                </span>
            </RouterLink>

            <!-- FREE AI MODELS -->
            <RouterLink v-if="sidebarItems.freeAiModels" to="/admin/free-ai-models" class="sidebar-btn" @click="$emit('close')">
                <span class="sidebar-link-content">
                    <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none">
                        <rect x="4" y="6" width="16" height="12" rx="3" stroke="currentColor" stroke-width="1.7" />
                        <path d="M9 10h.01M15 10h.01M9 14h6M12 3v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                    </svg>
                    <span>Free AI Models</span>
                </span>
            </RouterLink>

            <!-- PAYMENTS -->
            <div v-if="sidebarItems.payments" class="sidebar-group">
                <button class="sidebar-btn dropdown-toggle" :class="{ activeParent: paymentsActive }"
                    @click="toggle('payments')">
                    <span class="sidebar-link-content">
                        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none">
                            <rect x="3" y="5" width="18" height="14" rx="3" stroke="currentColor" stroke-width="1.8" />
                            <path d="M3 10h18" stroke="currentColor" stroke-width="1.8" />
                            <path d="M7 15h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                        <span>Payments</span>
                    </span>
                    <span class="arrow" :class="{ open: openMenus.payments }"></span>
                </button>

                <div v-if="openMenus.payments" class="dropdown">
                    <RouterLink to="/admin/payments" class="sidebar-btn dropdown-item mt-2" @click="$emit('close')">
                        All Payments
                    </RouterLink>
                </div>
            </div>

            <!-- COST LOGS -->
            <div v-if="sidebarItems.costs" class="sidebar-group">
                <button class="sidebar-btn dropdown-toggle" :class="{ activeParent: costsActive }"
                    @click="toggle('costs')">
                    <span class="sidebar-link-content">
                        <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none">
                            <path d="M4 6h16M4 12h16M4 18h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <circle cx="18" cy="18" r="3" stroke="currentColor" stroke-width="1.8" />
                        </svg>
                        <span>Cost Logs</span>
                    </span>
                    <span class="arrow" :class="{ open: openMenus.costs }"></span>
                </button>

                <div v-if="openMenus.costs" class="dropdown">
                    <RouterLink to="/admin/cost" class="sidebar-btn dropdown-item mt-2" @click="$emit('close')">
                        All Cost Logs
                    </RouterLink>
                    <RouterLink to="/admin/cost/today" class="sidebar-btn dropdown-item" @click="$emit('close')">
                        Today Costs
                    </RouterLink>
                </div>
            </div>

            <!-- SETTINGS -->
            <div v-if="sidebarItems.settings" class="sidebar-group">

                <button class="sidebar-btn dropdown-toggle" :class="{ activeParent: settingsActive }"
                    @click="toggle('settings')">

                    <span class="sidebar-link-content">
                        <!-- Settings Icon --> <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none">
                            <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="1.8" />
                            <path
                                d="M19.4 15a1.7 1.7 0 0 0 .34 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.34 1.82V22a2 2 0 1 1-4 0v-.08a1.7 1.7 0 0 0-.34-1.82 1.7 1.7 0 0 0-1-.6 1.7 1.7 0 0 0-1.82.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.82-.34H2a2 2 0 1 1 0-4h.08a1.7 1.7 0 0 0 1.82-.34 1.7 1.7 0 0 0 .6-1 1.7 1.7 0 0 0-.34-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6c.38 0 .74-.14 1-.4.26-.26.4-.62.4-1V3a2 2 0 1 1 4 0v.08c0 .38.14.74.4 1 .26.26.62.4 1 .4.38 0 .74-.14 1-.4l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06c-.26.26-.4.62-.4 1 0 .38.14.74.4 1 .26.26.62.4 1 .4H22a2 2 0 1 1 0 4h-.08c-.38 0-.74.14-1 .4-.26.26-.4.62-.4 1Z"
                                stroke="currentColor" stroke-width="1.2" />
                        </svg>
                        <span>Settings</span>
                    </span>

                    <span class="arrow" :class="{ open: openMenus.settings }"></span>
                </button>

                <div v-if="openMenus.settings" class="dropdown">
                    <RouterLink to="/admin/contacts" class="sidebar-btn dropdown-item mt-2">
                        Contacts
                    </RouterLink>
                    <RouterLink to="/admin/footer" class="sidebar-btn dropdown-item">
                        Footer
                    </RouterLink>
                </div>
            </div>

            <!-- ADMIN SETTINGS -->
            <div v-if="sidebarItems.adminSettings" class="sidebar-group">

                <button class="sidebar-btn dropdown-toggle" :class="{ activeParent: adminSettingsActive }"
                    @click="toggle('adminSettings')">

                    <span class="sidebar-link-content">
                        <!-- Admin Icon --> <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none">
                            <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="1.8" />
                            <path d="M4 20a8 8 0 0 1 16 0" stroke="currentColor" stroke-width="1.8" />
                        </svg>
                        <span>Admin Settings</span>
                    </span>

                    <span class="arrow" :class="{ open: openMenus.adminSettings }"></span>
                </button>

                <div v-if="openMenus.adminSettings" class="dropdown">
                    <RouterLink to="/admin/profile" class="sidebar-btn dropdown-item mt-2">
                        Profile
                    </RouterLink>
                    <RouterLink to="/admin/profile/edit" class="sidebar-btn dropdown-item">
                        Update Profile
                    </RouterLink>
                    <RouterLink to="/admin/password" class="sidebar-btn dropdown-item">
                        Change Password
                    </RouterLink>
                </div>
            </div>

        </div>

        <!-- Bottom -->
        <div class="sidebar-bottom">

            <!-- Visit Site -->
            <a href="/ar" target="_blank" class="sidebar-btn">
                <span class="sidebar-link-content">
                    <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none">
                        <path d="M14 3h7v7M21 3l-9 9" stroke="currentColor" stroke-width="1.8" />
                        <path d="M5 5v14h14" stroke="currentColor" stroke-width="1.8" />
                    </svg>
                    <span>Visit Site</span>
                </span>
            </a>

            <!-- Logout -->
            <RouterLink v-if="sidebarItems.logout" to="/admin/logout" class="sidebar-btn danger-item logout-btn">
                <span class="sidebar-link-content">
                    <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" stroke="currentColor" stroke-width="1.8" />
                        <path d="M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="1.8" />
                    </svg>
                    <span>Logout</span>
                </span>
            </RouterLink>

        </div>

    </aside>
</template>

<script setup>
import { reactive, computed, watch } from "vue"
import { useRoute } from "vue-router"

defineProps({
    open: Boolean,
})

defineEmits(["close"])

const route = useRoute()

// 🔥 التحكم في الظهور
const sidebarItems = reactive({
    dashboard: true,
    users: true,
    tools: true,
    freeAiModels: true,
    payments: true,
    costs: true,
    settings: true,
    adminSettings: true,
    logout: true,
})

// dropdown states
const openMenus = reactive({
    users: route.path.startsWith('/admin/users'),
    payments: route.path.startsWith('/admin/payments'),
    costs: route.path.startsWith('/admin/cost'),
    settings: route.path.startsWith('/admin/contacts') || route.path.startsWith('/admin/footer'),
    adminSettings: route.path.startsWith('/admin/profile') || route.path.startsWith('/admin/password'),
})

const toggle = (key) => {
    openMenus[key] = !openMenus[key]
}

// active states
const usersActive = computed(() =>
    route.path.startsWith('/admin/users')
)

const settingsActive = computed(() =>
    route.path.startsWith('/admin/contacts') ||
    route.path.startsWith('/admin/footer')
)

const paymentsActive = computed(() =>
    route.path.startsWith('/admin/payments')
)

const costsActive = computed(() =>
    route.path.startsWith('/admin/cost')
)

const adminSettingsActive = computed(() =>
    route.path.startsWith('/admin/profile') ||
    route.path.startsWith('/admin/password')
)

// 🔥 function للتحكم من أي مكان
const toggleSidebarItem = (key) => {
    sidebarItems[key] = !sidebarItems[key]
}

watch(
    () => route.path,
    (path) => {
        if (path.startsWith('/admin/users')) openMenus.users = true
        if (path.startsWith('/admin/payments')) openMenus.payments = true
        if (path.startsWith('/admin/cost')) openMenus.costs = true
        if (path.startsWith('/admin/contacts') || path.startsWith('/admin/footer')) openMenus.settings = true
        if (path.startsWith('/admin/profile') || path.startsWith('/admin/password')) openMenus.adminSettings = true
    }
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
    background: rgba(7, 67, 119, 0.25);
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
    color: #074377;
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
    margin-top: 5px;
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

.sidebar-link-content {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.sidebar-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 12px;
    flex-shrink: 0;
}

/* Active link */

.router-link-active {
    background: rgba(7, 67, 119, 0.08) !important;
    color: #074377 !important;
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
    background: #ffffff;
    border-right: 1px solid #e5e7eb;
}

[data-theme="dark"] .sidebar-btn {
    background: #f8fafc;
    color: #1e293b;
}

[data-theme="dark"] .sidebar-btn:hover {
    background: rgba(7, 67, 119, 0.08);
    color: #074377;
    transform: translateX(5px);
}

[data-theme="dark"] .dropdown-item {
    background: #f1f5f9;
}

[data-theme="dark"] .sidebar-icon {
    background: rgba(7, 67, 119, 0.08);
    color: #074377;
}

[data-theme="dark"] .danger-item {
    background: rgba(127, 29, 29, 0.18) !important;
    color: #f87171 !important;
}

[data-theme='dark'] .activeParent {
    background: #074377 !important;
    color: #ffffff !important;
}

/* ================= LIGHT THEME ================= */

[data-theme="light"] .admin-sidebar {
    background: #ffffff;
    border-right: 1px solid #e5e7eb;
}

[data-theme="light"] .sidebar-btn {
    background: #f8fafc;
    color: #1e293b;
}

[data-theme="light"] .sidebar-btn:hover {
    background: rgba(7, 67, 119, 0.08);
    color: #074377;
    transform: translateX(5px);
}

[data-theme="light"] .dropdown-item {
    background: #f1f5f9;
}

[data-theme="light"] .sidebar-icon {
    background: rgba(7, 67, 119, 0.08);
    color: #074377;
}

[data-theme="light"] .danger-item {
    background: #fef2f2 !important;
    color: #dc2626 !important;
}

[data-theme='light'] .activeParent {
    background: #074377 !important;
    color: #ffffff !important;
}

.dropdown-toggle::after {
    display: none !important;
    content: none !important;
}
</style>

