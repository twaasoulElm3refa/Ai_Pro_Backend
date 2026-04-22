<template>
    <AdminLayout>
        <div class="contacts-page">
            <div class="page-header">
                <div class="header-left">
                    <span class="page-badge">Admin Panel</span>
                    <h1 class="page-title">Contact Messages</h1>
                    <p class="page-subtitle">
                        Review all messages submitted through the contact form.
                    </p>
                </div>

                <div class="header-right">
                    <div class="stat-card">
                        <span class="stat-label">Total Messages</span>
                        <span class="stat-value">{{ pagination.total }}</span>
                    </div>
                </div>
            </div>

            <div class="toolbar">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="7" />
                        <path d="m20 20-3.5-3.5" />
                    </svg>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search by name, email, subject, or message..."
                    />
                </div>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="loading-grid">
                <div class="loading-card" v-for="i in 6" :key="i">
                    <div class="loading-line lg"></div>
                    <div class="loading-line md"></div>
                    <div class="loading-line sm"></div>
                    <div class="loading-line full"></div>
                </div>
            </div>

            <!-- Content -->
            <template v-else>
                <!-- Empty -->
                <div v-if="filteredContacts.length === 0" class="empty-state">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 4h16v12H4z" />
                            <path d="m4 6 8 6 8-6" />
                            <path d="M8 20h8" />
                        </svg>
                    </div>
                    <h3>No contact messages found</h3>
                    <p>There are no messages available right now.</p>
                </div>

                <!-- Cards -->
                <div v-else class="contacts-grid">
                    <div
                        v-for="contact in filteredContacts"
                        :key="contact.id"
                        class="contact-card"
                    >
                        <div class="card-top">
                            <div class="contact-avatar">
                                {{ getInitials(contact.name || contact.user?.name || "Unknown") }}
                            </div>

                            <div class="card-meta">
                                <div class="meta-row">
                                    <span class="meta-label">Message ID</span>
                                    <span class="meta-id">#{{ contact.id }}</span>
                                </div>
                                <div class="meta-date">
                                    {{ formatDate(contact.created_at) }}
                                </div>
                            </div>
                        </div>

                        <div class="info-list">
                            <div class="info-item">
                                <span class="info-label">Name</span>
                                <span class="info-value">
                                    {{ contact.name || contact.user?.name || "—" }}
                                </span>
                            </div>

                            <div class="info-item">
                                <span class="info-label">Email</span>
                                <span class="info-value email-text">
                                    {{ contact.email || contact.user?.email || "—" }}
                                </span>
                            </div>

                            <div class="info-item">
                                <span class="info-label">Subject</span>
                                <span class="info-value">
                                    {{ contact.subject || "No subject" }}
                                </span>
                            </div>

                            <div class="info-item full">
                                <span class="info-label">Message</span>
                                <p class="message-preview">
                                    {{ truncateText(contact.message, 140) }}
                                </p>
                            </div>
                        </div>

                        <div class="card-actions">
                            <button class="btn btn-primary" @click="openViewModal(contact)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                View Details
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div
                    v-if="pagination.last_page > 1"
                    class="pagination-wrap"
                >
                    <button
                        class="page-btn"
                        :disabled="pagination.current_page === 1"
                        @click="changePage(pagination.current_page - 1)"
                    >
                        Prev
                    </button>

                    <button
                        v-for="link in visibleLinks"
                        :key="`${link.label}-${link.page}`"
                        class="page-btn"
                        :class="{ active: link.active }"
                        @click="changePage(link.page)"
                    >
                        {{ link.page }}
                    </button>

                    <button
                        class="page-btn"
                        :disabled="pagination.current_page === pagination.last_page"
                        @click="changePage(pagination.current_page + 1)"
                    >
                        Next
                    </button>

                    <span class="page-info">
                        Page {{ pagination.current_page }} of {{ pagination.last_page }}
                    </span>
                </div>
            </template>

            <!-- View Modal -->
            <transition name="fade">
                <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
                    <div class="modal-card">
                        <div class="modal-header">
                            <div>
                                <h2 class="modal-title">Contact Message Details</h2>
                                <p class="modal-subtitle">
                                    Full message information and submission details.
                                </p>
                            </div>

                            <button class="icon-btn" @click="closeModal">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M18 6 6 18" />
                                    <path d="m6 6 12 12" />
                                </svg>
                            </button>
                        </div>

                        <div v-if="selectedContact" class="modal-body">
                            <div class="modal-grid">
                                <div class="modal-info">
                                    <span class="modal-label">ID</span>
                                    <span class="modal-value">#{{ selectedContact.id }}</span>
                                </div>

                                <div class="modal-info">
                                    <span class="modal-label">Created At</span>
                                    <span class="modal-value">{{ formatDate(selectedContact.created_at) }}</span>
                                </div>

                                <div class="modal-info">
                                    <span class="modal-label">Name</span>
                                    <span class="modal-value">
                                        {{ selectedContact.name || selectedContact.user?.name || "—" }}
                                    </span>
                                </div>

                                <div class="modal-info">
                                    <span class="modal-label">Email</span>
                                    <span class="modal-value break-all">
                                        {{ selectedContact.email || selectedContact.user?.email || "—" }}
                                    </span>
                                </div>

                                <div class="modal-info full-width">
                                    <span class="modal-label">Subject</span>
                                    <span class="modal-value">
                                        {{ selectedContact.subject || "No subject" }}
                                    </span>
                                </div>

                                <div class="modal-info full-width">
                                    <span class="modal-label">Message</span>
                                    <div class="message-box">
                                        {{ selectedContact.message || "—" }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-actions">
                            <button class="btn btn-secondary" @click="closeModal">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import AdminLayout from "@/layouts/AdminLayout.vue";
import contactService from "@/services/admin/contact/contactService";

const loading = ref(true);
const search = ref("");
const showModal = ref(false);
const selectedContact = ref(null);

const contacts = ref([]);

const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
    from: 1,
    links: [],
});

