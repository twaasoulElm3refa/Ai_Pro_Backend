<template>
  <AdminLayout>
    <div class="edit-page" dir="rtl">

      <!-- ── Top bar ── -->
      <div class="top-bar" :class="{ visible: !loading }">
        <button class="back-btn" @click="$router.back()">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="15 18 9 12 15 6"/></svg>
          رجوع
        </button>
        <nav class="breadcrumb">
          <span>المستخدمون</span>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          <span class="bc-cur">تعديل المستخدم</span>
        </nav>
      </div>

      <!-- ── Skeleton ── -->
      <div v-if="loading" class="sk-wrap">
        <div class="sk-left">
          <div class="sk sk-circle"></div>
          <div class="sk sk-ln w55"></div>
          <div class="sk sk-ln w40"></div>
        </div>
        <div class="sk-right">
          <div class="sk sk-field" v-for="n in 4" :key="n"></div>
          <div class="sk sk-btn"></div>
        </div>
      </div>

      <!-- ── Main ── -->
      <div v-else-if="form" class="main-grid">

        <!-- LEFT: Avatar preview panel -->
        <aside class="preview-panel">
          <div class="avatar-zone">
            <!-- Image preview / current -->
            <div class="avatar-ring">
              <img
                v-if="previewUrl"
                :src="previewUrl"
                alt="صورة المستخدم"
                class="avatar-img"
              />
              <div
                v-else
                class="avatar-initials"
                :style="{ background: avatarColor(form.name) }"
              >{{ initials(form.name) }}</div>

              <!-- Upload overlay -->
              <label class="upload-overlay" for="imageInput" title="تغيير الصورة">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                  <circle cx="12" cy="13" r="4"/>
                </svg>
              </label>
              <input
                id="imageInput"
                type="file"
                accept="image/*"
                class="hidden-input"
                @change="onImagePick"
              />
            </div>

            <p class="preview-name">{{ form.name || 'اسم المستخدم' }}</p>
            <span class="preview-role" :class="`role-${form.role}`">{{ roleLabel(form.role) }}</span>

            <!-- Active toggle card -->
            <div class="toggle-card" :class="form.is_active ? 'tc-on' : 'tc-off'">
              <div class="tc-info">
                <span class="tc-title">{{ form.is_active ? 'الحساب نشط' : 'الحساب معطل' }}</span>
                <span class="tc-sub">{{ form.is_active ? 'يمكن للمستخدم تسجيل الدخول' : 'الحساب موقوف مؤقتاً' }}</span>
              </div>
              <button
                type="button"
                class="toggle-btn"
                :class="{ 'tb-on': form.is_active }"
                @click="form.is_active = !form.is_active"
                :aria-label="form.is_active ? 'إيقاف الحساب' : 'تفعيل الحساب'"
              >
                <span class="tb-knob"></span>
              </button>
            </div>
          </div>
        </aside>

        <!-- RIGHT: Form -->
        <section class="form-panel">
          <div class="form-header">
            <h1 class="form-title">تعديل بيانات المستخدم</h1>
            <p class="form-sub">قم بتحديث المعلومات الأساسية للمستخدم</p>
          </div>

          <form @submit.prevent="handleSubmit" class="edit-form" novalidate>

            <!-- Name -->
            <div class="field-group" :class="{ 'has-error': errors.name }">
              <label class="field-label">
                <span class="label-icon">👤</span>
                الاسم الكامل
              </label>
              <div class="input-wrap">
                <input
                  v-model="form.name"
                  type="text"
                  class="field-input"
                  placeholder="أدخل الاسم الكامل"
                  @input="clearError('name')"
                />
              </div>
              <span v-if="errors.name" class="field-error">{{ errors.name }}</span>
            </div>

            <!-- Email -->
            <div class="field-group" :class="{ 'has-error': errors.email }">
              <label class="field-label">
                <span class="label-icon">✉️</span>
                البريد الإلكتروني
              </label>
              <div class="input-wrap">
                <input
                  v-model="form.email"
                  type="email"
                  class="field-input dir-ltr"
                  placeholder="example@email.com"
                  @input="clearError('email')"
                />
              </div>
              <span v-if="errors.email" class="field-error">{{ errors.email }}</span>
            </div>

            <!-- Role -->
            <div class="field-group" :class="{ 'has-error': errors.role }">
              <label class="field-label">
                <span class="label-icon">🏷️</span>
                الدور الوظيفي
              </label>
              <div class="select-wrap">
                <select v-model="form.role" class="field-select" @change="clearError('role')">
                  <option value="" disabled>اختر الدور</option>
                  <option value="admin">مدير النظام</option>
                  <option value="user">مستخدم</option>
                </select>
                <span class="select-arrow">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
              </div>
              <span v-if="errors.role" class="field-error">{{ errors.role }}</span>
            </div>

            <!-- Image upload hint -->
            <div class="field-group">
              <label class="field-label">
                <span class="label-icon">🖼️</span>
                الصورة الشخصية
              </label>
              <label for="imageInput" class="image-drop-zone" :class="{ 'dz-has-file': imageFile }">
                <div v-if="!imageFile" class="dz-idle">
                  <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                  </svg>
                  <span>اضغط لاختيار صورة</span>
                  <span class="dz-hint">PNG, JPG — بحد أقصى 2MB</span>
                </div>
                <div v-else class="dz-selected">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                  <span>{{ imageFile.name }}</span>
                  <button type="button" class="dz-clear" @click.prevent="clearImage">✕</button>
                </div>
              </label>
            </div>

            <!-- Divider -->
            <div class="form-divider"></div>

            <!-- Actions -->
            <div class="form-actions">
              <button type="button" class="btn-cancel" @click="$router.back()">إلغاء</button>
              <button type="submit" class="btn-save" :class="{ 'btn-loading': saving }">
                <span v-if="!saving">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="vertical-align:-2px;margin-left:.4rem"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                  حفظ التعديلات
                </span>
                <span v-else class="loader-dots">
                  <span></span><span></span><span></span>
                </span>
              </button>
            </div>

          </form>
        </section>

      </div>

      <!-- ── Not Found ── -->
      <div v-else class="not-found">
        <span class="nf-icon">🔍</span>
        <p>المستخدم غير موجود</p>
        <button class="back-btn" @click="$router.back()">رجوع</button>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted }   from "vue";
