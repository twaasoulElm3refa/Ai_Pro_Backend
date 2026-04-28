<template>
    <AdminLayout>
        <div class="users-page">

            <!-- Header -->
            <div class="page-header">
                <div class="header-left">
                    <span class="header-label">System Management</span>
                    <h1 class="page-title">Users</h1>
                    <p class="page-subtitle">View and manage all platform users</p>
                </div>

                <div class="header-right">
                    <div class="total-badge">
                        <span class="badge-count">{{ pagination.total }}</span>
                        <span class="badge-label">Total Users</span>
                    </div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="table-card">

                <!-- Loading State -->
                <div v-if="loading" class="loading-state">
                    <div class="skeleton-row" v-for="i in 5" :key="i">
                        <div class="skeleton avatar-sk"></div>
                        <div class="skeleton-lines">
                            <div class="skeleton line-lg"></div>
                            <div class="skeleton line-sm"></div>
                        </div>
                        <div class="skeleton line-md"></div>
                        <div class="skeleton line-md"></div>
                        <div class="skeleton line-sm"></div>
                    </div>
                </div>

                <!-- Table -->
                <div v-else-if="users.length > 0" class="table-wrapper">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Balance</th>
                                <th>Last Seen</th>
                                <th>is active</th>
                                <th>Deleted_At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="(user, index) in users" :key="user.id" class="table-row"
                                :style="{ animationDelay: `${index * 60}ms` }">
                                <!-- ID -->
                                <td class="td-id">
                                    <span class="row-num">{{ (pagination.from + index) }}</span>
                                </td>

                                <!-- User Info -->
                                <td class="td-user">
                                    <div class="user-info">
                                        <div class="avatar" :style="{ background: avatarColor(user.name) }">
                                            {{ initials(user.name) }}
                                        </div>

                                        <div class="user-details">
                                            <span class="user-name">{{ user.name }}</span>
                                            <span class="user-email">{{ user.email }}</span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Role -->
                                <td class="td-role">
                                    <span class="role-pill" :class="roleClass(user.role)">
                                        <span class="role-dot"></span>
                                        {{ roleLabel(user.role) }}
                                    </span>
                                </td>

                                <!-- Wallet -->
                                <td class="td-wallet">
                                    <div class="wallet-info">
                                        <span class="wallet-icon">﷼</span>
                                        <span class="wallet-balance">
                                            {{ formatBalance(user.wallet?.balance) }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Last Seen -->
                                <td class="td-last-seen">
                                    <span class="last-seen-text">
                                        {{ formatLastSeen(user.last_seen) }}
                                    </span>
                                </td>
                                <!-- Last Seen -->
                                <td class="td-last-seen">
                                    <span class="status-badge" :class="user.is_active ? 'active' : 'inactive'">
                                        {{ user.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <!-- Last Seen -->
                                <td class="td-last-seen">
                                    <span class="last-seen-text">
                                        {{ formatLastSeen(user.deleted_at) }}
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="td-actions">

                                    <!-- View Button -->
                                    <router-link :to="`/admin/users/show/${user.id}`" class="btn-view"
                                        title="View Details">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                        View
                                    </router-link>

                                    <!-- Edit Button -->
                                    <router-link :to="`/admin/users/edit/${user.id}`" class="btn-edit"
                                        title="Edit User">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2">
                                            <path d="M12 20h9" />
                                            <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z" />
                                        </svg>
                                        Edit
                                    </router-link>

                                    <button @click="deleteUser(user.id)" class="btn-delete" title="Delete User">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6" />
                                            <path d="M19 6l-1 14H6L5 6" />
                                            <path d="M10 11v6" />
                                            <path d="M14 11v6" />
                                            <path d="M9 6V4h6v2" />
                                        </svg>
                                        Delete
                                    </button>

                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div v-else class="empty-state">
                    <div class="empty-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                    </div>
                    <p class="empty-text">No users found.</p>
                </div>

                <!-- Pagination -->
                <div v-if="!loading && pagination.last_page > 1" class="pagination">
                    <button class="page-btn" :disabled="pagination.current_page === 1"
                        @click="changePage(pagination.current_page - 1)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <polyline points="15 18 9 12 15 6" />
                        </svg>
                    </button>

                    <template v-for="link in visibleLinks" :key="`${link.label}-${link.page}`">
                        <button v-if="link.page" class="page-btn" :class="{ active: link.active }"
                            @click="changePage(link.page)">
                            {{ link.page }}
                        </button>
                    </template>

                    <button class="page-btn" :disabled="pagination.current_page === pagination.last_page"
                        @click="changePage(pagination.current_page + 1)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </button>

                    <span class="page-info">
                        Page {{ pagination.current_page }} of {{ pagination.last_page }}
                    </span>
                </div>

            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import AdminLayout from "@/layouts/AdminLayout.vue";
import userService from "@/services/admin/users/userService";

// ───────────────────────── state ──────────────────────────
const users = ref([]);
const loading = ref(false);
const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
    from: 1,
    links: [],
});

// ───────────────────────── fetch ──────────────────────────
async function fetchUsers(page = 1) {
    loading.value = true;

    try {
        const res = await userService.getUsers(page);
        const d = res.data;
        users.value = d.data || [];
        pagination.value = {
            current_page: d.current_page ?? 1,
            last_page: d.last_page ?? 1,
            total: d.total ?? 0,
            from: d.from ?? 1,
            links: d.links ?? [],
        };
    } catch (error) {
        console.error("Failed to fetch users:", error);
        users.value = [];
    } finally {
        loading.value = false;
    }
}

function changePage(page) {
    if (page < 1 || page > pagination.value.last_page) return;
    fetchUsers(page);
}

onMounted(() => fetchUsers());

// ───────────────────────── helpers ────────────────────────
const visibleLinks = computed(() =>
    pagination.value.links.filter((l) => l.page !== null)
);

function initials(name = "") {
    return name
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0])
        .join("")
        .toUpperCase();
}

