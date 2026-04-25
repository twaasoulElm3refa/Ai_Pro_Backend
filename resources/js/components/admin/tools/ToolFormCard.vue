<template>
    <form
        class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm shadow-slate-200/60 dark:border-slate-800 dark:bg-slate-950 dark:shadow-none"
        @submit.prevent="handleSubmit"
    >
        <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
            <div class="space-y-5">
                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">Tool Name</label>
                    <input
                        v-model.trim="localForm.name"
                        type="text"
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-amber-400 focus:bg-white focus:ring-4 focus:ring-amber-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-amber-500 dark:focus:bg-slate-950 dark:focus:ring-amber-500/10"
                        :class="errors.name ? 'border-red-300 focus:border-red-400 focus:ring-red-100 dark:border-red-500/40 dark:focus:ring-red-500/10' : ''"
                        placeholder="AI Writing Assistant"
                    />
                    <p v-if="errors.name" class="mt-2 text-sm font-medium text-red-500">{{ errors.name }}</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">Description</label>
                    <textarea
                        v-model.trim="localForm.description"
                        rows="5"
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-amber-400 focus:bg-white focus:ring-4 focus:ring-amber-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-amber-500 dark:focus:bg-slate-950 dark:focus:ring-amber-500/10"
                        :class="errors.description ? 'border-red-300 focus:border-red-400 focus:ring-red-100 dark:border-red-500/40 dark:focus:ring-red-500/10' : ''"
                        placeholder="Short summary about what this tool does."
                    ></textarea>
                    <p v-if="errors.description" class="mt-2 text-sm font-medium text-red-500">{{ errors.description }}</p>
                </div>
            </div>

            <div class="space-y-5">
                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">Sort Order</label>
                    <input
                        v-model="localForm.sort_order"
                        type="number"
                        min="0"
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-amber-400 focus:bg-white focus:ring-4 focus:ring-amber-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-amber-500 dark:focus:bg-slate-950 dark:focus:ring-amber-500/10"
                        :class="errors.sort_order ? 'border-red-300 focus:border-red-400 focus:ring-red-100 dark:border-red-500/40 dark:focus:ring-red-500/10' : ''"
                        placeholder="0"
                    />
                    <p v-if="errors.sort_order" class="mt-2 text-sm font-medium text-red-500">{{ errors.sort_order }}</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">Image</label>
                    <label class="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center transition hover:border-amber-400 hover:bg-amber-50/60 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-amber-500 dark:hover:bg-slate-900">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-slate-700 shadow-sm dark:bg-slate-950 dark:text-slate-200">
                            <i class="bi bi-image text-xl"></i>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Upload tool image</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">PNG, JPG, GIF, SVG, or WebP up to 2MB</p>
                        </div>
                        <input class="hidden" type="file" accept="image/*" @change="onFileChange" />
                    </label>
                    <p v-if="errors.image" class="mt-2 text-sm font-medium text-red-500">{{ errors.image }}</p>
                </div>

                <div v-if="previewImage" class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-900">
                    <img :src="previewImage" alt="Tool preview" class="h-48 w-full object-cover" />
                </div>
                <div v-else class="flex h-48 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-500">
                    No image selected
                </div>
            </div>
        </div>

        <div class="mt-8 flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-end dark:border-slate-800">
            <RouterLink
                :to="cancelTo"
                class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-800 dark:text-slate-200 dark:hover:bg-slate-900"
            >
                Cancel
            </RouterLink>
            <button
                type="submit"
                class="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70 disabled:hover:translate-y-0 dark:bg-amber-500 dark:text-slate-950 dark:hover:bg-amber-400"
                :disabled="submitting"
            >
                <span v-if="submitting" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white dark:border-slate-950/30 dark:border-t-slate-950"></span>
                {{ submitLabel }}
            </button>
        </div>
    </form>
</template>

<script setup>
import { computed, reactive, watch } from "vue";

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
        default: "Save Tool",
    },
    cancelTo: {
        type: [String, Object],
        default: "/admin/tools",
    },
});

const emit = defineEmits(["submit", "update:form"]);

const localForm = reactive({
    name: "",
    description: "",
    sort_order: "",
    image: null,
    image_url: "",
});

watch(
    () => props.form,
    (value) => {
        localForm.name = value?.name ?? "";
        localForm.description = value?.description ?? "";
        localForm.sort_order = value?.sort_order ?? "";
        localForm.image = value?.image ?? null;
        localForm.image_url = value?.image_url ?? "";
    },
    { immediate: true, deep: true }
);

watch(
    localForm,
    (value) => {
        emit("update:form", { ...value });
    },
    { deep: true }
);

const previewImage = computed(() => {
    if (localForm.image instanceof File) {
        return URL.createObjectURL(localForm.image);
    }

    return localForm.image_url || "";
});

const onFileChange = (event) => {
    const [file] = event.target.files || [];
    localForm.image = file || null;
};

const handleSubmit = () => {
    emit("submit");
};
</script>