import { useRoute, useRouter } from "vue-router";
import AdminLayout             from "@/layouts/AdminLayout.vue";
import userService             from "@/services/admin/users/userService";
import toastr                  from "toastr";

const route  = useRoute();
const router = useRouter();

// ── state ──────────────────────────────────────────────────
const loading    = ref(false);
const saving     = ref(false);
const form       = ref(null);
const errors     = ref({});
const imageFile  = ref(null);
const previewUrl = ref(null);

// ── fetch user ─────────────────────────────────────────────
onMounted(async () => {
  loading.value = true;
  try {
    const res  = await userService.getUser(route.params.id);
    const user = res.data;

    form.value = {
      name:      user.name      ?? "",
      email:     user.email     ?? "",
      role:      user.role      ?? "user",
      is_active: !!user.is_active,
    };

    // Set existing avatar as preview
    if (user.image) {
      previewUrl.value = `http://localhost:8000/storage/${user.image}`;
    }
  } finally {
    loading.value = false;
  }
});

// ── image pick ─────────────────────────────────────────────
function onImagePick(e) {
  const file = e.target.files[0];
  if (!file) return;
  imageFile.value  = file;
  previewUrl.value = URL.createObjectURL(file);
}
function clearImage() {
  imageFile.value  = null;
  previewUrl.value = null;
  document.getElementById("imageInput").value = "";
}

