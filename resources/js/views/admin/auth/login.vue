<template>
  <div class="login-wrapper">
    <div class="login-card">

      <div class="brand">
        <div class="brand-icon">
          <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
            <rect width="28" height="28" rx="8" fill="#1a1a2e"/>
            <path d="M7 14L14 7L21 14L14 21L7 14Z" fill="#e8c97e" opacity="0.9"/>
            <circle cx="14" cy="14" r="3" fill="#1a1a2e"/>
          </svg>
        </div>
        <h1 class="brand-title">لوحة التحكم</h1>
        <p class="brand-sub">تسجيل دخول المشرف</p>
      </div>

      <form @submit.prevent="handleLogin" class="form" novalidate>

        <div class="field-group">
          <label for="email" class="field-label">البريد الإلكتروني</label>
          <div class="input-wrap" :class="{ focused: focusedField === 'email', filled: form.email }">
            <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path d="M2 4.5C2 3.67 2.67 3 3.5 3h9c.83 0 1.5.67 1.5 1.5v7c0 .83-.67 1.5-1.5 1.5h-9C2.67 12 2 11.33 2 10.5v-7Z" stroke="currentColor" stroke-width="1.1"/>
              <path d="M2 4.5L8 8.5l6-4" stroke="currentColor" stroke-width="1.1"/>
            </svg>
            <input
              id="email"
              v-model="form.email"
              type="email"
              placeholder="admin@example.com"
              autocomplete="email"
              dir="ltr"
              @focus="focusedField = 'email'"
              @blur="focusedField = null"
              :disabled="loading"
            />
          </div>
        </div>

        <div class="field-group">
          <label for="password" class="field-label">كلمة المرور</label>
          <div class="input-wrap" :class="{ focused: focusedField === 'password', filled: form.password }">
            <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
              <rect x="2.5" y="7" width="11" height="7.5" rx="1.5" stroke="currentColor" stroke-width="1.1"/>
              <path d="M5 7V5a3 3 0 016 0v2" stroke="currentColor" stroke-width="1.1"/>
              <circle cx="8" cy="10.5" r="1" fill="currentColor"/>
            </svg>
            <input
              id="password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              placeholder="••••••••"
              autocomplete="current-password"
              dir="ltr"
              @focus="focusedField = 'password'"
              @blur="focusedField = null"
              :disabled="loading"
            />
            <button type="button" class="toggle-pass" @click="showPassword = !showPassword" tabindex="-1">
              <svg v-if="!showPassword" width="16" height="16" viewBox="0 0 16 16" fill="none">
                <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5Z" stroke="currentColor" stroke-width="1.1"/>
                <circle cx="8" cy="8" r="2" stroke="currentColor" stroke-width="1.1"/>
              </svg>
              <svg v-else width="16" height="16" viewBox="0 0 16 16" fill="none">
                <path d="M2 2l12 12M6.5 6.7A2 2 0 0010 10M4 4.4C2.4 5.5 1 8 1 8s2.5 5 7 5c1.4 0 2.7-.4 3.7-1M7 3.1C7.3 3 7.7 3 8 3c4.5 0 7 5 7 5s-.6 1.2-1.7 2.3" stroke="currentColor" stroke-width="1.1"/>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="submit-btn" :disabled="loading || !form.email || !form.password">
          <span v-if="!loading">تسجيل الدخول</span>
          <span v-else class="spinner-wrap">
            <span class="spinner"></span>
            جارٍ التحقق...
          </span>
        </button>

      </form>

    </div>

    <div class="bg-orb orb-1"></div>
    <div class="bg-orb orb-2"></div>
  </div>
</template>

<script setup>
import { ref, reactive } from "vue";
import { useRouter } from "vue-router";
import adminAuthService from "@/services/admin/auth/authService";

const router = useRouter();

const form = reactive({ email: "", password: "" });
const loading = ref(false);
const showPassword = ref(false);
const focusedField = ref(null);

async function handleLogin() {
  if (loading.value) return;
  loading.value = true;

  try {
    await adminAuthService.login(form.email, form.password);
    await router.push("/admin");
  } catch {
    // Errors handled by axios interceptor (toastr)
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.login-wrapper {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #0d0d1a;
  font-family: 'Cairo', 'Segoe UI', sans-serif;
  direction: rtl;
  position: relative;
  overflow: hidden;
}

.bg-orb {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
}
.orb-1 {
  width: 500px;
  height: 500px;
  background: radial-gradient(circle, rgba(232, 201, 126, 0.06) 0%, transparent 70%);
  top: -150px;
  right: -100px;
}
.orb-2 {
  width: 400px;
  height: 400px;
  background: radial-gradient(circle, rgba(99, 102, 241, 0.07) 0%, transparent 70%);
  bottom: -100px;
  left: -80px;
}

.login-card {
  position: relative;
  z-index: 1;
  background: #13132b;
  border: 1px solid rgba(255,255,255,0.07);
  border-radius: 20px;
  padding: 48px 40px;
  width: 100%;
  max-width: 420px;
  box-shadow: 0 40px 80px rgba(0, 0, 0, 0.5);
}

.brand {
  text-align: center;
  margin-bottom: 36px;
}
.brand-icon {
  display: inline-flex;
  margin-bottom: 16px;
}
.brand-title {
  font-size: 22px;
  font-weight: 700;
  color: #f0ece0;
  margin: 0 0 6px;
  letter-spacing: 0.5px;
}
.brand-sub {
  font-size: 13px;
  color: rgba(240, 236, 224, 0.4);
  margin: 0;
}

.form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.field-label {
  font-size: 13px;
  font-weight: 600;
  color: rgba(240, 236, 224, 0.6);
  letter-spacing: 0.3px;
}

.input-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 12px;
  padding: 0 14px;
  transition: border-color 0.2s, background 0.2s;
}
.input-wrap.focused {
  border-color: rgba(232, 201, 126, 0.5);
  background: rgba(232, 201, 126, 0.04);
}
.input-wrap.filled:not(.focused) {
  border-color: rgba(255,255,255,0.13);
}
.input-icon {
  color: rgba(240, 236, 224, 0.3);
  flex-shrink: 0;
  transition: color 0.2s;
}
.focused .input-icon {
  color: rgba(232, 201, 126, 0.7);
}

.input-wrap input {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  color: #f0ece0;
  font-size: 14px;
  font-family: inherit;
  padding: 14px 0;
  min-width: 0;
}
.input-wrap input::placeholder {
  color: rgba(240, 236, 224, 0.2);
}
.input-wrap input:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.toggle-pass {
  background: none;
  border: none;
  cursor: pointer;
  color: rgba(240, 236, 224, 0.3);
  padding: 0;
  display: flex;
  align-items: center;
  transition: color 0.2s;
  flex-shrink: 0;
}
.toggle-pass:hover { color: rgba(240, 236, 224, 0.6); }

.submit-btn {
  margin-top: 8px;
  width: 100%;
  padding: 15px;
  background: #e8c97e;
  color: #1a1a2e;
  border: none;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  transition: opacity 0.2s, transform 0.15s;
  letter-spacing: 0.3px;
}
.submit-btn:hover:not(:disabled) {
  opacity: 0.9;
  transform: translateY(-1px);
}
.submit-btn:active:not(:disabled) {
  transform: translateY(0);
}
.submit-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
  transform: none;
}

.spinner-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}
.spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(26,26,46,0.3);
  border-top-color: #1a1a2e;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 480px) {
  .login-card {
    margin: 16px;
    padding: 36px 24px;
  }
}
</style>
