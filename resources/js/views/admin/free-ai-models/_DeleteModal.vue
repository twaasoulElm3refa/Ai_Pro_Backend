<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-[#154677]/20 px-4 backdrop-blur-md">
                <div class="w-full max-w-md rounded-[1.75rem] border border-[#154677]/10 bg-white p-6 shadow-[0_28px_50px_rgba(21,70,119,0.16)] dark:border-slate-800 dark:bg-[#154677]">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#2ba6de]/10 text-[#154677] dark:bg-white/10 dark:text-white">
                            <i class="bi bi-trash3 text-xl"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-bold text-[#154677] dark:text-slate-100">Delete free AI model</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-300">
                                This will remove
                                <span class="font-semibold text-[#154677] dark:text-white">{{ modelName || "this model" }}</span>.
                                This action cannot be undone from the admin screen.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="theme-button-soft inline-flex items-center justify-center rounded-2xl px-4 py-2.5 text-sm font-semibold dark:border-white/10 dark:bg-white/10 dark:text-white dark:hover:bg-white/15"
                            :disabled="loading"
                            @click="$emit('cancel')"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="theme-button-danger inline-flex items-center justify-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-70"
                            :disabled="loading"
                            @click="$emit('confirm')"
                        >
                            <span v-if="loading" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    modelName: {
        type: String,
        default: "",
    },
});

defineEmits(["cancel", "confirm"]);
</script>
