<template>
    <main :dir="currentDir" :class="['min-h-50 py-10 px-4 transition-colors duration-300', isDark ? 'bg-slate-900' : 'bg-slate-100']"
        aria-labelledby="profile-page-title">
        <div :class="['max-w-3xl mx-auto rounded-2xl shadow-2xl border p-6 transition-colors duration-300',
            isDark ? 'bg-[#154677] text-white border-slate-800' : 'bg-white text-[#154677] border-[#154677]/10']">

            <div class="mb-6">
                <h1 id="profile-page-title" class="text-2xl font-bold" :class="isDark ? 'text-[#2ba6de]' : 'text-[#154677]'">
                    {{ t("user.profile.title") }}
                </h1>
            </div>

            <div :class="['flex border-b mb-6', isDark ? 'border-slate-800' : 'border-[#154677]/10']">
                <button v-for="tab in tabs" :key="tab.id" type="button" :aria-label="t('user.profile.openTabAria', { tab: tab.label })"
                    @click="currentTab = tab.id" :class="[
                        'py-3 px-4 text-sm font-medium transition-colors',
                        currentTab === tab.id
                            ? isDark
                                ? 'border-b-2 border-[#2ba6de] text-[#2ba6de]'
                                : 'border-b-2 border-[#154677] text-[#154677]'
                            : isDark
                                ? 'text-slate-400 hover:text-white'
                                : 'text-slate-500 hover:text-[#154677]'
                    ]">
                    {{ tab.label }}
                </button>
            </div>

            <div v-if="currentTab === 1" class="space-y-3">
                <div class="flex items-center gap-4 mb-5">
                    <div class="w-14 h-14 rounded-full overflow-hidden shadow-md flex-shrink-0">
                        <img v-if="profile.user.image" :src="getImageUrl(profile.user.image)"
                            :alt="t('user.profile.avatarAlt', { name: profile.user.name || t('user.profile.userFallback') })"
                            class="w-full h-full object-cover" loading="lazy" decoding="async" />
                        <div v-else
                            class="w-full h-full bg-[#154677] flex items-center justify-center text-white text-xl font-bold">
                            {{ avatarInitials }}
                        </div>
                    </div>
                    <div>
                        <p class="font-semibold text-base">{{ profile.user.name }}</p>
                        <p class="text-sm" :class="isDark ? 'text-[#2ba6de]' : 'text-[#154677]'">
                            {{ profile.user.role }}
                        </p>
                    </div>
                </div>

                <div v-for="row in infoRows" :key="row.label" :class="['flex items-center gap-3 text-sm px-4 py-3 rounded-xl transition-colors',
                    isDark ? 'bg-slate-900' : 'bg-slate-50']">
                    <span :class="['font-medium min-w-[130px]', isDark ? 'text-slate-400' : 'text-slate-500']">
                        {{ row.label }}
                    </span>
                    <span :class="row.class || ''">{{ row.value || t("user.profile.na") }}</span>
                </div>
            </div>

            <div v-if="currentTab === 2" class="space-y-4">

                <div>
                    <label :class="['block text-sm mb-1', isDark ? 'text-slate-400' : 'text-slate-500']">{{ t("user.profile.fields.name") }}</label>
                    <input v-model="editData.name" :class="inputClass" :placeholder="t('user.profile.placeholders.name')" />
                </div>

                <div>
                    <label :class="['block text-sm mb-1', isDark ? 'text-slate-400' : 'text-slate-500']">{{ t("user.profile.fields.email") }}</label>
                    <input v-model="editData.email" type="email" :class="inputClass" placeholder="example@email.com" />
                </div>

                <div>
                    <label :class="['block text-sm mb-1', isDark ? 'text-slate-400' : 'text-slate-500']">{{ t("user.profile.fields.phone") }}</label>
                    <input v-model="editData.phone" type="tel" :class="inputClass" placeholder="+201xxxxxxxxx" />
                </div>

                <div>
                    <label :class="['block text-sm mb-1', isDark ? 'text-slate-400' : 'text-slate-500']">{{ t("user.profile.fields.image") }}</label>
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-full overflow-hidden flex-shrink-0 shadow">
                            <img v-if="imagePreview || profile.user.image"
                                :src="imagePreview || getImageUrl(profile.user.image)"
                                :alt="t('user.profile.previewAlt', { name: profile.user.name || t('user.profile.userFallback') })" class="w-full h-full object-cover"
                                loading="lazy" decoding="async" />
                            <div v-else
                                class="w-full h-full bg-[#154677] flex items-center justify-center text-white font-bold text-lg">
                                {{ avatarInitials }}
                            </div>
                        </div>
                        <label :class="['cursor-pointer text-sm px-4 py-2 rounded-lg border transition',
                            isDark
                                ? 'border-slate-600 text-slate-300 hover:border-[#154677] hover:text-[#154677]'
                                : 'border-slate-300 text-slate-600 hover:border-[#154677] hover:text-[#154677]']">
                            {{ t("user.profile.chooseImage") }}
                            <input type="file" accept="image/*" class="hidden" :aria-label="t('user.profile.uploadImageAria')"
                                @change="handleImageUpload" />
                        </label>
                        <span v-if="editData.image" class="text-xs text-[#154677] dark:text-[#2ba6de] truncate max-w-[140px]">
                            {{ editData.image.name }}
                        </span>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="button" @click="updateProfile" :disabled="profileLoading"
                        :aria-label="t('user.profile.saveAria')"
                        class="bg-[#154677] hover:bg-[#2ba6de] active:scale-95 transition text-white font-medium px-6 py-2.5 rounded shadow-md disabled:opacity-60">
                        {{ profileLoading ? t('user.profile.loading.save') : t('user.profile.actions.save') }}
                    </button>

                    <button type="button" @click="deleteProfile" :disabled="profileLoading"
                        :aria-label="t('user.profile.deleteAria')"
                        class="bg-slate-1000 hover:bg-[#2ba6de] active:scale-95 transition text-white font-medium px-6 py-2.5 rounded shadow-md disabled:opacity-60">
                        {{ profileLoading ? t('user.profile.loading.delete') : t('user.profile.actions.deleteAccount') }}
                    </button>
                </div>
            </div>

            <div v-if="currentTab === 3" class="space-y-4">
                <PasswordInput :label="t('user.profile.password.current')" v-model="passwordData.current_password" :isDark="isDark" />
                <PasswordInput :label="t('user.profile.password.new')" v-model="passwordData.new_password" :isDark="isDark" />
                <PasswordInput :label="t('user.profile.password.confirm')" v-model="passwordData.confirm_password" :isDark="isDark" />

                <button type="button" @click="updatePassword" :disabled="passwordLoading"
                    :aria-label="t('user.profile.updatePasswordAria')"
                    class="bg-[#154677] hover:bg-[#2ba6de] active:scale-95 transition text-white font-medium px-6 py-2.5 rounded-lg shadow-md disabled:opacity-60">
                    {{ passwordLoading ? t('user.profile.loading.updatePassword') : t('user.profile.actions.updatePassword') }}
                </button>
            </div>

            <transition name="fade">
                <div v-if="statusMessage" role="status" aria-live="polite" :class="['mt-6 p-3 rounded-lg text-sm font-medium flex items-center gap-2 transition-all',
                    statusIsError
                        ? 'bg-slate-1000/10 border border-[#154677]/20 text-[#154677]'
                        : 'bg-[#154677]/10 border border-[#154677]/30 text-[#154677] dark:text-[#2ba6de]']">
                    <i class="bi" :class="statusIsError ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill'" aria-hidden="true"></i>
                    {{ statusMessage }}
                </div>
            </transition>
        </div>
    </main>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useI18n } from "vue-i18n";