async function fetchContacts(page = 1) {
    loading.value = true;

    try {
        const response = await contactService.getContacts(page);
        const d = response.data;

        contacts.value = d.data || [];
        pagination.value = {
            current_page: d.current_page ?? 1,
            last_page: d.last_page ?? 1,
            total: d.total ?? 0,
            from: d.from ?? 1,
            links: d.links ?? [],
        };
    } catch (error) {
        console.error("Failed to fetch contacts:", error);
        contacts.value = [];
    } finally {
        loading.value = false;
    }
}

function changePage(page) {
    if (!page || page < 1 || page > pagination.value.last_page) return;
    fetchContacts(page);
}

const visibleLinks = computed(() =>
    pagination.value.links.filter((l) => l.page !== null)
);

const filteredContacts = computed(() => {
    const keyword = search.value.trim().toLowerCase();
    if (!keyword) return contacts.value;

    return contacts.value.filter((contact) => {
        const haystack = [
            contact.name,
            contact.email,
            contact.subject,
            contact.message,
            contact.user?.name,
            contact.user?.email,
        ]
            .filter(Boolean)
            .join(" ")
            .toLowerCase();

        return haystack.includes(keyword);
    });
});

function openViewModal(contact) {
    selectedContact.value = contact;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    selectedContact.value = null;
}

function truncateText(text, max = 120) {
    if (!text) return "—";
    return text.length > max ? text.slice(0, max) + "..." : text;
}

function getInitials(name = "") {
    return name
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0])
        .join("")
        .toUpperCase();
}

function formatDate(value) {
    if (!value) return "—";

    try {
        return new Date(value).toLocaleString("en-US", {
            year: "numeric",
            month: "short",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
        });
    } catch {
        return value;
    }
}

onMounted(() => {
    fetchContacts();
});
</script>

<style scoped>
*,
*::before,
*::after {
    box-sizing: border-box;
}

