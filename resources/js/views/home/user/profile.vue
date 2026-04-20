<template>
    <div :class="['min-h-screen py-10 px-4 transition-colors duration-300', isDark ? 'bg-slate-900' : 'bg-slate-100']">
        <div :class="['max-w-3xl mx-auto rounded-2xl shadow-2xl border p-6 transition-colors duration-300',
                      isDark ? 'bg-slate-950 text-white border-slate-800' : 'bg-white text-slate-900 border-slate-200']">

            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold"
                    :class="isDark ? 'text-emerald-400' : 'text-emerald-600'">
                    ملفي الشخصي
                </h1>
            </div>

            <!-- Tabs -->
            <div :class="['flex border-b mb-6', isDark ? 'border-slate-800' : 'border-slate-200']">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="currentTab = tab.id"
                    :class="[
                        'py-3 px-4 text-sm font-medium transition-colors',
                        currentTab === tab.id
                            ? isDark
                                ? 'border-b-2 border-emerald-500 text-emerald-400'
                                : 'border-b-2 border-emerald-600 text-emerald-600'
                            : isDark
                                ? 'text-slate-400 hover:text-white'
                                : 'text-slate-500 hover:text-slate-900'
                    ]">
                    {{ tab.label }}
                </button>
            </div>

            <!-- Tab 1: معلومات المستخدم -->
            <div v-if="currentTab === 1" class="space-y-3">
                <div class="flex items-center gap-4 mb-5">
                    <div class="w-14 h-14 rounded-full bg-emerald-500 flex items-center justify-center text-white text-xl font-bold shadow-md">
                        {{ avatarInitials }}
                    </div>
                    <div>
                        <p class="font-semibold text-base">{{ profile.user.name }}</p>
                        <p class="text-sm" :class="isDark ? 'text-emerald-400' : 'text-emerald-600'">
                            {{ profile.user.role }}
                        </p>
                    </div>
                </div>

                <div
                    v-for="row in infoRows"
                    :key="row.label"
                    :class="['flex items-center gap-3 text-sm px-4 py-3 rounded-xl transition-colors',
                             isDark ? 'bg-slate-900' : 'bg-slate-50']">
                    <span
                        :class="['font-medium min-w-[130px]',
                                 isDark ? 'text-slate-400' : 'text-slate-500']">
                        {{ row.label }}
                    </span>
                    <span :class="row.class || ''">{{ row.value }}</span>
                </div>
            </div>

            <!-- Tab 2: تعديل البيانات -->
            <div v-if="currentTab === 2" class="space-y-4">
                <div>
                    <label :class="['block text-sm mb-1', isDark ? 'text-slate-400' : 'text-slate-500']">الاسم</label>
                    <input
                        v-model="editData.name"
                        :class="inputClass"
                        placeholder="أدخل اسمك"
                    />
                </div>
                <div>
                    <label :class="['block text-sm mb-1', isDark ? 'text-slate-400' : 'text-slate-500']">البريد الإلكتروني</label>
                    <input
                        v-model="editData.email"
                        type="email"
                        :class="inputClass"
                        placeholder="example@email.com"
                    />
                </div>
                <button
                    @click="updateProfile"
                    class="bg-emerald-500 hover:bg-emerald-600 active:scale-95 transition text-white font-medium px-6 py-2.5 rounded-lg shadow-md">
                    حفظ التغييرات
                </button>
            </div>

            <!-- Tab 3: تحديث كلمة المرور -->
            <div v-if="currentTab === 3" class="space-y-4">
                <PasswordInput
                    label="كلمة المرور الحالية"
                    v-model="passwordData.current_password"
                    :isDark="isDark"
                />
                <PasswordInput
                    label="كلمة المرور الجديدة"
                    v-model="passwordData.new_password"
                    :isDark="isDark"
                />
                <PasswordInput
                    label="تأكيد كلمة المرور"
                    v-model="passwordData.confirm_password"
                    :isDark="isDark"
                />

                <button
                    @click="updatePassword"
                    class="bg-emerald-500 hover:bg-emerald-600 active:scale-95 transition text-white font-medium px-6 py-2.5 rounded-lg shadow-md">
                    تحديث كلمة المرور
                </button>
            </div>

            <!-- Status Message -->
            <transition name="fade">
                <div
                    v-if="statusMessage"
                    :class="['mt-6 p-3 rounded-lg text-sm font-medium flex items-center gap-2 transition-all',
                             statusIsError
                        ? 'bg-red-500/10 border border-red-500/30 text-red-400'
                        : 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400']">
                    <span>{{ statusIsError ? '✗' : '✓' }}</span>
                    {{ statusMessage }}
                </div>
            </transition>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from "vue";

