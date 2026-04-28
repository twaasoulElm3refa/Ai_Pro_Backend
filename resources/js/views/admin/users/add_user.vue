<template>
  <AdminLayout>
    <div class="add-user-page">
      <div class="page-shell">
        <!-- Header -->
        <div class="page-header">
          <div class="header-copy">
            <span class="eyebrow">Admin Panel</span>
            <h1 class="page-title">Add New User</h1>
            <p class="page-subtitle">
              Create a new account and assign the appropriate role.
            </p>
          </div>

          <router-link to="/admin/users" class="back-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <path d="M15 18l-6-6 6-6" />
            </svg>
            Back to Users
          </router-link>
        </div>

        <!-- Card -->
        <div class="form-card">
          <div class="card-glow glow-1"></div>
          <div class="card-glow glow-2"></div>

          <div class="card-head">
            <div class="head-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5Z" />
                <path d="M20 21a8 8 0 1 0-16 0" />
                <path d="M19 8v6" />
                <path d="M22 11h-6" />
              </svg>
            </div>

            <div>
              <h2 class="card-title">User Information</h2>
              <p class="card-text">
                Fill in the details below to add a new user to the platform.
              </p>
            </div>
          </div>

          <form class="user-form" @submit.prevent="handleSubmit">
            <div class="form-grid">
              <!-- Name -->
              <div class="field-group">
                <label class="field-label">Full Name</label>
                <div class="input-wrap" :class="{ invalid: errors.name }">
                  <span class="input-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                      <path d="M20 21a8 8 0 1 0-16 0" />
                      <circle cx="12" cy="7" r="4" />
                    </svg>
                  </span>
                  <input
                    v-model="form.name"
                    type="text"
                    class="form-input"
                    placeholder="Enter full name"
                  />
                </div>
                <p v-if="errors.name" class="error-text">{{ errors.name }}</p>
              </div>

              <!-- Email -->
              <div class="field-group">
                <label class="field-label">Email Address</label>
                <div class="input-wrap" :class="{ invalid: errors.email }">
                  <span class="input-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                      <path d="M4 6h16v12H4z" />
                      <path d="m4 7 8 6 8-6" />
                    </svg>
                  </span>
                  <input
                    v-model="form.email"
                    type="email"
                    class="form-input"
                    placeholder="Enter email address"
                  />
                </div>
                <p v-if="errors.email" class="error-text">{{ errors.email }}</p>
              </div>

              <!-- Password -->
              <div class="field-group">
                <label class="field-label">Password</label>
                <div class="input-wrap" :class="{ invalid: errors.password }">
                  <span class="input-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                      <rect x="4" y="11" width="16" height="10" rx="2" />
                      <path d="M8 11V8a4 4 0 1 1 8 0v3" />
                    </svg>
                  </span>
                  <input
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    class="form-input has-action"
                    placeholder="Enter password"
                  />
                  <button
                    type="button"
                    class="input-action"
                    @click="showPassword = !showPassword"
                  >
                    {{ showPassword ? 'Hide' : 'Show' }}
                  </button>
                </div>
                <p v-if="errors.password" class="error-text">{{ errors.password }}</p>
              </div>

              <!-- Password Confirmation -->
              <div class="field-group">
                <label class="field-label">Confirm Password</label>
                <div class="input-wrap" :class="{ invalid: errors.password_confirmation }">
                  <span class="input-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                      <path d="M20 6 9 17l-5-5" />
                    </svg>
                  </span>
                  <input
                    v-model="form.password_confirmation"
                    :type="showConfirmPassword ? 'text' : 'password'"
                    class="form-input has-action"
                    placeholder="Re-enter password"
                  />
                  <button
                    type="button"
                    class="input-action"
                    @click="showConfirmPassword = !showConfirmPassword"
                  >
                    {{ showConfirmPassword ? 'Hide' : 'Show' }}
                  </button>
                </div>
                <p v-if="errors.password_confirmation" class="error-text">
                  {{ errors.password_confirmation }}
                </p>
              </div>
            </div>

            <!-- Role -->
            <div class="field-group role-group">
              <label class="field-label">Select Role</label>

              <div class="role-options">
                <button
                  type="button"
                  class="role-card"
                  :class="{ active: form.role === 'admin' }"
                  @click="form.role = 'admin'"
                >
                  <div class="role-card-top">
                    <span class="role-badge admin">ADMIN</span>
                    <span class="role-check" v-if="form.role === 'admin'">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
                        <path d="M20 6 9 17l-5-5" />
                      </svg>
                    </span>
                  </div>

                  <h3 class="role-title">Administrator</h3>
                  <p class="role-desc">
                    Full dashboard access and advanced permissions.
                  </p>
                </button>

                <button
                  type="button"
                  class="role-card"
                  :class="{ active: form.role === 'user' }"
                  @click="form.role = 'user'"
                >
                  <div class="role-card-top">
                    <span class="role-badge user">USER</span>
                    <span class="role-check" v-if="form.role === 'user'">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
                        <path d="M20 6 9 17l-5-5" />
                      </svg>
                    </span>
                  </div>

                  <h3 class="role-title">Regular User</h3>
                  <p class="role-desc">
                    Standard access for normal platform usage.
                  </p>
                </button>
              </div>

              <p v-if="errors.role" class="error-text">{{ errors.role }}</p>
            </div>

            <!-- Summary -->
            <div class="summary-box">
              <div class="summary-item">
                <span class="summary-label">Selected Role</span>
                <span class="summary-value">{{ roleLabel }}</span>
              </div>

              <div class="summary-item">
                <span class="summary-label">Account Status</span>
                <span class="summary-value success">Active by default</span>
              </div>
            </div>

            <!-- Server Error -->
            <div v-if="serverError" class="server-error">
              {{ serverError }}
            </div>

            <!-- Actions -->
            <div class="form-actions">
              <router-link to="/admin/users" class="btn btn-light">
                Cancel
              </router-link>

              <button type="submit" class="btn btn-primary" :disabled="loading">
                <span v-if="loading" class="btn-loader"></span>
                <span>{{ loading ? 'Creating User...' : 'Create User' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { reactive, ref, computed } from "vue";
import { useRouter } from "vue-router";
import AdminLayout from "@/layouts/AdminLayout.vue";
import userService from "@/services/admin/users/userService";

const router = useRouter();

const loading = ref(false);
const showPassword = ref(false);
const showConfirmPassword = ref(false);
const serverError = ref("");

const form = reactive({
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
  role: "user",
});

const errors = reactive({
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
  role: "",
});

const roleLabel = computed(() => {
  return form.role === "admin" ? "Administrator" : "Regular User";
});

function resetErrors() {
  errors.name = "";
  errors.email = "";
  errors.password = "";
  errors.password_confirmation = "";
  errors.role = "";
  serverError.value = "";
}

function validateForm() {
  resetErrors();
  let isValid = true;

  if (!form.name.trim()) {
    errors.name = "Name is required.";
    isValid = false;
  }

  if (!form.email.trim()) {
    errors.email = "Email is required.";
    isValid = false;
  } else if (!/^\S+@\S+\.\S+$/.test(form.email)) {
    errors.email = "Please enter a valid email address.";
    isValid = false;
  }

  if (!form.password) {
    errors.password = "Password is required.";
    isValid = false;
  } else if (form.password.length < 6) {
    errors.password = "Password must be at least 6 characters.";
    isValid = false;
  }

  if (!form.password_confirmation) {
    errors.password_confirmation = "Password confirmation is required.";
    isValid = false;
  } else if (form.password !== form.password_confirmation) {
    errors.password_confirmation = "Passwords do not match.";
    isValid = false;
  }

  if (!["admin", "user"].includes(form.role)) {
    errors.role = "Please select a valid role.";
    isValid = false;
  }

  return isValid;
}

async function handleSubmit() {
  if (!validateForm()) return;

  loading.value = true;
  serverError.value = "";

  try {
    await userService.storeUser({
      name: form.name,
      email: form.email,
      password: form.password,
      password_confirmation: form.password_confirmation,
      role: form.role,
    });

    router.push("/admin/users");
  } catch (error) {
    const response = error?.response?.data;

    if (response?.errors) {
      errors.name = response.errors.name?.[0] || "";
      errors.email = response.errors.email?.[0] || "";
      errors.password = response.errors.password?.[0] || "";
      errors.password_confirmation = response.errors.password_confirmation?.[0] || "";
      errors.role = response.errors.role?.[0] || "";
    } else {
      serverError.value = response?.message || "Something went wrong while creating the user.";
    }
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
*,
*::before,
*::after {
  box-sizing: border-box;
}

.add-user-page {
  min-height: 100vh;
  padding: 28px;
  background:
    radial-gradient(circle at top left, rgba(99, 102, 241, 0.12), transparent 28%),
    radial-gradient(circle at bottom right, rgba(168, 85, 247, 0.12), transparent 26%),
    linear-gradient(180deg, #f8faff 0%, #f3f6fd 100%);
}

.page-shell {
  max-width: 1180px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.eyebrow {
  display: inline-block;
  margin-bottom: 10px;
  padding: 7px 12px;
  border-radius: 999px;
  background: rgba(99, 102, 241, 0.1);
  color: #154677;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.page-title {
  margin: 0;
  font-size: clamp(30px, 4vw, 42px);
  line-height: 1.05;
  font-weight: 900;
  color: #121826;
}

.page-subtitle {
  margin-top: 10px;
  max-width: 620px;
  font-size: 15px;
  line-height: 1.8;
  color: #667085;
}

.back-btn {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  height: 48px;
  padding: 0 18px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.75);
  border: 1px solid rgba(226, 232, 240, 0.9);
  color: #154677;
  text-decoration: none;
  font-weight: 700;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
  backdrop-filter: blur(10px);
  transition: 0.2s ease;
}

.back-btn:hover {
  transform: translateY(-1px);
  background: #fff;
}

.back-btn svg {
  width: 18px;
  height: 18px;
}

.form-card {
  position: relative;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.84);
  border: 1px solid rgba(255, 255, 255, 0.7);
  border-radius: 28px;
  padding: 28px;
  box-shadow:
    0 20px 60px rgba(15, 23, 42, 0.08),
    inset 0 1px 0 rgba(255, 255, 255, 0.5);
  backdrop-filter: blur(18px);
}

.card-glow {
  position: absolute;
  border-radius: 999px;
  filter: blur(50px);
  opacity: 0.32;
  pointer-events: none;
}

.glow-1 {
  width: 220px;
  height: 220px;
  background: #2ba6de;
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

.card-head {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 16px;
  padding-bottom: 22px;
  margin-bottom: 24px;
  border-bottom: 1px solid rgba(226, 232, 240, 0.9);
}

.head-icon {
  width: 58px;
  height: 58px;
  border-radius: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #154677, #2ba6de);
  color: #fff;
  box-shadow: 0 16px 30px rgba(99, 102, 241, 0.25);
  flex-shrink: 0;
}

.head-icon svg {
  width: 26px;
  height: 26px;
}

.card-title {
  margin: 0;
  font-size: 24px;
  font-weight: 900;
  color: #154677;
}

.card-text {
  margin-top: 6px;
  color: #6b7280;
  font-size: 14px;
}

.user-form {
  position: relative;
  z-index: 1;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 22px;
}

.field-group {
  display: flex;
  flex-direction: column;
}

.field-label {
  margin-bottom: 10px;
  font-size: 13px;
  font-weight: 800;
  color: #344054;
}

.input-wrap {
  position: relative;
  display: flex;
  align-items: center;
  min-height: 58px;
  border-radius: 18px;
  background: rgba(248, 250, 252, 0.95);
  border: 1px solid #e5e7eb;
  transition: 0.2s ease;
  overflow: hidden;
}

.input-wrap:hover {
  border-color: #c7d2fe;
  background: #fff;
}

.input-wrap:focus-within {
  border-color: #2ba6de;
  background: #fff;
  box-shadow: 0 0 0 5px rgba(99, 102, 241, 0.1);
}

.input-wrap.invalid {
  border-color: #154677;
  box-shadow: 0 0 0 5px rgba(239, 68, 68, 0.08);
}

.input-icon {
  width: 54px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #98a2b3;
  flex-shrink: 0;
}

.input-icon svg {
  width: 20px;
  height: 20px;
}

.form-input {
  width: 100%;
  height: 56px;
  border: 0;
  outline: none;
  background: transparent;
  padding: 0 16px 0 0;
  font-size: 15px;
  color: #154677;
}

.form-input::placeholder {
  color: #9ca3af;
}

.input-action {
  margin-right: 10px;
  margin-left: 10px;
  height: 36px;
  padding: 0 14px;
  border: none;
  border-radius: 12px;
  background: #eef2ff;
  color: #154677;
  font-weight: 800;
  font-size: 12px;
  cursor: pointer;
  transition: 0.2s ease;
}

.input-action:hover {
  background: #e0e7ff;
}

.error-text {
  margin-top: 8px;
  color: #154677;
  font-size: 12px;
  font-weight: 700;
}

.role-group {
  margin-top: 24px;
}

.role-options {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
}

.role-card {
  position: relative;
  text-align: left;
  padding: 20px;
  border: 1px solid #e5e7eb;
  border-radius: 22px;
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
  cursor: pointer;
  transition: 0.25s ease;
  box-shadow: 0 8px 25px rgba(15, 23, 42, 0.04);
}

.role-card:hover {
  transform: translateY(-2px);
  border-color: #c7d2fe;
  box-shadow: 0 16px 30px rgba(99, 102, 241, 0.1);
}

.role-card.active {
  border-color: #2ba6de;
  box-shadow:
    0 18px 35px rgba(99, 102, 241, 0.14),
    0 0 0 4px rgba(99, 102, 241, 0.08);
  background: linear-gradient(180deg, #ffffff 0%, #eef2ff 100%);
}

.role-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.role-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 29px;
  padding: 0 12px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 900;
  letter-spacing: 0.08em;
}

.role-badge.admin {
  background: #ede9fe;
  color: #6d28d9;
}

.role-badge.user {
  background: #dbeafe;
  color: #154677;
}

.role-check {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #154677;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.role-check svg {
  width: 14px;
  height: 14px;
}

.role-title {
  margin: 0;
  font-size: 18px;
  font-weight: 900;
  color: #154677;
}

.role-desc {
  margin-top: 8px;
  font-size: 13px;
  line-height: 1.8;
  color: #667085;
}

.summary-box {
  margin-top: 24px;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  padding: 18px;
  border-radius: 20px;
  background: linear-gradient(135deg, #0f172a, #1e1b4b);
  box-shadow: 0 20px 40px rgba(15, 23, 42, 0.14);
}

.summary-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.summary-label {
  font-size: 12px;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.65);
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.summary-value {
  font-size: 18px;
  font-weight: 900;
  color: #fff;
}

.summary-value.success {
  color: #86efac;
}

.server-error {
  margin-top: 18px;
  padding: 14px 16px;
  border-radius: 16px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #b91c1c;
  font-size: 13px;
  font-weight: 700;
}

.form-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 14px;
  margin-top: 28px;
}

.btn {
  height: 52px;
  padding: 0 22px;
  border-radius: 16px;
  border: none;
  text-decoration: none;
  font-weight: 800;
  font-size: 14px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  cursor: pointer;
  transition: 0.2s ease;
}

.btn-light {
  background: rgba(255, 255, 255, 0.92);
  color: #154677;
  border: 1px solid #e5e7eb;
}

.btn-light:hover {
  background: #fff;
  transform: translateY(-1px);
}

.btn-primary {
  min-width: 180px;
  background: linear-gradient(135deg, #154677, #2ba6de);
  color: #fff;
  box-shadow: 0 18px 35px rgba(99, 102, 241, 0.25);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 24px 40px rgba(99, 102, 241, 0.32);
}

.btn-primary:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.btn-loader {
  width: 18px;
  height: 18px;
  border: 2px solid rgba(255, 255, 255, 0.45);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.75s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 920px) {
  .form-grid,
  .role-options,
  .summary-box {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .add-user-page {
    padding: 16px;
  }

  .form-card {
    padding: 20px;
    border-radius: 22px;
  }

  .page-header {
    align-items: stretch;
  }

  .back-btn {
    width: 100%;
    justify-content: center;
  }

  .form-actions {
    flex-direction: column-reverse;
    align-items: stretch;
  }

  .btn,
  .btn-primary,
  .btn-light {
    width: 100%;
  }
}
</style>
