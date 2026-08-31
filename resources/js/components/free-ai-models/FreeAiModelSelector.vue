<template>
    <div ref="selectorRef" class="composer-model-selector">
        <button
            type="button"
            class="selector-trigger"
            :disabled="disabled || loading"
            :aria-expanded="open"
            aria-haspopup="listbox"
            @click="handleTrigger"
            @keydown.down.prevent="openAndFocus"
            @keydown.esc.prevent="close"
        >
            <span class="selector-icon"><i class="bi bi-stars"></i></span>
            <span class="selector-label">
                <small>{{ t("freeAiModels.model") }}</small>
                <strong>{{ triggerLabel }}</strong>
            </span>
            <span v-if="selectedModel" class="tier-dot" :class="`tier-${selectedModel.tier}`"></span>
            <span v-if="loading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            <i v-else class="bi bi-chevron-up selector-chevron" :class="{ open }"></i>
        </button>

        <div v-if="open" class="selector-menu" role="listbox">
            <button
                v-for="model in models"
                :key="modelKey(model)"
                type="button"
                class="selector-option"
                :class="{ selected: isSelected(model), unavailable: !model.isAvailable }"
                :disabled="!model.isAvailable"
                :aria-selected="isSelected(model)"
                role="option"
                @click="choose(model)"
            >
                <span class="option-icon"><i class="bi bi-cpu"></i></span>
                <span class="option-copy">
                    <strong>
                        {{ model.name }}
                        <i
                            v-if="model.isRecommended"
                            class="bi bi-patch-check-fill recommended"
                            :title="t('freeAiModels.recommended')"
                        ></i>
                    </strong>
                    <small v-if="!model.isAvailable">{{ t("freeAiModels.modelUnavailable") }}</small>
                    <small v-else>
                        {{ tierLabel(model.tier) }} · {{ model.isFree ? t("freeAiModels.free") : t("freeAiModels.paid") }}
                    </small>
                </span>
                <i v-if="isSelected(model)" class="bi bi-check-lg selected-check"></i>
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";

const props = defineProps({
    models: { type: Array, default: () => [] },
    selectedModel: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["select", "retry"]);
const { t } = useI18n();
const selectorRef = ref(null);
const open = ref(false);

const triggerLabel = computed(() => {
    if (props.loading) return t("freeAiModels.catalogLoading");
    if (props.selectedModel?.name) return props.selectedModel.name;
    if (props.error) return t("freeAiModels.catalogUnavailable");
    return t("freeAiModels.selectModel");
});

const modelKey = (model) => String(model?.providerModelId || model?.id || model?.name || "");
const isSelected = (model) => modelKey(model) === modelKey(props.selectedModel);

const tierLabel = (tier) => {
    const key = { free: "tierFree", standard: "tierStandard", advanced: "tierAdvanced" }[tier] || "tierStandard";
    return t(`freeAiModels.${key}`);
};

const close = () => {
    open.value = false;
};

const handleTrigger = () => {
    if (props.error && !props.models.length) {
        emit("retry");
        return;
    }

    if (!props.models.length) return;
    open.value = !open.value;
};

const openAndFocus = async () => {
    if (!props.models.length) return;
    open.value = true;
    await nextTick();
    selectorRef.value?.querySelector(".selector-option:not(:disabled)")?.focus();
};

const choose = (model) => {
    if (!model?.isAvailable) return;
    emit("select", model);
    close();
};

const handleOutsidePointer = (event) => {
    if (open.value && !selectorRef.value?.contains(event.target)) close();
};

const handleEscape = (event) => {
    if (event.key === "Escape") close();
};

onMounted(() => {
    document.addEventListener("pointerdown", handleOutsidePointer);
    document.addEventListener("keydown", handleEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener("pointerdown", handleOutsidePointer);
    document.removeEventListener("keydown", handleEscape);
});
</script>

<style scoped>
.composer-model-selector {
    position: relative;
    width: min(260px, 31vw);
    flex: 0 0 auto;
}

button {
    font: inherit;
}

.selector-trigger {
    width: 100%;
    min-height: 48px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 10px;
    border: 0;
    border-inline-end: 1px solid var(--theme-border);
    color: var(--theme-text-primary);
    background: transparent;
    text-align: start;
    cursor: pointer;
}

.selector-trigger:hover:not(:disabled) {
    background: var(--theme-hover);
}

.selector-trigger:focus-visible,
.selector-trigger[aria-expanded="true"] {
    outline: 0;
    background: var(--theme-hover);
    box-shadow: inset 0 0 0 2px rgba(43, 166, 222, 0.22);
}

.selector-trigger:disabled {
    cursor: not-allowed;
    opacity: 0.62;
}

.selector-icon {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    flex: 0 0 34px;
    border-radius: 10px;
    color: #ffffff;
    background: linear-gradient(145deg, #154677, #2ba6de);
}

.selector-label {
    min-width: 0;
    flex: 1;
}

.selector-label small,
.selector-label strong {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.selector-label small {
    color: var(--theme-text-muted);
    font-size: 8px;
    font-weight: 800;
    text-transform: uppercase;
}

.selector-label strong {
    margin-top: 1px;
    font-size: 11px;
}

.tier-dot {
    width: 7px;
    height: 7px;
    flex: 0 0 7px;
    border-radius: 50%;
    background: var(--theme-accent);
}

.tier-free {
    background: var(--app-success);
}

.tier-advanced {
    background: var(--theme-primary);
}

.selector-chevron {
    color: var(--theme-text-muted);
    transition: transform 0.2s ease;
}

.selector-chevron.open {
    transform: rotate(180deg);
}

.selector-menu {
    position: absolute;
    inset-inline-start: 0;
    bottom: calc(100% + 10px);
    z-index: 200;
    width: min(340px, calc(100vw - 24px));
    max-height: min(380px, 50vh);
    padding: 7px;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
    border: 1px solid var(--theme-border-strong);
    border-radius: 15px;
    background: var(--theme-surface-elevated);
    box-shadow: 0 22px 55px var(--theme-shadow);
    scrollbar-width: thin;
    scrollbar-color: var(--theme-border-strong) transparent;
}

.selector-option {
    width: 100%;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 9px;
    border: 1px solid transparent;
    border-radius: 11px;
    color: var(--theme-text-primary);
    background: transparent;
    text-align: start;
    cursor: pointer;
}

.selector-option:hover:not(:disabled),
.selector-option:focus-visible,
.selector-option.selected {
    outline: 0;
    border-color: var(--theme-border);
    background: var(--theme-hover);
}

.selector-option.unavailable {
    cursor: not-allowed;
    opacity: 0.48;
}

.option-icon {
    width: 31px;
    height: 31px;
    display: grid;
    place-items: center;
    flex: 0 0 31px;
    border-radius: 9px;
    color: var(--theme-accent);
    background: var(--theme-surface-secondary);
}

.option-copy {
    min-width: 0;
    flex: 1;
}

.option-copy strong,
.option-copy small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.option-copy strong {
    font-size: 11px;
}

.option-copy small {
    margin-top: 2px;
    color: var(--theme-text-muted);
    font-size: 9px;
}

.recommended,
.selected-check {
    color: var(--theme-accent);
}

@media (max-width: 640px) {
    .composer-model-selector {
        width: 100%;
    }

    .selector-trigger {
        min-height: 44px;
        border-inline-end: 0;
        border-bottom: 1px solid var(--theme-border);
    }

    .selector-menu {
        width: 100%;
        max-height: min(330px, 46vh);
    }
}
</style>
