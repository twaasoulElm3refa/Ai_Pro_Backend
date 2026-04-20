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

            <!-- User Info Row -->
            <div class="user-row">
                <div class="avatar">
                    <!-- عرض الصورة إذا وجدت، وإلا عرض الحرف الأول -->
                    <img v-if="walletData.data.image" :src="getImageUrl(walletData.data.image)" alt="صورة المستخدم"
                        class="avatar-image" />
                    <span v-else>
                        {{ getInitials(walletData.data.name) }}
                    </span>
                </div>
                <div class="user-details">
                    <h3>{{ walletData.data.name }}</h3>
                    <p>{{ walletData.data.email }}</p>
                </div>
                <span class="role-badge">{{ walletData.data.role }}</span>
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
                    <span class="info-label">آخر عمليه شحن</span>
                    <span class="info-value">{{ formatLastSeen(walletData.data.wallet.updated_at) }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">الحالة</span>
                    <span class="status-badge" :class="{ active: walletData.data.wallet.is_active }">
                        {{ walletData.data.wallet.is_active ? 'نشطة' : 'غير نشطة' }}
                    </span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="actions">
                <button class="btn-charge" @click="chargeWallet" :disabled="isCharging">
                    {{ isCharging ? 'جاري الشحن...' : 'شحن الرصيد' }}
                </button>
            </div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="error-state">
            <div class="error-icon">!</div>
            <h3>حدث خطأ</h3>
            <p>{{ error }}</p>
            <button @click="fetchWallet" class="retry-btn">إعادة المحاولة</button>
        </div>
    </div>
</template>

<script>
import walletService from '../../../services/profile/walletService';

export default {
    name: 'Wallet',
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
                const response = await walletService.getWallet();
                this.walletData = response;
            } catch (error) {
                console.error('Error fetching wallet:', error);
                this.error = error.response?.data?.message || 'فشل في تحميل بيانات المحفظة';
            } finally {
                this.loading = false;
            }
        },
        getImageUrl(imagePath) {
            if (!imagePath) return '';
            if (imagePath.startsWith('http')) {
                return imagePath;
            }
            if (imagePath.startsWith('/storage') || imagePath.startsWith('storage')) {
                return imagePath;
            }
            return `/storage/${imagePath}`;
        },

        chargeWallet() {
            this.isCharging = true;
            setTimeout(() => {
                this.isCharging = false;
                alert('هذه الميزة غير متاحة حالياً - قريباً إن شاء الله');
            }, 1000);
        },

        formatBalance(balance) {
            return new Intl.NumberFormat('ar-EG').format(balance);
        },

        formatDate(date) {
            if (!date) return '—';
            return new Date(date).toLocaleDateString('ar-EG');
        },

        formatLastSeen(lastSeen) {
            if (!lastSeen) return '—';
            const now = new Date();
            const seen = new Date(lastSeen);
            const diff = Math.floor((now - seen) / 1000 / 60);

            if (diff < 1) return 'الآن';
            if (diff < 60) return `منذ ${diff} دقيقة`;
            if (diff < 1440) return `منذ ${Math.floor(diff / 60)} ساعة`;
            return this.formatDate(lastSeen);
        },

        getInitials(name) {
            return name ? name.charAt(0).toUpperCase() : '?';
        }
    }
};
</script>

<style scoped>
.wallet-page {
    padding: 24px;
    background: #ffffff;
    min-height: 100vh;
}

/* Loading State */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    color: #666;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #f0f0f0;
    border-top: 3px solid #1a1a1a;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-bottom: 16px;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }

    100% {
        transform: rotate(360deg);
    }
}

/* Wallet Wrapper */
.wallet-wrapper {
    max-width: 800px;
    margin: 0 auto;
}

/* Header */
.wallet-header {
    margin-bottom: 32px;
}

.wallet-header h2 {
    font-size: 28px;
    font-weight: 600;
    color: #1a1a1a;
    margin: 0 0 8px 0;
}

.wallet-header p {
    font-size: 14px;
    color: #888;
    margin: 0;
}

/* Balance Card */
.balance-card {
    background: #1a1a1a;
    border-radius: 24px;
    padding: 32px;
    margin-bottom: 24px;
    text-align: center;
}

.balance-label {
    font-size: 14px;
    color: #aaa;
    margin-bottom: 12px;
    letter-spacing: 0.5px;
}

.balance-value {
    font-size: 48px;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 12px;
}

.balance-value span {
    font-size: 16px;
    font-weight: 400;
    color: #aaa;
    margin-right: 8px;
}

.wallet-id {
    font-size: 12px;
    color: #888;
    font-family: monospace;
}

/* User Row */
.user-row {
    background: #f8f8f8;
    border-radius: 16px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}

.avatar {
    width: 56px;
    height: 56px;
    background: #1a1a1a;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    font-weight: 600;
    color: white;
}

.user-details {
    flex: 1;
}

.user-details h3 {
    font-size: 18px;
    font-weight: 600;
    color: #1a1a1a;
    margin: 0 0 4px 0;
}

.user-details p {
    font-size: 13px;
    color: #888;
    margin: 0;
}

.role-badge {
    background: #e8e8e8;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    color: #1a1a1a;
    text-transform: capitalize;
}

/* Info Grid */
.info-grid {
    background: #f8f8f8;
    border-radius: 16px;
    padding: 20px;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.info-label {
    font-size: 12px;
    color: #888;
    letter-spacing: 0.3px;
}

.info-value {
    font-size: 14px;
    font-weight: 500;
    color: #1a1a1a;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    background: #e8e8e8;
    color: #666;
}

.status-badge.active {
    background: #1a1a1a;
    color: white;
}

/* Actions */
.actions {
    display: flex;
    gap: 12px;
}

.btn-charge {
    flex: 1;
    background: #1a1a1a;
    color: white;
    border: none;
    border-radius: 12px;
    padding: 14px 24px;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-charge:hover:not(:disabled) {
    background: #333;
    transform: translateY(-1px);
}

.btn-charge:active:not(:disabled) {
    transform: translateY(0);
}

.btn-charge:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Error State */
.error-state {
    text-align: center;
    padding: 60px 20px;
    max-width: 400px;
    margin: 0 auto;
}

.error-icon {
    width: 56px;
    height: 56px;
    background: #f5f5f5;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: bold;
    color: #e53935;
    margin: 0 auto 20px;
}

.error-state h3 {
    font-size: 20px;
    color: #1a1a1a;
    margin: 0 0 8px 0;
}

.error-state p {
    color: #888;
    margin: 0 0 24px 0;
}

.retry-btn {
    background: #1a1a1a;
    color: white;
    border: none;
    border-radius: 10px;
    padding: 10px 24px;
    font-size: 14px;
    cursor: pointer;
    transition: background 0.2s;
}

.retry-btn:hover {
    background: #333;
}

/* Responsive */
@media (max-width: 600px) {
    .wallet-page {
        padding: 16px;
    }

    .balance-value {
        font-size: 36px;
    }

    .info-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .user-row {
        flex-wrap: wrap;
    }

    .role-badge {
        margin-right: auto;
    }
}
</style>