// ── submit ─────────────────────────────────────────────────
async function handleSubmit() {
  errors.value = {};

  // Client validation
  if (!form.value.name.trim())  { errors.value.name  = "الاسم مطلوب";              return; }
  if (!form.value.email.trim()) { errors.value.email = "البريد الإلكتروني مطلوب"; return; }
  if (!form.value.role)         { errors.value.role  = "الدور مطلوب";              return; }

  saving.value = true;
  try {
    const fd = new FormData();
    fd.append("name",      form.value.name);
    fd.append("email",     form.value.email);
    fd.append("role",      form.value.role);
    fd.append("is_active", form.value.is_active ? "1" : "0");
    if (imageFile.value) fd.append("image", imageFile.value);

    await userService.updateUser(route.params.id, fd);
    toastr.success("تم تحديث بيانات المستخدم بنجاح ✓");
    window.location.href = "/admin/users";
  } finally {
    saving.value = false;
  }
}

function clearError(field) { delete errors.value[field]; }

// ── helpers ────────────────────────────────────────────────
function initials(name = "") {
  return (name || "U").split(" ").slice(0, 2).map((w) => w[0]).join("").toUpperCase();
}
const palette = ["#154677","#2ba6de","#2ba6de","#2ba6de","#2ba6de","#2ba6de","#2ba6de"];
function avatarColor(name = "") {
  let n = 0; for (const c of (name || "U")) n += c.charCodeAt(0);
  return palette[n % palette.length];
}
function roleLabel(role) {
  const map = { admin: "مدير النظام", user: "مستخدم", moderator: "مشرف" };
  return map[role] ?? role ?? "—";
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── Variables ── */
:root {
  --bg:        #f0f2f9;
  --card:      #ffffff;
  --border:    #e2e5f0;
  --text:      #1a1d2e;
  --muted:     #8a90a8;
  --accent:    #2ba6de;
  --accent2:   #154677;
  --green:     #2ba6de;
  --radius:    20px;
  --shadow:    0 4px 32px rgba(0,0,0,.07);
}

.edit-page {
  padding: 1.75rem 2rem;
  background: var(--bg);
  min-height: 100vh;
  font-family: 'Cairo', sans-serif;
  direction: rtl;
}

/* ── Top bar ── */
.top-bar {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 1.75rem;
  opacity: 0; transform: translateY(-8px);
  transition: opacity .4s, transform .4s;
}
.top-bar.visible { opacity: 1; transform: none; }

.back-btn {
  display: inline-flex; align-items: center; gap: .4rem;
  padding: .5rem 1.1rem;
  border-radius: 12px; border: 1px solid var(--border);
  background: var(--card); color: var(--text);
  font-size: .84rem; font-weight: 700; cursor: pointer;
  font-family: inherit;
  box-shadow: 0 2px 8px rgba(0,0,0,.05);
  transition: background .15s, box-shadow .15s;
}
.back-btn:hover { background: #f3f4f6; box-shadow: 0 4px 14px rgba(0,0,0,.08); }

.breadcrumb {
  display: flex; align-items: center; gap: .4rem;
  font-size: .8rem; color: var(--muted); font-weight: 600;
}
.bc-cur { color: var(--accent); }

/* ── Grid ── */
.main-grid {
  display: grid;
  grid-template-columns: 290px 1fr;
  gap: 1.5rem;
  align-items: start;
}
@media (max-width: 860px) {
  .main-grid { grid-template-columns: 1fr; }
}

/* ══ LEFT: Preview Panel ══ */
.preview-panel {
  background: var(--card);
  border-radius: var(--radius);
  border: 1px solid var(--border);
  box-shadow: var(--shadow);
  padding: 2rem 1.5rem;
  animation: fadeUp .4s ease both;
}
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}
.avatar-zone { display: flex; flex-direction: column; align-items: center; gap: 1rem; }

/* Avatar ring */
.avatar-ring {
  position: relative;
  width: 96px; height: 96px;
}
.avatar-img, .avatar-initials {
  width: 96px; height: 96px; border-radius: 26px;
  object-fit: cover;
  border: 3px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; font-weight: 900; color: #fff;
}
.upload-overlay {
  position: absolute; inset: 0;
  border-radius: 26px;
  background: rgba(14,165,233,.55);
  display: flex; align-items: center; justify-content: center;
  color: #fff; cursor: pointer;
  opacity: 0; transition: opacity .2s;
}
.avatar-ring:hover .upload-overlay { opacity: 1; }
.hidden-input { display: none; }

.preview-name {
  font-size: 1.05rem; font-weight: 800; color: var(--text);
  text-align: center; word-break: break-word;
}

.preview-role {
  padding: .25rem .9rem; border-radius: 999px;
  font-size: .75rem; font-weight: 800; letter-spacing: .04em;
}
.role-admin     { background: #ede9fe; color: #6d28d9; }
.role-user      { background: #dbeafe; color: #154677; }
.role-moderator { background: #d1fae5; color: #065f46; }

/* Toggle card */
.toggle-card {
  width: 100%;
  border-radius: 16px;
  padding: 1rem 1.1rem;
  display: flex; align-items: center; justify-content: space-between; gap: .75rem;
  border: 1.5px solid;
  transition: background .25s, border-color .25s;
}
.tc-on  { background: #ecfdf5; border-color: #6ee7b7; }
.tc-off { background: #fef2f2; border-color: #fca5a5; }
.tc-info { display: flex; flex-direction: column; gap: .15rem; }
.tc-title { font-size: .85rem; font-weight: 800; color: var(--text); }
.tc-sub   { font-size: .72rem; color: var(--muted); font-weight: 600; }

/* Toggle button */
.toggle-btn {
  width: 48px; height: 26px;
  border-radius: 999px;
  border: none; cursor: pointer;
  position: relative;
  background: #d1d5db;
  transition: background .25s;
  flex-shrink: 0;
  padding: 0;
}
.toggle-btn.tb-on { background: var(--green); }
.tb-knob {
  position: absolute;
  top: 3px; right: 3px;
  width: 20px; height: 20px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 4px rgba(0,0,0,.18);
  transition: transform .25s;
}
.tb-on .tb-knob { transform: translateX(-22px); }

/* ══ RIGHT: Form Panel ══ */
.form-panel {
  background: var(--card);
  border-radius: var(--radius);
  border: 1px solid var(--border);
  box-shadow: var(--shadow);
  padding: 2rem 2rem 1.75rem;
  animation: fadeUp .4s ease .08s both;
}
.form-header { margin-bottom: 1.75rem; }
.form-title  { font-size: 1.35rem; font-weight: 900; color: var(--text); }
.form-sub    { font-size: .84rem; color: var(--muted); font-weight: 600; margin-top: .25rem; }

/* Fields */
.edit-form { display: flex; flex-direction: column; gap: 1.35rem; }

.field-group { display: flex; flex-direction: column; gap: .45rem; }
.field-label {
  display: flex; align-items: center; gap: .45rem;
  font-size: .82rem; font-weight: 800; color: var(--text);
}
.label-icon { font-size: .95rem; }

.input-wrap, .select-wrap { position: relative; }

.field-input, .field-select {
  width: 100%;
  padding: .8rem 1rem;
  border-radius: 12px;
  border: 1.5px solid var(--border);
  background: #fafbff;
  font-size: .9rem; font-weight: 600;
  color: var(--text);
  font-family: inherit;
  outline: none;
  transition: border-color .2s, box-shadow .2s;
  appearance: none;
}
.field-input:focus, .field-select:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(14,165,233,.12);
  background: #fff;
}
.has-error .field-input,
.has-error .field-select {
  border-color: var(--accent2);
  box-shadow: 0 0 0 3px rgba(244,63,94,.1);
}
.field-error {
  font-size: .78rem; color: var(--accent2); font-weight: 700;
}
.dir-ltr { direction: ltr; text-align: right; }

.select-arrow {
  position: absolute;
  left: .9rem; top: 50%; transform: translateY(-50%);
  pointer-events: none; color: var(--muted);
  display: flex; align-items: center;
}

/* Image drop zone */
.image-drop-zone {
  display: flex;
  align-items: center; justify-content: center;
  border: 2px dashed var(--border);
  border-radius: 14px;
  padding: 1.5rem 1rem;
  cursor: pointer;
  transition: border-color .2s, background .2s;
  background: #fafbff;
}
.image-drop-zone:hover { border-color: var(--accent); background: #f0f9ff; }
.dz-has-file { border-color: var(--green); background: #f0fdf4; border-style: solid; }

.dz-idle {
  display: flex; flex-direction: column; align-items: center; gap: .4rem;
  color: var(--muted); font-size: .85rem; font-weight: 700;
}
.dz-hint { font-size: .72rem; color: #c4c9d9; font-weight: 600; }

.dz-selected {
  display: flex; align-items: center; gap: .65rem;
  color: var(--green); font-size: .85rem; font-weight: 700;
}
.dz-clear {
  background: none; border: none; cursor: pointer;
  color: var(--muted); font-size: .85rem; margin-right: auto;
  font-weight: 800; padding: .15rem .3rem; border-radius: 6px;
  transition: background .15s;
}
.dz-clear:hover { background: #fee2e2; color: var(--accent2); }

/* Divider */
.form-divider { border: none; border-top: 1px solid var(--border); margin: .25rem 0; }

/* Actions */
.form-actions { display: flex; align-items: center; gap: .75rem; justify-content: flex-end; }

.btn-cancel {
  padding: .7rem 1.5rem;
  border-radius: 12px; border: 1.5px solid var(--border);
  background: var(--card); color: var(--muted);
  font-size: .88rem; font-weight: 800;
  cursor: pointer; font-family: inherit;
  transition: background .15s, color .15s;
}
.btn-cancel:hover { background: #f3f4f6; color: var(--text); }

.btn-save {
  padding: .7rem 1.75rem;
  border-radius: 12px; border: none;
  background: linear-gradient(135deg, #2ba6de 0%, #2ba6de 100%);
  color: #fff; font-size: .9rem; font-weight: 800;
  cursor: pointer; font-family: inherit;
  box-shadow: 0 4px 18px rgba(14,165,233,.4);
  transition: opacity .2s, transform .1s, box-shadow .2s;
  min-width: 145px; display: inline-flex; align-items: center; justify-content: center;
}
.btn-save:hover:not(.btn-loading) { opacity: .9; transform: translateY(-1px); box-shadow: 0 6px 22px rgba(14,165,233,.45); }
.btn-loading { opacity: .7; cursor: not-allowed; }

/* Loader dots */
.loader-dots { display: flex; gap: 5px; align-items: center; }
.loader-dots span {
  width: 7px; height: 7px; border-radius: 50%; background: rgba(255,255,255,.8);
  animation: bounce 1s infinite;
}
.loader-dots span:nth-child(2) { animation-delay: .15s; }
.loader-dots span:nth-child(3) { animation-delay: .3s; }
@keyframes bounce {
  0%, 80%, 100% { transform: scale(1); }
  40%           { transform: scale(1.4); }
}

/* ── Skeletons ── */
.sk-wrap {
  display: grid; grid-template-columns: 290px 1fr; gap: 1.5rem;
}
.sk-left, .sk-right {
  background: var(--card); border-radius: var(--radius);
  border: 1px solid var(--border);
  padding: 2rem 1.5rem;
  display: flex; flex-direction: column; align-items: center; gap: 1rem;
}
.sk-right { align-items: stretch; }
.sk {
  background: #e8eaf2; border-radius: 10px;
  animation: shimmer 1.4s infinite;
}
@keyframes shimmer {
  0%, 100% { opacity: 1; } 50% { opacity: .4; }
}
.sk-circle { width: 96px; height: 96px; border-radius: 26px; }
.sk-ln     { height: 14px; }
.w55 { width: 55%; } .w40 { width: 40%; }
.sk-field  { height: 52px; border-radius: 12px; }
.sk-btn    { height: 44px; width: 50%; align-self: flex-end; border-radius: 12px; }

/* ── Not found ── */
.not-found {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; gap: 1rem; padding: 5rem;
  color: var(--muted); font-size: .95rem; font-weight: 600;
}
.nf-icon { font-size: 3rem; }
</style>
