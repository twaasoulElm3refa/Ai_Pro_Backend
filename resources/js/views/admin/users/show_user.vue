<template>
  <AdminLayout>
    <div class="show-user" dir="rtl">

      <!-- ── Back + Header ── -->
      <div class="top-bar" :class="{ loaded: !loading }">
        <button class="back-btn" @click="$router.back()">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <polyline points="15 18 9 12 15 6"/>
          </svg>
          رجوع
        </button>
        <div class="breadcrumb">
          <span>المستخدمون</span>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 18 9 12 15 6"/>
          </svg>
          <span class="bc-active">تفاصيل المستخدم</span>
        </div>
      </div>

      <!-- ── Loading ── -->
      <div v-if="loading" class="skeleton-layout">
        <div class="sk-profile-card">
          <div class="sk sk-avatar"></div>
          <div class="sk sk-line w60"></div>
          <div class="sk sk-line w40"></div>
          <div class="sk sk-line w50"></div>
        </div>
        <div class="sk-main">
          <div class="sk sk-block"></div>
          <div class="sk sk-block"></div>
        </div>
      </div>

      <!-- ── Main Layout ── -->
      <div v-else-if="user" class="layout">

        <!-- ══ LEFT: Profile Card ══ -->
        <aside class="profile-card" :style="{ animationDelay: '0ms' }">

          <!-- Avatar -->
          <div class="avatar-wrap">
            <img
              v-if="user.image"
              :src="`http://localhost:8000/storage/${user.image}`"
              :alt="user.name"
              class="avatar-img"
              @error="imgError = true"
            />
            <div v-else class="avatar-fallback" :style="{ background: avatarColor(user.name) }">
              {{ initials(user.name) }}
            </div>

            <!-- Status dot -->
            <span class="status-dot" :class="user.is_active ? 'dot-active' : 'dot-inactive'"></span>
          </div>

          <h2 class="profile-name">{{ user.name }}</h2>
          <span class="role-badge" :class="`role-${user.role}`">{{ roleLabel(user.role) }}</span>

          <!-- Quick info list -->
          <ul class="info-list">
            <li>
              <span class="info-icon">✉</span>
              <span class="info-val dir-ltr">{{ user.email }}</span>
            </li>
            <li>
              <span class="info-icon">📞</span>
              <span class="info-val dir-ltr">{{ user.phone || '—' }}</span>
            </li>
            <li>
              <span class="info-icon">🕐</span>
              <span class="info-val">{{ formatDate(user.last_seen) }}</span>
            </li>
            <li>
              <span class="info-icon">📅</span>
              <span class="info-val">{{ formatDate(user.created_at) }}</span>
            </li>
          </ul>

          <!-- Flags row -->
          <div class="flags-row">
            <div class="flag-item" :class="user.is_active ? 'flag-on' : 'flag-off'">
              <span class="flag-icon">{{ user.is_active ? '✓' : '✕' }}</span>
              <span>الحساب {{ user.is_active ? 'نشط' : 'معطل' }}</span>
            </div>
            <div class="flag-item" :class="user.is_verified ? 'flag-on' : 'flag-off'">
              <span class="flag-icon">{{ user.is_verified ? '✓' : '✕' }}</span>
              <span>{{ user.is_verified ? 'موثق' : 'غير موثق' }}</span>
            </div>
          </div>

          <!-- Wallet Card -->
          <div class="wallet-card">
            <div class="wallet-header">
              <span class="wallet-label">المحفظة</span>
              <span class="wallet-status" :class="user.wallet?.is_active ? 'ws-on' : 'ws-off'">
                {{ user.wallet?.is_active ? 'نشطة' : 'معطلة' }}
              </span>
            </div>
            <div class="wallet-balance">
              <span class="balance-num">{{ formatNum(user.wallet?.balance ?? 0) }}</span>
              <span class="balance-currency">نقطة</span>
            </div>
            <div class="wallet-uuid">{{ user.wallet?.uuid }}</div>
          </div>

        </aside>

        <!-- ══ RIGHT: Tabs ══ -->
        <main class="main-content">

          <!-- Tabs -->
          <div class="tabs">
            <button
              v-for="tab in tabs"
              :key="tab.key"
              class="tab-btn"
              :class="{ 'tab-active': activeTab === tab.key }"
              @click="activeTab = tab.key"
            >
              <span class="tab-icon">{{ tab.icon }}</span>
              {{ tab.label }}
              <span v-if="tab.count !== undefined" class="tab-count">{{ tab.count }}</span>
            </button>
          </div>

          <!-- ── Tab: Payments ── -->
          <div v-if="activeTab === 'payments'" class="tab-panel">
            <div v-if="user.payment.length === 0" class="empty-panel">
              <div class="empty-emoji">💳</div>
              <p>لا توجد مدفوعات مسجلة</p>
            </div>
            <div v-else class="payments-list">
              <div
                v-for="(pay, i) in user.payment"
                :key="pay.id"
                class="payment-item"
                :style="{ animationDelay: `${i * 60}ms` }"
              >
                <div class="pay-row">
                  <div class="pay-left">
                    <span class="pay-status" :class="`pay-${pay.status}`">
                      <span class="pay-dot"></span>
                      {{ payStatusLabel(pay.status) }}
                    </span>
                    <span class="pay-txn dir-ltr">{{ pay.transaction_id }}</span>
                  </div>
                  <div class="pay-right">
                    <span class="pay-amount">{{ pay.amount }} {{ pay.currency }}</span>
                    <span class="pay-points">+{{ formatNum(pay.points) }} نقطة</span>
                  </div>
                </div>
                <div class="pay-meta">
                  <span>طلب: {{ pay.order_id }}</span>
                  <span v-if="pay.paid_at">{{ formatDate(pay.paid_at) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- ── Tab: Wallet Transactions ── -->
          <div v-if="activeTab === 'transactions'" class="tab-panel">
            <div v-if="user.wallet_transaction.length === 0" class="empty-panel">
              <div class="empty-emoji">🔄</div>
              <p>لا توجد معاملات في المحفظة</p>
            </div>
            <div v-else class="tx-list">
              <div
                v-for="(tx, i) in user.wallet_transaction"
                :key="tx.id"
                class="tx-item"
                :class="`tx-${tx.type}`"
                :style="{ animationDelay: `${i * 60}ms` }"
              >
                <div class="tx-icon-wrap">
                  <span class="tx-type-icon">{{ tx.type === 'credit' ? '↑' : '↓' }}</span>
                </div>
                <div class="tx-body">
                  <div class="tx-desc">{{ tx.description || 'بدون وصف' }}</div>
                  <div class="tx-meta">
                    <span class="tx-date">{{ formatDate(tx.created_at) }}</span>
                    <span class="tx-balance">{{ tx.balance_before }} ← {{ tx.balance_after }}</span>
                  </div>
                </div>
                <div class="tx-amount" :class="tx.type === 'credit' ? 'tx-credit' : 'tx-debit'">
                  {{ tx.type === 'credit' ? '+' : '-' }}{{ formatNum(tx.points) }}
                </div>
              </div>
            </div>
          </div>

        </main>
      </div>

      <!-- ── Not Found ── -->
      <div v-else class="not-found">
        <div class="nf-icon">🔍</div>
        <p>المستخدم غير موجود</p>
        <button class="back-btn" @click="$router.back()">رجوع</button>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute }                 from "vue-router";
import AdminLayout                  from "@/layouts/AdminLayout.vue";
import userService from "@/services/admin/users/userService";

// ── state ──────────────────────────────────────────────────
const route      = useRoute();
const user       = ref(null);
const loading    = ref(false);
const imgError   = ref(false);
const activeTab  = ref("payments");

// ── tabs ───────────────────────────────────────────────────
const tabs = computed(() => [
  {
    key:   "payments",
    label: "المدفوعات",
    icon:  "💳",
    count: user.value?.payment?.length ?? 0,
  },
  {
    key:   "transactions",
    label: "معاملات المحفظة",
    icon:  "🔄",
    count: user.value?.wallet_transaction?.length ?? 0,
  },
]);

// ── fetch ──────────────────────────────────────────────────
onMounted(async () => {
  loading.value = true;
  try {
    const res  = await userService.getUser(route.params.id);
    user.value = res.data;
  } finally {
    loading.value = false;
  }
});

// ── helpers ────────────────────────────────────────────────
function initials(name = "") {
  return name.split(" ").slice(0, 2).map((w) => w[0]).join("").toUpperCase();
}
const palette = ["#154677","#2ba6de","#2ba6de","#2ba6de","#2ba6de","#2ba6de","#2ba6de","#2ba6de"];
function avatarColor(name = "") {
  let n = 0; for (const c of name) n += c.charCodeAt(0);
  return palette[n % palette.length];
}
function formatDate(val) {
  if (!val) return "—";
  return new Date(val).toLocaleString("ar-EG", {
    year: "numeric", month: "short", day: "numeric",
    hour: "2-digit", minute: "2-digit",
  });
}
function formatNum(val) {
  return Number(val ?? 0).toLocaleString("ar-EG");
}
function roleLabel(role) {
  const map = { admin: "مدير", user: "مستخدم", moderator: "مشرف" };
  return map[role] ?? role;
}
function payStatusLabel(s) {
  const map = { success: "ناجحة", pending: "قيد الانتظار", failed: "فاشلة" };
  return map[s] ?? s;
}
</script>

<style scoped>

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:        #f0f2f9;
  --card:      #ffffff;
  --border:    #e4e7f2;
  --text:      #1a1d2e;
  --muted:     #8a90a8;
  --accent:    #2ba6de;
  --accent2:   #154677;
  --green:     #2ba6de;
  --amber:     #2ba6de;
  --red:       #154677;
  --radius:    18px;
  --shadow:    0 4px 30px rgba(0,0,0,.07);
}

