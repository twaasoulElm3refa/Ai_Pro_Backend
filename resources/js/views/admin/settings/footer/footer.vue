<template>
    <AdminLayout>
        <div class="footer-page">
            <div class="page-header">
                <div>
                    <span class="page-badge">Admin Panel</span>
                    <h1 class="page-title">Footer Settings</h1>
                    <p class="page-subtitle">
                        Manage footer social links, store buttons, and logo.
                    </p>
                </div>

                <button class="edit-btn" @click="openEditModal" :disabled="loading">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 20h9" />
                        <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z" />
                    </svg>
                    Edit Footer
                </button>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="loading-card">
                <div class="loading-line lg"></div>
                <div class="loading-line md"></div>
                <div class="loading-grid">
                    <div class="loading-box" v-for="i in 6" :key="i"></div>
                </div>
            </div>

            <!-- Data Card -->
            <div v-else class="footer-card">
                <div class="card-glow glow-1"></div>
                <div class="card-glow glow-2"></div>

                <div class="card-top">
                    <div class="logo-preview-wrap">
                        <div class="logo-preview" v-if="previewImage">
                            <img :src="previewImage" alt="Footer Logo" />
                        </div>

                        <div class="logo-placeholder" v-else>
                            <img src="https://t4.ftcdn.net/jpg/06/57/37/01/360_F_657370150_pdNeG5pjI976ZasVbKN9VqH1rfoykdYU.jpg"
                                alt="placeholder" style="width: 100%; height: 100%; object-fit: cover;" />
                        </div>
                    </div>

                    <div class="card-meta">
                        <div class="meta-box">
                            <span class="meta-label">Updated At</span>
                            <span class="meta-value">{{ formatDate(footer.updated_at) }}</span>
                        </div>
                        <div class="meta-box">
                            <span class="meta-label">Created At</span>
                            <span class="meta-value">{{ formatDate(footer.created_at) }}</span>
                        </div>
                    </div>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Facebook</span>
                        <a class="info-value link" :href="footer.facebook || '#'" target="_blank">
                            {{ footer.facebook || "—" }}
                        </a>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Twitter</span>
                        <a class="info-value link" :href="footer.twitter || '#'" target="_blank">
                            {{ footer.twitter || "—" }}
                        </a>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Instagram</span>
                        <a class="info-value link" :href="footer.instagram || '#'" target="_blank">
                            {{ footer.instagram || "—" }}
                        </a>
                    </div>

                    <div class="info-item">
                        <span class="info-label">LinkedIn</span>
                        <a class="info-value link" :href="footer.linkedin || '#'" target="_blank">
                            {{ footer.linkedin || "—" }}
                        </a>
                    </div>

                    <div class="info-item">
                        <span class="info-label">YouTube</span>
                        <a class="info-value link" :href="footer.youtube || '#'" target="_blank">
                            {{ footer.youtube || "—" }}
                        </a>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Google Play</span>
                        <a class="info-value link" :href="footer.google_play || '#'" target="_blank">
                            {{ footer.google_play || "—" }}
                        </a>
                    </div>

                    <div class="info-item">
                        <span class="info-label">App Store</span>
                        <a class="info-value link" :href="footer.app_store || '#'" target="_blank">
                            {{ footer.app_store || "—" }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- Edit Modal -->
            <transition name="fade">
                <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
                    <div class="modal-card">
                        <div class="modal-header">
                            <div>
                                <h2 class="modal-title">Edit Footer</h2>
                                <p class="modal-subtitle">
                                    Update social media links, app links, and logo.
                                </p>
                            </div>

                            <button class="icon-btn" @click="closeModal">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M18 6 6 18" />
                                    <path d="m6 6 12 12" />
                                </svg>
                            </button>
                        </div>

                        <form class="form-grid" @submit.prevent="handleUpdate">
                            <div class="form-group">
                                <label>Facebook</label>
                                <input v-model="form.facebook" type="text" placeholder="https://facebook.com/..." />
                            </div>

                            <div class="form-group">
                                <label>Twitter</label>
                                <input v-model="form.twitter" type="text" placeholder="https://twitter.com/..." />
                            </div>

                            <div class="form-group">
                                <label>Instagram</label>
                                <input v-model="form.instagram" type="text" placeholder="https://instagram.com/..." />
                            </div>

                            <div class="form-group">
                                <label>LinkedIn</label>
                                <input v-model="form.linkedin" type="text" placeholder="https://linkedin.com/..." />
                            </div>

                            <div class="form-group">
                                <label>YouTube</label>
                                <input v-model="form.youtube" type="text" placeholder="https://youtube.com/..." />
                            </div>

                            <div class="form-group">
                                <label>Google Play</label>
                                <input v-model="form.google_play" type="text" placeholder="Google Play link" />
                            </div>

                            <div class="form-group">
                                <label>App Store</label>
                                <input v-model="form.app_store" type="text" placeholder="App Store link" />
                            </div>

                            <div class="form-group full-width">
                                <label>Logo</label>
                                <div class="file-upload">
                                    <input type="file" @change="handleFileChange" accept="image/*" />
                                    <span>{{ selectedFileName || "Choose logo image" }}</span>
                                </div>

                                <div v-if="previewImage" class="preview-box">
                                    <img :src="previewImage" alt="Preview" />
                                </div>
                            </div>

                            <div class="modal-actions full-width">
                                <button type="button" class="btn btn-secondary" @click="closeModal">
                                    Cancel
                                </button>

                                <button type="submit" class="btn btn-primary" :disabled="submitting">
                                    <span v-if="submitting" class="spinner"></span>
                                    {{ submitting ? "Saving..." : "Save Changes" }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </transition>
        </div>
    </AdminLayout>
</template>

<script setup>
import { reactive, ref, onMounted } from "vue";
import toastr from "toastr";
import AdminLayout from "@/layouts/AdminLayout.vue";
import footerService from "@/services/admin/footer/footerService";

const loading = ref(true);
const submitting = ref(false);
const showModal = ref(false);
const selectedFileName = ref("");
const previewImage = ref("");

const footer = ref({
    id: null,
    facebook: "",
    twitter: "",
    instagram: "",
    linkedin: "",
    youtube: "",
    logo: "",
    google_play: "",
    app_store: "",
    created_at: "",
    updated_at: "",
});

const form = reactive({
    facebook: "",
    twitter: "",
    instagram: "",
    linkedin: "",
    youtube: "",
    google_play: "",
    app_store: "",
    logo: null,
});

async function fetchFooter() {
    loading.value = true;
    try {
        const response = await footerService.getFooter();
        footer.value = response.data;

        fillForm(response.data);
    } catch (error) {
        console.error("Fetch footer error:", error);
    } finally {
        loading.value = false;
    }
}

function fillForm(data) {
    form.facebook = data.facebook || "";
    form.twitter = data.twitter || "";
    form.instagram = data.instagram || "";
    form.linkedin = data.linkedin || "";
    form.youtube = data.youtube || "";
    form.google_play = data.google_play || "";
    form.app_store = data.app_store || "";
    form.logo = null;

    previewImage.value = data.logo
        ? getImageUrl(data.logo)
        : "https://t4.ftcdn.net/jpg/06/57/37/01/360_F_657370150_pdNeG5pjI976ZasVbKN9VqH1rfoykdYU.jpg";

    selectedFileName.value = "";
}

function openEditModal() {
    fillForm(footer.value);
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
}

function handleFileChange(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    form.logo = file;
    selectedFileName.value = file.name;

    previewImage.value = URL.createObjectURL(file);
}

async function handleUpdate() {
    submitting.value = true;

    try {
        const formData = new FormData();
        formData.append("facebook", form.facebook || "");
        formData.append("twitter", form.twitter || "");
        formData.append("instagram", form.instagram || "");
        formData.append("linkedin", form.linkedin || "");
        formData.append("youtube", form.youtube || "");
        formData.append("google_play", form.google_play || "");
        formData.append("app_store", form.app_store || "");

        if (form.logo) {
            formData.append("logo", form.logo);
        }

        const response = await footerService.updateFooter(formData);

        footer.value = response.data;
        showModal.value = false;
        toastr.success(response.message || "Footer updated successfully");
    } catch (error) {
        console.error("Update footer error:", error);
    } finally {
        submitting.value = false;
    }
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
function getImageUrl(path) {
    if (!path) return "";
    return `http://localhost:8000/storage/${path}`;
}
onMounted(() => {
    fetchFooter();

    document.title = "Footer";
});
</script>

<style scoped>
*,
*::before,
*::after {
    box-sizing: border-box;
}

.footer-page {
    min-height: 100vh;
    padding: 28px;
    background:
        radial-gradient(circle at top left, rgba(99, 102, 241, 0.12), transparent 25%),
        radial-gradient(circle at bottom right, rgba(168, 85, 247, 0.12), transparent 25%),
        linear-gradient(180deg, #f8fbff 0%, #f3f6fb 100%);
}

.page-header {
    max-width: 1200px;
    margin: 0 auto 24px;
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

.edit-btn {
    height: 50px;
    padding: 0 18px;
    border: none;
    border-radius: 16px;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: #fff;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-weight: 800;
    font-size: 14px;
    cursor: pointer;
    box-shadow: 0 18px 35px rgba(99, 102, 241, 0.28);
    transition: 0.2s ease;
}

.edit-btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 22px 40px rgba(99, 102, 241, 0.32);
}

.edit-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.edit-btn svg {
    width: 18px;
    height: 18px;
}

.footer-card,
.loading-card {
    position: relative;
    max-width: 1200px;
    margin: 0 auto;
    border-radius: 28px;
    padding: 28px;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid rgba(255, 255, 255, 0.85);
    box-shadow:
        0 20px 50px rgba(15, 23, 42, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(18px);
}

.card-glow {
    position: absolute;
    border-radius: 999px;
    filter: blur(55px);
    opacity: 0.28;
    pointer-events: none;
}

.glow-1 {
    width: 220px;
    height: 220px;
    background: #6366f1;
    top: -70px;
    right: -60px;
}

.glow-2 {
    width: 240px;
    height: 240px;
    background: #a855f7;
    bottom: -90px;
    left: -80px;
}

.card-top {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 28px;
    flex-wrap: wrap;
}

.logo-preview-wrap {
    display: flex;
    align-items: center;
    gap: 16px;
}

.logo-preview,
.logo-placeholder {
    width: 120px;
    height: 120px;
    border-radius: 24px;
    background: linear-gradient(180deg, #ffffff, #f8fafc);
    border: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(15, 23, 42, 0.06);
}

.logo-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.logo-placeholder {
    flex-direction: column;
    gap: 10px;
    color: #94a3b8;
    font-weight: 700;
    font-size: 13px;
}

.logo-placeholder svg {
    width: 28px;
    height: 28px;
}

.card-meta {
    display: grid;
    grid-template-columns: repeat(2, minmax(180px, 1fr));
    gap: 14px;
}

.meta-box {
    padding: 18px;
    border-radius: 18px;
    background: linear-gradient(180deg, #ffffff, #f8fafc);
    border: 1px solid #e5e7eb;
}

.meta-label {
    display: block;
    font-size: 12px;
    font-weight: 800;
    color: #667085;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 8px;
}

.meta-value {
    color: #111827;
    font-size: 15px;
    font-weight: 800;
}

.info-grid {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.info-item {
    padding: 18px;
    border-radius: 20px;
    background: linear-gradient(180deg, #ffffff, #f8fafc);
    border: 1px solid #e5e7eb;
    min-height: 98px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    transition: 0.2s ease;
}

.info-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
    border-color: #c7d2fe;
}

.info-label {
    font-size: 12px;
    font-weight: 800;
    color: #667085;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.info-value {
    font-size: 15px;
    font-weight: 800;
    color: #101828;
    word-break: break-word;
}

.info-value.link {
    color: #4f46e5;
    text-decoration: none;
}

.info-value.link:hover {
    text-decoration: underline;
}

/* Loading */
.loading-line {
    height: 18px;
    border-radius: 12px;
    background: linear-gradient(90deg, #eef2f7 25%, #e2e8f0 50%, #eef2f7 75%);
    background-size: 200% 100%;
    animation: shimmer 1.4s infinite linear;
    margin-bottom: 14px;
}

.loading-line.lg {
    width: 38%;
    height: 24px;
}

.loading-line.md {
    width: 25%;
}

.loading-grid {
    margin-top: 28px;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
}

.loading-box {
    height: 110px;
    border-radius: 20px;
    background: linear-gradient(90deg, #eef2f7 25%, #e2e8f0 50%, #eef2f7 75%);
    background-size: 200% 100%;
    animation: shimmer 1.4s infinite linear;
}

@keyframes shimmer {
    0% {
        background-position: 200% 0;
    }

    100% {
        background-position: -200% 0;
    }
}

/* Modal */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(2, 6, 23, 0.48);
    backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    z-index: 999;
}

.modal-card {
    width: 100%;
    max-width: 920px;
    max-height: 92vh;
    overflow-y: auto;
    border-radius: 28px;
    background: #fff;
    padding: 26px;
    box-shadow: 0 28px 70px rgba(15, 23, 42, 0.25);
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

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    margin-bottom: 10px;
    font-size: 13px;
    font-weight: 800;
    color: #344054;
}

.form-group input {
    height: 54px;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    background: #f8fafc;
    padding: 0 16px;
    outline: none;
    font-size: 14px;
    color: #111827;
    transition: 0.2s ease;
}

.form-group input:focus {
    border-color: #6366f1;
    background: #fff;
    box-shadow: 0 0 0 5px rgba(99, 102, 241, 0.1);
}

.file-upload {
    position: relative;
    min-height: 56px;
    border: 1px dashed #cbd5e1;
    border-radius: 18px;
    background: #f8fafc;
    display: flex;
    align-items: center;
    padding: 0 16px;
    overflow: hidden;
}

.file-upload input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}

.file-upload span {
    color: #64748b;
    font-size: 14px;
    font-weight: 700;
}

.preview-box {
    margin-top: 14px;
    width: 120px;
    height: 120px;
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    background: #fff;
}

.preview-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.modal-actions {
    margin-top: 8px;
    display: flex;
    justify-content: flex-end;
    gap: 14px;
}

.btn {
    min-width: 140px;
    height: 52px;
    border: none;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    transition: 0.2s ease;
}

.btn-secondary {
    background: #f3f4f6;
    color: #111827;
}

.btn-secondary:hover {
    background: #e5e7eb;
}

.btn-primary {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: #fff;
    box-shadow: 0 18px 35px rgba(99, 102, 241, 0.25);
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-1px);
}

.btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.spinner {
    width: 18px;
    height: 18px;
    border: 2px solid rgba(255, 255, 255, 0.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.75s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.fade-enter-active,
.fade-leave-active {
    transition: 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

@media (max-width: 900px) {

    .info-grid,
    .form-grid,
    .loading-grid,
    .card-meta {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .footer-page {
        padding: 16px;
    }

    .footer-card,
    .loading-card,
    .modal-card {
        padding: 20px;
        border-radius: 22px;
    }

    .page-header,
    .card-top,
    .modal-header,
    .modal-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .edit-btn,
    .btn {
        width: 100%;
    }
}
</style>
