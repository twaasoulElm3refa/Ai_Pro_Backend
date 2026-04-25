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
            <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 px-4 backdrop-blur-sm">
                <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-950">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-500 dark:bg-red-500/10 dark:text-red-300">
                            <i class="bi bi-trash3 text-xl"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">Delete tool</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
                                This will remove
                                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ toolName || "this tool" }}</span>.
                                This action cannot be undone.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-800 dark:text-slate-200 dark:hover:bg-slate-900"
                            @click="$emit('cancel')"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-500 disabled:cursor-not-allowed disabled:opacity-70"
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
    toolName: {
        type: String,
        default: "",
    },
});

defineEmits(["cancel", "confirm"]);
</script>