import api from "@/services/ApiClient";
import { useRoute } from "vue-router";
import useSeoMeta from "@/composables/useSeoMeta";

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
                        "w-full border rounded-lg px-3 py-2 pr-10 focus:outline-none focus:border-[#154677] transition-colors",
                        this.isDark
                            ? "bg-slate-900 border-slate-700 text-white placeholder-slate-500"
                            : "bg-slate-50 border-slate-300 text-[#154677] placeholder-slate-400",
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
                            :aria-label="show ? $t('user.profile.password.hide') : $t('user.profile.password.show')"
                            class="absolute inset-y-0 right-3 flex items-center text-slate-400 hover:text-slate-300 transition-colors">
                            <i class="bi" :class="show ? 'bi-eye-fill' : 'bi-eye-slash-fill'" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            `,
        },
    },

    setup() {
        const route = useRoute();
        const { t, locale } = useI18n();
        const currentDir = computed(() => locale.value === "ar" ? "rtl" : "ltr");

        const isDark = ref(
            localStorage.getItem("theme") === "dark" || localStorage.getItem("theme") === null
        );

        const handleStorageChange = (e) => {
            if (e.key === "theme") isDark.value = e.newValue === "dark";
        };

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

        const avatarInitials = computed(() =>
            (profile.value.user.name || "")
                .split(" ")
                .map((w) => w[0])
                .slice(0, 2)
                .join("")
                .toUpperCase()
        );

        const infoRows = computed(() => [
            { label: t("user.profile.fields.name"), value: profile.value.user.name },
            { label: t("user.profile.fields.email"), value: profile.value.user.email },
            { label: t("user.profile.fields.phone"), value: profile.value.user.phone },
            { label: t("user.profile.fields.role"), value: profile.value.user.role },
            {
                label: t("user.profile.fields.status"),
                value: profile.value.user.is_active ? t("user.profile.status.active") : t("user.profile.status.inactive"),
                class: profile.value.user.is_active
                    ? isDark.value ? "text-[#2ba6de] font-semibold" : "text-[#154677] font-semibold"
                    : "text-[#154677] font-semibold",
            },
            { label: t("user.profile.fields.lastSeen"), value: profile.value.user.last_seen },
        ]);

        const inputClass = computed(() =>
            [
                "w-full border rounded-lg px-3 py-2 focus:outline-none focus:border-[#154677] transition-colors",
                isDark.value
                    ? "bg-slate-900 border-slate-700 text-white placeholder-slate-500"
                    : "bg-slate-50 border-slate-300 text-[#154677] placeholder-slate-400",
            ].join(" ")
        );

        const tabs = computed(() => [
            { id: 1, label: t("user.profile.tabs.info") },
            { id: 2, label: t("user.profile.tabs.edit") },
            { id: 3, label: t("user.profile.tabs.password") },
        ]);

        const currentTab = ref(1);
        const isArabic = computed(() =>
            String(route.params.lang || localStorage.getItem("language") || "en").toLowerCase() === "ar"
        );
        const seoTitle = computed(() => t("user.profile.seoTitle"));
        const seoDescription = computed(() => t("user.profile.seoDescription"));

        useSeoMeta({
            title: seoTitle,
            description: seoDescription,
        });

        const statusMessage = ref("");
        const statusIsError = ref(false);
        let statusTimer = null;

        const showStatus = (msg, isError = false) => {
            clearTimeout(statusTimer);
            statusMessage.value = msg;
            statusIsError.value = isError;
            statusTimer = setTimeout(() => (statusMessage.value = ""), 4000);
        };

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

        const fetchProfile = async () => {
            try {
                const { data } = await api.get("/users/profile");
                if (data.data) {
                    profile.value = data.data;
                    fillEditData(data.data.user);
                }
            } catch {
                // Interceptor handles toast
            }
        };

        const getImageUrl = (path) => {
            if (!path) return "";
            if (path.startsWith("http")) return path;
            return `http://localhost:8000/storage/${path}`;
        };

        const updateProfile = async () => {
            if (!editData.value.name || !editData.value.email) {
                showStatus(t("user.profile.messages.nameEmailRequired"), true);
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

                const updated = data.data?.user ?? data.data;
                if (updated) {
                    profile.value.user = { ...profile.value.user, ...updated };
                    fillEditData(profile.value.user);
                }

                showStatus(t("user.profile.messages.profileUpdated"));
            } catch {
                // Interceptor handles toast
            } finally {
                profileLoading.value = false;
            }
        };

        const deleteProfile = async () => {
            profileLoading.value = true;
            try {
                await api.delete("/users/delete-account");
                showStatus(t("user.profile.messages.accountDeleted"));
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
                showStatus(t("user.profile.messages.fillAllFields"), true);
                return;
            }
            if (new_password !== confirm_password) {
                showStatus(t("user.profile.messages.passwordMismatch"), true);
                return;
            }
            if (new_password.length < 6) {
                showStatus(t("user.profile.messages.passwordTooShort"), true);
                return;
            }

            passwordLoading.value = true;
            try {
                await api.post("/users/password", passwordData.value);
                passwordData.value = { current_password: "", new_password: "", confirm_password: "" };
                showStatus(t("user.profile.messages.passwordUpdated"));
            } catch {
                // Interceptor handles toast
            } finally {
                passwordLoading.value = false;
            }
        };

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
            t,
            currentDir,
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

:root {
    --primary-color: #154677;
    --primary-hover: #2ba6de;
    --primary-light: #2ba6de;
    --primary-bg: rgba(7, 67, 119, 0.1);
    --primary-border: rgba(7, 67, 119, 0.3);
}

.dark {
    --primary-light: #2ba6de;
}
</style>
