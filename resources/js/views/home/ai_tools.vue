<template>
    <div class="ai-tools">
        <h1>AI Tools</h1>
    </div>
</template>


<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import aiModelsService from "@/services/AiModels/toolServices";
import useSeoMeta from "@/composables/useSeoMeta";

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();

</script>
