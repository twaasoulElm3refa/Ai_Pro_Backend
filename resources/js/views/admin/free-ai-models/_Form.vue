<template>
    <form
        class="rounded-[1.75rem] border border-[#154677]/10 bg-white p-6 shadow-[0_24px_46px_rgba(21,70,119,0.1)] dark:border-slate-800 dark:bg-[#154677] dark:shadow-none"
        @submit.prevent="$emit('submit')"
    >
        <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
            <div class="space-y-5">
                <div>
                    <label class="theme-label mb-2 block text-sm dark:text-slate-100">Model Name</label>
                    <input
                        :value="form.name"
                        type="text"
                        class="theme-input w-full rounded-2xl px-4 py-3 text-sm dark:border-white/10 dark:bg-white dark:text-[#154677]"
                        :class="errors.name ? 'border-[#2ba6de] focus:border-[#2ba6de] focus:ring-[#2ba6de]/15' : ''"
                        placeholder="Llama 3.1 8B"
                        @input="updateField('name', $event.target.value)"
                    />
                    <p v-if="errors.name" class="mt-2 text-sm font-medium text-[#154677] dark:text-[#2ba6de]">{{ errors.name }}</p>
                </div>

                <div>
                    <label class="theme-label mb-2 block text-sm dark:text-slate-100">Description</label>
                    <textarea
                        :value="form.description"
                        rows="5"
                        class="theme-input w-full rounded-2xl px-4 py-3 text-sm dark:border-white/10 dark:bg-white dark:text-[#154677]"
                        :class="errors.description ? 'border-[#2ba6de] focus:border-[#2ba6de] focus:ring-[#2ba6de]/15' : ''"
                        placeholder="Short summary about this free AI model."
                        @input="updateField('description', $event.target.value)"
                    ></textarea>
                    <p v-if="errors.description" class="mt-2 text-sm font-medium text-[#154677] dark:text-[#2ba6de]">{{ errors.description }}</p>
                </div>

                <label class="flex items-center justify-between gap-4 rounded-2xl border border-[#154677]/10 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                    <span>
                        <span class="block text-sm font-semibold text-[#154677] dark:text-white">Active</span>
                        <span class="mt-1 block text-xs text-slate-500 dark:text-slate-300">Make this model available in the catalog.</span>
                    </span>
                    <input
                        :checked="Boolean(form.is_active)"
                        type="checkbox"
                        class="h-5 w-5 rounded border-slate-300 text-[#2ba6de] focus:ring-[#2ba6de]"
                        @change="updateField('is_active', $event.target.checked)"
                    />
                </label>
                <p v-if="errors.is_active" class="text-sm font-medium text-[#154677] dark:text-[#2ba6de]">{{ errors.is_active }}</p>
            </div>

            <div class="space-y-5">
                <div>
                    <label class="theme-label mb-2 block text-sm dark:text-slate-100">Sort Order</label>
                    <input
                        :value="form.sort_order"
                        type="number"
                        min="0"
                        class="theme-input w-full rounded-2xl px-4 py-3 text-sm dark:border-white/10 dark:bg-white dark:text-[#154677]"
                        :class="errors.sort_order ? 'border-[#2ba6de] focus:border-[#2ba6de] focus:ring-[#2ba6de]/15' : ''"
                        placeholder="0"
                        @input="updateField('sort_order', $event.target.value)"
                    />
                    <p v-if="errors.sort_order" class="mt-2 text-sm font-medium text-[#154677] dark:text-[#2ba6de]">{{ errors.sort_order }}</p>
                </div>

                <div>
                    <label class="theme-label mb-2 block text-sm dark:text-slate-100">Image</label>
                    <label class="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-[#154677]/20 bg-[#2ba6de]/5 px-5 py-8 text-center transition hover:border-[#2ba6de] hover:bg-[#2ba6de]/10 dark:border-white/15 dark:bg-white/5 dark:hover:border-white/30 dark:hover:bg-white/10">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-[#154677] shadow-sm">
                            <i class="bi bi-image text-xl"></i>
                        </span>
                        <span>
                            <span class="block text-sm font-semibold text-[#154677] dark:text-white">Upload model image</span>
                            <span class="mt-1 block text-xs text-slate-500 dark:text-slate-200">PNG, JPG, GIF, or WebP up to 2MB</span>
                        </span>
                        <input class="hidden" type="file" accept="image/jpeg,image/png,image/gif,image/webp" @change="onFileChange" />
                    </label>
                    <p v-if="errors.image" class="mt-2 text-sm font-medium text-[#154677] dark:text-[#2ba6de]">{{ errors.image }}</p>
                </div>

                <div v-if="previewImage" class="overflow-hidden rounded-2xl border border-[#154677]/10 bg-slate-50 dark:border-white/10 dark:bg-white/5">
                    <img :src="previewImage" alt="Free AI model preview" class="h-48 w-full object-cover" />
                </div>
                <div v-else class="flex h-48 items-center justify-center rounded-2xl border border-[#154677]/10 bg-slate-50 text-sm text-slate-400 dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                    No image selected
                </div>
            </div>
        </div>

        <div class="mt-8 flex flex-col-reverse gap-3 border-t border-[#154677]/10 pt-6 sm:flex-row sm:items-center sm:justify-end dark:border-white/10">
            <RouterLink
                :to="cancelTo"
                class="theme-button-soft inline-flex items-center justify-center rounded-2xl px-4 py-3 text-sm font-semibold dark:border-white/10 dark:bg-white/10 dark:text-white dark:hover:bg-white/15"
            >
                Cancel
            </RouterLink>
            <button
                type="submit"
                class="theme-button inline-flex items-center justify-center gap-2 rounded-2xl px-5 py-3 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-70 disabled:hover:translate-y-0"
                :disabled="submitting"
            >
                <span v-if="submitting" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                {{ submitLabel }}
            </button>
        </div>
    </form>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from "vue";

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    submitting: {
        type: Boolean,
        default: false,
    },
    submitLabel: {
        type: String,
        default: "Save Model",
    },
    cancelTo: {
        type: [String, Object],
        default: "/admin/free-ai-models",
    },
});

const emit = defineEmits(["submit", "update:form"]);
const selectedPreview = ref("");

const previewImage = computed(() => selectedPreview.value || props.form.image_url || "");

const updateField = (key, value) => {
    emit("update:form", {
        ...props.form,
        [key]: value,
    });
};

const revokePreview = () => {
    if (selectedPreview.value) {
        URL.revokeObjectURL(selectedPreview.value);
        selectedPreview.value = "";
    }
};

const onFileChange = (event) => {
    const [file] = event.target.files || [];
    revokePreview();

    if (file) {
        selectedPreview.value = URL.createObjectURL(file);
    }

    updateField("image", file || null);
};

onBeforeUnmount(revokePreview);
</script>