.show-user {
  padding: 1.75rem 2rem;
  background: var(--bg);
  min-height: 100vh;
  font-family: 'Cairo', sans-serif;
  direction: rtl;
}

/* ── Top bar ── */
.top-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.75rem;
  opacity: 0;
  transform: translateY(-6px);
  transition: opacity .4s, transform .4s;
}
.top-bar.loaded { opacity: 1; transform: none; }
.back-btn {
  display: inline-flex; align-items: center; gap: .4rem;
  padding: .5rem 1.1rem;
  border-radius: 12px;
  border: 1px solid var(--border);
  background: var(--card);
  color: var(--text);
  font-size: .85rem;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
  transition: background .15s, box-shadow .15s;
  box-shadow: 0 2px 8px rgba(0,0,0,.05);
}
.back-btn:hover { background: #f3f4f6; box-shadow: 0 4px 14px rgba(0,0,0,.08); }
.breadcrumb {
  display: flex; align-items: center; gap: .4rem;
  font-size: .8rem; color: var(--muted); font-weight: 600;
}
.bc-active { color: var(--accent); }

/* ── Layout ── */
.layout {
  display: grid;
  grid-template-columns: 310px 1fr;
  gap: 1.5rem;
  align-items: start;
}
@media (max-width: 900px) {
  .layout { grid-template-columns: 1fr; }
}

/* ── Profile Card ── */
.profile-card {
  background: var(--card);
  border-radius: var(--radius);
  border: 1px solid var(--border);
  box-shadow: var(--shadow);
  padding: 2rem 1.5rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  animation: fadeUp .4s ease both;
}
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* Avatar */
.avatar-wrap { position: relative; }
.avatar-img, .avatar-fallback {
  width: 88px; height: 88px;
  border-radius: 24px;
  object-fit: cover;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.4rem; font-weight: 900; color: #fff;
  border: 3px solid var(--border);
}
.status-dot {
  position: absolute;
  bottom: 4px; left: 4px;
  width: 14px; height: 14px;
  border-radius: 50%;
  border: 2px solid var(--card);
}
.dot-active   { background: var(--green); }
.dot-inactive { background: var(--muted); }

.profile-name {
  font-size: 1.15rem; font-weight: 800;
  color: var(--text); text-align: center;
}

/* Role badge */
.role-badge {
  padding: .25rem .85rem;
  border-radius: 999px;
  font-size: .75rem; font-weight: 800;
  letter-spacing: .04em;
}
.role-admin     { background: #ede9fe; color: #6d28d9; }
.role-user      { background: #dbeafe; color: #154677; }
.role-moderator { background: #d1fae5; color: #065f46; }

/* Info list */
.info-list {
  width: 100%;
  list-style: none;
  display: flex; flex-direction: column; gap: .55rem;
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
  padding: 1rem 0;
}
.info-list li {
  display: flex; align-items: center; gap: .6rem;
  font-size: .82rem; color: var(--text);
}
.info-icon { font-size: .95rem; }
.info-val { color: var(--muted); font-weight: 600; word-break: break-all; }
.dir-ltr { direction: ltr; text-align: right; }

/* Flags */
.flags-row { display: flex; gap: .6rem; flex-wrap: wrap; justify-content: center; }
.flag-item {
  display: flex; align-items: center; gap: .35rem;
  padding: .3rem .75rem;
  border-radius: 10px;
  font-size: .75rem; font-weight: 700;
}
.flag-on  { background: #d1fae5; color: #065f46; }
.flag-off { background: #fee2e2; color: #154677; }

/* Wallet */
.wallet-card {
  width: 100%;
  background: linear-gradient(135deg, #154677 0%, #2ba6de 100%);
  border-radius: 16px;
  padding: 1.25rem 1.25rem 1rem;
  color: #fff;
}
.wallet-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: .75rem; }
.wallet-label { font-size: .8rem; font-weight: 700; opacity: .8; }
.wallet-status {
  font-size: .72rem; font-weight: 800;
  padding: .2rem .6rem; border-radius: 999px;
}
.ws-on  { background: rgba(255,255,255,.25); }
.ws-off { background: rgba(255,100,100,.3); }
.wallet-balance { display: flex; align-items: baseline; gap: .5rem; }
.balance-num { font-size: 2rem; font-weight: 900; line-height: 1; }
.balance-currency { font-size: .85rem; opacity: .7; font-weight: 600; }
.wallet-uuid {
  margin-top: .75rem;
  font-size: .65rem;
  opacity: .45;
  font-family: monospace;
  word-break: break-all;
  direction: ltr;
  text-align: right;
}

/* ── Main Content ── */
.main-content {
  display: flex; flex-direction: column; gap: 1.25rem;
  animation: fadeUp .4s ease .1s both;
}

/* Tabs */
.tabs {
  display: flex; gap: .5rem;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: .5rem;
  box-shadow: var(--shadow);
  flex-wrap: wrap;
}
.tab-btn {
  flex: 1;
  display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
  padding: .65rem 1.25rem;
  border-radius: 10px;
  border: none; background: transparent;
  font-size: .85rem; font-weight: 700;
  color: 000000;
  cursor: pointer;
  font-family: inherit;
  transition: background .15s, color .15s;
  white-space: nowrap;
}
.tab-btn:hover { background: #f3f4f6; color: var(--text); }
.tab-active {
  background: var(--accent) !important;
  color: #000000 !important;
  box-shadow: 0 4px 12px rgba(99,102,241,.35);
}
.tab-count {
  background: rgba(255,255,255,.25);
  border-radius: 999px;
  padding: .1rem .45rem;
  font-size: .72rem;
  font-weight: 800;
}
.tab-btn:not(.tab-active) .tab-count {
  background: #e8eaf2; color: var(--muted);
}

/* Tab panel */
.tab-panel {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 1.5rem;
  min-height: 280px;
}

/* Empty */
.empty-panel {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; gap: .75rem;
  padding: 3rem 1rem;
  color: var(--muted); font-size: .9rem; font-weight: 600;
}
.empty-emoji { font-size: 2.5rem; }

/* ── Payments ── */
.payments-list { display: flex; flex-direction: column; gap: .85rem; }
.payment-item {
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 1rem 1.25rem;
  background: #fafbff;
  animation: fadeUp .35s ease both;
  transition: box-shadow .15s;
}
.payment-item:hover { box-shadow: 0 4px 18px rgba(0,0,0,.07); }
.pay-row {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: .5rem; margin-bottom: .5rem;
}
.pay-left { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }
.pay-status {
  display: inline-flex; align-items: center; gap: .4rem;
  padding: .25rem .75rem; border-radius: 999px;
  font-size: .75rem; font-weight: 800;
}
.pay-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.pay-success { background: #d1fae5; color: #059669; }
.pay-pending { background: #fef3c7; color: #b45309; }
.pay-failed  { background: #fee2e2; color: #154677; }
.pay-txn { font-size: .75rem; color: var(--muted); font-family: monospace; direction: ltr; }
.pay-right { display: flex; flex-direction: column; align-items: flex-start; gap: .1rem; }
.pay-amount { font-size: 1rem; font-weight: 800; color: var(--text); }
.pay-points { font-size: .75rem; color: var(--green); font-weight: 700; }
.pay-meta {
  display: flex; gap: 1.25rem; flex-wrap: wrap;
  font-size: .75rem; color: var(--muted); font-weight: 600;
  border-top: 1px solid var(--border); padding-top: .5rem;
}

/* ── Wallet Transactions ── */
.tx-list { display: flex; flex-direction: column; gap: .75rem; }
.tx-item {
  display: flex; align-items: center; gap: 1rem;
  padding: .9rem 1.1rem;
  border-radius: 14px;
  border: 1px solid var(--border);
  background: #fafbff;
  animation: fadeUp .35s ease both;
  transition: box-shadow .15s;
}
.tx-item:hover { box-shadow: 0 4px 18px rgba(0,0,0,.07); }
.tx-icon-wrap {
  width: 38px; height: 38px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; font-size: 1rem; font-weight: 900;
}
.tx-credit .tx-icon-wrap { background: #d1fae5; color: #059669; }
.tx-debit  .tx-icon-wrap { background: #fee2e2; color: #154677; }
.tx-body { flex: 1; }
.tx-desc { font-size: .88rem; font-weight: 700; color: var(--text); margin-bottom: .2rem; }
.tx-meta { display: flex; gap: .85rem; flex-wrap: wrap; }
.tx-date  { font-size: .75rem; color: var(--muted); font-weight: 600; }
.tx-balance { font-size: .72rem; color: var(--muted); font-family: monospace; direction: ltr; }
.tx-amount { font-size: 1rem; font-weight: 900; }
.tx-credit .tx-amount { color: var(--green); }
.tx-debit  .tx-amount { color: var(--red); }

/* ── Skeletons ── */
.skeleton-layout {
  display: grid; grid-template-columns: 310px 1fr; gap: 1.5rem;
}
.sk-profile-card {
  background: var(--card); border-radius: var(--radius);
  border: 1px solid var(--border);
  padding: 2rem 1.5rem;
  display: flex; flex-direction: column; align-items: center; gap: 1rem;
}
.sk-main { display: flex; flex-direction: column; gap: 1rem; }
.sk {
  background: #e8eaf2; border-radius: 10px;
  animation: shimmer 1.4s infinite;
}
@keyframes shimmer {
  0%, 100% { opacity: 1; } 50% { opacity: .4; }
}
.sk-avatar   { width: 88px; height: 88px; border-radius: 24px; }
.sk-line     { height: 13px; }
.sk-block    { height: 200px; border-radius: var(--radius); }
.w60 { width: 60%; } .w40 { width: 40%; } .w50 { width: 50%; }

/* ── Not Found ── */
.not-found {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; gap: 1rem; padding: 5rem 2rem;
  color: var(--muted); font-size: .95rem; font-weight: 600;
}
.nf-icon { font-size: 3rem; }
</style>


