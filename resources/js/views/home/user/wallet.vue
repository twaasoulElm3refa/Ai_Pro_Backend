<template>
    <div class="wallet-page">
        <!-- Loading State -->
        <div v-if="loading" class="loading-state">
            <div class="spinner"></div>
            <p>جاري التحميل...</p>
        </div>

        <!-- Wallet Content -->
        <div v-else-if="walletData" class="wallet-wrapper">
            <!-- Header -->
            <div class="wallet-header">
                <h2>محفظتي</h2>
                <p>إدارة رصيدك ومعاملاتك</p>
            </div>

            <!-- Balance Card -->
            <div class="balance-card">
                <div class="balance-label">الرصيد الحالي</div>
                <div class="balance-value">
                    {{ formatBalance(walletData.data.wallet.balance) }}
                    <span>EGP</span>
                </div>
                <div class="wallet-id">
                    {{ walletData.data.wallet.uuid.slice(0, 8) }}...
                </div>
            </div>

            <!-- User Info -->
            <div class="user-row">
                <div class="avatar">
                    <img v-if="walletData.data.image"
                        :src="getImageUrl(walletData.data.image)"
                        class="avatar-image" />
                    <span v-else>
                        {{ getInitials(walletData.data.name) }}
                    </span>
                </div>

                <div class="user-details">
                    <h3>{{ walletData.data.name }}</h3>
                    <p>{{ walletData.data.email }}</p>
                </div>

                <span class="role-badge">
                    {{ walletData.data.role }}
                </span>
            </div>

            <!-- Info Grid -->
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">رقم المحفظة</span>
                    <span class="info-value">{{ walletData.data.wallet.uuid }}...</span>
                </div>

                <div class="info-item">
                    <span class="info-label">تاريخ الإنشاء</span>
                    <span class="info-value">{{ formatDate(walletData.data.wallet.created_at) }}</span>
                </div>

                <div class="info-item">
                    <span class="info-label">آخر عملية</span>
                    <span class="info-value">{{ formatLastSeen(walletData.data.wallet.updated_at) }}</span>
                </div>

                <div class="info-item">
                    <span class="info-label">الحالة</span>
                    <span class="status-badge"
                        :class="{ active: walletData.data.wallet.is_active }">
                        {{ walletData.data.wallet.is_active ? 'نشطة' : 'غير نشطة' }}
                    </span>
                </div>
            </div>

            <!-- Actions -->
            <div class="actions">
                <button class="btn-charge"
                    @click="chargeWallet"
                    :disabled="isCharging">
                    {{ isCharging ? 'جاري...' : 'شحن الرصيد' }}
                </button>
            </div>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="error-state">
            <div class="error-icon">!</div>
            <h3>حدث خطأ</h3>
            <p>{{ error }}</p>
            <button @click="fetchWallet" class="retry-btn">
                إعادة المحاولة
            </button>
        </div>
    </div>
</template>

<script>
import walletService from '../../../services/profile/walletService';

export default {
    data() {
        return {
            walletData: null,
            loading: true,
            error: null,
            isCharging: false
        };
    },
    mounted() {
        this.fetchWallet();
    },
    methods: {
        async fetchWallet() {
            this.loading = true;
            this.error = null;

            try {
                const res = await walletService.getWallet();
                this.walletData = res;
            } catch (e) {
                this.error = e.response?.data?.message || 'فشل في تحميل البيانات';
            } finally {
                this.loading = false;
            }
        },

        getImageUrl(path) {
            if (!path) return '';
            if (path.startsWith('http')) return path;
            return `/storage/${path}`;
        },

        chargeWallet() {
            this.isCharging = true;
            setTimeout(() => {
                this.isCharging = false;
                alert('قريباً');
            }, 800);
        },

        formatBalance(b) {
            return new Intl.NumberFormat('ar-EG').format(b);
        },

        formatDate(d) {
            if (!d) return '—';
            return new Date(d).toLocaleDateString('ar-EG');
        },

        formatLastSeen(d) {
            if (!d) return '—';
            const diff = (Date.now() - new Date(d)) / 60000;

            if (diff < 1) return 'الآن';
            if (diff < 60) return `منذ ${Math.floor(diff)} دقيقة`;
            if (diff < 1440) return `منذ ${Math.floor(diff / 60)} ساعة`;

            return this.formatDate(d);
        },

        getInitials(name) {
            return name ? name[0].toUpperCase() : '?';
        }
    }
};
</script>

<style scoped>
@import url('https://fonts.cdnfonts.com/css/year-of-the-camel');

.wallet-page {
    padding: 16px;
    background: #fff;
    min-height: 70vh;
    font-family: 'Year of the Camel', sans-serif;
}

/* Loading */
.loading-state {
    text-align: center;
    padding: 40px;
}

.spinner {
    width: 28px;
    height: 28px;
    border: 2px solid #eee;
    border-top: 2px solid #074377;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    margin: auto;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Wrapper */
.wallet-wrapper {
    max-width: 600px;
    margin: auto;
}

/* Header */
.wallet-header {
    margin-bottom: 20px;
}

.wallet-header h2 {
    font-size: 20px;
    color: #074377;
}

.wallet-header p {
    font-size: 12px;
    color: #888;
}

/* Balance */
.balance-card {
    background: #074377;
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    margin-bottom: 16px;
}

.balance-label {
    font-size: 12px;
    color: #cfd8dc;
}

.balance-value {
    font-size: 30px;
    font-weight: bold;
    color: white;
}

.balance-value span {
    font-size: 12px;
    margin-right: 4px;
}

.wallet-id {
    font-size: 10px;
    color: #ccc;
}

/* User */
.user-row {
    background: #f7f9fb;
    border-radius: 12px;
    padding: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
}

.avatar {
    width: 42px;
    height: 42px;
    background: #074377;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.avatar-image {
    width: 100%;
    height: 100%;
    border-radius: 50%;
}

/* User text */
.user-details h3 {
    font-size: 14px;
    margin: 0;
}

.user-details p {
    font-size: 11px;
    color: #777;
}

/* Badge */
.role-badge {
    font-size: 10px;
    background: #e3eef5;
    color: #074377;
    padding: 3px 8px;
    border-radius: 10px;
}

/* Grid */
.info-grid {
    background: #f7f9fb;
    border-radius: 12px;
    padding: 12px;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-bottom: 16px;
}

.info-label {
    font-size: 10px;
    color: #888;
}

.info-value {
    font-size: 12px;
    color: #074377;
}

.status-badge {
    font-size: 10px;
    padding: 3px 8px;
    border-radius: 10px;
    background: #ddd;
}

.status-badge.active {
    background: #074377;
    color: white;
}

/* Button */
.btn-charge {
    width: 100%;
    background: #074377;
    color: white;
    border: none;
    border-radius: 10px;
    padding: 10px;
    font-size: 13px;
    cursor: pointer;
}

.btn-charge:disabled {
    opacity: 0.6;
}

/* Error */
.error-state {
    text-align: center;
    padding: 40px;
}
</style>