const palette = [
    "#2ba6de", "#2ba6de", "#2ba6de", "#2ba6de",
    "#2ba6de", "#2ba6de", "#154677", "#2ba6de",
];

function avatarColor(name = "") {
    let n = 0;
    for (const c of name) n += c.charCodeAt(0);
    return palette[n % palette.length];
}

function formatBalance(val) {
    if (val === null || val === undefined) return "—";
    return Number(val).toLocaleString("en-US");
}

function roleClass(role) {
    const map = {
        admin: "role-admin",
        user: "role-user",
        moderator: "role-moderator",
    };
    return map[role] ?? "role-default";
}

function roleLabel(role) {
    const map = {
        admin: "Admin",
        user: "User",
        moderator: "Moderator",
    };
    return map[role] ?? role ?? "—";
}

function formatLastSeen(value) {
    if (!value) return "—";

    const date = new Date(value);
    const now = new Date();

    const diffMs = now - date;
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHour = Math.floor(diffMin / 60);
    const diffDay = Math.floor(diffHour / 24);

    if (diffSec < 60) return "منذ لحظات";
    if (diffMin < 60) return `منذ ${diffMin} دقيقة`;
    if (diffHour < 24) return `منذ ${diffHour} ساعة`;
    if (diffDay === 1) return "أمس";
    if (diffDay < 7) return `منذ ${diffDay} أيام`;

    return date.toLocaleDateString("ar-EG", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
}

async function deleteUser(id) {
    const confirmDelete = confirm("Are you sure you want to delete this user?");
    if (!confirmDelete) return;

    try {
        await userService.deleteUser(id);

        // refresh نفس الصفحة
        fetchUsers(pagination.value.current_page);

    } catch (error) {
        console.error("Delete failed:", error);
        alert("Failed to delete user");
    }
}
</script>

<style scoped>
/* ── Reset / Base ── */
*,
*::before,
*::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

.users-page {
    padding: 2rem;
    min-height: 100vh;
    background: #f4f6fb;
    direction: ltr;
    font-family: 'Cairo', 'Segoe UI', sans-serif;
}

/* ── Header ── */
.page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 1.75rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.header-left {
    text-align: left;
}

.header-right {
    margin-left: auto;
}

.header-label {
    display: block;
    font-size: .75rem;
    font-weight: 600;
    letter-spacing: .08em;
    color: #2ba6de;
    text-transform: uppercase;
    margin-bottom: .3rem;
}

.page-title {
    font-size: 1.85rem;
    font-weight: 800;
    color: #1a1d2e;
    line-height: 1.1;
}

.page-subtitle {
    margin-top: .35rem;
    font-size: .875rem;
    color: #8a8fa8;
}

.total-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    background: #fff;
    border: 1px solid #e5e7ef;
    border-radius: 14px;
    padding: .85rem 1.4rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
}

