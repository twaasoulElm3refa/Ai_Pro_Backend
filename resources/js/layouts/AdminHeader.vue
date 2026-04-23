<template>
    <header class="admin-header z-3">

        <!-- LEFT -->
        <div class="left">
            <button class="menu-btn" @click="$emit('toggle-sidebar')" title="Open menu">
                <i class="bi bi-list"></i>
            </button>
            <span class="user-name">{{ userName }}</span>
        </div>

        <!-- RIGHT -->
        <div class="actions">
            <span class="role">{{ role }}</span>

            <!-- <button class="btn-icon" @click="toggleTheme" title="Change Theme">
                <i class="bi" :class="theme === 'dark' ? 'bi-sun-fill' : 'bi-moon-fill'"></i>
            </button> -->

            <button class="logout-btn" :disabled="loggingOut" @click="logout">
                <span v-if="loggingOut" class="spinner"></span>
                Logout
            </button>
        </div>

    </header>
</template>


<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import toastr from 'toastr'
import profileService from '@/services/admin/profile/profileService'

const router = useRouter()
defineEmits(['toggle-sidebar'])
const theme = ref('dark')
const userName = ref('')
const role = ref('')
const loggingOut = ref(false)

/* ===== Theme ===== */
const applyTheme = () => {
    document.documentElement.setAttribute('data-theme', theme.value)
}

const toggleTheme = () => {
    theme.value = theme.value === 'dark' ? 'light' : 'dark'
    localStorage.setItem('theme', theme.value)
    applyTheme()
}

/* ===== Fetch Profile ===== */

const fetchProfile = async () => {
    try {
        const cachedUser = JSON.parse(localStorage.getItem('user_data') || '{}')
        if (cachedUser?.name) {
            userName.value = cachedUser.name
            role.value = cachedUser.role || localStorage.getItem('user_role') || ''
        }

        const data = await profileService.getProfile()
        const user = data?.data?.user

        if (user?.name) {
            userName.value = user.name
            role.value = user.role
            localStorage.setItem('user_data', JSON.stringify(user))
            if (user.role) localStorage.setItem('user_role', user.role)
        }

    } catch (err) {
        console.error('Profile error:', err)
    }
}

onMounted(() => {
    const savedTheme = localStorage.getItem('theme')
    if (savedTheme) theme.value = savedTheme

    applyTheme()
    fetchProfile()
})

/* ===== Logout ===== */

const clearSession = () => {
    localStorage.removeItem('auth_token')
    localStorage.removeItem('user_role')
    localStorage.removeItem('user_data')
}

const logout = async () => {
    loggingOut.value = true
    try {
        const response = await profileService.logout()
        toastr.success(response?.message || 'Logged out successfully.')
    } catch (error) {
        if (error.response?.status !== 401) {
            toastr.info('Your local session has been cleared.')
        }
    } finally {
        clearSession()
        loggingOut.value = false
        router.push('/login')
    }
}

</script>


<style scoped>
.user-name {
    font-weight: bold;
    margin-right: 10px;
    font-size: 0.95rem;
}

[data-theme='dark'] .user-name {
    color: #FBBF24;
}

[data-theme='light'] .user-name {
    color: #111827;
}

.admin-header {
    height: 60px;
    padding: 0 20px;
    display: flex;
    justify-content: space-between;
    /* المهم */
    align-items: center;
}

/* left user name */
.left {
    display: flex;
    align-items: center;
    gap: 12px;
}

/* right actions */
.actions {
    display: flex;
    align-items: center;
    gap: 14px;
}

/* role */
.role {
    font-weight: bold;
    opacity: 0.85;
}

/* dark */
[data-theme='dark'] .role {
    color: #FBBF24;
}

/* light */
[data-theme='light'] .role {
    color: #111827;
}


/* Title */
.title {
    font-size: 1.2rem;
    font-weight: bold;
}

/* Theme toggle button */
.btn-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.35s ease;
}

.menu-btn {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    transition: all 0.3s ease;
}

/* Logout */
.logout-btn {
    padding: 8px 14px;
    border-radius: 8px;
    border: none;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.35s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.spinner {
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255, 255, 255, 0.45);
    border-top-color: #fff;
    border-radius: 999px;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* ================= DARK MODE ================= */
[data-theme='dark'] .admin-header {
    background: rgba(15, 23, 42, 0.95);
    color: #FBBF24;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.45);
}

[data-theme='dark'] .btn-icon {
    background: rgba(212, 175, 55, 0.25);
    color: #FBBF24;
}

[data-theme='dark'] .menu-btn {
    background: rgba(212, 175, 55, 0.2);
    color: #FBBF24;
}

[data-theme='dark'] .btn-icon:hover {
    background: rgba(212, 175, 55, 0.45);
    transform: rotate(15deg) scale(1.1);
    box-shadow: 0 0 18px rgba(251, 191, 36, 0.55);
}

[data-theme='dark'] .logout-btn {
    background: #ef4444;
    color: white;
}

[data-theme='dark'] .logout-btn:hover {
    background: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.45);
}

/* ================= LIGHT MODE ================= */
[data-theme='light'] .admin-header {
    background: #ffffff;
    color: #111827;
    border-bottom: 1px solid #e5e7eb;
}

[data-theme='light'] .btn-icon {
    background: #111827;
    color: white;
}

[data-theme='light'] .menu-btn {
    background: #f1f5f9;
    color: #111827;
}

[data-theme='light'] .btn-icon:hover {
    background: #1f2937;
    transform: rotate(-15deg) scale(1.1);
}

[data-theme='light'] .logout-btn {
    background: #111827;
    color: white;
}

[data-theme='light'] .logout-btn:hover {
    background: #000000;
    transform: translateY(-2px);
}

@media (max-width: 1023px) {
    .menu-btn {
        display: flex;
    }

    .admin-header {
        padding: 0 14px;
    }

    .role {
        display: none;
    }
}

@media (max-width: 520px) {
    .user-name {
        max-width: 120px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .actions {
        gap: 8px;
    }

    .logout-btn {
        padding: 8px 10px;
    }
}
</style>