export default {
    name: "UserProfile",

    components: {
        PasswordInput: {
            props: ["label", "modelValue", "isDark"],
            emits: ["update:modelValue"],
            data() {
                return { show: false };
            },
            computed: {
                inputClass() {
                    return [
                        "w-full border rounded-lg px-3 py-2 pr-10 focus:outline-none focus:border-emerald-500 transition-colors",
                        this.isDark
                            ? "bg-slate-900 border-slate-700 text-white placeholder-slate-500"
                            : "bg-slate-50 border-slate-300 text-slate-900 placeholder-slate-400",
                    ].join(" ");
                },
            },
            template: `
                <div>
                    <label :class="['block text-sm mb-1', isDark ? 'text-slate-400' : 'text-slate-500']">{{ label }}</label>
                    <div class="relative">
                        <input
                            :type="show ? 'text' : 'password'"
                            :value="modelValue"
                            @input="$emit('update:modelValue', $event.target.value)"
                            :class="inputClass"
                            placeholder="••••••••"
                        />
                        <button
                            type="button"
                            @click="show = !show"
                            class="absolute inset-y-0 right-3 flex items-center text-slate-400 hover:text-slate-300 transition-colors">
                            {{ show ? '🙈' : '👁️' }}
                        </button>
                    </div>
                </div>
            `,
        },
    },

    setup() {
        // ===================== Theme Management (مطبقة بنفس طريقة النافبار) =====================
        const isDark = ref(localStorage.getItem('theme') === 'dark' || localStorage.getItem('theme') === null);

        const handleStorageChange = (e) => {
            if (e.key === 'theme') {
                isDark.value = e.newValue === 'dark';
            }
        };

        // ===================== Profile Data =====================
        const profile = ref({
            user: {
                name: "أحمد حسن",
                email: "ahmed@example.com",
                role: "Admin",
                is_active: true,
                last_seen: "2024-04-20 09:41",
            },
        });

        const avatarInitials = computed(() =>
            (profile.value.user.name || "")
                .split(" ")
                .map((w) => w[0])
                .slice(0, 2)
                .join("")
                .toUpperCase()
        );

        const infoRows = computed(() => [
            { label: "الاسم", value: profile.value.user.name },
            { label: "البريد الإلكتروني", value: profile.value.user.email },
            { label: "الدور", value: profile.value.user.role },
            {
                label: "الحالة",
                value: profile.value.user.is_active ? "نشط ✓" : "غير نشط",
                class: profile.value.user.is_active
                    ? (isDark.value ? "text-emerald-400 font-semibold" : "text-emerald-600 font-semibold")
                    : "text-red-400 font-semibold",
            },
            { label: "آخر تسجيل دخول", value: profile.value.user.last_seen },
        ]);

        // ===================== Tabs =====================
        const tabs = [
            { id: 1, label: "معلومات المستخدم" },
            { id: 2, label: "تعديل البيانات" },
            { id: 3, label: "تحديث كلمة المرور" },
        ];
        const currentTab = ref(1);

        // ===================== Edit & Password Data =====================
        const editData = ref({ name: "", email: "" });
        const passwordData = ref({ current_password: "", new_password: "", confirm_password: "" });

        // ===================== Status =====================
        const statusMessage = ref("");
        const statusIsError = ref(false);
        let statusTimer = null;

        const showStatus = (msg, isError = false) => {
            clearTimeout(statusTimer);
            statusMessage.value = msg;
            statusIsError.value = isError;
            statusTimer = setTimeout(() => {
                statusMessage.value = "";
            }, 4000);
        };

        // ===================== Input Class (مطبقة حسب الثيم) =====================
        const inputClass = computed(() => [
            "w-full border rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500 transition-colors",
            isDark.value
                ? "bg-slate-900 border-slate-700 text-white placeholder-slate-500"
                : "bg-slate-50 border-slate-300 text-slate-900 placeholder-slate-400",
        ].join(" "));

        // ===================== API Calls =====================
        const fetchProfile = async () => {
            try {
                const token = localStorage.getItem("auth_token");
                if (!token) return;

                const res = await fetch("/api/v1/users/profile", {
                    headers: {
                        Authorization: `Bearer ${token}`,
                        Accept: "application/json"
                    },
                });

                if (res.ok) {
                    const data = await res.json();
                    if (data.data) {
                        profile.value = data.data;
                        editData.value.name = data.data.user.name || "";
                        editData.value.email = data.data.user.email || "";
                    }
                }
            } catch (err) {
                console.log("Profile fetch error:", err);
            }
        };

        const updateProfile = async () => {
            if (!editData.value.name || !editData.value.email) {
                showStatus("يرجى ملء جميع الحقول", true);
                return;
            }

            try {
                const token = localStorage.getItem("auth_token");
                await fetch("/api/v1/users/profile", {
                    method: "PUT",
                    headers: {
                        Authorization: `Bearer ${token}`,
                        "Content-Type": "application/json",
                        Accept: "application/json"
                    },
                    body: JSON.stringify(editData.value),
                });

                // تحديث البيانات محلياً
                profile.value.user.name = editData.value.name;
                profile.value.user.email = editData.value.email;
                showStatus("تم تحديث البيانات بنجاح ✓");
            } catch (err) {
                console.log("Update profile error:", err);
                showStatus("حدث خطأ أثناء التحديث", true);
            }
        };

        const updatePassword = async () => {
            if (!passwordData.value.current_password ||
                !passwordData.value.new_password ||
                !passwordData.value.confirm_password) {
                showStatus("يرجى ملء جميع الحقول", true);
                return;
            }

            if (passwordData.value.new_password !== passwordData.value.confirm_password) {
                showStatus("كلمات المرور غير متطابقة", true);
                return;
            }

            if (passwordData.value.new_password.length < 6) {
                showStatus("كلمة المرور يجب أن تكون 6 أحرف على الأقل", true);
                return;
            }

            try {
                const token = localStorage.getItem("auth_token");
                await fetch("/api/v1/users/password", {
                    method: "PUT",
                    headers: {
                        Authorization: `Bearer ${token}`,
                        "Content-Type": "application/json",
                        Accept: "application/json"
                    },
                    body: JSON.stringify(passwordData.value),
                });

                passwordData.value = { current_password: "", new_password: "", confirm_password: "" };
                showStatus("تم تحديث كلمة المرور بنجاح ✓");
            } catch (err) {
                console.log("Update password error:", err);
                showStatus("حدث خطأ أثناء تحديث كلمة المرور", true);
            }
        };

        // ===================== Lifecycle =====================
        onMounted(() => {
            window.addEventListener("storage", handleStorageChange);

            // تحميل البيانات عند فتح الصفحة
            fetchProfile();

            // الاستماع لحدث تسجيل الدخول من النافبار (نفس النظام في الـ navbar)
            window.addEventListener('login', fetchProfile);
        });

        onUnmounted(() => {
            window.removeEventListener("storage", handleStorageChange);
            window.removeEventListener('login', fetchProfile);
        });

        return {
            isDark,
            profile,
            avatarInitials,
            infoRows,
            tabs,
            currentTab,
            editData,
            passwordData,
            statusMessage,
            statusIsError,
            inputClass,
            updateProfile,
            updatePassword,
        };
    },
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