.badge-count {
    font-size: 1.6rem;
    font-weight: 800;
    color: #2ba6de;
    line-height: 1;
}

.badge-label {
    font-size: .72rem;
    color: #9ca3af;
    margin-top: .2rem;
}

/* ── Table Card ── */
.table-card {
    background: #fff;
    border-radius: 18px;
    border: 1px solid #e8eaf2;
    box-shadow: 0 4px 24px rgba(0, 0, 0, .05);
    overflow: hidden;
}

/* ── Table ── */
.table-wrapper {
    overflow-x: auto;
}

.users-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 760px;
}

.users-table thead tr {
    background: #f8f9fd;
    border-bottom: 1px solid #eceef6;
}

.users-table th {
    padding: .95rem 1.25rem;
    font-size: .78rem;
    font-weight: 700;
    color: #6b7280;
    letter-spacing: .05em;
    text-align: left;
    white-space: nowrap;
}

.table-row {
    border-bottom: 1px solid #f1f3fa;
    transition: background .15s;
    animation: rowIn .35s ease both;
}

.table-row:last-child {
    border-bottom: none;
}

.table-row:hover {
    background: #f8f9fd;
}

@keyframes rowIn {
    from {
        opacity: 0;
        transform: translateY(8px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.users-table td {
    padding: 1rem 1.25rem;
    font-size: .88rem;
    color: #374151;
    vertical-align: middle;
    text-align: left;
}

/* ── Row Number ── */
.row-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    background: #f3f4f6;
    border-radius: 8px;
    font-size: .78rem;
    font-weight: 700;
    color: #9ca3af;
}

/* ── User Info ── */
.user-info {
    display: flex;
    align-items: center;
    gap: .85rem;
}

.avatar {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    color: #fff;
    font-size: .78rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    letter-spacing: .02em;
}

.user-name {
    display: block;
    font-weight: 700;
    color: #2ba6de;
    font-size: .9rem;
}

.user-email {
    display: block;
    font-size: .78rem;
    color: #9ca3af;
    margin-top: .15rem;
    direction: ltr;
    text-align: left;
    word-break: break-word;
}

/* ── Role ── */
.role-pill {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .3rem .75rem;
    border-radius: 999px;
    font-size: .78rem;
    font-weight: 700;
    white-space: nowrap;
}

.role-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
}

.role-admin {
    background: #fee2e2;
    color: #154677;
}

.role-user {
    background: #dbeafe;
    color: #2563eb;
}

.role-moderator {
    background: #fef3c7;
    color: #2ba6de;
}

.role-default {
    background: #ede9fe;
    color: #2ba6de;
}

/* ── Wallet ── */
.wallet-info {
    display: flex;
    align-items: center;
    gap: .4rem;
}

.wallet-icon {
    font-size: .9rem;
    color: #2ba6de;
}

.wallet-balance {
    font-weight: 700;
    color: #2ba6de;
}

/* ── Last Seen ── */
.last-seen-text {
    color: #6b7280;
    font-size: .82rem;
    white-space: nowrap;
}

/* ── Actions ── */
.btn-view {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .4rem .9rem;
    border-radius: 10px;
    background: #ede9fe;
    color: #2ba6de;
    border: none;
    font-size: .8rem;
    font-weight: 700;
    cursor: pointer;
    transition: background .15s, transform .1s;
    font-family: inherit;
}

.btn-view:hover {
    background: #ddd6fe;
    transform: scale(1.03);
}

/* ── Skeleton Loading ── */
.loading-state {
    padding: 1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: .75rem;
}

.skeleton-row {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.skeleton {
    background: #e8eaf2;
    border-radius: 8px;
    animation: shimmer 1.4s infinite;
}

@keyframes shimmer {

    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: .4;
    }
}

.avatar-sk {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    flex-shrink: 0;
}

.skeleton-lines {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: .4rem;
}

.line-lg {
    height: 13px;
    width: 55%;
}

.line-md {
    height: 13px;
    width: 30%;
}

.line-sm {
    height: 11px;
    width: 20%;
}

/* ── Empty ── */
.empty-state {
    padding: 4rem 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    color: #c4c9d9;
}

.empty-text {
    font-size: .95rem;
    font-weight: 600;
}

/* ── Pagination ── */
.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    padding: 1.25rem;
    border-top: 1px solid #f1f3fa;
    flex-wrap: wrap;
}

