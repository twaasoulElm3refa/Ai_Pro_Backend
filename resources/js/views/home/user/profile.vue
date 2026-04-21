<template>
    <div :class="['min-h-screen py-10 px-4 transition-colors duration-300', isDark ? 'bg-slate-900' : 'bg-slate-100']">
        <div :class="['max-w-3xl mx-auto rounded-2xl shadow-2xl border p-6 transition-colors duration-300',
            isDark ? 'bg-slate-950 text-white border-slate-800' : 'bg-white text-slate-900 border-slate-200']">

            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold" :class="isDark ? 'text-emerald-400' : 'text-emerald-600'">
                    ملفي الشخصي
                </h1>
            </div>

            <!-- Tabs -->
            <div :class="['flex border-b mb-6', isDark ? 'border-slate-800' : 'border-slate-200']">
                <button v-for="tab in tabs" :key="tab.id" @click="currentTab = tab.id" :class="[
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
                    <!-- Avatar: صورة أو أحرف -->
                    <div class="w-14 h-14 rounded-full overflow-hidden shadow-md flex-shrink-0">
                        <img v-if="profile.user.image" :src="getImageUrl(profile.user.image)" alt="avatar"
                            class="w-full h-full object-cover" />
                        <div v-else
                            class="w-full h-full bg-emerald-500 flex items-center justify-center text-white text-xl font-bold">
                            {{ avatarInitials }}
                        </div>
                    </div>
                    <div>
                        <p class="font-semibold text-base">{{ profile.user.name }}</p>
                        <p class="text-sm" :class="isDark ? 'text-emerald-400' : 'text-emerald-600'">
                            {{ profile.user.role }}
                        </p>
                    </div>
                </div>

                <div v-for="row in infoRows" :key="row.label" :class="['flex items-center gap-3 text-sm px-4 py-3 rounded-xl transition-colors',
                    isDark ? 'bg-slate-900' : 'bg-slate-50']">
                    <span :class="['font-medium min-w-[130px]', isDark ? 'text-slate-400' : 'text-slate-500']">
                        {{ row.label }}
                    </span>
                    <span :class="row.class || ''">{{ row.value || '—' }}</span>
                </div>
            </div>

            <!-- Tab 2: تعديل البيانات -->
            <div v-if="currentTab === 2" class="space-y-4">

                <div>
                    <label :class="['block text-sm mb-1', isDark ? 'text-slate-400' : 'text-slate-500']">الاسم</label>
                    <input v-model="editData.name" :class="inputClass" placeholder="أدخل اسمك" />
                </div>

                <div>
                    <label :class="['block text-sm mb-1', isDark ? 'text-slate-400' : 'text-slate-500']">البريد
                        الإلكتروني</label>
                    <input v-model="editData.email" type="email" :class="inputClass" placeholder="example@email.com" />
                </div>

                <div>
                    <label :class="['block text-sm mb-1', isDark ? 'text-slate-400' : 'text-slate-500']">رقم
                        الهاتف</label>
                    <input v-model="editData.phone" type="tel" :class="inputClass" placeholder="+201xxxxxxxxx" />
                </div>

                <!-- Image Upload -->
                <div>
                    <label :class="['block text-sm mb-1', isDark ? 'text-slate-400' : 'text-slate-500']">الصورة
                        الشخصية</label>
                    <div class="flex items-center gap-4">
                        <!-- Preview -->
                        <div class="w-14 h-14 rounded-full overflow-hidden flex-shrink-0 shadow">

                            <img v-if="imagePreview || profile.user.image"
                                :src="imagePreview || getImageUrl(profile.user.image)"
                                class="w-full h-full object-cover" alt="preview" />
                            <div v-else
                                class="w-full h-full bg-emerald-500 flex items-center justify-center text-white font-bold text-lg">
                                {{ avatarInitials }}
                            </div>
                        </div>
                        <label :class="['cursor-pointer text-sm px-4 py-2 rounded-lg border transition',
                            isDark
                                ? 'border-slate-600 text-slate-300 hover:border-emerald-500 hover:text-emerald-400'
                                : 'border-slate-300 text-slate-600 hover:border-emerald-500 hover:text-emerald-600']">
                            اختر صورة
                            <input type="file" accept="image/*" class="hidden" @change="handleImageUpload" />
                        </label>
                        <span v-if="editData.image" class="text-xs text-emerald-400 truncate max-w-[140px]">
                            {{ editData.image.name }}
                        </span>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button @click="updateProfile" :disabled="profileLoading"
                        class="bg-emerald-500 hover:bg-emerald-600 active:scale-95 transition text-white font-medium px-6 py-2.5 rounded shadow-md disabled:opacity-60">
                        {{ profileLoading ? 'جاري الحفظ...' : 'حفظ التغييرات' }}
                    </button>

                    <button @click="deleteProfile" :disabled="profileLoading"
                        class="bg-red-500 hover:bg-red-700 active:scale-95 transition text-white font-medium px-6 py-2.5 rounded shadow-md disabled:opacity-60">
                        {{ profileLoading ? 'جاري الحذف...' : 'حذف الحساب' }}
                    </button>
                </div>
            </div>

            <!-- Tab 3: تحديث كلمة المرور -->
            <div v-if="currentTab === 3" class="space-y-4">
                <PasswordInput label="كلمة المرور الحالية" v-model="passwordData.current_password" :isDark="isDark" />
                <PasswordInput label="كلمة المرور الجديدة" v-model="passwordData.new_password" :isDark="isDark" />
                <PasswordInput label="تأكيد كلمة المرور" v-model="passwordData.confirm_password" :isDark="isDark" />

                <button @click="updatePassword" :disabled="passwordLoading"
                    class="bg-emerald-500 hover:bg-emerald-600 active:scale-95 transition text-white font-medium px-6 py-2.5 rounded-lg shadow-md disabled:opacity-60">
                    {{ passwordLoading ? 'جاري التحديث...' : 'تحديث كلمة المرور' }}
                </button>
            </div>

            <!-- Status Message -->
            <transition name="fade">
                <div v-if="statusMessage" :class="['mt-6 p-3 rounded-lg text-sm font-medium flex items-center gap-2 transition-all',
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
import api from "@/services/ApiClient";
import { useRouter } from "vue-router";
const router = useRouter();
export default {
    name: "UserProfile",

    components: {
        PasswordInput: {
            props: ["label", "modelValue", "isDark"],
            emits: ["update:modelValue"],
            data() { return { show: false }; },
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
        // ─── Theme ───────────────────────────────────────────
        const isDark = ref(
            localStorage.getItem("theme") === "dark" || localStorage.getItem("theme") === null
        );

        const handleStorageChange = (e) => {
            if (e.key === "theme") isDark.value = e.newValue === "dark";
        };

        // ─── Profile State ───────────────────────────────────
        const profile = ref({
            user: {
                name: "",
                email: "",
                phone: "",
                role: "",
                image: "",
                is_active: true,
                last_seen: "",
            },
        });

        const editData = ref({
            name: "",
            email: "",
            phone: "",
            image: null,
        });

        const imagePreview = ref("");
        const profileLoading = ref(false);
        const passwordLoading = ref(false);

        const passwordData = ref({
            current_password: "",
            new_password: "",
            confirm_password: "",
        });

        // ─── Computed ─────────────────────────────────────────
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
            { label: "رقم الهاتف", value: profile.value.user.phone },
            { label: "الدور", value: profile.value.user.role },
            {
                label: "الحالة",
                value: profile.value.user.is_active ? "نشط ✓" : "غير نشط",
                class: profile.value.user.is_active
                    ? isDark.value ? "text-emerald-400 font-semibold" : "text-emerald-600 font-semibold"
                    : "text-red-400 font-semibold",
            },
            { label: "آخر تسجيل دخول", value: profile.value.user.last_seen },
        ]);

        const inputClass = computed(() =>
            [
                "w-full border rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500 transition-colors",
                isDark.value
                    ? "bg-slate-900 border-slate-700 text-white placeholder-slate-500"
                    : "bg-slate-50 border-slate-300 text-slate-900 placeholder-slate-400",
            ].join(" ")
        );

        // ─── Tabs ─────────────────────────────────────────────
        const tabs = [
            { id: 1, label: "معلومات المستخدم" },
            { id: 2, label: "تعديل البيانات" },
            { id: 3, label: "تحديث كلمة المرور" },
        ];
        const currentTab = ref(1);

        // ─── Status ───────────────────────────────────────────
        const statusMessage = ref("");
        const statusIsError = ref(false);
        let statusTimer = null;

        const showStatus = (msg, isError = false) => {
            clearTimeout(statusTimer);
            statusMessage.value = msg;
            statusIsError.value = isError;
            statusTimer = setTimeout(() => (statusMessage.value = ""), 4000);
        };

        // ─── Helpers ──────────────────────────────────────────
        const fillEditData = (user) => {
            editData.value.name = user.name || "";
            editData.value.email = user.email || "";
            editData.value.phone = user.phone || "";
            editData.value.image = null;
            imagePreview.value = "";
        };

        const handleImageUpload = (e) => {
            const file = e.target.files[0] ?? null;
            editData.value.image = file;
            imagePreview.value = file ? URL.createObjectURL(file) : "";
        };

        // ─── API Calls ────────────────────────────────────────
        const fetchProfile = async () => {
            try {
                const { data } = await api.get("/users/profile");
                if (data.data) {
                    profile.value = data.data;
                    fillEditData(data.data.user);
                }
            } catch {
                // الـ interceptor بيتولى الـ error toast
            }
        };

        const getImageUrl = (path) => {
            if (!path) return "";

            if (path.startsWith("http")) return path;

            return `http://localhost:8000/storage/${path}`;
        };
        const updateProfile = async () => {
            if (!editData.value.name || !editData.value.email) {
                showStatus("يرجى ملء الاسم والبريد الإلكتروني", true);
                return;
            }

            profileLoading.value = true;
            try {
                const payload = new FormData();
                payload.append("name", editData.value.name);
                payload.append("email", editData.value.email);
                if (editData.value.phone) payload.append("phone", editData.value.phone);
                if (editData.value.image) payload.append("image", editData.value.image);

                const { data } = await api.post("/users/update-profile", payload, {
                    headers: { "Content-Type": "multipart/form-data" },
                });

                // تحديث البيانات محلياً من الـ response
                const updated = data.data?.user ?? data.data;
                if (updated) {
                    profile.value.user = { ...profile.value.user, ...updated };
                    fillEditData(profile.value.user);
                }

                showStatus("تم تحديث البيانات بنجاح ✓");
            } catch {
                // الـ interceptor بيتولى الـ error toast
            } finally {
                profileLoading.value = false;
            }
        };

        const deleteProfile = async () => {
            profileLoading.value = true;
            try {
                await api.delete("/users/delete-account");
                showStatus("تم حذف الحساب بنجاح ✓");
                localStorage.removeItem("auth_token");
                window.location.href = "/";
            } catch (error) {
                console.log(error);
            } finally {
                profileLoading.value = false;
            }
        };
        const updatePassword = async () => {
            const { current_password, new_password, confirm_password } = passwordData.value;

            if (!current_password || !new_password || !confirm_password) {
                showStatus("يرجى ملء جميع الحقول", true);
                return;
            }
            if (new_password !== confirm_password) {
                showStatus("كلمات المرور غير متطابقة", true);
                return;
            }
            if (new_password.length < 6) {
                showStatus("كلمة المرور يجب أن تكون 6 أحرف على الأقل", true);
                return;
            }

            passwordLoading.value = true;
            try {
                await api.post("/users/password", passwordData.value);
                passwordData.value = { current_password: "", new_password: "", confirm_password: "" };
                showStatus("تم تحديث كلمة المرور بنجاح ✓");
            } catch {
                // الـ interceptor بيتولى الـ error toast
            } finally {
                passwordLoading.value = false;
            }
        };

        // ─── Lifecycle ────────────────────────────────────────
        onMounted(() => {
            window.addEventListener("storage", handleStorageChange);
            window.addEventListener("login", fetchProfile);
            fetchProfile();
        });

        onUnmounted(() => {
            window.removeEventListener("storage", handleStorageChange);
            window.removeEventListener("login", fetchProfile);
            clearTimeout(statusTimer);
        });

        return {
            isDark,
            profile,
            avatarInitials,
            infoRows,
            tabs,
            currentTab,
            editData,
            imagePreview,
            passwordData,
            statusMessage,
            statusIsError,
            inputClass,
            profileLoading,
            passwordLoading,
            handleImageUpload,
            updateProfile,
            updatePassword,
            getImageUrl,
            deleteProfile,
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
