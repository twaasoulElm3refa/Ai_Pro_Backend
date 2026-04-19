<template>
    <div class=" bg-slate-900 py-10 px-4">
        <div class="max-w-3xl mx-auto bg-slate-950 text-white rounded-2xl shadow-2xl border border-slate-800 p-6">
            <h1 class="text-2xl font-bold mb-6 text-emerald-400">
                ملفي الشخصي
            </h1>

            <!-- Tabs -->
            <div class="flex border-b border-slate-800 mb-6">
                <button v-for="tab in tabs" :key="tab.id" @click="currentTab = tab.id" :class="[
                    'py-3 px-4 text-sm font-medium transition',
                    currentTab === tab.id
                        ? 'border-b-2 border-emerald-500 text-emerald-400'
                        : 'text-slate-400 hover:text-white'
                ]">
                    {{ tab.label }}
                </button>
            </div>

            <!-- Tab 1 -->
            <div v-if="currentTab === 1" class="space-y-3 text-sm">
                <p><span class="text-slate-400">Name:</span> {{ profile.user.name }}</p>
                <p><span class="text-slate-400">Email:</span> {{ profile.user.email }}</p>
                <p><span class="text-slate-400">Role:</span> {{ profile.user.role }}</p>
                <p>
                    <span class="text-slate-400">Active:</span>
                    <span :class="profile.user.is_active ? 'text-emerald-400' : 'text-red-400'">
                        {{ profile.user.is_active ? 'Yes' : 'No' }}
                    </span>
                </p>
                <p><span class="text-slate-400">Last Login:</span> {{ profile.user.last_login_at }}</p>
            </div>

            <!-- Tab 2 -->
            <div v-if="currentTab === 2" class="space-y-4">
                <div>
                    <label class="block text-sm text-slate-400 mb-1">الاسم</label>
                    <input v-model="editData.name"
                        class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-emerald-500 focus:outline-none" />
                </div>

                <div>
                    <label class="block text-sm text-slate-400 mb-1">البريد الإلكتروني</label>
                    <input v-model="editData.email" type="email"
                        class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:border-emerald-500 focus:outline-none" />
                </div>

                <button @click="updateProfile"
                    class="bg-emerald-500 hover:bg-emerald-600 transition text-black font-medium px-6 py-2 rounded-lg">
                    حفظ التغييرات
                </button>
            </div>

            <!-- Tab 3 -->
            <div v-if="currentTab === 3" class="space-y-4">
                <PasswordInput label="Current Password" v-model="passwordData.current_password" />
                <PasswordInput label="New Password" v-model="passwordData.new_password" />
                <PasswordInput label="Confirm Password" v-model="passwordData.confirm_password" />

                <button @click="updatePassword"
                    class="bg-emerald-500 hover:bg-emerald-600 transition text-black font-medium px-6 py-2 rounded-lg">
                    تحديث كلمة المرور
                </button>
            </div>

            <!-- Status -->
            <div v-if="statusMessage"
                class="mt-6 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 p-3 rounded-lg text-sm">
                {{ statusMessage }}
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from "vue";

export default {
    name: "UserProfile",

    components: {
        PasswordInput: {
            props: ["label", "modelValue"],
            emits: ["update:modelValue"],
            data() {
                return { show: false };
            },
            template: `
        <div>
          <label class="block text-sm text-slate-400 mb-1">{{ label }}</label>
          <div class="relative">
            <input
              :type="show ? 'text' : 'password'"
              :value="modelValue"
              @input="$emit('update:modelValue', $event.target.value)"
              class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 pr-10 text-white focus:border-emerald-500 focus:outline-none"
            />
            <button
              type="button"
              @click="show = !show"
              class="absolute inset-y-0 right-3 text-slate-400"
            >
              👁️
            </button>
          </div>
        </div>
      `,
        },
    },

    setup() {
        const profile = ref({ user: {} });

        const tabs = [
            { id: 1, label: "معلومات المستخدم" },
            { id: 2, label: "تعديل البيانات" },
            { id: 3, label: "تحديث كلمة المرور" },
        ];

        const currentTab = ref(1);
        const statusMessage = ref("");

        const editData = ref({ name: "", email: "" });
        const passwordData = ref({
            current_password: "",
            new_password: "",
            confirm_password: "",
        });

        const fetchProfile = async () => {
            const token = localStorage.getItem("auth_token");
            const res = await fetch("/api/v1/users/profile", {
                headers: {
                    Authorization: `Bearer ${token}`,
                    Accept: "application/json",
                },
            });
            const data = await res.json();
            profile.value = data.data;
            editData.value.name = data.data.user.name;
            editData.value.email = data.data.user.email;
        };

        const updateProfile = async () => {
            const token = localStorage.getItem("auth_token");
            await fetch("/api/v1/users/profile", {
                method: "PUT",
                headers: {
                    Authorization: `Bearer ${token}`,
                    "Content-Type": "application/json",
                    Accept: "application/json",
                },
                body: JSON.stringify(editData.value),
            });
            statusMessage.value = "تم تحديث البيانات بنجاح";
            fetchProfile();
        };

        const updatePassword = async () => {
            if (passwordData.value.new_password !== passwordData.value.confirm_password) {
                statusMessage.value = "كلمات المرور غير متطابقة";
                return;
            }

            const token = localStorage.getItem("auth_token");
            await fetch("/api/v1/users/password", {
                method: "PUT",
                headers: {
                    Authorization: `Bearer ${token}`,
                    "Content-Type": "application/json",
                    Accept: "application/json",
                },
                body: JSON.stringify(passwordData.value),
            });

            statusMessage.value = "تم تحديث كلمة المرور";
        };

        onMounted(fetchProfile);

        return {
            profile,
            tabs,
            currentTab,
            editData,
            passwordData,
            statusMessage,
            updateProfile,
            updatePassword,
        };
    },
};
</script>