.contacts-page {
    min-height: 100vh;
    padding: 28px;
    background:
        radial-gradient(circle at top left, rgba(99, 102, 241, 0.12), transparent 25%),
        radial-gradient(circle at bottom right, rgba(168, 85, 247, 0.12), transparent 25%),
        linear-gradient(180deg, #f8fbff 0%, #f3f6fb 100%);
}

.page-header {
    max-width: 1250px;
    margin: 0 auto 22px;
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 18px;
    flex-wrap: wrap;
}

.page-badge {
    display: inline-flex;
    align-items: center;
    padding: 7px 12px;
    border-radius: 999px;
    background: rgba(79, 70, 229, 0.1);
    color: #4f46e5;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 10px;
}

.page-title {
    margin: 0;
    font-size: clamp(30px, 4vw, 42px);
    line-height: 1.05;
    color: #101828;
    font-weight: 900;
}

.page-subtitle {
    margin-top: 10px;
    color: #667085;
    font-size: 15px;
    line-height: 1.8;
}

.stat-card {
    min-width: 180px;
    padding: 18px 20px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid rgba(255, 255, 255, 0.85);
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
    backdrop-filter: blur(12px);
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.stat-label {
    font-size: 12px;
    font-weight: 800;
    color: #667085;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.stat-value {
    font-size: 30px;
    font-weight: 900;
    color: #111827;
}

.toolbar {
    max-width: 1250px;
    margin: 0 auto 22px;
}

.search-box {
    height: 58px;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 18px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
}

.search-box svg {
    width: 20px;
    height: 20px;
    color: #98a2b3;
    flex-shrink: 0;
}

.search-box input {
    width: 100%;
    border: none;
    outline: none;
    background: transparent;
    color: #111827;
    font-size: 14px;
}

.loading-grid,
.contacts-grid {
    max-width: 1250px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
}

.loading-card,
.contact-card {
    border-radius: 24px;
    padding: 22px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(255, 255, 255, 0.85);
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
    backdrop-filter: blur(12px);
}

.loading-line {
    height: 14px;
    border-radius: 999px;
    margin-bottom: 12px;
    background: linear-gradient(90deg, #eef2f7 25%, #e2e8f0 50%, #eef2f7 75%);
    background-size: 200% 100%;
    animation: shimmer 1.3s infinite linear;
}

.loading-line.lg { width: 52%; height: 22px; }
.loading-line.md { width: 36%; }
.loading-line.sm { width: 25%; }
.loading-line.full { width: 100%; height: 70px; border-radius: 18px; margin-top: 18px; }

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.contact-card {
    transition: 0.22s ease;
}

.contact-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 24px 45px rgba(15, 23, 42, 0.1);
}

.card-top {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 18px;
}

.contact-avatar {
    width: 56px;
    height: 56px;
    border-radius: 18px;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 900;
    flex-shrink: 0;
    box-shadow: 0 14px 30px rgba(99, 102, 241, 0.24);
}

.card-meta {
    min-width: 0;
    flex: 1;
}

.meta-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.meta-label {
    font-size: 12px;
    font-weight: 700;
    color: #667085;
}

.meta-id {
    font-size: 14px;
    font-weight: 900;
    color: #111827;
}

.meta-date {
    margin-top: 8px;
    font-size: 13px;
    color: #98a2b3;
}

.info-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.info-item {
    min-width: 0;
    padding: 14px;
    border-radius: 18px;
    background: linear-gradient(180deg, #ffffff, #f8fafc);
    border: 1px solid #edf2f7;
}

.info-item.full {
    grid-column: 1 / -1;
}

.info-label {
    display: block;
    margin-bottom: 8px;
    font-size: 11px;
    font-weight: 800;
    color: #667085;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.info-value {
    color: #111827;
    font-size: 14px;
    font-weight: 800;
    word-break: break-word;
}

.email-text {
    font-size: 13px;
}

.message-preview {
    margin: 0;
    color: #344054;
    font-size: 14px;
    line-height: 1.8;
    word-break: break-word;
}

.card-actions {
    margin-top: 18px;
    display: flex;
    justify-content: flex-end;
}

.btn {
    min-width: 140px;
    height: 48px;
    border: none;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    transition: 0.2s ease;
}

.btn svg {
    width: 17px;
    height: 17px;
}

.btn-primary {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: #fff;
    box-shadow: 0 16px 30px rgba(99, 102, 241, 0.22);
}

.btn-primary:hover {
    transform: translateY(-1px);
}

.btn-secondary {
    background: #f3f4f6;
    color: #111827;
}

.btn-secondary:hover {
    background: #e5e7eb;
}

/* Pagination */
.pagination-wrap {
    max-width: 1250px;
    margin: 28px auto 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
}

.page-btn {
    min-width: 42px;
    height: 42px;
    padding: 0 14px;
    border-radius: 14px;
    border: 1px solid #e5e7eb;
    background: #fff;
    color: #111827;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    transition: 0.2s ease;
}

.page-btn:hover:not(:disabled) {
    border-color: #c7d2fe;
    color: #4f46e5;
    background: #eef2ff;
}

.page-btn.active {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: #fff;
    border-color: transparent;
}

.page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.page-info {
    font-size: 13px;
    font-weight: 700;
    color: #667085;
    margin-left: 8px;
}

/* Empty */
.empty-state {
    max-width: 720px;
    margin: 0 auto;
    text-align: center;
    padding: 70px 24px;
    border-radius: 28px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(255, 255, 255, 0.85);
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
}

.empty-icon {
    width: 82px;
    height: 82px;
    margin: 0 auto 18px;
    border-radius: 24px;
    background: linear-gradient(135deg, #eef2ff, #f5f3ff);
    color: #6366f1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-icon svg {
    width: 34px;
    height: 34px;
}

.empty-state h3 {
    margin: 0 0 8px;
    color: #111827;
    font-size: 24px;
    font-weight: 900;
}

.empty-state p {
    margin: 0;
    color: #667085;
    font-size: 15px;
}

/* Modal */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(2, 6, 23, 0.52);
    backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    z-index: 999;
}

.modal-card {
    width: 100%;
    max-width: 860px;
    border-radius: 28px;
    background: #fff;
    padding: 26px;
    box-shadow: 0 30px 70px rgba(15, 23, 42, 0.25);
    max-height: 92vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    align-items: start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
}

.modal-title {
    margin: 0;
    font-size: 28px;
    font-weight: 900;
    color: #111827;
}

.modal-subtitle {
    margin-top: 8px;
    font-size: 14px;
    color: #667085;
    line-height: 1.8;
}

.icon-btn {
    width: 42px;
    height: 42px;
    border: none;
    border-radius: 14px;
    background: #f3f4f6;
    color: #111827;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.icon-btn svg {
    width: 18px;
    height: 18px;
}

.modal-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.modal-info {
    padding: 16px;
    border-radius: 18px;
    background: linear-gradient(180deg, #ffffff, #f8fafc);
    border: 1px solid #edf2f7;
}

.modal-info.full-width {
    grid-column: 1 / -1;
}

.modal-label {
    display: block;
    margin-bottom: 8px;
    font-size: 11px;
    font-weight: 800;
    color: #667085;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.modal-value {
    font-size: 15px;
    font-weight: 800;
    color: #111827;
    line-height: 1.8;
    word-break: break-word;
}

.message-box {
    padding: 16px;
    border-radius: 16px;
    background: #f8fafc;
    color: #344054;
    font-size: 14px;
    line-height: 1.9;
    white-space: pre-wrap;
    word-break: break-word;
}

.break-all {
    word-break: break-all;
}

.modal-actions {
    margin-top: 22px;
    display: flex;
    justify-content: flex-end;
}

.fade-enter-active,
.fade-leave-active {
    transition: 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

@media (max-width: 1100px) {
    .loading-grid,
    .contacts-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {
    .loading-grid,
    .contacts-grid,
    .info-list,
    .modal-grid {
        grid-template-columns: 1fr;
    }

    .info-item.full,
    .modal-info.full-width {
        grid-column: auto;
    }
}

@media (max-width: 640px) {
    .contacts-page {
        padding: 16px;
    }

    .page-header,
    .modal-header,
    .modal-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .modal-card,
    .contact-card,
    .loading-card,
    .empty-state {
        border-radius: 22px;
        padding: 20px;
    }

    .btn,
    .page-btn {
        width: 100%;
    }

    .pagination-wrap {
        align-items: stretch;
    }
}
</style>