.page-btn {
    min-width: 38px;
    height: 38px;
    border-radius: 10px;
    border: 1px solid #e5e7ef;
    background: #fff;
    color: #374151;
    font-size: .85rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all .15s;
    font-family: inherit;
    padding: 0 .6rem;
}

.page-btn:hover:not(:disabled) {
    background: #ede9fe;
    border-color: #c4b5fd;
    color: #2ba6de;
}

.page-btn.active {
    background: #2ba6de;
    border-color: #2ba6de;
    color: #fff;
}

.page-btn:disabled {
    opacity: .35;
    cursor: not-allowed;
}

.page-info {
    font-size: .8rem;
    color: #9ca3af;
    margin-left: .5rem;
    font-weight: 600;
}

/* ── Responsive ── */
@media (max-width: 640px) {
    .users-page {
        padding: 1rem;
    }

    .page-title {
        font-size: 1.4rem;
    }

    .td-actions {
        display: none;
    }

    .users-table {
        min-width: 680px;
    }
}

.btn-view,
.btn-edit {
    display: inline-flex;
    align-items: center;

    gap: 5px;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.3s ease;
}

/* View */
.btn-view {
    color: #2ba6de;
}

.btn-view:hover {
    color: #154677;
    transform: scale(1.1);
}

/* Edit */
.btn-edit {
    color: #2ba6de;
    background-color: #1a1d2e;
    padding: 5px 10px;
    margin-left: 6px;
    border-radius: 5px;
}

.btn-edit:hover {
    color: #2ba6de;
    transform: scale(1.1);
}

.status-badge.active {
    background-color: rgba(34, 197, 94, 0.15);
    color: #2ba6de;
    padding: 6px 10px;
    border-radius: 20%;
}

.status-badge.inactive {
    background-color: rgba(239, 68, 68, 0.15);
    color: #154677;
    padding: 6px 10px;
    border-radius: 20%;
}

.btn-delete {
    padding: 8px 12px;
    display: inline-flex;
    align-items: center;
    border-radius: 20%;
    gap: 5px;
    font-size: 14px;
    color: #b91c1c;
    background-color: #fee2e2;
    border: none;
    cursor: pointer;
    margin-left: 6px;
    transition: all 0.3s ease;
}

.btn-delete:hover {
    color: #b91c1c;
    transform: scale(1.1);
}
</style>
