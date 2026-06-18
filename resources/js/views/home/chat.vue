<template>
    <main class="chat-root" :dir="locale === 'ar' ? 'rtl' : 'ltr'" :aria-label="t('user.chat.workspaceAria')">
        <aside class="sidebar" :class="{ 'sidebar-open': sidebarOpen }">
            <div class="sidebar-header">
                <div class="brand">
                    <div class="brand-icon"><i class="bi bi-chat-square-text-fill"></i></div>
                    <div>
                        <span class="brand-name">{{ t("user.chat.conversations") }}</span>
                        <p class="brand-subtitle">{{ subtool.name || t("user.chat.workspaceTitle") }}</p>
                    </div>
                </div>
                <button type="button" class="icon-btn mobile-only" :aria-label="t('user.chat.closeSidebarAria')"
                    @click="sidebarOpen = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <button type="button" class="new-chat-btn" :disabled="creatingConversation" @click="startNewChat">
                <i class="bi bi-plus-lg"></i>
                <span>{{ creatingConversation ? t("user.chat.creating") : t("user.chat.newChat") }}</span>
            </button>

            <div class="history-section">
                <p class="history-label">{{ t("user.chat.recentChats") }}</p>

                <div v-if="loadingConversations" class="history-skeletons">
                    <div v-for="item in 5" :key="item" class="history-skeleton"></div>
                </div>

                <div v-else-if="filteredConversations.length === 0" class="history-empty">
                    <i class="bi bi-chat-dots"></i>
                    <span>{{ t("user.chat.noConversations") }}</span>
                </div>

                <TransitionGroup v-else name="history-item" tag="div" class="history-list">
                    <div v-for="conversation in filteredConversations" :key="conversation.uuid" class="history-item"
                        :class="{ active: activeConversation?.uuid === conversation.uuid }">
                        <button type="button" class="history-item-main" @click="openConversation(conversation)">
                            <i class="bi bi-chat-left-text"></i>
                            <div class="history-item-info">
                                <span class="history-item-title">{{ conversation.title }}</span>
                            </div>
                        </button>

                        <button type="button" class="history-delete" :aria-label="t('user.chat.deleteConversationAria', {
                            name: conversation.title || t('user.chat.conversationFallback')
                        })" @click="removeConversation(conversation)">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </div>
                </TransitionGroup>
            </div>
        </aside>

        <div v-if="sidebarOpen" class="sidebar-overlay" @click="sidebarOpen = false"></div>

        <div class="main-area">
            <div
                class="messages-wrap"
                ref="messagesContainer"
                role="log"
                aria-live="polite"
                aria-relevant="additions text"
            >
                <div v-if="loadingMessages" class="messages-skeleton">
                    <div v-for="item in 4" :key="item" class="message-skeleton"
                        :class="item % 2 === 0 ? 'assistant' : 'user'"></div>
                </div>

                <div v-else-if="messages.length === 0" class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-stars"></i>
                    </div>
                    <h2 class="empty-title">{{ subtool.name || t("user.chat.newConversation") }}</h2>
                    <p class="empty-desc">
                        {{
                            activeConversation?.uuid
                                ? t("user.chat.emptyWithConversation")
                                : t("user.chat.emptyWithoutConversation")
                        }}
                    </p>
                    <button v-if="subtool.promptPlaceholder" type="button" class="suggestion-chip"
                        @click="fillPlaceholder">
                        <i class="bi bi-lightning-charge-fill"></i>
                        {{ subtool.promptPlaceholder }}
                    </button>
                </div>

                <TransitionGroup v-else name="msg" tag="div" class="messages-list">
                    <div v-for="msg in messages" :key="msg.localKey" class="message-row" :class="msg.role">
                        <div class="msg-avatar" v-if="msg.role === 'assistant'">
                            <i class="bi bi-stars"></i>
                        </div>

                        <div class="msg-bubble">
                            <div
                                v-if="msg.isTyping"
                                class="typing-bubble"
                                role="status"
                                aria-live="polite"
                                :aria-label="typingAriaLabel"
                            >
                                <span class="typing-text">{{ typingText }}</span>
                                <span class="typing-dots" aria-hidden="true">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </span>
                            </div>
                            <div
                                v-else-if="msg.role === 'assistant'"
                                class="ai-result-card"
                                :class="{ 'error-message': msg.is_error }"
                            >
                                <div class="ai-result-header">
                                    <strong class="ai-result-title">
                                        {{ isArabic ? "النتيجة" : "Result" }}
                                    </strong>

                                    <button
                                        type="button"
                                        class="ai-copy-btn"
                                        @click="copyAssistantMessage(msg)"
                                    >
                                        <i class="bi bi-copy"></i>
                                        {{
                                            copiedMessageKey === msg.localKey
                                                ? (isArabic ? "تم النسخ" : "Copied")
                                                : (isArabic ? "نسخ" : "Copy")
                                        }}
                                    </button>
                                </div>

                                <div class="ai-result-text">
                                    <span v-if="msg.streaming && !msg.content" class="typing-indicator">
                                        <span></span><span></span><span></span>
                                    </span>
                                    <div v-else-if="msg.plainText" class="markdown-body plain-text-message">{{ displayMessageContent(msg) }}</div>
                                    <div v-else class="markdown-body" v-html="formatMessage(displayMessageContent(msg), msg.role)"></div>
                                </div>
                            </div>
                            <div v-else class="msg-content" :class="{ 'error-message': msg.is_error }">
                                <span v-if="msg.streaming && !msg.content" class="typing-indicator">
                                    <span></span><span></span><span></span>
                                </span>
                                <div v-else-if="msg.plainText" class="markdown-body plain-text-message">{{ displayMessageContent(msg) }}</div>
                                <div v-else class="markdown-body" v-html="formatMessage(displayMessageContent(msg), msg.role)"></div>
                            </div>
                            <span v-if="!msg.isTyping" class="msg-time">{{ msg.time }}</span>
                            <div v-if="isTextEditorResult(msg)" class="result-actions">
                                <button type="button" :disabled="chatSendDisabled" @click="regenerateTextEditorResult(msg)">
                                    <i class="bi bi-arrow-clockwise"></i>
                                    {{ isArabic ? "إعادة التوليد" : "Regenerate" }}
                                </button>
                            </div>
                            <div v-if="isTextSummarizerResult(msg)" class="result-actions">
                                <button type="button" :disabled="chatSendDisabled" @click="regenerateTextSummarizerResult(msg)">
                                    <i class="bi bi-arrow-clockwise"></i>
                                    {{ isArabic ? "إعادة التوليد" : "Regenerate" }}
                                </button>
                            </div>
                            <div v-if="isProductDescriptionResult(msg)" class="result-actions">
                                <button type="button" @click="copyProductDescription(msg)">
                                    <i class="bi bi-copy"></i>
                                    {{ isArabic ? "نسخ" : "Copy" }}
                                </button>
                                <button type="button" :disabled="chatSendDisabled" @click="regenerateProductDescription(msg)">
                                    <i class="bi bi-arrow-clockwise"></i>
                                    {{ isArabic ? "إعادة التوليد" : "Regenerate" }}
                                </button>
                                <button type="button" @click="editProductDescriptionInputs">
                                    <i class="bi bi-sliders"></i>
                                    {{ isArabic ? "تعديل المدخلات" : "Edit inputs" }}
                                </button>
                            </div>
                            <!-- <div v-if="productDescriptionUsage(msg)" class="result-usage">
                                {{ productDescriptionUsage(msg) }}
                            </div> -->
                            <div v-if="isHeadlineGeneratorResult(msg)" class="result-actions">
                                <button type="button" :disabled="chatSendDisabled" @click="regenerateHeadlineResult(msg)">
                                    <i class="bi bi-arrow-clockwise"></i>
                                    {{ isArabic ? "إعادة التوليد" : "Regenerate" }}
                                </button>
                            </div>
                            <div v-if="isParaphraserResult(msg)" class="result-actions">
                                <button type="button" :disabled="chatSendDisabled" @click="regenerateParaphraserResult(msg)">
                                    <i class="bi bi-arrow-clockwise"></i>
                                    {{ isArabic ? "إعادة التوليد" : "Regenerate" }}
                                </button>
                            </div>
                            <div v-if="isSocialPostResult(msg)" class="result-actions">
                                <button type="button" :disabled="chatSendDisabled" @click="regenerateSocialPostResult(msg)">
                                    <i class="bi bi-arrow-clockwise"></i>
                                    {{ isArabic ? "إعادة التوليد" : "Regenerate" }}
                                </button>
                                <button type="button" @click="editSocialPostInputs">
                                    <i class="bi bi-sliders"></i>
                                    {{ isArabic ? "تعديل المدخلات" : "Edit inputs" }}
                                </button>
                            </div>
                            <div v-if="isEmailWriterResult(msg)" class="result-actions">
                                <button type="button" :disabled="chatSendDisabled" @click="regenerateEmailWriterResult(msg)">
                                    <i class="bi bi-arrow-clockwise"></i>
                                    {{ isArabic ? "إعادة التوليد" : "Regenerate" }}
                                </button>
                                <button type="button" @click="editEmailWriterInputs">
                                    <i class="bi bi-sliders"></i>
                                    {{ isArabic ? "تعديل المدخلات" : "Edit inputs" }}
                                </button>
                            </div>
                            <div v-if="isScriptGeneratorResult(msg)" class="result-actions">
                                <button type="button" :disabled="chatSendDisabled" @click="regenerateScriptGeneratorResult(msg)">
                                    <i class="bi bi-arrow-clockwise"></i>
                                    {{ isArabic ? "إعادة التوليد" : "Regenerate" }}
                                </button>
                                <button type="button" @click="editScriptGeneratorInputs">
                                    <i class="bi bi-sliders"></i>
                                    {{ isArabic ? "تعديل المدخلات" : "Edit inputs" }}
                                </button>
                            </div>
                        </div>

                        <div class="msg-avatar user-avatar" v-if="msg.role === 'user'">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    </div>
                </TransitionGroup>
            </div>

            <div class="input-area">
                <div v-if="conversationLimitExceeded" class="limit-warning">
                    <div class="limit-warning-icon">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <div class="limit-warning-content">
                        <strong>ÙˆØµÙ„Øª Ù‡Ø°Ù‡ Ø§Ù„Ù…Ø­Ø§Ø¯Ø«Ø© Ø¥Ù„Ù‰ Ø§Ù„Ø­Ø¯ Ø§Ù„Ø£Ù‚ØµÙ‰</strong>
                        <span>Ø§Ø¨Ø¯Ø£ Ù…Ø­Ø§Ø¯Ø«Ø© Ø¬Ø¯ÙŠØ¯Ø© Ù„Ù„Ù…ØªØ§Ø¨Ø¹Ø© Ø¨Ø±Ø³Ø§Ø¦Ù„ Ø¥Ø¶Ø§ÙÙŠØ©.</span>
                    </div>
                </div>

                <div v-if="insufficientPoints && !conversationLimitExceeded" class="points-warning">
                    <div class="points-warning-icon">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <div class="points-warning-content">
                        <strong>Insufficient points</strong>
                        <span>You can send again, but the assistant may return insufficient points until you
                            recharge.</span>
                    </div>
                    <button type="button" class="points-warning-action" @click="goToWallet">
                        Recharge wallet
                    </button>
                </div>

                <div v-if="isSocialPostGeneratorTool" class="advanced-options">
                    <button type="button" class="advanced-options-toggle" @click="socialPostOptionsOpen = !socialPostOptionsOpen">
                        <span>
                            <i class="bi bi-sliders"></i>
                            {{ isArabic ? "خيارات متقدمة" : "Advanced options" }}
                        </span>
                        <i class="bi" :class="socialPostOptionsOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                    </button>

                    <div v-if="socialPostOptionsOpen" class="advanced-options-grid">
                        <label class="wide-field">
                            <span>{{ isArabic ? "موضوع المنشور" : "Post content" }}</span>
                            <textarea v-model="socialPostState.content" rows="2"></textarea>
                        </label>

                        <label v-for="field in socialPostSelectFields" :key="field.key">
                            <span>{{ isArabic ? field.labelAr : field.labelEn }}</span>
                            <select v-model="socialPostState[field.key]">
                                <option :value="null">{{ isArabic ? "افتراضي" : "Default" }}</option>
                                <option v-for="option in field.options" :key="option" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                        </label>

                        <label>
                            <span>{{ isArabic ? "عدد الهاشتاقات" : "Hashtag count" }}</span>
                            <input v-model.number="socialPostState.hashtag_count" type="number" min="0" max="30">
                        </label>

                        <label>
                            <span>{{ isArabic ? "عدد النتائج" : "Results count" }}</span>
                            <input v-model.number="socialPostState.results_count" type="number" min="1" max="10">
                        </label>

                        <label>
                            <span>{{ isArabic ? "استخدام الإيموجي" : "Include emojis" }}</span>
                            <select v-model="socialPostState.include_emojis">
                                <option :value="null">{{ isArabic ? "افتراضي" : "Default" }}</option>
                                <option :value="true">{{ isArabic ? "نعم" : "Yes" }}</option>
                                <option :value="false">{{ isArabic ? "لا" : "No" }}</option>
                            </select>
                        </label>

                        <fieldset class="wide-field extra-options-field">
                            <legend>{{ isArabic ? "خيارات إضافية" : "Extra options" }}</legend>
                            <label v-for="option in socialPostExtraOptions" :key="option" class="check-option">
                                <input v-model="socialPostState.extra_options" type="checkbox" :value="option">
                                <span>{{ option }}</span>
                            </label>
                        </fieldset>
                    </div>
                </div>

                <div v-if="isEmailWriterTool" class="advanced-options">
                    <button type="button" class="advanced-options-toggle" @click="emailWriterOptionsOpen = !emailWriterOptionsOpen">
                        <span>
                            <i class="bi bi-sliders"></i>
                            {{ isArabic ? "خيارات متقدمة" : "Advanced options" }}
                        </span>
                        <i class="bi" :class="emailWriterOptionsOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                    </button>

                    <div v-if="emailWriterOptionsOpen" class="advanced-options-grid">
                        <label class="wide-field">
                            <span>{{ isArabic ? "الغرض من الإيميل" : "Email purpose" }}</span>
                            <textarea v-model="emailWriterState.purpose" rows="2"></textarea>
                        </label>

                        <label>
                            <span>{{ isArabic ? "المستلم" : "Recipient" }}</span>
                            <input v-model="emailWriterState.recipient" type="text">
                        </label>

                        <label>
                            <span>{{ isArabic ? "اسم المرسل" : "Sender name" }}</span>
                            <input v-model="emailWriterState.sender_name" type="text">
                        </label>

                        <label>
                            <span>{{ isArabic ? "سطر العنوان" : "Subject line" }}</span>
                            <input v-model="emailWriterState.subject_line" type="text">
                        </label>

                        <label>
                            <span>{{ isArabic ? "دعوة لاتخاذ إجراء" : "Call to action" }}</span>
                            <input v-model="emailWriterState.call_to_action" type="text">
                        </label>

                        <label v-for="field in emailWriterSelectFields" :key="field.key">
                            <span>{{ isArabic ? field.labelAr : field.labelEn }}</span>
                            <select v-model="emailWriterState[field.key]">
                                <option :value="null">{{ isArabic ? "افتراضي" : "Default" }}</option>
                                <option v-for="option in field.options" :key="option" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                        </label>

                        <label>
                            <span>{{ isArabic ? "تضمين عنوان" : "Include subject" }}</span>
                            <select v-model="emailWriterState.include_subject">
                                <option :value="null">{{ isArabic ? "افتراضي" : "Default" }}</option>
                                <option :value="true">{{ isArabic ? "نعم" : "Yes" }}</option>
                                <option :value="false">{{ isArabic ? "لا" : "No" }}</option>
                            </select>
                        </label>

                        <fieldset class="wide-field extra-options-field">
                            <legend>{{ isArabic ? "خيارات إضافية" : "Extra options" }}</legend>
                            <label v-for="option in emailWriterExtraOptions" :key="option" class="check-option">
                                <input v-model="emailWriterState.extra_options" type="checkbox" :value="option">
                                <span>{{ option }}</span>
                            </label>
                        </fieldset>
                    </div>
                </div>

                <div v-if="isScriptGeneratorTool" class="advanced-options">
                    <button type="button" class="advanced-options-toggle" @click="scriptGeneratorOptionsOpen = !scriptGeneratorOptionsOpen">
                        <span>
                            <i class="bi bi-sliders"></i>
                            {{ isArabic ? "خيارات متقدمة" : "Advanced options" }}
                        </span>
                        <i class="bi" :class="scriptGeneratorOptionsOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                    </button>

                    <div v-if="scriptGeneratorOptionsOpen" class="advanced-options-grid">
                        <label class="wide-field">
                            <span>{{ isArabic ? "موضوع السكريبت" : "Script topic" }}</span>
                            <textarea v-model="scriptGeneratorState.topic" rows="2"></textarea>
                        </label>

                        <label v-for="field in scriptGeneratorSelectFields" :key="field.key">
                            <span>{{ isArabic ? field.labelAr : field.labelEn }}</span>
                            <select v-model="scriptGeneratorState[field.key]">
                                <option :value="null">{{ isArabic ? "افتراضي" : "Default" }}</option>
                                <option v-for="option in field.options" :key="option" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                        </label>

                        <label>
                            <span>{{ isArabic ? "عدد النتائج" : "Results count" }}</span>
                            <input v-model.number="scriptGeneratorState.results_count" type="number" min="1" max="10">
                        </label>

                        <label>
                            <span>{{ isArabic ? "تضمين ملاحظات المشاهد" : "Include scene notes" }}</span>
                            <select v-model="scriptGeneratorState.include_scene_notes">
                                <option :value="null">{{ isArabic ? "افتراضي" : "Default" }}</option>
                                <option :value="true">{{ isArabic ? "نعم" : "Yes" }}</option>
                                <option :value="false">{{ isArabic ? "لا" : "No" }}</option>
                            </select>
                        </label>

                        <fieldset class="wide-field extra-options-field">
                            <legend>{{ isArabic ? "خيارات إضافية" : "Extra options" }}</legend>
                            <label v-for="option in scriptGeneratorExtraOptions" :key="option" class="check-option">
                                <input v-model="scriptGeneratorState.extra_options" type="checkbox" :value="option">
                                <span>{{ option }}</span>
                            </label>
                        </fieldset>
                    </div>
                </div>

                <div v-if="isProductDescriptionGeneratorTool" class="advanced-options">
                    <button type="button" class="advanced-options-toggle" @click="productOptionsOpen = !productOptionsOpen">
                        <span>
                            <i class="bi bi-sliders"></i>
                            {{ isArabic ? "خيارات متقدمة" : "Advanced options" }}
                        </span>
                        <i class="bi" :class="productOptionsOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                    </button>

                    <div v-if="productOptionsOpen" class="advanced-options-grid">
                        <label>
                            <span>{{ isArabic ? "اسم المنتج" : "Product name" }}</span>
                            <input v-model="productDescriptionState.product" type="text">
                        </label>
                        <label>
                            <span>{{ isArabic ? "العلامة التجارية" : "Brand name" }}</span>
                            <input v-model="productDescriptionState.brand_name" type="text">
                        </label>
                        <label class="wide-field">
                            <span>{{ isArabic ? "مميزات المنتج" : "Product features" }}</span>
                            <textarea v-model="productDescriptionState.product_features" rows="2"></textarea>
                        </label>
                        <label>
                            <span>{{ isArabic ? "الجمهور المستهدف" : "Target audience" }}</span>
                            <input v-model="productDescriptionState.target_audience" type="text">
                        </label>
                        <label v-for="field in productDescriptionSelectFields" :key="field.key">
                            <span>{{ isArabic ? field.labelAr : field.labelEn }}</span>
                            <select v-model="productDescriptionState[field.key]">
                                <option :value="null">{{ isArabic ? "افتراضي" : "Default" }}</option>
                                <option v-for="option in field.options" :key="option" :value="option">
                                    {{ option }}
                                </option>
                            </select>
                        </label>
                        <label>
                            <span>{{ isArabic ? "تضمين نقاط مختصرة" : "Include bullets" }}</span>
                            <select v-model="productDescriptionState.include_bullets">
                                <option :value="null">{{ isArabic ? "افتراضي" : "Default" }}</option>
                                <option :value="true">{{ isArabic ? "نعم" : "Yes" }}</option>
                                <option :value="false">{{ isArabic ? "لا" : "No" }}</option>
                            </select>
                        </label>
                        <label>
                            <span>{{ isArabic ? "تضمين كلمات SEO" : "Include SEO keywords" }}</span>
                            <select v-model="productDescriptionState.include_seo_keywords">
                                <option :value="null">{{ isArabic ? "افتراضي" : "Default" }}</option>
                                <option :value="true">{{ isArabic ? "نعم" : "Yes" }}</option>
                                <option :value="false">{{ isArabic ? "لا" : "No" }}</option>
                            </select>
                        </label>
                        <fieldset class="wide-field extra-options-field">
                            <legend>{{ isArabic ? "خيارات إضافية" : "Extra options" }}</legend>
                            <label v-for="option in productDescriptionExtraOptions" :key="option" class="check-option">
                                <input v-model="productDescriptionState.extra_options" type="checkbox" :value="option">
                                <span>{{ option }}</span>
                            </label>
                        </fieldset>
                    </div>
                </div>

                <div class="input-box" :class="{ focused: inputFocused }">
                    <textarea ref="textareaRef" v-model="userInput" class="chat-input"
                        :aria-label="inputAriaLabel" :placeholder="chatPlaceholder" rows="1"
                        :disabled="chatSendDisabled" @focus="inputFocused = true" @blur="inputFocused = false"
                        @keydown.enter.exact.prevent="onSubmitMessage" @keydown.shift.enter.exact="newLine"
                        @input="autoResize"></textarea>

                    <div class="input-actions">
                        <span class="char-count">{{ userInput.length }}</span>

                        <button v-if="hideSearchToggle" type="button" class="search-toggle-btn"
                            :class="{ active: searchEnabled }"
                            :aria-label="searchEnabled ? 'Disable web search' : 'Enable web search'"
                            :disabled="chatSendDisabled" @click="searchEnabled = !searchEnabled">
                            <i class="bi bi-search"></i>
                        </button>

                        <button type="button" class="send-btn" :aria-label="t('user.chat.sendAria')"
                            :disabled="!canSubmitCurrentTool || chatSendDisabled" @click="onSubmitMessage">
                            <i class="bi"
                                :class="sendingMessage || streamingAssistant ? 'bi-hourglass-split' : 'bi-send-fill'"></i>
                        </button>
                    </div>
                </div>

                <p class="input-hint">
                    <i class="bi bi-info-circle"></i>
                    {{ t("user.chat.inputHint") }}
                </p>
            </div>
        </div>
    </main>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import MarkdownIt from "markdown-it";
import DOMPurify from "dompurify";
import homeService from "@/services/home/homeService";
import chatServices from "@/services/chat/chatServices";
import useSeoMeta from "@/composables/useSeoMeta";

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();

const isArabic = computed(() =>
    String(locale.value || homeService.getLang() || "ar").toLowerCase() === "ar"
);

const isAuthenticated = () => Boolean(localStorage.getItem("auth_token"));

const redirectToAuth = async () => {
    const lang = localStorage.getItem("lang") || "en";
    await router.push(`/${lang}/auth`);
};

const requireAuth = async () => {
    if (isAuthenticated()) return true;
    await redirectToAuth();
    return false;
};

const toolLoading = ref(true);
const loadingConversations = ref(true);
const loadingMessages = ref(false);
const creatingConversation = ref(false);
const sendingMessage = ref(false);
const streamingAssistant = ref(false);
const conversationLimitExceeded = ref(false);
const insufficientPoints = ref(false);
const removingConversationUuid = ref("");

const subtool = ref({
    id: null,
    name: "",
    description: "",
    promptPlaceholder: "",
    imageUrl: "",
    optimizedImageUrl: "",
});

const conversations = ref([]);
const activeConversation = ref(null);
const messages = ref([]);
const userInput = ref("");
const searchEnabled = ref(false);
const inputFocused = ref(false);
const sidebarOpen = ref(false);
const messagesContainer = ref(null);
const textareaRef = ref(null);
const activeEventSource = ref(null);
const streamingConversationUuid = ref("");
const copiedMessageKey = ref(null);

const PENDING_SEND_TTL = 5 * 60 * 1000;
const inFlightSignatures = new Set();

const filteredConversations = computed(() =>
    conversations.value.filter((conversation) =>
        !subtool.value.id || conversation.sub_tool_id === subtool.value.id
    )
);

const chatSendDisabled = computed(() =>
    conversationLimitExceeded.value ||
    sendingMessage.value ||
    streamingAssistant.value
);

const seoTitle = computed(() =>
    isArabic.value
        ? `${subtool.value.name || t("user.chat.workspaceTitle")} | Ai Pro`
        : `${subtool.value.name || t("user.chat.workspaceTitle")} | Ai Pro`
);

const seoDescription = computed(() =>
    isArabic.value
        ? "ØªØ­Ø¯Ø« Ù…Ø¹ Ø§Ù„Ø£Ø¯Ø§Ø© Ø§Ù„ÙØ±Ø¹ÙŠØ© Ø§Ù„Ù…Ø®ØªØ§Ø±Ø©ØŒ Ù†Ø¸Ù‘Ù… Ø³Ø¬Ù„ Ø§Ù„Ù…Ø­Ø§Ø¯Ø«Ø§ØªØŒ ÙˆØ£Ø±Ø³Ù„ Ø±Ø³Ø§Ø¦Ù„Ùƒ ÙÙŠ Ù…Ø³Ø§Ø­Ø© Ø¹Ù…Ù„ Ù…Ø±ÙƒØ²Ø© Ù…Ø¹ Ø§Ø³ØªØ¬Ø§Ø¨Ø§Øª ÙÙˆØ±ÙŠØ© ÙˆØ³ÙŠØ§Ù‚ ÙˆØ§Ø¶Ø­ Ù„ÙƒÙ„ Ù…Ø­Ø§Ø¯Ø«Ø©."
        : "Chat with your selected AI subtool, manage conversation history, and send messages in a focused workspace with real-time responses and organized context."
);

useSeoMeta({
    title: seoTitle,
    description: seoDescription,
});

const now = () => {
    const date = new Date();
    return `${date.getHours()}:${String(date.getMinutes()).padStart(2, "0")}`;
};

const createTempId = () => `temp_${Date.now()}_${Math.random().toString(16).slice(2)}`;

const createClientMessageId = () => {
    if (window?.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    return `msg-${Date.now()}-${Math.random().toString(16).slice(2)}`;
};

const pendingSendStorageKey = (conversationUuid) =>
    `chat-pending-send:${conversationUuid || "unknown"}`;

const readPendingSend = (conversationUuid) => {
    if (!conversationUuid) return null;

    try {
        const raw = sessionStorage.getItem(pendingSendStorageKey(conversationUuid));
        if (!raw) return null;

        const parsed = JSON.parse(raw);

        if (!parsed?.idempotencyKey || !parsed?.content || !parsed?.expiresAt) {
            sessionStorage.removeItem(pendingSendStorageKey(conversationUuid));
            return null;
        }

        if (parsed.expiresAt < Date.now()) {
            sessionStorage.removeItem(pendingSendStorageKey(conversationUuid));
            return null;
        }

        return parsed;
    } catch {
        return null;
    }
};

const writePendingSend = (conversationUuid, content, idempotencyKey) => {
    if (!conversationUuid) return;

    try {
        sessionStorage.setItem(
            pendingSendStorageKey(conversationUuid),
            JSON.stringify({
                content,
                idempotencyKey,
                expiresAt: Date.now() + PENDING_SEND_TTL,
            })
        );
    } catch {
        // Ignore storage edge cases.
    }
};

const clearPendingSend = (conversationUuid) => {
    if (!conversationUuid) return;

    try {
        sessionStorage.removeItem(pendingSendStorageKey(conversationUuid));
    } catch {
        // Ignore storage edge cases.
    }
};

const resolveIdempotencyKey = (conversationUuid, content, options = {}) => {
    const pending = options?.forceNew ? null : readPendingSend(conversationUuid);

    if (pending && pending.content === content) {
        return pending.idempotencyKey;
    }

    const idempotencyKey = createClientMessageId();
    writePendingSend(conversationUuid, content, idempotencyKey);

    return idempotencyKey;
};

const cleanConversationTitleText = (text = "") =>
    String(text || "")
        .replace(/<[^>]*>/g, " ")
        .replace(/[#*_`"'$%^&]/g, "")
        .replace(/[â€œâ€â€˜â€™]/g, "")
        .replace(/\s+/g, " ")
        .trim();

const getFirstUserMessageContent = (conversation = {}) => {
    if (typeof conversation.first_user_message_content === "string") {
        return conversation.first_user_message_content;
    }

    if (typeof conversation.first_message_content === "string") {
        return conversation.first_message_content;
    }

    if (typeof conversation.first_user_message === "string") {
        return conversation.first_user_message;
    }

    if (typeof conversation.first_message === "string") {
        return conversation.first_message;
    }

    if (conversation.first_user_message?.content) {
        return conversation.first_user_message.content;
    }

    if (conversation.first_message?.content) {
        return conversation.first_message.content;
    }

    const rows = Array.isArray(conversation.message)
        ? conversation.message
        : Array.isArray(conversation.messages)
            ? conversation.messages
            : [];

    const firstUserMessage = rows.find((message) =>
        message?.role === "user" && message?.content
    );

    return firstUserMessage?.content || "";
};

const makeConversationTitle = (conversation = {}) => {
    const firstUserContent = cleanConversationTitleText(
        getFirstUserMessageContent(conversation)
    );

    const words = firstUserContent
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 3);

    if (words.length) {
        return words.join(" ");
    }

    return t("user.chat.conversationTitle", {
        short: String(conversation.uuid || "").slice(-6) || conversation.id,
    });
};

const formatConversation = (conversation) => ({
    ...conversation,
    title: makeConversationTitle(conversation),
    subtitle: t("user.chat.conversationSubtitle", {
        uuid: conversation.uuid,
    }),
});

const mapMessage = (message, index = 0) => ({
    ...message,
    localKey: `${message.role || "message"}-${index}-${message.id || message.created_at || message.content}`,
    time: message.created_at
        ? new Intl.DateTimeFormat("en-US", {
            hour: "2-digit",
            minute: "2-digit",
        }).format(new Date(message.created_at))
        : now(),
});

const markdownParser = new MarkdownIt({
    html: false,
    breaks: true,
    linkify: true,
    typographer: true,
});

const escapeHtml = (text = "") =>
    String(text || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");

const formatMessage = (text = "", role = "") => {
    const safeText = escapeHtml(String(text || ""));
    const renderedHtml = markdownParser.render(safeText);

    return DOMPurify.sanitize(renderedHtml, {
        USE_PROFILES: { html: true },
    });
};

const hasInsufficientPointsContent = (content = "") => {
    const normalized = String(content || "").toLowerCase();

    return (
        normalized.includes("insufficient points") ||
        normalized.includes("recharge your wallet") ||
        normalized.includes("wallet to continue")
    );
};

const resolveInsufficientPointsState = (rows = []) => {
    const lastAssistantMessage = [...rows]
        .reverse()
        .find((message) => message?.role === "assistant");

    insufficientPoints.value = Boolean(
        lastAssistantMessage?.is_error === true &&
        hasInsufficientPointsContent(lastAssistantMessage?.content || "")
    );
};

const scrollToBottom = async () => {
    await nextTick();

    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
};

const addAssistantTypingMessage = () => {
    const typingId = createTempId();

    const typingMessage = mapMessage(
        {
            id: typingId,
            uuid: typingId,
            role: "assistant",
            sender: "assistant",
            content: "",
            isTyping: true,
            created_at: new Date().toISOString(),
            localOnly: true,
            plainText: true,
        },
        messages.value.length
    );

    messages.value.push(typingMessage);

    nextTick(() => {
        scrollToBottom();
    });

    return typingId;
};

const removeAssistantTypingMessage = (typingId) => {
    if (!typingId) return;

    messages.value = messages.value.filter((message) =>
        message.id !== typingId
        && message.uuid !== typingId
    );
};

const clearLocalTypingMessages = () => {
    messages.value = messages.value.filter((message) => !message?.localOnly);
};

const autoResize = () => {
    const el = textareaRef.value;
    if (!el) return;

    el.style.height = "auto";
    el.style.height = `${Math.min(el.scrollHeight, 180)}px`;
};

const resetTextarea = () => {
    if (textareaRef.value) {
        textareaRef.value.style.height = "auto";
    }
};

const closeAssistantStream = () => {
    if (activeEventSource.value) {
        activeEventSource.value.close();
        activeEventSource.value = null;
    }

    streamingAssistant.value = false;
    streamingConversationUuid.value = "";
};

const streamUrl = (uuid, afterId) => {
    const token = localStorage.getItem("auth_token") || "";

    const params = new URLSearchParams({
        after_id: String(afterId || 0),
        token,
    });

    return `/api/v1/conversation/${uuid}/stream?${params.toString()}`;
};

const openAssistantStream = async (conversation, afterId) => {
    if (!conversation?.uuid || streamingAssistant.value) return;

    const assistantMessage = mapMessage(
        {
            content: "",
            role: "assistant",
            created_at: new Date().toISOString(),
            streaming: true,
        },
        messages.value.length
    );

    messages.value.push(assistantMessage);
    streamingAssistant.value = true;
    streamingConversationUuid.value = conversation.uuid;

    await scrollToBottom();

    const source = new EventSource(streamUrl(conversation.uuid, afterId));
    activeEventSource.value = source;

    source.onmessage = async (event) => {
        const payload = JSON.parse(event.data || "{}");
        const index = messages.value.findIndex((item) => item.localKey === assistantMessage.localKey);

        if (index === -1) return;

        if (payload.type === "token") {
            messages.value[index].content += payload.content || "";
            await scrollToBottom();
        }

        if (payload.type === "error") {
            const errorContent = payload.content || t("user.chat.genericError");

            messages.value[index].content = errorContent;
            messages.value[index].streaming = false;
            messages.value[index].is_error = true;

            if (hasInsufficientPointsContent(errorContent)) {
                insufficientPoints.value = true;
            }

            closeAssistantStream();
        }

        if (payload.type === "done") {
            messages.value[index] = mapMessage(
                {
                    ...(payload.message || messages.value[index]),
                    content: payload.message?.content || messages.value[index].content,
                    role: "assistant",
                    streaming: false,
                },
                index
            );

            if (
                payload.message?.is_error === true &&
                hasInsufficientPointsContent(payload.message?.content || "")
            ) {
                insufficientPoints.value = true;
            }

            closeAssistantStream();
            await scrollToBottom();
        }
    };

    source.onerror = () => {
        const index = messages.value.findIndex((item) => item.localKey === assistantMessage.localKey);

        if (index !== -1 && !messages.value[index].content) {
            messages.value[index].content = t("user.chat.connectionInterrupted");
            messages.value[index].streaming = false;
            messages.value[index].is_error = true;
        }

        closeAssistantStream();
    };
};

const hideSearchToggle = computed(() =>
    Number(subtool.value?.id) === 1
);

const TEXT_EDITOR_SUB_TOOL_ID = 1;
const TEXT_EDITOR_TOOL_KEY = "ai_text_editor";
const TEXT_SUMMARIZER_SUB_TOOL_ID = 2;
const TEXT_SUMMARIZER_TOOL_KEY = "ai_text_summarizer";
const TEXT_SUMMARIZER_TASK_KEY = "summarizer";
const PARAPHRASER_SUB_TOOL_ID = 3;
const PARAPHRASER_TOOL_KEY = "ai_paraphraser";
const HEADLINE_GENERATOR_SUB_TOOL_ID = 4;
const HEADLINE_DEBUG_MODE = true;
const SOCIAL_POST_GENERATOR_SUB_TOOL_ID = 5;
const SOCIAL_POST_GENERATOR_TOOL_KEY = "ai_social_post_generator";
const EMAIL_WRITER_SUB_TOOL_ID = 6;
const EMAIL_WRITER_TOOL_KEY = "ai_email_writer";
const SCRIPT_GENERATOR_SUB_TOOL_ID = 7;
const SCRIPT_GENERATOR_TOOL_KEY = "ai_script_generator";
const PRODUCT_DESCRIPTION_GENERATOR_SUB_TOOL_ID = 8;
const PRODUCT_DESCRIPTION_GENERATOR_TOOL_KEY = "ai_product_description_generator";
const PRODUCT_DESCRIPTION_GENERATOR_MODEL_KEY = "product_description_generator";

const getInitialHeadlineState = () => ({
    content: null,
    content_type: null,
    goal: null,
    language: null,
    tone: null,
    number_of_headlines: null,
    headline_length: null,
    extra_options: [],
});

const headlineState = ref(getInitialHeadlineState());

const createEmptyParaphraserState = () => ({
    content: null,
    language: null,
    tone: null,
    rewrite_mode: null,
    change_level: null,
    results_count: null,
    extra_options: [],
});

const paraphraserState = ref(createEmptyParaphraserState());

const createEmptySocialPostState = () => ({
    content: null,
    platform: null,
    language: null,
    tone: null,
    audience: null,
    goal: null,
    length: null,
    hashtag_count: null,
    include_emojis: null,
    results_count: 2,
    extra_options: [],
    last_output: null,
});

const socialPostState = ref(createEmptySocialPostState());
const socialPostOptionsOpen = ref(false);

const socialPostSelectFields = [
    {
        key: "platform",
        labelAr: "المنصة",
        labelEn: "Platform",
        options: ["Facebook", "Instagram", "LinkedIn", "X", "TikTok"],
    },
    {
        key: "language",
        labelAr: "اللغة",
        labelEn: "Language",
        options: ["Arabic", "English", "French", "Chinese", "Russian"],
    },
    {
        key: "tone",
        labelAr: "النبرة",
        labelEn: "Tone",
        options: ["Engaging", "Professional", "Friendly", "Formal", "Persuasive", "Simple"],
    },
    {
        key: "audience",
        labelAr: "الجمهور المستهدف",
        labelEn: "Audience",
        options: ["General Audience", "Customers", "Business Owners", "Content Creators", "Students", "Professionals"],
    },
    {
        key: "goal",
        labelAr: "الهدف",
        labelEn: "Goal",
        options: ["Engagement", "Awareness", "Sales", "Traffic", "Branding"],
    },
    {
        key: "length",
        labelAr: "الطول",
        labelEn: "Length",
        options: ["Short", "Medium", "Long"],
    },
];

const socialPostExtraOptions = [
    "Make it ready to publish",
    "Include call to action",
    "Use catchy opening",
    "SEO-friendly",
    "Hashtag-friendly",
    "Emotional style",
    "Professional style",
    "Simple and direct",
];

const createEmptyEmailWriterState = () => ({
    purpose: null,
    email_type: null,
    recipient: null,
    sender_name: null,
    language: null,
    tone: null,
    length: null,
    subject_line: null,
    call_to_action: null,
    include_subject: null,
    extra_options: [],
    last_output: null,
});

const emailWriterState = ref(createEmptyEmailWriterState());
const emailWriterOptionsOpen = ref(false);

const emailWriterSelectFields = [
    {
        key: "email_type",
        labelAr: "نوع الإيميل",
        labelEn: "Email type",
        options: ["General Email", "Business Email", "Marketing Email", "Support Email", "Follow-up Email", "Apology Email", "Sales Email"],
    },
    {
        key: "language",
        labelAr: "اللغة",
        labelEn: "Language",
        options: ["Arabic", "English", "French", "Chinese", "Russian"],
    },
    {
        key: "tone",
        labelAr: "النبرة",
        labelEn: "Tone",
        options: ["Professional", "Formal", "Friendly", "Casual", "Persuasive", "Simple"],
    },
    {
        key: "length",
        labelAr: "الطول",
        labelEn: "Length",
        options: ["Short", "Medium", "Long"],
    },
];

const emailWriterExtraOptions = [
    "Clear structure",
    "Ready to send",
    "Polite opening",
    "Strong subject line",
    "Professional closing",
    "Include call to action",
    "Simple and direct",
    "Persuasive style",
];

const createEmptyScriptGeneratorState = () => ({
    topic: null,
    script_type: null,
    platform: null,
    language: null,
    tone: null,
    audience: null,
    duration: null,
    format: null,
    include_scene_notes: null,
    results_count: 2,
    extra_options: [],
    last_output: null,
});

const scriptGeneratorState = ref(createEmptyScriptGeneratorState());
const scriptGeneratorOptionsOpen = ref(false);

const scriptGeneratorSelectFields = [
    {
        key: "script_type",
        labelAr: "نوع السكريبت",
        labelEn: "Script type",
        options: ["Video Script", "Ad Script", "YouTube Script", "TikTok Script", "Reel Script", "Podcast Script", "Voice Over Script"],
    },
    {
        key: "platform",
        labelAr: "المنصة",
        labelEn: "Platform",
        options: ["YouTube", "TikTok", "Instagram Reels", "Facebook", "X", "Podcast", "General"],
    },
    {
        key: "language",
        labelAr: "اللغة",
        labelEn: "Language",
        options: ["Arabic", "English", "French", "Chinese", "Russian"],
    },
    {
        key: "tone",
        labelAr: "النبرة",
        labelEn: "Tone",
        options: ["Engaging", "Professional", "Friendly", "Formal", "Persuasive", "Funny", "Emotional", "Simple"],
    },
    {
        key: "audience",
        labelAr: "الجمهور المستهدف",
        labelEn: "Target audience",
        options: ["General Audience", "Customers", "Business Owners", "Content Creators", "Students", "Professionals", "Parents", "Youth"],
    },
    {
        key: "duration",
        labelAr: "مدة السكريبت",
        labelEn: "Script duration",
        options: ["15 seconds", "30 seconds", "60 seconds", "90 seconds", "2 minutes", "3 minutes", "5 minutes"],
    },
    {
        key: "format",
        labelAr: "تنسيق السكريبت",
        labelEn: "Script format",
        options: ["Hook + Body + CTA", "Scene by Scene", "Voice Over", "Dialogue", "Bullet Script", "Full Script"],
    },
];

const scriptGeneratorExtraOptions = [
    "Strong hook",
    "Include call to action",
    "Scene by scene",
    "Add visual notes",
    "Add voice over direction",
    "Short and catchy",
    "Emotional style",
    "Professional style",
    "Ready to record",
    "Simple and direct",
];

const createEmptyProductDescriptionState = () => ({
    product: null,
    brand_name: null,
    product_features: null,
    target_audience: null,
    language: null,
    tone: null,
    length: null,
    platform: null,
    include_bullets: null,
    include_seo_keywords: null,
    extra_options: [],
    last_output: null,
});

const productDescriptionState = ref(createEmptyProductDescriptionState());
const productOptionsOpen = ref(false);
const textSummarizerLastRequest = ref(null);

const productDescriptionSelectFields = [
    {
        key: "language",
        labelAr: "اللغة",
        labelEn: "Language",
        options: ["Arabic", "English", "French", "Chinese", "Russian"],
    },
    {
        key: "tone",
        labelAr: "النبرة",
        labelEn: "Tone",
        options: ["Marketing", "Professional", "Luxury", "Friendly", "Persuasive", "Simple"],
    },
    {
        key: "length",
        labelAr: "الطول",
        labelEn: "Length",
        options: ["Short", "Medium", "Long"],
    },
    {
        key: "platform",
        labelAr: "المنصة",
        labelEn: "Platform",
        options: ["Website / Store", "Social Media", "Amazon", "Noon", "Shopify", "Landing Page"],
    },
];

const productDescriptionExtraOptions = [
    "Benefit-focused",
    "Clear and persuasive",
    "SEO-friendly",
    "Emotional style",
    "Luxury style",
    "Simple and direct",
];

const isHeadlineGeneratorTool = computed(() =>
    Number(subtool.value?.id) === HEADLINE_GENERATOR_SUB_TOOL_ID
);

const isParaphraserTool = computed(() =>
    Number(subtool.value?.id) === PARAPHRASER_SUB_TOOL_ID
);

const isTextSummarizerTool = computed(() =>
    Number(subtool.value?.id) === TEXT_SUMMARIZER_SUB_TOOL_ID
);

const isSocialPostGeneratorTool = computed(() =>
    Number(subtool.value?.id) === SOCIAL_POST_GENERATOR_SUB_TOOL_ID
);

const isEmailWriterTool = computed(() =>
    Number(subtool.value?.id) === EMAIL_WRITER_SUB_TOOL_ID
);

const isScriptGeneratorTool = computed(() =>
    Number(subtool.value?.id) === SCRIPT_GENERATOR_SUB_TOOL_ID
);

const isProductDescriptionGeneratorTool = computed(() =>
    Number(subtool.value?.id) === PRODUCT_DESCRIPTION_GENERATOR_SUB_TOOL_ID
);

const canSubmitCurrentTool = computed(() =>
    Boolean(
        userInput.value.trim()
        || (
            isSocialPostGeneratorTool.value
            && String(socialPostState.value.content || "").trim()
        )
        || (
            isEmailWriterTool.value
            && String(emailWriterState.value.purpose || "").trim()
        )
        || (
            isScriptGeneratorTool.value
            && String(scriptGeneratorState.value.topic || "").trim()
        )
        || (
            isProductDescriptionGeneratorTool.value
            && String(productDescriptionState.value.product || "").trim()
        )
    )
);

const chatPlaceholder = computed(() => {
    if (conversationLimitExceeded.value) {
        return "This conversation has reached the maximum limit. Start a new chat to continue.";
    }

    if (isProductDescriptionGeneratorTool.value) {
        return isArabic.value
            ? "اكتب وصف المنتج أو فكرته هنا"
            : "Write the product description or idea here";
    }

    if (isScriptGeneratorTool.value) {
        return isArabic.value
            ? "اكتب موضوع السكريبت أو فكرته هنا"
            : "Write the script topic or idea here";
    }

    return subtool.value.promptPlaceholder || t("user.chat.inputPlaceholder");
});

const inputAriaLabel = computed(() => {
    if (isHeadlineGeneratorTool.value) return "اكتب رسالتك الخاصة بتوليد العناوين";
    if (isSocialPostGeneratorTool.value) return "اكتب طلبك لتوليد منشور السوشيال";
    if (isEmailWriterTool.value) return "اكتب طلبك لكتابة الإيميل";
    if (isScriptGeneratorTool.value) return "اكتب طلبك لتوليد السكريبت";
    if (isProductDescriptionGeneratorTool.value) {
        return isArabic.value
            ? "اكتب وصف المنتج أو فكرته هنا"
            : "Write the product description or idea here";
    }
    return t("user.chat.inputAria");
});

const typingAriaLabel = computed(() =>
    isArabic.value ? "جاري كتابة الرد" : "Assistant is typing"
);

const typingText = computed(() =>
    isArabic.value ? "جاري الكتابة" : "Assistant is typing"
);

const HEADLINE_FIELD_LABELS = {
    content: "الموضوع",
    content_type: "نوع المحتوى",
    goal: "الهدف",
    language: "اللغة",
    tone: "النبرة",
    number_of_headlines: "عدد العناوين",
    headline_length: "طول العنوان",
    extra_options: "تحسين SEO",
};

const HEADLINE_VALUE_LABELS = {
    Article: "مقال",
    News: "خبر",
    Product: "وصف منتج",
    "Social Post": "منشور اجتماعي",
    "Attract Attention": "جذب الانتباه",
    "Improve SEO": "تحسين SEO",
    Arabic: "العربية",
    English: "الإنجليزية",
    Powerful: "قوية",
    Formal: "رسمية",
    Professional: "احترافية",
    Auto: "تلقائي",
    Short: "قصير",
    Medium: "متوسط",
    Long: "طويل",
    "Include SEO-friendly headlines": "نعم، عناوين مناسبة للسيو",
};

const SOCIAL_POST_FIELD_LABELS = {
    content: "موضوع المنشور",
    platform: "المنصة",
    language: "اللغة",
    tone: "النبرة",
    audience: "الجمهور المستهدف",
    goal: "الهدف",
    length: "طول المنشور",
    hashtag_count: "عدد الهاشتاقات",
    include_emojis: "استخدام الإيموجي",
    results_count: "عدد النتائج",
    extra_options: "خيارات إضافية",
};

const SOCIAL_POST_VALUE_LABELS = {
    LinkedIn: "لينكدإن",
    Facebook: "فيسبوك",
    Instagram: "إنستغرام",
    X: "منصة إكس",
    Twitter: "منصة إكس",
    Arabic: "العربية",
    English: "الإنجليزية",
    Engaging: "جذابة",
    Professional: "احترافية",
    Formal: "رسمية",
    Friendly: "ودية",
    "General Audience": "جمهور عام",
    Engagement: "زيادة التفاعل",
    Awareness: "زيادة الوعي",
    Sales: "المبيعات",
    Short: "قصير",
    Medium: "متوسط",
    Long: "طويل",
    "Make it ready to publish": "جعله جاهزًا للنشر",
};

const EMAIL_WRITER_FIELD_LABELS = {
    purpose: "الغرض من الإيميل",
    email_type: "نوع الإيميل",
    recipient: "المستلم",
    sender_name: "اسم المرسل",
    language: "اللغة",
    tone: "النبرة",
    length: "طول الإيميل",
    subject_line: "عنوان الإيميل",
    call_to_action: "الدعوة لاتخاذ إجراء",
    include_subject: "تضمين العنوان",
    extra_options: "خيارات إضافية",
};

const SCRIPT_GENERATOR_FIELD_LABELS = {
    topic: "موضوع السكريبت",
    script_type: "نوع السكريبت",
    platform: "المنصة",
    language: "اللغة",
    tone: "النبرة",
    audience: "الجمهور المستهدف",
    duration: "مدة السكريبت",
    format: "تنسيق السكريبت",
    include_scene_notes: "تضمين ملاحظات المشاهد",
    results_count: "عدد النتائج",
    extra_options: "خيارات إضافية",
};

const EMAIL_WRITER_VALUE_LABELS = {
    Arabic: "العربية",
    English: "الإنجليزية",
    Professional: "احترافية",
    Formal: "رسمية",
    Friendly: "ودية",
    Casual: "غير رسمية",
    Short: "قصير",
    Medium: "متوسط",
    Long: "طويل",
    "General Email": "إيميل عام",
    "Business Email": "إيميل أعمال",
    "Marketing Email": "إيميل تسويقي",
    "Support Email": "إيميل دعم",
    "General Recipient": "مستلم عام",
    "Clear structure": "هيكل واضح",
    "Ready to send": "جاهز للإرسال",
};

const humanizeHeadlineValue = (value) => {
    if (value === null || value === undefined || value === "") return "غير محدد";

    if (Array.isArray(value)) {
        return value.length
            ? value.map((item) => HEADLINE_VALUE_LABELS[item] || String(item)).join("، ")
            : "لا";
    }

    return HEADLINE_VALUE_LABELS[value] || String(value);
};

const isEmptyHeadlineValue = (value) => {
    if (Array.isArray(value)) return false;
    return value === null || value === undefined || value === "";
};

const getMissingHeadlineFields = (state = {}) => {
    return Object.entries(state)
        .filter(([key, value]) => key !== "extra_options" && isEmptyHeadlineValue(value))
        .map(([key]) => key);
};

const mergeHeadlineState = (oldState = {}, newState = {}) => {
    const merged = { ...getInitialHeadlineState(), ...oldState };

    Object.entries(newState || {}).forEach(([key, value]) => {
        if (Array.isArray(value)) {
            merged[key] = value;
            return;
        }

        if (value !== null && value !== undefined && value !== "") {
            merged[key] = value;
        } else if (!(key in merged)) {
            merged[key] = value;
        }
    });

    return merged;
};

const normalizeParaphraserState = (state = {}) => {
    const base = createEmptyParaphraserState();
    const candidate = state && typeof state === "object" && !Array.isArray(state) ? state : {};
    const merged = { ...base, ...candidate };

    merged.content = merged.content === null || merged.content === undefined
        ? null
        : String(merged.content).trim() || null;
    merged.language = merged.language === null || merged.language === undefined
        ? null
        : String(merged.language).trim() || null;
    merged.tone = merged.tone === null || merged.tone === undefined
        ? null
        : String(merged.tone).trim() || null;
    merged.rewrite_mode = merged.rewrite_mode === null || merged.rewrite_mode === undefined
        ? null
        : String(merged.rewrite_mode).trim() || null;
    merged.change_level = merged.change_level === null || merged.change_level === undefined
        ? null
        : String(merged.change_level).trim() || null;

    const count = Number(merged.results_count);
    merged.results_count = Number.isFinite(count) && count > 0 ? Math.floor(count) : null;

    const options = Array.isArray(merged.extra_options) ? merged.extra_options : [];
    merged.extra_options = options
        .map((item) => String(item || "").trim())
        .filter(Boolean);

    return merged;
};

const hasParaphraserStateValue = (state = {}) => {
    const normalized = normalizeParaphraserState(state);

    return Boolean(
        normalized.content
        || normalized.language
        || normalized.tone
        || normalized.rewrite_mode
        || normalized.change_level
        || normalized.results_count
        || (Array.isArray(normalized.extra_options) && normalized.extra_options.length > 0)
    );
};

const mergeParaphraserState = (oldState = {}, newState = {}) => {
    const merged = normalizeParaphraserState(oldState);
    const incoming = normalizeParaphraserState(newState);

    Object.entries(incoming).forEach(([key, value]) => {
        if (key === "extra_options") {
            if (Array.isArray(value) && value.length > 0) {
                merged[key] = value;
            }
            return;
        }

        if (key === "results_count") {
            if (Number.isFinite(Number(value)) && Number(value) > 0) {
                merged[key] = Number(value);
            }
            return;
        }

        if (value !== null && value !== undefined && String(value).trim() !== "") {
            merged[key] = value;
        }
    });

    return normalizeParaphraserState(merged);
};

const normalizeSocialPostState = (state = {}) => {
    const base = createEmptySocialPostState();
    const candidate = state && typeof state === "object" && !Array.isArray(state) ? state : {};
    const merged = { ...base, ...candidate };

    [
        "content",
        "platform",
        "language",
        "tone",
        "audience",
        "goal",
        "length",
        "last_output",
    ].forEach((key) => {
        merged[key] = merged[key] === null || merged[key] === undefined
            ? null
            : String(merged[key]).trim() || null;
    });

    if (
        merged.hashtag_count === null
        || merged.hashtag_count === undefined
        || merged.hashtag_count === ""
    ) {
        merged.hashtag_count = null;
    } else {
        const hashtagCount = Number(merged.hashtag_count);
        merged.hashtag_count = Number.isFinite(hashtagCount) && hashtagCount >= 0
            ? Math.floor(hashtagCount)
            : null;
    }

    if (typeof merged.include_emojis === "boolean") {
        merged.include_emojis = merged.include_emojis;
    } else if (merged.include_emojis === "true" || merged.include_emojis === 1 || merged.include_emojis === "1") {
        merged.include_emojis = true;
    } else if (merged.include_emojis === "false" || merged.include_emojis === 0 || merged.include_emojis === "0") {
        merged.include_emojis = false;
    } else {
        merged.include_emojis = null;
    }

    const resultsCount = Number(merged.results_count);
    merged.results_count = Number.isFinite(resultsCount) && resultsCount > 0
        ? Math.floor(resultsCount)
        : null;

    merged.extra_options = Array.isArray(merged.extra_options)
        ? merged.extra_options.map((item) => String(item || "").trim()).filter(Boolean)
        : [];

    return merged;
};

const isEmptySocialPostValue = (value) => {
    if (Array.isArray(value)) return false;
    return value === null || value === undefined || value === "";
};

const getMissingSocialPostFields = (state = {}) => {
    const normalized = normalizeSocialPostState(state);

    return Object.entries(normalized)
        .filter(([key, value]) =>
            !["extra_options", "last_output"].includes(key)
            && isEmptySocialPostValue(value)
        )
        .map(([key]) => key);
};

const hasSocialPostStateValue = (state = {}) => {
    const normalized = normalizeSocialPostState(state);

    return Boolean(
        normalized.content
        || normalized.platform
        || normalized.language
        || normalized.tone
        || normalized.audience
        || normalized.goal
        || normalized.length
        || normalized.hashtag_count !== null
        || normalized.include_emojis !== null
        || normalized.results_count
        || normalized.extra_options.length
        || normalized.last_output
    );
};

const mergeSocialPostState = (oldState = {}, newState = {}) => {
    const merged = normalizeSocialPostState(oldState);
    const incoming = normalizeSocialPostState(newState);

    Object.entries(incoming).forEach(([key, value]) => {
        if (key === "extra_options") {
            if (Array.isArray(value) && value.length > 0) {
                merged[key] = value;
            }
            return;
        }

        if (value !== null && value !== undefined && value !== "") {
            merged[key] = value;
        }
    });

    return normalizeSocialPostState(merged);
};

const humanizeSocialPostValue = (value) => {
    if (value === null || value === undefined || value === "") return "غير محدد";

    if (typeof value === "boolean") {
        return value ? "نعم" : "لا";
    }

    if (Array.isArray(value)) {
        return value.length
            ? value.map((item) => SOCIAL_POST_VALUE_LABELS[item] || String(item)).join("، ")
            : "لا";
    }

    return SOCIAL_POST_VALUE_LABELS[value] || String(value);
};

const buildSocialPostStateSummary = (state = {}) => {
    const normalized = normalizeSocialPostState(state);
    const lines = [];

    Object.entries(normalized).forEach(([key, value]) => {
        if (key === "last_output") return;

        if (!isEmptySocialPostValue(value)) {
            lines.push(`${SOCIAL_POST_FIELD_LABELS[key] || key}: ${humanizeSocialPostValue(value)}`);
        }
    });

    return lines.join("\n");
};

const buildMissingSocialPostText = (missing = []) => {
    if (!missing.length) return "";

    const labels = missing
        .map((field) => SOCIAL_POST_FIELD_LABELS[field] || field)
        .join("، ");

    return `المطلوب لاستكمال توليد المنشور: ${labels}`;
};

const buildSocialPostQuestionMessage = (apiResponse) => {
    const message = apiResponse?.message || "من فضلك أكمل البيانات المطلوبة لتوليد منشور السوشيال.";
    const nextState = apiResponse?.state || socialPostState.value;
    const missing = getMissingSocialPostFields(nextState);
    const summary = buildSocialPostStateSummary(nextState);
    const missingText = buildMissingSocialPostText(missing);

    return [
        message,
        summary ? `\nالبيانات الحالية:\n${summary}` : "",
        missingText ? `\n${missingText}` : "",
    ].filter(Boolean).join("\n");
};

const normalizeEmailWriterState = (state = {}) => {
    const base = createEmptyEmailWriterState();
    const candidate = state && typeof state === "object" && !Array.isArray(state) ? state : {};
    const merged = { ...base, ...candidate };

    [
        "purpose",
        "email_type",
        "recipient",
        "sender_name",
        "language",
        "tone",
        "length",
        "subject_line",
        "call_to_action",
        "last_output",
    ].forEach((key) => {
        merged[key] = merged[key] === null || merged[key] === undefined
            ? null
            : String(merged[key]).trim() || null;
    });

    if (typeof merged.include_subject === "boolean") {
        merged.include_subject = merged.include_subject;
    } else if (merged.include_subject === "true" || merged.include_subject === 1 || merged.include_subject === "1") {
        merged.include_subject = true;
    } else if (merged.include_subject === "false" || merged.include_subject === 0 || merged.include_subject === "0") {
        merged.include_subject = false;
    } else {
        merged.include_subject = null;
    }

    merged.extra_options = Array.isArray(merged.extra_options)
        ? merged.extra_options.map((item) => String(item || "").trim()).filter(Boolean)
        : [];

    return merged;
};

const isEmptyEmailWriterValue = (value) => {
    if (Array.isArray(value)) return false;
    return value === null || value === undefined || value === "";
};

const getMissingEmailWriterFields = (state = {}) => {
    const normalized = normalizeEmailWriterState(state);

    return Object.entries(normalized)
        .filter(([key, value]) =>
            !["extra_options", "last_output", "sender_name", "subject_line", "call_to_action"].includes(key)
            && isEmptyEmailWriterValue(value)
        )
        .map(([key]) => key);
};

const hasEmailWriterStateValue = (state = {}) => {
    const normalized = normalizeEmailWriterState(state);

    return Boolean(
        normalized.purpose
        || normalized.email_type
        || normalized.recipient
        || normalized.sender_name
        || normalized.language
        || normalized.tone
        || normalized.length
        || normalized.subject_line
        || normalized.call_to_action
        || normalized.include_subject !== null
        || normalized.extra_options.length
        || normalized.last_output
    );
};

const mergeEmailWriterState = (oldState = {}, newState = {}) => {
    const merged = normalizeEmailWriterState(oldState);
    const incoming = normalizeEmailWriterState(newState);

    Object.entries(incoming).forEach(([key, value]) => {
        if (key === "extra_options") {
            if (value.length > 0) merged[key] = value;
            return;
        }

        if (value !== null && value !== undefined && value !== "") {
            merged[key] = value;
        }
    });

    return normalizeEmailWriterState(merged);
};

const humanizeEmailWriterValue = (value) => {
    if (value === null || value === undefined || value === "") return "غير محدد";
    if (typeof value === "boolean") return value ? "نعم" : "لا";

    if (Array.isArray(value)) {
        return value.length
            ? value.map((item) => EMAIL_WRITER_VALUE_LABELS[item] || String(item)).join("، ")
            : "لا";
    }

    return EMAIL_WRITER_VALUE_LABELS[value] || String(value);
};

const buildEmailWriterStateSummary = (state = {}) => {
    const normalized = normalizeEmailWriterState(state);

    return Object.entries(normalized)
        .filter(([key, value]) => key !== "last_output" && !isEmptyEmailWriterValue(value))
        .map(([key, value]) =>
            `${EMAIL_WRITER_FIELD_LABELS[key] || key}: ${humanizeEmailWriterValue(value)}`
        )
        .join("\n");
};

const buildEmailWriterQuestionMessage = (apiResponse) => {
    const message = apiResponse?.message || "من فضلك أكمل البيانات المطلوبة لكتابة الإيميل.";
    const nextState = apiResponse?.state || emailWriterState.value;
    const summary = buildEmailWriterStateSummary(nextState);
    const missing = getMissingEmailWriterFields(nextState);
    const missingText = missing.length
        ? `المطلوب لاستكمال كتابة الإيميل: ${missing
            .map((field) => EMAIL_WRITER_FIELD_LABELS[field] || field)
            .join("، ")}`
        : "";

    return [
        message,
        summary ? `\nالبيانات الحالية:\n${summary}` : "",
        missingText ? `\n${missingText}` : "",
    ].filter(Boolean).join("\n");
};

const normalizeScriptGeneratorState = (state = {}) => {
    const base = createEmptyScriptGeneratorState();
    const candidate = state && typeof state === "object" && !Array.isArray(state) ? state : {};
    const merged = { ...base, ...candidate };

    [
        "topic",
        "script_type",
        "platform",
        "language",
        "tone",
        "audience",
        "duration",
        "format",
        "last_output",
    ].forEach((key) => {
        merged[key] = merged[key] === null || merged[key] === undefined
            ? null
            : String(merged[key]).trim() || null;
    });

    if (typeof merged.include_scene_notes === "boolean") {
        merged.include_scene_notes = merged.include_scene_notes;
    } else if (merged.include_scene_notes === "true" || merged.include_scene_notes === 1 || merged.include_scene_notes === "1") {
        merged.include_scene_notes = true;
    } else if (merged.include_scene_notes === "false" || merged.include_scene_notes === 0 || merged.include_scene_notes === "0") {
        merged.include_scene_notes = false;
    } else {
        merged.include_scene_notes = null;
    }

    if (
        merged.results_count === null
        || merged.results_count === undefined
        || merged.results_count === ""
    ) {
        merged.results_count = 2;
    } else {
        const resultsCount = Number(merged.results_count);
        merged.results_count = Number.isFinite(resultsCount) && resultsCount > 0
            ? Math.floor(resultsCount)
            : 2;
    }

    merged.extra_options = Array.isArray(merged.extra_options)
        ? merged.extra_options.map((item) => String(item || "").trim()).filter(Boolean)
        : [];

    return merged;
};

const isEmptyScriptGeneratorValue = (value) => {
    if (Array.isArray(value)) return false;
    return value === null || value === undefined || value === "";
};

const getMissingScriptGeneratorFields = (state = {}) => {
    const normalized = normalizeScriptGeneratorState(state);

    return Object.entries(normalized)
        .filter(([key, value]) =>
            !["extra_options", "last_output"].includes(key)
            && isEmptyScriptGeneratorValue(value)
        )
        .map(([key]) => key);
};

const hasScriptGeneratorStateValue = (state = {}) => {
    const normalized = normalizeScriptGeneratorState(state);

    return Boolean(
        normalized.topic
        || normalized.script_type
        || normalized.platform
        || normalized.language
        || normalized.tone
        || normalized.audience
        || normalized.duration
        || normalized.format
        || normalized.include_scene_notes !== null
        || normalized.results_count
        || normalized.extra_options.length
        || normalized.last_output
    );
};

const mergeScriptGeneratorState = (oldState = {}, newState = {}) => {
    const merged = normalizeScriptGeneratorState(oldState);
    const incoming = normalizeScriptGeneratorState(newState);

    Object.entries(incoming).forEach(([key, value]) => {
        if (key === "extra_options") {
            if (Array.isArray(value) && value.length > 0) merged[key] = value;
            return;
        }

        if (value !== null && value !== undefined && value !== "") {
            merged[key] = value;
        }
    });

    return normalizeScriptGeneratorState(merged);
};

const buildScriptGeneratorQuestionMessage = (apiResponse) => {
    const state = normalizeScriptGeneratorState(
        apiResponse?.state || scriptGeneratorState.value
    );
    const missing = getMissingScriptGeneratorFields(state);
    const labels = missing
        .map((field) => SCRIPT_GENERATOR_FIELD_LABELS[field] || field)
        .join("، ");

    return [
        apiResponse?.message || "من فضلك أكمل البيانات المطلوبة لتوليد السكريبت.",
        labels ? `المطلوب لاستكمال السكريبت: ${labels}` : "",
    ].filter(Boolean).join("\n");
};

const normalizeProductDescriptionState = (state = {}) => {
    const candidate = state && typeof state === "object" && !Array.isArray(state)
        ? state
        : {};
    const merged = { ...createEmptyProductDescriptionState(), ...candidate };

    [
        "product",
        "brand_name",
        "product_features",
        "target_audience",
        "language",
        "tone",
        "length",
        "platform",
        "last_output",
    ].forEach((key) => {
        merged[key] = merged[key] === null || merged[key] === undefined
            ? null
            : String(merged[key]).trim() || null;
    });

    ["include_bullets", "include_seo_keywords"].forEach((key) => {
        if (typeof merged[key] === "boolean") return;
        if (merged[key] === true || merged[key] === 1 || merged[key] === "1" || merged[key] === "true") {
            merged[key] = true;
        } else if (merged[key] === false || merged[key] === 0 || merged[key] === "0" || merged[key] === "false") {
            merged[key] = false;
        } else {
            merged[key] = null;
        }
    });

    merged.extra_options = Array.isArray(merged.extra_options)
        ? [...new Set(merged.extra_options.map((item) => String(item || "").trim()).filter(Boolean))]
        : [];

    return merged;
};

const mergeProductDescriptionState = (oldState = {}, newState = {}) => {
    const merged = normalizeProductDescriptionState(oldState);
    const incoming = normalizeProductDescriptionState(newState);

    Object.entries(incoming).forEach(([key, value]) => {
        if (key === "extra_options") {
            if (value.length > 0) merged[key] = value;
            return;
        }

        if (value !== null && value !== undefined && value !== "") {
            merged[key] = value;
        }
    });

    return normalizeProductDescriptionState(merged);
};

const hasProductDescriptionStateValue = (state = {}) => {
    const normalized = normalizeProductDescriptionState(state);

    return Object.entries(normalized).some(([key, value]) => {
        if (key === "last_output") return Boolean(value);
        if (Array.isArray(value)) return value.length > 0;
        return value !== null && value !== "";
    });
};

const buildHeadlineStateSummary = (state = {}) => {
    const lines = [];

    Object.entries(state).forEach(([key, value]) => {
        if (key === "extra_options") return;

        if (!isEmptyHeadlineValue(value)) {
            lines.push(`${HEADLINE_FIELD_LABELS[key] || key}: ${humanizeHeadlineValue(value)}`);
        }
    });

    if (Array.isArray(state.extra_options)) {
        lines.push(`تحسين SEO: ${humanizeHeadlineValue(state.extra_options)}`);
    }

    return lines.join("\n");
};

const buildMissingHeadlineText = (missing = []) => {
    if (!missing.length) return "";
    const labels = missing.map((field) => HEADLINE_FIELD_LABELS[field] || field).join("، ");
    return `المطلوب لاستكمال الطلب: ${labels}`;
};

const buildHeadlineQuestionMessage = (apiResponse) => {
    const message = apiResponse?.message || "من فضلك أكمل البيانات المطلوبة.";
    const nextState = apiResponse?.state || headlineState.value;
    const missing = getMissingHeadlineFields(nextState);

    const summary = buildHeadlineStateSummary(nextState);
    const missingText = buildMissingHeadlineText(missing);

    return [
        message,
        summary ? `\nالبيانات الحالية:\n${summary}` : "",
        missingText ? `\n${missingText}` : "",
    ].filter(Boolean).join("\n");
};

const buildHeadlineResultMessage = (apiResponse) => {
    const intro = apiResponse?.message || "تم توليد العناوين بنجاح.";
    const headlines = Array.isArray(apiResponse?.headlines) ? apiResponse.headlines : [];

    if (!headlines.length) {
        return intro;
    }

    const lines = headlines.map((headline) => {
        const title = String(headline?.text || "").trim();
        const subheadline = String(headline?.subheadline || "").trim();

        if (!title) return "";

        return [
            `• ${title}`,
            subheadline ? `  ${subheadline}` : "",
        ].filter(Boolean).join("\n");
    }).filter(Boolean);

    return [intro, "", ...lines].join("\n\n");
};

const isHeadlineGeneratorMessage = (message = {}) => {
    const currentSubToolId = Number(subtool.value?.id || 0);
    const messageSubToolId = Number(message?.sub_tool_id || message?.subToolId || 0);
    const metadataSubToolId = Number(message?.metadata?.sub_tool_id || 0);

    return messageSubToolId === HEADLINE_GENERATOR_SUB_TOOL_ID
        || metadataSubToolId === HEADLINE_GENERATOR_SUB_TOOL_ID
        || currentSubToolId === HEADLINE_GENERATOR_SUB_TOOL_ID;
};

const isHeadlineGeneratorResult = (msg = {}) =>
    msg?.role === "assistant"
    && !msg?.isTyping
    && !msg?.is_error
    && isHeadlineGeneratorMessage(msg)
    && String(msg?.metadata?.type || "").toLowerCase() === "result"
    && Boolean(displayMessageContent(msg));

const isTextEditorResult = (msg = {}) => {
    return (
        msg?.role === "assistant"
        && Number(subtool.value?.id) === TEXT_EDITOR_SUB_TOOL_ID
        && !msg?.isTyping
        && !msg?.streaming
        && !msg?.is_error
    );
};

const getTextSummarizerMeta = (msg = {}) => {
    if (msg?.toolMeta && typeof msg.toolMeta === "object") {
        return msg.toolMeta;
    }

    if (msg?.metadata && typeof msg.metadata === "object") {
        return msg.metadata;
    }

    return {};
};

const isTextSummarizerMessage = (msg = {}) => {
    const meta = getTextSummarizerMeta(msg);

    return Number(msg?.sub_tool_id || 0) === TEXT_SUMMARIZER_SUB_TOOL_ID
        || Number(meta?.sub_tool_id || 0) === TEXT_SUMMARIZER_SUB_TOOL_ID
        || String(msg?.task_key || "").toLowerCase() === TEXT_SUMMARIZER_TASK_KEY
        || String(meta?.task_key || "").toLowerCase() === TEXT_SUMMARIZER_TASK_KEY
        || String(meta?.tool || "").toLowerCase() === TEXT_SUMMARIZER_TOOL_KEY;
};

const isTextSummarizerResult = (msg = {}) => {
    if (!msg || msg.role !== "assistant") return false;
    if (msg.isTyping || msg.streaming || msg.is_error) return false;

    return (
        isTextSummarizerMessage(msg)
        || (
            Number(subtool.value?.id) === TEXT_SUMMARIZER_SUB_TOOL_ID
            && !isProductDescriptionResult(msg)
            && !isSocialPostResult(msg)
            && !isEmailWriterResult(msg)
            && !isScriptGeneratorResult(msg)
            && !isHeadlineGeneratorResult(msg)
            && !isParaphraserResult(msg)
        )
    );
};

const getTextSummarizerOutput = (msg = {}) => {
    const meta = getTextSummarizerMeta(msg);

    return String(
        meta?.reply
        || msg?.reply
        || msg?.content
        || msg?.message
        || ""
    ).trim();
};

const isParaphraserMessage = (msg = {}) => {
    const metadata = msg?.metadata && typeof msg.metadata === "object"
        ? msg.metadata
        : {};

    return Number(metadata.sub_tool_id || msg?.sub_tool_id || 0) === PARAPHRASER_SUB_TOOL_ID
        || String(metadata.tool || "").toLowerCase() === PARAPHRASER_TOOL_KEY;
};

const getParaphraserOutput = (msg = {}) => {
    const metadata = msg?.metadata && typeof msg.metadata === "object"
        ? msg.metadata
        : {};

    const resultText = Array.isArray(metadata.results)
        ? metadata.results
            .map((item) => String(item?.text || item || "").trim())
            .filter(Boolean)
            .join("\n\n")
        : "";

    return resultText
        || String(metadata.state?.last_output || "").trim()
        || String(msg?.content || msg?.message || "").trim();
};

const isParaphraserResult = (msg = {}) =>
    msg?.role === "assistant"
    && !msg?.isTyping
    && !msg?.is_error
    && isParaphraserMessage(msg)
    && String(msg?.metadata?.type || "result") === "result"
    && Boolean(getParaphraserOutput(msg));

const looksLikeGeneratedHeadlinesText = (content = "") => {
    const text = String(content || "");

    return text.includes("تم توليد العناوين")
        || /^\s*\d+\.\s+/m.test(text);
};

const formatGeneratedHeadlinesForDisplay = (content = "") => {
    const text = String(content || "").trim();

    if (!text) return "";

    const lines = text.split(/\r?\n/);
    const formattedLines = [];

    lines.forEach((line) => {
        const trimmed = line.trim();

        if (!trimmed) {
            formattedLines.push("");
            return;
        }

        if (trimmed.includes("تم توليد العناوين")) {
            formattedLines.push(trimmed);
            return;
        }

        if (/^[•\-]\s+/.test(trimmed)) {
            formattedLines.push(trimmed);
            return;
        }

        const numberedMatch = trimmed.match(/^\d+\.\s*(.+)$/);

        if (numberedMatch?.[1]) {
            formattedLines.push(`• ${numberedMatch[1].trim()}`);
            return;
        }

        formattedLines.push(trimmed);
    });

    return formattedLines
        .join("\n")
        .replace(/\n{3,}/g, "\n\n")
        .trim();
};

const isSocialPostMessage = (msg = {}) => {
    const metadata = msg?.metadata && typeof msg.metadata === "object"
        ? msg.metadata
        : {};

    return Number(metadata.sub_tool_id || msg?.sub_tool_id || 0) === SOCIAL_POST_GENERATOR_SUB_TOOL_ID
        || String(metadata.tool || "").toLowerCase() === SOCIAL_POST_GENERATOR_TOOL_KEY;
};

const getSocialPostOutput = (msg = {}) => {
    const metadata = msg?.metadata && typeof msg.metadata === "object"
        ? msg.metadata
        : {};

    const resultText = Array.isArray(metadata.results)
        ? metadata.results
            .map((item) => String(item?.text || item || "").trim())
            .filter(Boolean)
            .join("\n\n")
        : "";

    return resultText
        || String(metadata.state?.last_output || "").trim()
        || String(msg?.content || msg?.message || "").trim();
};

const isSocialPostResult = (msg = {}) =>
    msg?.role === "assistant"
    && !msg?.isTyping
    && isSocialPostMessage(msg)
    && String(msg?.metadata?.type || "result") === "result"
    && Boolean(getSocialPostOutput(msg));

const isEmailWriterMessage = (msg = {}) => {
    const metadata = msg?.metadata && typeof msg.metadata === "object"
        ? msg.metadata
        : {};

    return Number(metadata.sub_tool_id || msg?.sub_tool_id || 0) === EMAIL_WRITER_SUB_TOOL_ID
        || String(metadata.tool || "").toLowerCase() === EMAIL_WRITER_TOOL_KEY;
};

const getEmailWriterOutput = (msg = {}) => {
    const metadata = msg?.metadata && typeof msg.metadata === "object"
        ? msg.metadata
        : {};

    const resultText = Array.isArray(metadata.results)
        ? metadata.results
            .map((item) => {
                if (typeof item === "string") return item.trim();

                const subject = String(item?.subject || item?.title || "").trim();
                const text = String(item?.text || item || "").trim();

                return [subject, text].filter(Boolean).join("\n\n");
            })
            .filter(Boolean)
            .join("\n\n")
        : "";

    return resultText
        || String(metadata.state?.last_output || "").trim()
        || String(msg?.content || msg?.message || "").trim();
};

const isEmailWriterResult = (msg = {}) =>
    msg?.role === "assistant"
    && !msg?.isTyping
    && isEmailWriterMessage(msg)
    && String(msg?.metadata?.type || "result") === "result"
    && Boolean(getEmailWriterOutput(msg));

const isScriptGeneratorMessage = (msg = {}) => {
    const metadata = msg?.metadata && typeof msg.metadata === "object"
        ? msg.metadata
        : {};

    return Number(metadata.sub_tool_id || msg?.sub_tool_id || 0) === SCRIPT_GENERATOR_SUB_TOOL_ID
        || String(metadata.tool || "").toLowerCase() === SCRIPT_GENERATOR_TOOL_KEY;
};

const getScriptGeneratorOutput = (msg = {}) => {
    const metadata = msg?.metadata && typeof msg.metadata === "object"
        ? msg.metadata
        : {};

    const resultText = Array.isArray(metadata.results)
        ? metadata.results
            .map((item) => {
                if (typeof item === "string") return item.trim();

                const title = String(item?.title || item?.name || "").trim();
                const text = String(item?.text || item?.content || item?.script || "").trim();

                return [title, text].filter(Boolean).join("\n\n");
            })
            .filter(Boolean)
            .join("\n\n")
        : "";

    return resultText
        || String(metadata.state?.last_output || "").trim()
        || String(msg?.content || msg?.message || "").trim();
};

const isScriptGeneratorResult = (msg = {}) =>
    msg?.role === "assistant"
    && !msg?.isTyping
    && isScriptGeneratorMessage(msg)
    && String(msg?.metadata?.type || "result") === "result"
    && Boolean(getScriptGeneratorOutput(msg));

const isProductDescriptionMessage = (msg = {}) => {
    const metadata = msg?.metadata && typeof msg.metadata === "object"
        ? msg.metadata
        : {};

    return Number(metadata.sub_tool_id || msg?.sub_tool_id || 0) === PRODUCT_DESCRIPTION_GENERATOR_SUB_TOOL_ID
        || String(metadata.tool || "").toLowerCase() === PRODUCT_DESCRIPTION_GENERATOR_TOOL_KEY;
};

const getProductDescriptionOutput = (msg = {}) => {
    const metadata = msg?.metadata && typeof msg.metadata === "object"
        ? msg.metadata
        : {};
    const resultText = Array.isArray(metadata.results)
        ? String(metadata.results[0]?.text || "").trim()
        : "";

    return resultText
        || String(metadata.state?.last_output || "").trim()
        || String(msg?.content || msg?.message || "").trim();
};

const isProductDescriptionResult = (msg = {}) =>
    msg?.role === "assistant"
    && !msg?.isTyping
    && isProductDescriptionMessage(msg)
    && String(msg?.metadata?.type || "result") === "result"
    && Boolean(getProductDescriptionOutput(msg));

const productDescriptionUsage = (msg = {}) => {
    if (!isProductDescriptionResult(msg)) return "";

    const usage = msg?.metadata?.usage;
    const cost = msg?.metadata?.cost;
    const parts = [];

    if (usage && Number.isFinite(Number(usage.total_tokens))) {
        parts.push(`${isArabic.value ? "الرموز" : "Tokens"}: ${Number(usage.total_tokens)}`);
    }

    if (cost && Number.isFinite(Number(cost.total_cost))) {
        parts.push(`${isArabic.value ? "التكلفة" : "Cost"}: ${Number(cost.total_cost).toFixed(6)} ${cost.currency || "USD"}`);
    }

    return parts.join(" · ");
};

const displayMessageContent = (msg = {}) => {
    const content = String(msg?.content || msg?.message || "");

    if (msg?.role === "assistant" && isTextSummarizerMessage(msg)) {
        return getTextSummarizerOutput(msg);
    }

    if (msg?.role === "assistant" && isProductDescriptionMessage(msg)) {
        return getProductDescriptionOutput(msg);
    }

    if (msg?.role === "assistant" && isSocialPostMessage(msg)) {
        return getSocialPostOutput(msg);
    }

    if (msg?.role === "assistant" && isEmailWriterMessage(msg)) {
        return getEmailWriterOutput(msg);
    }

    if (msg?.role === "assistant" && isScriptGeneratorMessage(msg)) {
        return getScriptGeneratorOutput(msg);
    }

    if (msg?.role === "assistant" && isParaphraserMessage(msg)) {
        return getParaphraserOutput(msg);
    }

    if (
        msg?.role === "assistant"
        && isHeadlineGeneratorMessage(msg)
        && looksLikeGeneratedHeadlinesText(content)
    ) {
        return formatGeneratedHeadlinesForDisplay(content);
    }

    return content;
};

const copyAssistantMessage = async (msg) => {
    const output = displayMessageContent(msg);
    if (!output) return;

    await navigator.clipboard.writeText(output);
    copiedMessageKey.value = msg?.localKey || msg?.id || null;

    window.setTimeout(() => {
        if (copiedMessageKey.value === (msg?.localKey || msg?.id || null)) {
            copiedMessageKey.value = null;
        }
    }, 1200);
};

const copyProductDescription = async (msg) => {
    const output = getProductDescriptionOutput(msg);
    if (!output) return;

    await navigator.clipboard.writeText(output);
};

const findUserInputBeforeMessage = (msg) => {
    const targetIndex = messages.value.findIndex((item) => item.localKey === msg?.localKey);
    const rows = targetIndex >= 0 ? messages.value.slice(0, targetIndex) : messages.value;

    return [...rows].reverse().find((item) => item?.role === "user")?.content || "";
};

const buildTextSummarizerTitle = (body = "") => {
    const cleaned = String(body || "")
        .replace(/<[^>]*>/g, " ")
        .replace(/\s+/g, " ")
        .trim();

    return cleaned.slice(0, 120) || "Text summary";
};

const createTextSummarizerPayload = (body, conversation = {}) => {
    const cleanBody = String(body || "").trim();

    return {
        user_id: resolveCurrentUserId(),
        sub_tool_id: TEXT_SUMMARIZER_SUB_TOOL_ID,
        title: buildTextSummarizerTitle(cleanBody),
        conversation_uuid: conversation.uuid || activeConversation.value?.uuid || route.params.uuid || "",
        body: cleanBody,
        user_message: "Summarize the provided text.",
        task_key: TEXT_SUMMARIZER_TASK_KEY,
        tool: TEXT_SUMMARIZER_TOOL_KEY,
    };
};

const getTextSummarizerRequestPayload = (msg = {}) => {
    const meta = getTextSummarizerMeta(msg);

    if (meta?.request_payload && typeof meta.request_payload === "object") {
        return meta.request_payload;
    }

    if (msg?.request_payload && typeof msg.request_payload === "object") {
        return msg.request_payload;
    }

    const previousUserMessage = findUserInputBeforeMessage(msg);
    if (previousUserMessage) {
        const conversationUuid = activeConversation.value?.uuid || route.params.uuid || "";

        return {
            user_id: resolveCurrentUserId(),
            sub_tool_id: TEXT_SUMMARIZER_SUB_TOOL_ID,
            title: buildTextSummarizerTitle(previousUserMessage),
            conversation_uuid: conversationUuid,
            body: previousUserMessage,
            user_message: "Summarize the provided text.",
            task_key: TEXT_SUMMARIZER_TASK_KEY,
            tool: TEXT_SUMMARIZER_TOOL_KEY,
        };
    }

    if (textSummarizerLastRequest.value) {
        return textSummarizerLastRequest.value;
    }

    return null;
};

const sendTextSummarizerRequest = async (payload, options = {}) => {
    const submitOptions = {
        forceNewIdempotency: false,
        addUserMessage: true,
        ...options,
    };
    const body = String(payload?.body || "").trim();

    if (conversationLimitExceeded.value || sendingMessage.value || streamingAssistant.value) {
        return;
    }

    if (!body) {
        await addAssistantLocalMessage(
            "من فضلك أدخل النص الذي تريد تلخيصه.",
            {
                plainText: true,
                is_error: true,
                sub_tool_id: TEXT_SUMMARIZER_SUB_TOOL_ID,
                metadata: {
                    type: "error",
                    tool: TEXT_SUMMARIZER_TOOL_KEY,
                    task_key: TEXT_SUMMARIZER_TASK_KEY,
                    sub_tool_id: TEXT_SUMMARIZER_SUB_TOOL_ID,
                },
            }
        );
        return;
    }

    if (!(await requireAuth())) {
        return;
    }

    sendingMessage.value = true;
    const conversation = await ensureConversation();

    if (!conversation?.uuid) {
        sendingMessage.value = false;
        await addAssistantLocalMessage("تعذر إنشاء المحادثة. حاول مرة أخرى.", {
            is_error: true,
            sub_tool_id: TEXT_SUMMARIZER_SUB_TOOL_ID,
        });
        return;
    }

    const requestPayload = {
        ...payload,
        user_id: payload?.user_id || resolveCurrentUserId(),
        sub_tool_id: TEXT_SUMMARIZER_SUB_TOOL_ID,
        title: payload?.title || buildTextSummarizerTitle(body),
        conversation_uuid: conversation.uuid,
        conversation_id: conversation.id,
        body,
        user_message: String(payload?.user_message || "Summarize the provided text.").trim(),
        task_key: TEXT_SUMMARIZER_TASK_KEY,
        tool: TEXT_SUMMARIZER_TOOL_KEY,
    };

    textSummarizerLastRequest.value = { ...requestPayload };

    const idempotencyKey = resolveIdempotencyKey(
        conversation.uuid,
        JSON.stringify({
            body: requestPayload.body,
            user_message: requestPayload.user_message,
            regenerate: Boolean(requestPayload.regenerate),
            previous_output: requestPayload.previous_output || "",
        }),
        { forceNew: submitOptions.forceNewIdempotency }
    );
    const requestSignature = `${conversation.id}:${idempotencyKey}`;

    if (inFlightSignatures.has(requestSignature)) {
        sendingMessage.value = false;
        return;
    }

    inFlightSignatures.add(requestSignature);

    if (submitOptions.addUserMessage) {
        await addUserLocalMessage(body, {
            sub_tool_id: TEXT_SUMMARIZER_SUB_TOOL_ID,
            metadata: {
                type: "summarizer_request",
                tool: TEXT_SUMMARIZER_TOOL_KEY,
                task_key: TEXT_SUMMARIZER_TASK_KEY,
                sub_tool_id: TEXT_SUMMARIZER_SUB_TOOL_ID,
                request_payload: requestPayload,
            },
        });
    }

    userInput.value = "";
    resetTextarea();
    const typingId = addAssistantTypingMessage();

    try {
        const response = await chatServices.sendMessage({
            ...requestPayload,
            idempotency_key: idempotencyKey,
        });
        removeAssistantTypingMessage(typingId);

        const apiResponse = normalizeTextSummarizerApiResponse(response);

        if (!apiResponse || !apiResponse.reply) {
            await addAssistantLocalMessage(
                "تعذر قراءة نتيجة تلخيص النص. حاول مرة أخرى.",
                {
                    plainText: true,
                    is_error: true,
                    sub_tool_id: TEXT_SUMMARIZER_SUB_TOOL_ID,
                    metadata: {
                        type: "error",
                        tool: TEXT_SUMMARIZER_TOOL_KEY,
                        task_key: TEXT_SUMMARIZER_TASK_KEY,
                        sub_tool_id: TEXT_SUMMARIZER_SUB_TOOL_ID,
                        request_payload: requestPayload,
                    },
                }
            );
            return;
        }

        const metadata = {
            type: "result",
            tool: apiResponse.tool || TEXT_SUMMARIZER_TOOL_KEY,
            task_key: apiResponse.task_key || TEXT_SUMMARIZER_TASK_KEY,
            model_key: apiResponse.model_key,
            request_id: apiResponse.request_id,
            request_payload: apiResponse.request_payload || requestPayload,
            reply: apiResponse.reply,
            usage: apiResponse.usage,
            cost: apiResponse.cost,
            sub_tool_id: TEXT_SUMMARIZER_SUB_TOOL_ID,
            conversation_uuid: apiResponse.conversation_uuid || conversation.uuid,
        };

        await addAssistantLocalMessage(apiResponse.reply, {
            plainText: false,
            sub_tool_id: TEXT_SUMMARIZER_SUB_TOOL_ID,
            task_key: TEXT_SUMMARIZER_TASK_KEY,
            toolMeta: metadata,
            metadata,
        });

        if (!conversations.value.find((item) => item.uuid === conversation.uuid)) {
            conversations.value.unshift(conversation);
        }

        clearPendingSend(conversation.uuid);
        await focusChatInput();
    } catch (error) {
        console.error("[TextSummarizer] send failed:", error);
        removeAssistantTypingMessage(typingId);
        await addAssistantLocalMessage("حدث خطأ أثناء تلخيص النص. حاول مرة أخرى.", {
            plainText: true,
            is_error: true,
            sub_tool_id: TEXT_SUMMARIZER_SUB_TOOL_ID,
            metadata: {
                type: "error",
                tool: TEXT_SUMMARIZER_TOOL_KEY,
                task_key: TEXT_SUMMARIZER_TASK_KEY,
                sub_tool_id: TEXT_SUMMARIZER_SUB_TOOL_ID,
                request_payload: requestPayload,
            },
        });
    } finally {
        removeAssistantTypingMessage(typingId);
        inFlightSignatures.delete(requestSignature);
        sendingMessage.value = false;
        await scrollToBottom();
    }
};

const handleTextSummarizerSubmit = async (text) => {
    const body = String(text || "").trim();

    if (!body) {
        await addAssistantLocalMessage(
            "من فضلك أدخل النص الذي تريد تلخيصه.",
            {
                plainText: true,
                is_error: true,
                sub_tool_id: TEXT_SUMMARIZER_SUB_TOOL_ID,
            }
        );
        return;
    }

    await sendTextSummarizerRequest(createTextSummarizerPayload(body));
};

const regenerateTextSummarizerResult = async (msg) => {
    if (chatSendDisabled.value) return;

    const oldOutput = displayMessageContent(msg);
    const oldPayload = getTextSummarizerRequestPayload(msg);

    if (!oldPayload) {
        console.warn("Missing summarizer request payload for regeneration.");
        return;
    }

    await sendTextSummarizerRequest(
        {
            ...oldPayload,
            sub_tool_id: TEXT_SUMMARIZER_SUB_TOOL_ID,
            task_key: TEXT_SUMMARIZER_TASK_KEY,
            tool: TEXT_SUMMARIZER_TOOL_KEY,
            regenerate: true,
            previous_output: oldOutput,
        },
        {
            forceNewIdempotency: true,
        }
    );
};

const regenerateTextEditorResult = async (msg) => {
    if (chatSendDisabled.value) return;

    const oldOutput = displayMessageContent(msg);
    const content = findUserInputBeforeMessage(msg)
        || String(userInput.value || "").trim();

    await submitMessage(content, {
        regenerate: true,
        previousOutput: oldOutput,
        forceNewIdempotency: true,
    });
};

const regenerateHeadlineResult = async (msg) => {
    if (chatSendDisabled.value) return;

    const oldOutput = displayMessageContent(msg);
    const metadataState = msg?.metadata?.state;

    if (metadataState && typeof metadataState === "object") {
        headlineState.value = mergeHeadlineState(getInitialHeadlineState(), metadataState);
    }

    const source = String(headlineState.value.content || "").trim()
        || findUserInputBeforeMessage(msg)
        || userInput.value;

    await handleHeadlineGeneratorSubmit(source, {
        regenerate: true,
        previousOutput: oldOutput,
        forceNewIdempotency: true,
    });
};

const regenerateParaphraserResult = async (msg) => {
    if (chatSendDisabled.value) return;

    const oldOutput = displayMessageContent(msg);
    const metadataState = msg?.metadata?.state;

    if (metadataState && typeof metadataState === "object") {
        paraphraserState.value = normalizeParaphraserState(metadataState);
    }

    const source = String(paraphraserState.value.content || "").trim()
        || findUserInputBeforeMessage(msg)
        || userInput.value;

    await handleParaphraserSubmit(source, {
        regenerate: true,
        previousOutput: oldOutput,
        forceNewIdempotency: true,
    });
};

const regenerateSocialPostResult = async (msg) => {
    if (chatSendDisabled.value) return;

    const oldOutput = displayMessageContent(msg);
    const metadataState = msg?.metadata?.state;

    if (metadataState && typeof metadataState === "object") {
        socialPostState.value = normalizeSocialPostState(metadataState);
    }

    const source = String(socialPostState.value.content || "").trim()
        || findUserInputBeforeMessage(msg)
        || userInput.value;

    await handleSocialPostGeneratorSubmit(source, {
        regenerate: true,
        previousOutput: oldOutput,
        forceNewIdempotency: true,
    });
};

const regenerateEmailWriterResult = async (msg) => {
    if (chatSendDisabled.value) return;

    const oldOutput = displayMessageContent(msg);
    const metadataState = msg?.metadata?.state;

    if (metadataState && typeof metadataState === "object") {
        emailWriterState.value = normalizeEmailWriterState(metadataState);
    }

    const source = String(emailWriterState.value.purpose || "").trim()
        || findUserInputBeforeMessage(msg)
        || userInput.value;

    await handleEmailWriterSubmit(source, {
        regenerate: true,
        previousOutput: oldOutput,
        forceNewIdempotency: true,
    });
};

const regenerateScriptGeneratorResult = async (msg) => {
    if (chatSendDisabled.value) return;

    const oldOutput = displayMessageContent(msg);
    const metadataState = msg?.metadata?.state;

    if (metadataState && typeof metadataState === "object") {
        scriptGeneratorState.value = normalizeScriptGeneratorState(metadataState);
    }

    const source = String(scriptGeneratorState.value.topic || "").trim()
        || findUserInputBeforeMessage(msg)
        || userInput.value;

    await handleScriptGeneratorSubmit(source, {
        regenerate: true,
        previousOutput: oldOutput,
        forceNewIdempotency: true,
    });
};

const regenerateProductDescription = async (msg) => {
    if (chatSendDisabled.value) return;

    const oldOutput = displayMessageContent(msg);
    const metadataState = msg?.metadata?.state;

    if (metadataState && typeof metadataState === "object") {
        productDescriptionState.value = mergeProductDescriptionState(
            productDescriptionState.value,
            metadataState
        );
    }

    const source = findUserInputBeforeMessage(msg)
        || productDescriptionState.value.product
        || "";

    await handleProductDescriptionSubmit(source, {
        regenerate: true,
        previousOutput: oldOutput,
        forceNewIdempotency: true,
    });
};

const editProductDescriptionInputs = async () => {
    productOptionsOpen.value = true;
    await focusChatInput();
};

const editSocialPostInputs = async () => {
    socialPostOptionsOpen.value = true;
    await focusChatInput();
};

const editEmailWriterInputs = async () => {
    emailWriterOptionsOpen.value = true;
    await focusChatInput();
};

const editScriptGeneratorInputs = async () => {
    scriptGeneratorOptionsOpen.value = true;
    await focusChatInput();
};

const focusChatInput = async () => {
    await nextTick();
    textareaRef.value?.focus();
};

const headlineStateStorageKey = (conversationUuid = "") =>
    `headline_state_${conversationUuid || activeConversation.value?.uuid || route.params.uuid || ""}`;

const saveHeadlineStateToSession = (conversationUuid, state) => {
    const key = headlineStateStorageKey(conversationUuid);

    try {
        sessionStorage.setItem(key, JSON.stringify(state || getInitialHeadlineState()));
    } catch {
        // Ignore storage edge cases.
    }
};

const readHeadlineStateFromSession = (conversationUuid) => {
    const key = headlineStateStorageKey(conversationUuid);

    try {
        const raw = sessionStorage.getItem(key);
        if (!raw) return null;

        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) return null;

        return parsed;
    } catch {
        return null;
    }
};

const clearHeadlineStateFromSession = (conversationUuid) => {
    const key = headlineStateStorageKey(conversationUuid);

    try {
        sessionStorage.removeItem(key);
    } catch {
        // Ignore storage edge cases.
    }
};

const resetHeadlineState = (conversationUuid = "") => {
    headlineState.value = getInitialHeadlineState();
    clearHeadlineStateFromSession(conversationUuid);
};

const paraphraserStateStorageKey = (conversationUuid = "") =>
    `tool_state_${conversationUuid || activeConversation.value?.uuid || route.params.uuid || ""}_${PARAPHRASER_SUB_TOOL_ID}`;

const saveParaphraserStateToSession = (conversationUuid, state) => {
    const key = paraphraserStateStorageKey(conversationUuid);

    try {
        sessionStorage.setItem(key, JSON.stringify(normalizeParaphraserState(state)));
    } catch {
        // Ignore storage edge cases.
    }
};

const readParaphraserStateFromSession = (conversationUuid) => {
    const key = paraphraserStateStorageKey(conversationUuid);

    try {
        const raw = sessionStorage.getItem(key);
        if (!raw) return null;

        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) return null;

        return normalizeParaphraserState(parsed);
    } catch {
        return null;
    }
};

const clearParaphraserStateFromSession = (conversationUuid) => {
    const key = paraphraserStateStorageKey(conversationUuid);

    try {
        sessionStorage.removeItem(key);
    } catch {
        // Ignore storage edge cases.
    }
};

const resetParaphraserState = (conversationUuid = "") => {
    paraphraserState.value = createEmptyParaphraserState();
    clearParaphraserStateFromSession(conversationUuid);
};

const extractParaphraserStateFromMessages = (rows = [], conversationUuid = "") => {
    if (Array.isArray(rows) && rows.length > 0) {
        const reversed = [...rows].reverse();

        for (const message of reversed) {
            const metadata = message?.metadata && typeof message.metadata === "object"
                ? message.metadata
                : null;
            const stateCandidate = metadata?.state && typeof metadata.state === "object"
                ? metadata.state
                : null;
            const subToolId = Number(
                metadata?.sub_tool_id
                || message?.sub_tool_id
                || message?.subToolId
                || 0
            );
            const toolKey = String(metadata?.tool || "").toLowerCase();

            if (
                stateCandidate
                && (subToolId === PARAPHRASER_SUB_TOOL_ID || toolKey === PARAPHRASER_TOOL_KEY)
            ) {
                const resolved = normalizeParaphraserState(stateCandidate);
                saveParaphraserStateToSession(conversationUuid, resolved);
                return resolved;
            }
        }
    }

    const stored = readParaphraserStateFromSession(conversationUuid);
    if (stored) {
        return stored;
    }

    return createEmptyParaphraserState();
};

const hydrateParaphraserStateFromMessages = (rows = []) => {
    if (!isParaphraserTool.value) {
        resetParaphraserState();
        return;
    }

    const conversationUuid = activeConversation.value?.uuid || route.params.uuid || "";
    paraphraserState.value = extractParaphraserStateFromMessages(rows, conversationUuid);
};

const resolveParaphraserStateForSubmit = (conversationUuid = "") => {
    let resolved = normalizeParaphraserState(paraphraserState.value);

    if (!hasParaphraserStateValue(resolved)) {
        const latestKnown = extractParaphraserStateFromMessages(messages.value, conversationUuid);
        resolved = hasParaphraserStateValue(latestKnown)
            ? normalizeParaphraserState(latestKnown)
            : createEmptyParaphraserState();
    }

    resolved = normalizeParaphraserState(resolved);
    paraphraserState.value = resolved;

    if (conversationUuid) {
        saveParaphraserStateToSession(conversationUuid, resolved);
    }

    return resolved;
};

const socialPostStateStorageKey = (conversationUuid = "") =>
    `tool_state_${conversationUuid || activeConversation.value?.uuid || route.params.uuid || ""}_${SOCIAL_POST_GENERATOR_SUB_TOOL_ID}`;

const saveSocialPostStateToSession = (conversationUuid, state) => {
    const key = socialPostStateStorageKey(conversationUuid);

    try {
        sessionStorage.setItem(key, JSON.stringify(normalizeSocialPostState(state)));
    } catch {
        // Ignore storage edge cases.
    }
};

const readSocialPostStateFromSession = (conversationUuid) => {
    const key = socialPostStateStorageKey(conversationUuid);

    try {
        const raw = sessionStorage.getItem(key);
        if (!raw) return null;

        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) return null;

        return normalizeSocialPostState(parsed);
    } catch {
        return null;
    }
};

const clearSocialPostStateFromSession = (conversationUuid) => {
    const key = socialPostStateStorageKey(conversationUuid);

    try {
        sessionStorage.removeItem(key);
    } catch {
        // Ignore storage edge cases.
    }
};

const resetSocialPostState = (conversationUuid = "") => {
    socialPostState.value = createEmptySocialPostState();
    clearSocialPostStateFromSession(conversationUuid);
};

const extractSocialPostStateFromMessages = (rows = [], conversationUuid = "") => {
    if (Array.isArray(rows) && rows.length > 0) {
        const reversed = [...rows].reverse();

        for (const message of reversed) {
            const metadata = message?.metadata && typeof message.metadata === "object"
                ? message.metadata
                : null;
            const stateCandidate = metadata?.state && typeof metadata.state === "object"
                ? metadata.state
                : null;
            const subToolId = Number(
                metadata?.sub_tool_id
                || message?.sub_tool_id
                || message?.subToolId
                || 0
            );
            const toolKey = String(metadata?.tool || "").toLowerCase();

            if (
                stateCandidate
                && (subToolId === SOCIAL_POST_GENERATOR_SUB_TOOL_ID || toolKey === SOCIAL_POST_GENERATOR_TOOL_KEY)
            ) {
                const resolved = normalizeSocialPostState(stateCandidate);
                saveSocialPostStateToSession(conversationUuid, resolved);
                return resolved;
            }
        }
    }

    const stored = readSocialPostStateFromSession(conversationUuid);
    if (stored) return stored;

    return createEmptySocialPostState();
};

const hydrateSocialPostStateFromMessages = (rows = []) => {
    if (!isSocialPostGeneratorTool.value) {
        resetSocialPostState();
        return;
    }

    const conversationUuid = activeConversation.value?.uuid || route.params.uuid || "";
    socialPostState.value = extractSocialPostStateFromMessages(rows, conversationUuid);
};

const resolveSocialPostStateForSubmit = (conversationUuid = "") => {
    let resolved = normalizeSocialPostState(socialPostState.value);

    if (!hasSocialPostStateValue(resolved)) {
        const latestKnown = extractSocialPostStateFromMessages(messages.value, conversationUuid);
        resolved = hasSocialPostStateValue(latestKnown)
            ? normalizeSocialPostState(latestKnown)
            : createEmptySocialPostState();
    }

    resolved = normalizeSocialPostState(resolved);
    socialPostState.value = resolved;

    if (conversationUuid) {
        saveSocialPostStateToSession(conversationUuid, resolved);
    }

    return resolved;
};

const emailWriterStateStorageKey = (conversationUuid = "") =>
    `tool_state_${conversationUuid || activeConversation.value?.uuid || route.params.uuid || ""}_${EMAIL_WRITER_SUB_TOOL_ID}`;

const saveEmailWriterStateToSession = (conversationUuid, state) => {
    try {
        sessionStorage.setItem(
            emailWriterStateStorageKey(conversationUuid),
            JSON.stringify(normalizeEmailWriterState(state))
        );
    } catch {
        // Ignore storage edge cases.
    }
};

const readEmailWriterStateFromSession = (conversationUuid) => {
    try {
        const raw = sessionStorage.getItem(emailWriterStateStorageKey(conversationUuid));
        if (!raw) return null;

        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === "object" && !Array.isArray(parsed)
            ? normalizeEmailWriterState(parsed)
            : null;
    } catch {
        return null;
    }
};

const clearEmailWriterStateFromSession = (conversationUuid) => {
    try {
        sessionStorage.removeItem(emailWriterStateStorageKey(conversationUuid));
    } catch {
        // Ignore storage edge cases.
    }
};

const resetEmailWriterState = (conversationUuid = "") => {
    emailWriterState.value = createEmptyEmailWriterState();
    clearEmailWriterStateFromSession(conversationUuid);
};

const extractEmailWriterStateFromMessages = (rows = [], conversationUuid = "") => {
    if (Array.isArray(rows)) {
        for (const message of [...rows].reverse()) {
            const metadata = message?.metadata && typeof message.metadata === "object"
                ? message.metadata
                : null;
            const stateCandidate = metadata?.state && typeof metadata.state === "object"
                ? metadata.state
                : null;
            const subToolId = Number(metadata?.sub_tool_id || message?.sub_tool_id || 0);
            const toolKey = String(metadata?.tool || "").toLowerCase();

            if (
                stateCandidate
                && (subToolId === EMAIL_WRITER_SUB_TOOL_ID || toolKey === EMAIL_WRITER_TOOL_KEY)
            ) {
                const resolved = normalizeEmailWriterState(stateCandidate);
                saveEmailWriterStateToSession(conversationUuid, resolved);
                return resolved;
            }
        }
    }

    return readEmailWriterStateFromSession(conversationUuid) || createEmptyEmailWriterState();
};

const hydrateEmailWriterStateFromMessages = (rows = []) => {
    if (!isEmailWriterTool.value) {
        resetEmailWriterState();
        return;
    }

    const conversationUuid = activeConversation.value?.uuid || route.params.uuid || "";
    emailWriterState.value = extractEmailWriterStateFromMessages(rows, conversationUuid);
};

const resolveEmailWriterStateForSubmit = (conversationUuid = "") => {
    let resolved = normalizeEmailWriterState(emailWriterState.value);

    if (!hasEmailWriterStateValue(resolved)) {
        const latestKnown = extractEmailWriterStateFromMessages(messages.value, conversationUuid);
        resolved = hasEmailWriterStateValue(latestKnown)
            ? latestKnown
            : createEmptyEmailWriterState();
    }

    resolved = normalizeEmailWriterState(resolved);
    emailWriterState.value = resolved;

    if (conversationUuid) saveEmailWriterStateToSession(conversationUuid, resolved);

    return resolved;
};

const scriptGeneratorStateStorageKey = (conversationUuid = "") =>
    `tool_state_${conversationUuid || activeConversation.value?.uuid || route.params.uuid || ""}_${SCRIPT_GENERATOR_SUB_TOOL_ID}`;

const saveScriptGeneratorStateToSession = (conversationUuid, state) => {
    try {
        sessionStorage.setItem(
            scriptGeneratorStateStorageKey(conversationUuid),
            JSON.stringify(normalizeScriptGeneratorState(state))
        );
    } catch {
        // Ignore storage edge cases.
    }
};

const readScriptGeneratorStateFromSession = (conversationUuid) => {
    try {
        const raw = sessionStorage.getItem(scriptGeneratorStateStorageKey(conversationUuid));
        if (!raw) return null;
        return normalizeScriptGeneratorState(JSON.parse(raw));
    } catch {
        return null;
    }
};

const clearScriptGeneratorStateFromSession = (conversationUuid) => {
    try {
        sessionStorage.removeItem(scriptGeneratorStateStorageKey(conversationUuid));
    } catch {
        // Ignore storage edge cases.
    }
};

const resetScriptGeneratorState = (conversationUuid = "") => {
    scriptGeneratorState.value = createEmptyScriptGeneratorState();
    clearScriptGeneratorStateFromSession(conversationUuid);
};

const extractScriptGeneratorStateFromMessages = (rows = [], conversationUuid = "") => {
    if (Array.isArray(rows) && rows.length > 0) {
        for (const message of [...rows].reverse()) {
            const metadata = message?.metadata && typeof message.metadata === "object"
                ? message.metadata
                : null;
            const stateCandidate = metadata?.state && typeof metadata.state === "object"
                ? metadata.state
                : null;
            const subToolId = Number(
                metadata?.sub_tool_id
                || message?.sub_tool_id
                || message?.subToolId
                || 0
            );
            const toolKey = String(metadata?.tool || "").toLowerCase();

            if (
                stateCandidate
                && (subToolId === SCRIPT_GENERATOR_SUB_TOOL_ID || toolKey === SCRIPT_GENERATOR_TOOL_KEY)
            ) {
                const resolved = normalizeScriptGeneratorState(stateCandidate);
                saveScriptGeneratorStateToSession(conversationUuid, resolved);
                return resolved;
            }
        }
    }

    return readScriptGeneratorStateFromSession(conversationUuid)
        || createEmptyScriptGeneratorState();
};

const hydrateScriptGeneratorStateFromMessages = (rows = []) => {
    if (!isScriptGeneratorTool.value) {
        resetScriptGeneratorState();
        return;
    }

    const conversationUuid = activeConversation.value?.uuid || route.params.uuid || "";
    scriptGeneratorState.value = extractScriptGeneratorStateFromMessages(
        rows,
        conversationUuid
    );
};

const resolveScriptGeneratorStateForSubmit = (conversationUuid = "") => {
    let resolved = normalizeScriptGeneratorState(scriptGeneratorState.value);

    if (!hasScriptGeneratorStateValue(resolved)) {
        const latestKnown = extractScriptGeneratorStateFromMessages(
            messages.value,
            conversationUuid
        );
        resolved = hasScriptGeneratorStateValue(latestKnown)
            ? normalizeScriptGeneratorState(latestKnown)
            : createEmptyScriptGeneratorState();
    }

    scriptGeneratorState.value = resolved;

    if (conversationUuid) {
        saveScriptGeneratorStateToSession(conversationUuid, resolved);
    }

    return resolved;
};

const productDescriptionStateStorageKey = (conversationUuid = "") =>
    `tool_state_${conversationUuid || activeConversation.value?.uuid || route.params.uuid || ""}_${PRODUCT_DESCRIPTION_GENERATOR_SUB_TOOL_ID}`;

const saveProductDescriptionStateToSession = (conversationUuid, state) => {
    try {
        sessionStorage.setItem(
            productDescriptionStateStorageKey(conversationUuid),
            JSON.stringify(normalizeProductDescriptionState(state))
        );
    } catch {
        // Ignore storage edge cases.
    }
};

const readProductDescriptionStateFromSession = (conversationUuid) => {
    try {
        const raw = sessionStorage.getItem(productDescriptionStateStorageKey(conversationUuid));
        return raw ? normalizeProductDescriptionState(JSON.parse(raw)) : null;
    } catch {
        return null;
    }
};

const clearProductDescriptionStateFromSession = (conversationUuid) => {
    try {
        sessionStorage.removeItem(productDescriptionStateStorageKey(conversationUuid));
    } catch {
        // Ignore storage edge cases.
    }
};

const resetProductDescriptionState = (conversationUuid = "") => {
    productDescriptionState.value = createEmptyProductDescriptionState();
    productOptionsOpen.value = false;
    clearProductDescriptionStateFromSession(conversationUuid);
};

const extractProductDescriptionStateFromMessages = (rows = [], conversationUuid = "") => {
    if (Array.isArray(rows)) {
        for (const message of [...rows].reverse()) {
            const metadata = message?.metadata && typeof message.metadata === "object"
                ? message.metadata
                : {};
            const subToolId = Number(metadata.sub_tool_id || message?.sub_tool_id || 0);
            const toolKey = String(metadata.tool || "").toLowerCase();

            if (
                metadata.state
                && typeof metadata.state === "object"
                && (
                    subToolId === PRODUCT_DESCRIPTION_GENERATOR_SUB_TOOL_ID
                    || toolKey === PRODUCT_DESCRIPTION_GENERATOR_TOOL_KEY
                )
            ) {
                const state = normalizeProductDescriptionState(metadata.state);
                saveProductDescriptionStateToSession(conversationUuid, state);
                return state;
            }
        }
    }

    return readProductDescriptionStateFromSession(conversationUuid)
        || createEmptyProductDescriptionState();
};

const hydrateProductDescriptionStateFromMessages = (rows = []) => {
    if (!isProductDescriptionGeneratorTool.value) {
        resetProductDescriptionState();
        return;
    }

    const conversationUuid = activeConversation.value?.uuid || route.params.uuid || "";
    productDescriptionState.value = extractProductDescriptionStateFromMessages(
        rows,
        conversationUuid
    );
};

const resolveProductDescriptionStateForSubmit = (conversationUuid = "") => {
    let state = normalizeProductDescriptionState(productDescriptionState.value);

    if (!hasProductDescriptionStateValue(state)) {
        state = extractProductDescriptionStateFromMessages(messages.value, conversationUuid);
    }

    productDescriptionState.value = normalizeProductDescriptionState(state);
    saveProductDescriptionStateToSession(conversationUuid, productDescriptionState.value);

    return productDescriptionState.value;
};

const extractHeadlineStateFromMessages = (rows = [], conversationUuid = "") => {
    if (!Array.isArray(rows) || !rows.length) {
        const stored = readHeadlineStateFromSession(conversationUuid);
        if (stored) {
            return mergeHeadlineState(getInitialHeadlineState(), stored);
        }

        return getInitialHeadlineState();
    }

    const reversed = [...rows].reverse();

    const lastAssistantMeta = reversed.find((message) =>
        message?.role === "assistant"
        && message?.metadata
        && typeof message.metadata === "object"
    )?.metadata;

    if ((lastAssistantMeta?.type || "") === "result") {
        clearHeadlineStateFromSession(conversationUuid);
        return getInitialHeadlineState();
    }

    for (const message of reversed) {
        const metadata = message?.metadata && typeof message.metadata === "object"
            ? message.metadata
            : null;

        const stateCandidate = metadata?.state && typeof metadata.state === "object"
            ? metadata.state
            : null;

        if (stateCandidate && (metadata?.type || "") === "question") {
            saveHeadlineStateToSession(conversationUuid, stateCandidate);
            return mergeHeadlineState(getInitialHeadlineState(), stateCandidate);
        }
    }

    const stored = readHeadlineStateFromSession(conversationUuid);
    if (stored) {
        return mergeHeadlineState(getInitialHeadlineState(), stored);
    }

    return getInitialHeadlineState();
};

const hydrateHeadlineStateFromMessages = (rows = []) => {
    if (!isHeadlineGeneratorTool.value) {
        resetHeadlineState();
        return;
    }

    const conversationUuid = activeConversation.value?.uuid || route.params.uuid || "";
    headlineState.value = extractHeadlineStateFromMessages(rows, conversationUuid);
};

const normalizeTextSummarizerApiResponse = (response = {}) => {
    if (response?.status === "error" || response?.success === false) {
        return {
            success: false,
            reply: String(response?.message || "حدث خطأ أثناء تلخيص النص."),
            task_key: TEXT_SUMMARIZER_TASK_KEY,
            tool: TEXT_SUMMARIZER_TOOL_KEY,
            model_key: null,
            request_id: null,
            usage: null,
            cost: null,
        };
    }

    const payload = response?.data && typeof response.data === "object"
        ? response.data
        : response;
    if (!payload || typeof payload !== "object") return null;

    const normalizedSubToolId = Number(payload?.sub_tool_id || subtool.value?.id || 0);
    const normalizedTaskKey = String(payload?.task_key || "").trim().toLowerCase();
    const normalizedTool = String(payload?.tool || "").trim().toLowerCase();

    if (
        normalizedSubToolId !== TEXT_SUMMARIZER_SUB_TOOL_ID
        && normalizedTaskKey !== TEXT_SUMMARIZER_TASK_KEY
        && normalizedTool !== TEXT_SUMMARIZER_TOOL_KEY
    ) {
        return null;
    }

    return {
        success: payload?.success !== false,
        reply: String(payload?.reply || payload?.message || "").trim(),
        task_key: normalizedTaskKey || TEXT_SUMMARIZER_TASK_KEY,
        tool: normalizedTool || TEXT_SUMMARIZER_TOOL_KEY,
        model_key: payload?.model_key || null,
        user_id: payload?.user_id ?? null,
        sub_tool_id: normalizedSubToolId || TEXT_SUMMARIZER_SUB_TOOL_ID,
        conversation_uuid: payload?.conversation_uuid || activeConversation.value?.uuid || route.params.uuid || null,
        request_id: payload?.request_id || null,
        debug: payload?.debug ?? null,
        usage: payload?.usage && typeof payload.usage === "object" ? payload.usage : null,
        cost: payload?.cost && typeof payload.cost === "object" ? payload.cost : null,
        request_payload: payload?.request_payload && typeof payload.request_payload === "object"
            ? payload.request_payload
            : null,
    };
};

const normalizeHeadlineApiResponse = (response = {}) => {
    if (response?.status === "error") {
        return {
            success: false,
            type: "message",
            tool: "ai_headline_generator",
            provider: null,
            model_key: null,
            user_id: null,
            sub_tool_id: HEADLINE_GENERATOR_SUB_TOOL_ID,
            conversation_uuid: activeConversation.value?.uuid || route.params.uuid || null,
            message: String(response?.message || "حدث خطأ أثناء معالجة الطلب."),
            state: null,
            headlines: [],
            count: 0,
            request_id: null,
            usage: null,
            cost: null,
            wallet: null,
            should_reset_state: false,
        };
    }

    const payload = response?.data && typeof response.data === "object" ? response.data : response;
    if (!payload || typeof payload !== "object") return null;

    return {
        success: payload.success !== false,
        type: payload.type || "",
        tool: payload.tool || "ai_headline_generator",
        provider: payload.provider || null,
        model_key: payload.model_key || null,
        user_id: payload.user_id ?? null,
        sub_tool_id: payload.sub_tool_id ?? HEADLINE_GENERATOR_SUB_TOOL_ID,
        conversation_uuid: payload.conversation_uuid || activeConversation.value?.uuid || route.params.uuid || null,
        message: payload.message || "",
        state: payload.state && typeof payload.state === "object" ? payload.state : null,
        headlines: Array.isArray(payload.headlines) ? payload.headlines : [],
        count: payload.count ?? null,
        request_id: payload.request_id || null,
        usage: payload.usage && typeof payload.usage === "object" ? payload.usage : null,
        cost: payload.cost && typeof payload.cost === "object" ? payload.cost : null,
        wallet: payload.wallet && typeof payload.wallet === "object" ? payload.wallet : null,
        should_reset_state: Boolean(payload.should_reset_state),
    };
};

const normalizeParaphraserApiResponse = (response = {}) => {
    const payload = response?.data && typeof response.data === "object" ? response.data : response;
    if (!payload || typeof payload !== "object") return null;

    const normalizedSubToolId = Number(payload?.sub_tool_id || subtool.value?.id || 0);
    const normalizedTool = String(payload?.tool || "").trim().toLowerCase();

    if (normalizedSubToolId !== PARAPHRASER_SUB_TOOL_ID && normalizedTool !== PARAPHRASER_TOOL_KEY) {
        return null;
    }

    const results = Array.isArray(payload?.results)
        ? payload.results
            .map((item, index) => ({
                id: Number(item?.id || index + 1),
                text: String(item?.text || "").trim(),
            }))
            .filter((item) => item.text)
        : [];

    const outputText = results.length
        ? results.map((r) => r.text).join("\n\n")
        : String(payload?.message || "");

    return {
        success: payload?.success !== false,
        sub_tool_id: normalizedSubToolId,
        tool: normalizedTool || PARAPHRASER_TOOL_KEY,
        message: String(payload?.message || ""),
        results,
        outputText,
        state: payload?.state && typeof payload.state === "object"
            ? normalizeParaphraserState(payload.state)
            : null,
    };
};

const normalizeSocialPostApiResponse = (response = {}) => {
    if (response?.status === "error") {
        return {
            success: false,
            type: "error",
            tool: SOCIAL_POST_GENERATOR_TOOL_KEY,
            provider: null,
            model_key: "social_post_generator",
            user_id: null,
            sub_tool_id: SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
            conversation_uuid: activeConversation.value?.uuid || route.params.uuid || null,
            message: String(response?.message || "حدث خطأ أثناء توليد منشور السوشيال."),
            state: null,
            results: [],
            outputText: "",
            count: 0,
            request_id: null,
            usage: null,
            cost: null,
        };
    }

    const payload = response?.data && typeof response.data === "object" ? response.data : response;
    if (!payload || typeof payload !== "object") return null;

    const normalizedSubToolId = Number(payload?.sub_tool_id || subtool.value?.id || 0);
    const normalizedTool = String(payload?.tool || "").trim().toLowerCase();

    if (
        normalizedSubToolId !== SOCIAL_POST_GENERATOR_SUB_TOOL_ID
        && normalizedTool !== SOCIAL_POST_GENERATOR_TOOL_KEY
    ) {
        return null;
    }

    const results = Array.isArray(payload?.results)
        ? payload.results
            .map((item, index) => ({
                id: Number(item?.id || index + 1),
                text: String(item?.text || "").trim(),
                title: item?.title ?? null,
                subject: item?.subject ?? null,
                meta: item?.meta && typeof item.meta === "object" ? item.meta : {},
            }))
            .filter((item) => item.text)
        : [];

    const outputText = results.length
        ? results.map((item) => item.text).join("\n\n")
        : "";

    return {
        success: payload?.success !== false,
        type: payload?.type || "",
        tool: normalizedTool || SOCIAL_POST_GENERATOR_TOOL_KEY,
        provider: payload?.provider || null,
        model_key: payload?.model_key || "social_post_generator",
        user_id: payload?.user_id ?? null,
        sub_tool_id: normalizedSubToolId || SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
        conversation_uuid: payload?.conversation_uuid || activeConversation.value?.uuid || route.params.uuid || null,
        message: String(payload?.message || ""),
        state: payload?.state && typeof payload.state === "object"
            ? normalizeSocialPostState(payload.state)
            : null,
        results,
        outputText,
        count: payload?.count ?? results.length,
        request_id: payload?.request_id || null,
        debug: payload?.debug ?? null,
        usage: payload?.usage && typeof payload.usage === "object" ? payload.usage : null,
        cost: payload?.cost && typeof payload.cost === "object" ? payload.cost : null,
    };
};

const normalizeEmailWriterApiResponse = (response = {}) => {
    if (response?.status === "error") {
        return {
            success: false,
            type: "error",
            tool: EMAIL_WRITER_TOOL_KEY,
            provider: null,
            model_key: "email_writer",
            sub_tool_id: EMAIL_WRITER_SUB_TOOL_ID,
            message: String(response?.message || "حدث خطأ أثناء كتابة الإيميل."),
            state: null,
            results: [],
            outputText: "",
            count: 0,
            request_id: null,
            usage: null,
            cost: null,
        };
    }

    const payload = response?.data && typeof response.data === "object" ? response.data : response;
    if (!payload || typeof payload !== "object") return null;

    const normalizedSubToolId = Number(payload?.sub_tool_id || subtool.value?.id || 0);
    const normalizedTool = String(payload?.tool || "").trim().toLowerCase();

    if (
        normalizedSubToolId !== EMAIL_WRITER_SUB_TOOL_ID
        && normalizedTool !== EMAIL_WRITER_TOOL_KEY
    ) {
        return null;
    }

    const results = Array.isArray(payload?.results)
        ? payload.results
            .map((item, index) => ({
                id: Number(item?.id || index + 1),
                text: String(item?.text || "").trim(),
                title: item?.title ?? null,
                subject: item?.subject ?? null,
                meta: item?.meta && typeof item.meta === "object" ? item.meta : {},
            }))
            .filter((item) => item.text)
        : [];

    return {
        success: payload?.success !== false,
        type: payload?.type || "",
        tool: normalizedTool || EMAIL_WRITER_TOOL_KEY,
        provider: payload?.provider || null,
        model_key: payload?.model_key || "email_writer",
        user_id: payload?.user_id ?? null,
        sub_tool_id: normalizedSubToolId || EMAIL_WRITER_SUB_TOOL_ID,
        conversation_uuid: payload?.conversation_uuid || activeConversation.value?.uuid || route.params.uuid || null,
        message: String(payload?.message || ""),
        state: payload?.state && typeof payload.state === "object"
            ? normalizeEmailWriterState(payload.state)
            : null,
        results,
        outputText: results[0]?.text || "",
        count: payload?.count ?? results.length,
        request_id: payload?.request_id || null,
        debug: payload?.debug ?? null,
        usage: payload?.usage && typeof payload.usage === "object" ? payload.usage : null,
        cost: payload?.cost && typeof payload.cost === "object" ? payload.cost : null,
    };
};

const normalizeScriptGeneratorApiResponse = (response = {}) => {
    if (response?.status === "error") {
        return {
            success: false,
            type: "error",
            tool: SCRIPT_GENERATOR_TOOL_KEY,
            provider: null,
            model_key: "script_generator",
            sub_tool_id: SCRIPT_GENERATOR_SUB_TOOL_ID,
            message: String(response?.message || "حدث خطأ أثناء توليد السكريبت."),
            state: null,
            results: [],
            outputText: "",
            count: 0,
            request_id: null,
            usage: null,
            cost: null,
        };
    }

    const payload = response?.data && typeof response.data === "object" ? response.data : response;
    if (!payload || typeof payload !== "object") return null;

    const normalizedSubToolId = Number(payload?.sub_tool_id || subtool.value?.id || 0);
    const normalizedTool = String(payload?.tool || "").trim().toLowerCase();

    if (
        normalizedSubToolId !== SCRIPT_GENERATOR_SUB_TOOL_ID
        && normalizedTool !== SCRIPT_GENERATOR_TOOL_KEY
    ) {
        return null;
    }

    const results = Array.isArray(payload?.results)
        ? payload.results
            .map((item, index) => ({
                id: Number(item?.id || index + 1),
                text: String(item?.text || "").trim(),
                title: item?.title ?? null,
                subject: item?.subject ?? null,
                meta: item?.meta && typeof item.meta === "object" ? item.meta : {},
            }))
            .filter((item) => item.text)
        : [];

    return {
        success: payload?.success !== false,
        type: payload?.type || "",
        tool: normalizedTool || SCRIPT_GENERATOR_TOOL_KEY,
        provider: payload?.provider || null,
        model_key: payload?.model_key || "script_generator",
        user_id: payload?.user_id ?? null,
        sub_tool_id: normalizedSubToolId || SCRIPT_GENERATOR_SUB_TOOL_ID,
        conversation_uuid: payload?.conversation_uuid || activeConversation.value?.uuid || route.params.uuid || null,
        message: String(payload?.message || ""),
        state: payload?.state && typeof payload.state === "object"
            ? normalizeScriptGeneratorState(payload.state)
            : null,
        results,
        outputText: results.length
            ? results.map((item) => item.text).join("\n\n")
            : "",
        count: payload?.count ?? results.length,
        request_id: payload?.request_id || null,
        debug: payload?.debug ?? null,
        usage: payload?.usage && typeof payload.usage === "object" ? payload.usage : null,
        cost: payload?.cost && typeof payload.cost === "object" ? payload.cost : null,
    };
};

const normalizeProductDescriptionApiResponse = (response = {}) => {
    if (response?.status === "error" || response?.success === false) {
        return {
            success: false,
            type: "error",
            tool: PRODUCT_DESCRIPTION_GENERATOR_TOOL_KEY,
            provider: null,
            model_key: PRODUCT_DESCRIPTION_GENERATOR_MODEL_KEY,
            sub_tool_id: PRODUCT_DESCRIPTION_GENERATOR_SUB_TOOL_ID,
            message: String(response?.message || "حدث خطأ أثناء توليد وصف المنتج. حاول مرة أخرى."),
            state: null,
            results: [],
            count: 0,
            request_id: null,
            usage: null,
            cost: null,
        };
    }

    const payload = response?.data && typeof response.data === "object"
        ? response.data
        : response;
    if (!payload || typeof payload !== "object") return null;

    const normalizedSubToolId = Number(payload.sub_tool_id || subtool.value?.id || 0);
    const normalizedTool = String(payload.tool || "").trim().toLowerCase();

    if (
        normalizedSubToolId !== PRODUCT_DESCRIPTION_GENERATOR_SUB_TOOL_ID
        && normalizedTool !== PRODUCT_DESCRIPTION_GENERATOR_TOOL_KEY
    ) {
        return null;
    }

    const results = Array.isArray(payload.results)
        ? payload.results
            .map((item, index) => ({
                id: Number(item?.id || index + 1),
                text: String(item?.text || "").trim(),
                title: item?.title ?? null,
                subject: item?.subject ?? null,
                meta: item?.meta && typeof item.meta === "object" ? item.meta : {},
            }))
            .filter((item) => item.text)
        : [];
    const state = payload.state && typeof payload.state === "object"
        ? normalizeProductDescriptionState(payload.state)
        : null;
    const fallbackOutput = String(state?.last_output || "").trim();

    if (!results.length && fallbackOutput) {
        results.push({
            id: 1,
            text: fallbackOutput,
            title: null,
            subject: null,
            meta: {},
        });
    }

    return {
        success: payload.success !== false,
        type: payload.type || "",
        tool: normalizedTool || PRODUCT_DESCRIPTION_GENERATOR_TOOL_KEY,
        provider: payload.provider || "openrouter",
        model_key: payload.model_key || PRODUCT_DESCRIPTION_GENERATOR_MODEL_KEY,
        user_id: payload.user_id ?? null,
        sub_tool_id: normalizedSubToolId || PRODUCT_DESCRIPTION_GENERATOR_SUB_TOOL_ID,
        conversation_uuid: payload.conversation_uuid || activeConversation.value?.uuid || route.params.uuid || null,
        message: String(payload.message || ""),
        state,
        results,
        count: payload.count ?? results.length,
        request_id: payload.request_id || null,
        debug: import.meta.env.DEV ? payload.debug ?? null : null,
        usage: payload.usage && typeof payload.usage === "object" ? payload.usage : null,
        cost: payload.cost && typeof payload.cost === "object" ? payload.cost : null,
    };
};

const addAssistantLocalMessage = async (content, extra = {}) => {
    const { plainText = true, ...rest } = extra;

    messages.value.push(
        mapMessage(
            {
                content,
                role: "assistant",
                created_at: new Date().toISOString(),
                plainText,
                ...rest,
            },
            messages.value.length
        )
    );

    await scrollToBottom();
};

const addUserLocalMessage = async (content, extra = {}) => {
    messages.value.push(
        mapMessage(
            {
                content,
                role: "user",
                created_at: new Date().toISOString(),
                plainText: true,
                ...extra,
            },
            messages.value.length
        )
    );

    await scrollToBottom();
};

const resolveCurrentUserId = () => {
    const candidates = [
        activeConversation.value?.user_id,
        conversations.value.find((item) => item.uuid === activeConversation.value?.uuid)?.user_id,
    ];

    for (const candidate of candidates) {
        const parsed = Number(candidate);
        if (Number.isFinite(parsed) && parsed > 0) {
            return parsed;
        }
    }

    return null;
};

const handleHeadlineGeneratorSubmit = async (text, options = {}) => {
    const submitOptions = {
        regenerate: false,
        previousOutput: "",
        forceNewIdempotency: false,
        ...options,
    };
    const inputText = String(text || "").trim();

    if (!inputText || sendingMessage.value || streamingAssistant.value || conversationLimitExceeded.value) {
        return;
    }

    if (!(await requireAuth())) {
        return;
    }

    await addUserLocalMessage(inputText, {
        sub_tool_id: HEADLINE_GENERATOR_SUB_TOOL_ID,
        metadata: {
            type: "user_input",
            state: headlineState.value,
            sub_tool_id: HEADLINE_GENERATOR_SUB_TOOL_ID,
        },
    });
    userInput.value = "";
    resetTextarea();

    sendingMessage.value = true;

    const conversation = await ensureConversation();

    if (!conversation) {
        sendingMessage.value = false;
        return;
    }

    const idempotencyKey = resolveIdempotencyKey(conversation.uuid, inputText, {
        forceNew: submitOptions.forceNewIdempotency,
    });
    const requestSignature = `${conversation.id}:${idempotencyKey}`;

    if (inFlightSignatures.has(requestSignature)) {
        sendingMessage.value = false;
        return;
    }

    inFlightSignatures.add(requestSignature);
    const typingId = addAssistantTypingMessage();

    try {
        const payload = {
            user_id: resolveCurrentUserId(),
            sub_tool_id: HEADLINE_GENERATOR_SUB_TOOL_ID,
            conversation_uuid: conversation.uuid,
            conversation_id: conversation.id,
            user_message: inputText,
            debug: HEADLINE_DEBUG_MODE,
            idempotency_key: idempotencyKey,
            state: headlineState.value,
            ...(submitOptions.regenerate
                ? {
                    regenerate: true,
                    previous_output: submitOptions.previousOutput,
                }
                : {}),
        };

        const response = await chatServices.sendMessage(payload);
        removeAssistantTypingMessage(typingId);
        const apiResponse = normalizeHeadlineApiResponse(response);

        if (!apiResponse) {
            await addAssistantLocalMessage("تعذر قراءة الاستجابة. حاول مرة أخرى.", { is_error: true });
            return;
        }

        if (apiResponse.state && apiResponse.type !== "result" && !apiResponse.should_reset_state) {
            headlineState.value = mergeHeadlineState(headlineState.value, apiResponse.state);
        }

        const metadata = {
            type: apiResponse.type || "message",
            request_id: apiResponse.request_id,
            provider: apiResponse.provider,
            model_key: apiResponse.model_key,
            tool: apiResponse.tool,
            state: headlineState.value,
            usage: apiResponse.usage,
            cost: apiResponse.cost,
            headlines: apiResponse.headlines,
            sub_tool_id: HEADLINE_GENERATOR_SUB_TOOL_ID,
        };

        if (apiResponse.type === "question") {
            if (apiResponse.state) {
                saveHeadlineStateToSession(conversation.uuid, headlineState.value);
            }

            await addAssistantLocalMessage(
                buildHeadlineQuestionMessage(apiResponse),
                {
                    plainText: true,
                    metadata,
                    sub_tool_id: HEADLINE_GENERATOR_SUB_TOOL_ID,
                }
            );
            await focusChatInput();
        } else if (apiResponse.type === "result") {
            await addAssistantLocalMessage(
                buildHeadlineResultMessage(apiResponse),
                {
                    plainText: false,
                    metadata,
                    sub_tool_id: HEADLINE_GENERATOR_SUB_TOOL_ID,
                }
            );
            resetHeadlineState(conversation.uuid);
            await focusChatInput();
        } else if (apiResponse.should_reset_state) {
            await addAssistantLocalMessage(
                String(apiResponse.message || "تم تنفيذ الطلب."),
                {
                    plainText: true,
                    metadata,
                    sub_tool_id: HEADLINE_GENERATOR_SUB_TOOL_ID,
                }
            );
            resetHeadlineState(conversation.uuid);
            await focusChatInput();
        } else {
            await addAssistantLocalMessage(
                String(apiResponse.message || "تم استلام الاستجابة."),
                {
                    plainText: true,
                    metadata,
                    sub_tool_id: HEADLINE_GENERATOR_SUB_TOOL_ID,
                }
            );
        }

        if (!conversations.value.find((item) => item.uuid === conversation.uuid)) {
            conversations.value.unshift(conversation);
        }

        clearPendingSend(conversation.uuid);
    } catch {
        removeAssistantTypingMessage(typingId);
        await addAssistantLocalMessage("حصل خطأ أثناء الإرسال. جرّب مرة أخرى.", { is_error: true });
    } finally {
        removeAssistantTypingMessage(typingId);
        inFlightSignatures.delete(requestSignature);
        sendingMessage.value = false;
        await scrollToBottom();
    }
};

const handleParaphraserSubmit = async (text, options = {}) => {
    const submitOptions = {
        regenerate: false,
        previousOutput: "",
        forceNewIdempotency: false,
        ...options,
    };
    const inputText = String(text || "").trim();

    if (!inputText || sendingMessage.value || streamingAssistant.value || conversationLimitExceeded.value) {
        return;
    }

    if (!(await requireAuth())) {
        return;
    }

    const currentConversationUuid = activeConversation.value?.uuid || route.params.uuid || "";
    const localParaphraserState = resolveParaphraserStateForSubmit(currentConversationUuid);

    await addUserLocalMessage(inputText, {
        sub_tool_id: PARAPHRASER_SUB_TOOL_ID,
        metadata: {
            type: "user_input",
            tool: PARAPHRASER_TOOL_KEY,
            sub_tool_id: PARAPHRASER_SUB_TOOL_ID,
            state: localParaphraserState,
        },
    });

    userInput.value = "";
    resetTextarea();

    sendingMessage.value = true;
    const conversation = await ensureConversation();

    if (!conversation) {
        sendingMessage.value = false;
        return;
    }

    const idempotencyKey = resolveIdempotencyKey(conversation.uuid, inputText, {
        forceNew: submitOptions.forceNewIdempotency,
    });
    const requestSignature = `${conversation.id}:${idempotencyKey}`;

    if (inFlightSignatures.has(requestSignature)) {
        sendingMessage.value = false;
        return;
    }

    inFlightSignatures.add(requestSignature);
    const typingId = addAssistantTypingMessage();

    try {
        const resolvedParaphraserState = resolveParaphraserStateForSubmit(conversation.uuid);
        const payload = {
            user_id: resolveCurrentUserId(),
            content: inputText,
            user_message: inputText,
            sub_tool_id: PARAPHRASER_SUB_TOOL_ID,
            conversation_uuid: conversation.uuid,
            conversation_id: conversation.id,
            role: "user",
            idempotency_key: idempotencyKey,
            tool: PARAPHRASER_TOOL_KEY,
            state: resolvedParaphraserState,
            debug: false,
            ...(submitOptions.regenerate
                ? {
                    regenerate: true,
                    previous_output: submitOptions.previousOutput,
                }
                : {}),
        };

        if (Number(payload.sub_tool_id) === PARAPHRASER_SUB_TOOL_ID && import.meta.env.DEV) {
            console.info("[Paraphraser] outgoing payload:", {
                ...payload,
                content: payload.content
                    ? "[content hidden in console, see content_preview/content_length]"
                    : payload.content,
                user_message: payload.user_message
                    ? "[user_message hidden in console, see user_message_preview/user_message_length]"
                    : payload.user_message,
                content_preview: String(payload.content || payload.user_message || "").slice(0, 300),
                content_length: String(payload.content || payload.user_message || "").length,
                user_message_preview: String(payload.user_message || "").slice(0, 300),
                user_message_length: String(payload.user_message || "").length,
            });
        }

        const response = await chatServices.sendMessage(payload);
        removeAssistantTypingMessage(typingId);

        const apiResponse = normalizeParaphraserApiResponse(response);

        if (!apiResponse) {
            await addAssistantLocalMessage("تعذر قراءة نتيجة إعادة الصياغة. حاول مرة أخرى.", { is_error: true });
            return;
        }

        const results = apiResponse?.results || [];
        const outputText = results.length ? results.map((r) => r.text).join("\n\n") : apiResponse?.message || "";

        if (apiResponse.state) {
            paraphraserState.value = normalizeParaphraserState(apiResponse.state);
            saveParaphraserStateToSession(conversation.uuid, paraphraserState.value);
        }

        if (apiResponse.success === false) {
            await addAssistantLocalMessage(
                String(apiResponse.message || "فشل تنفيذ إعادة الصياغة."),
                {
                    plainText: true,
                    is_error: true,
                    sub_tool_id: PARAPHRASER_SUB_TOOL_ID,
                    metadata: {
                        type: "error",
                        tool: PARAPHRASER_TOOL_KEY,
                        sub_tool_id: PARAPHRASER_SUB_TOOL_ID,
                        state: paraphraserState.value,
                    },
                }
            );
            return;
        }

        if (!results.length) {
            await addAssistantLocalMessage(
                String(outputText || "تم التنفيذ ولكن لم يتم العثور على نص معاد صياغته."),
                {
                    plainText: true,
                    sub_tool_id: PARAPHRASER_SUB_TOOL_ID,
                    metadata: {
                        type: "result_empty",
                        tool: PARAPHRASER_TOOL_KEY,
                        sub_tool_id: PARAPHRASER_SUB_TOOL_ID,
                        results,
                        state: paraphraserState.value,
                    },
                }
            );
            return;
        }

        await addAssistantLocalMessage(outputText, {
            plainText: true,
            sub_tool_id: PARAPHRASER_SUB_TOOL_ID,
            metadata: {
                type: "result",
                tool: PARAPHRASER_TOOL_KEY,
                sub_tool_id: PARAPHRASER_SUB_TOOL_ID,
                results,
                state: paraphraserState.value,
            },
        });

        if (!conversations.value.find((item) => item.uuid === conversation.uuid)) {
            conversations.value.unshift(conversation);
        }

        clearPendingSend(conversation.uuid);
    } catch {
        removeAssistantTypingMessage(typingId);
        await addAssistantLocalMessage("حصل خطأ أثناء إعادة الصياغة. جرّب مرة أخرى.", { is_error: true });
    } finally {
        removeAssistantTypingMessage(typingId);
        inFlightSignatures.delete(requestSignature);
        sendingMessage.value = false;
        await scrollToBottom();
    }
};

const handleSocialPostGeneratorSubmit = async (text, options = {}) => {
    const submitOptions = {
        regenerate: false,
        previousOutput: "",
        forceNewIdempotency: false,
        ...options,
    };
    const initialInputText = String(text || "").trim();
    const initialStateContent = String(socialPostState.value.content || "").trim();

    if (
        (!initialInputText && !initialStateContent)
        || sendingMessage.value
        || streamingAssistant.value
        || conversationLimitExceeded.value
    ) {
        return;
    }

    if (!(await requireAuth())) {
        return;
    }

    sendingMessage.value = true;

    const conversation = await ensureConversation();

    if (!conversation?.uuid) {
        sendingMessage.value = false;
        await addAssistantLocalMessage("تعذر إنشاء المحادثة. حاول مرة أخرى.", {
            is_error: true,
        });
        return;
    }

    const requestState = resolveSocialPostStateForSubmit(conversation.uuid);
    const inputText = initialInputText || String(requestState.content || "").trim();

    await addUserLocalMessage(inputText, {
        sub_tool_id: SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
        metadata: {
            type: "user_input",
            tool: SOCIAL_POST_GENERATOR_TOOL_KEY,
            sub_tool_id: SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
            state: requestState,
        },
    });

    userInput.value = "";
    resetTextarea();

    const idempotencyKey = resolveIdempotencyKey(conversation.uuid, inputText, {
        forceNew: submitOptions.forceNewIdempotency,
    });
    const requestSignature = `${conversation.id}:${idempotencyKey}`;

    if (inFlightSignatures.has(requestSignature)) {
        sendingMessage.value = false;
        return;
    }

    inFlightSignatures.add(requestSignature);
    const typingId = addAssistantTypingMessage();

    try {
        const payload = {
            user_id: resolveCurrentUserId(),
            sub_tool_id: SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
            conversation_uuid: conversation.uuid,
            user_message: inputText,
            state: requestState,
            debug: false,
            ...(submitOptions.regenerate
                ? {
                    regenerate: true,
                    previous_output: submitOptions.previousOutput,
                }
                : {}),
        };

        console.log("[SocialPostGenerator] payload before send:", JSON.stringify(payload, null, 2));

        const response = await chatServices.sendMessage(payload);
        console.log("[SocialPostGenerator] raw response:", response);
        removeAssistantTypingMessage(typingId);

        const apiResponse = normalizeSocialPostApiResponse(response);

        if (!apiResponse) {
            await addAssistantLocalMessage(
                "تعذر قراءة نتيجة توليد منشور السوشيال. حاول مرة أخرى.",
                { is_error: true }
            );
            return;
        }

        if (apiResponse.state) {
            socialPostState.value = mergeSocialPostState(socialPostState.value, apiResponse.state);
            saveSocialPostStateToSession(conversation.uuid, socialPostState.value);
        }

        const metadata = {
            type: apiResponse.type || "result",
            tool: SOCIAL_POST_GENERATOR_TOOL_KEY,
            sub_tool_id: SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
            provider: apiResponse.provider,
            model_key: apiResponse.model_key,
            results: apiResponse.results,
            count: apiResponse.count,
            state: socialPostState.value,
            request_id: apiResponse.request_id,
            usage: apiResponse.usage,
            cost: apiResponse.cost,
        };

        if (apiResponse.success === false || apiResponse.type === "error") {
            console.error("[SocialPostGenerator] API error response:", apiResponse);
            await addAssistantLocalMessage(
                String(apiResponse.message || "فشل توليد منشور السوشيال."),
                {
                    plainText: true,
                    is_error: true,
                    sub_tool_id: SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
                    metadata: { ...metadata, type: "error" },
                }
            );
            return;
        }

        if (apiResponse.type === "question") {
            await addAssistantLocalMessage(
                buildSocialPostQuestionMessage(apiResponse),
                {
                    plainText: true,
                    sub_tool_id: SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
                    metadata: { ...metadata, type: "question" },
                }
            );

            await focusChatInput();
            return;
        }

        const outputText = apiResponse.results.length
            ? apiResponse.results.map((item) => item.text).join("\n\n")
            : "";

        if (!outputText) {
            await addAssistantLocalMessage(
                "تم التنفيذ ولكن لم يتم العثور على منشور جاهز للعرض.",
                {
                    plainText: true,
                    sub_tool_id: SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
                    metadata: { ...metadata, type: "error" },
                }
            );
            return;
        }

        await addAssistantLocalMessage(outputText, {
            plainText: true,
            sub_tool_id: SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
            metadata: { ...metadata, type: "result" },
        });

        if (!conversations.value.find((item) => item.uuid === conversation.uuid)) {
            conversations.value.unshift(conversation);
        }

        clearPendingSend(conversation.uuid);
        await focusChatInput();
    } catch (error) {
        removeAssistantTypingMessage(typingId);
        console.error("[SocialPostGenerator] frontend send error:", error);
        await addAssistantLocalMessage(
            "حصل خطأ أثناء توليد منشور السوشيال. جرّب مرة أخرى.",
            {
                plainText: true,
                is_error: true,
                sub_tool_id: SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
                metadata: {
                    type: "frontend_error",
                    tool: SOCIAL_POST_GENERATOR_TOOL_KEY,
                    sub_tool_id: SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
                    error_message: error?.message || String(error),
                },
            }
        );
    } finally {
        removeAssistantTypingMessage(typingId);
        inFlightSignatures.delete(requestSignature);
        sendingMessage.value = false;
        await scrollToBottom();
    }
};

const newLine = () => {
    userInput.value += "\n";
    nextTick(autoResize);
};

const fillPlaceholder = () => {
    userInput.value = subtool.value.promptPlaceholder;
    textareaRef.value?.focus();
    autoResize();
};

const goToWallet = async () => {
    await router.push(`/${homeService.getLang()}/wallet`);
};

const onModelImageError = (event) => {
    if (event?.target?.src !== subtool.value.imageUrl) {
        event.target.src = subtool.value.imageUrl;
    }
};

const loadSubtool = async () => {
    toolLoading.value = true;

    try {
        const res = await homeService.showSubtool(route.params.slug);
        const data = res?.data || {};

        subtool.value = {
            id: data.id || null,
            name: data.name || data.translation?.name || t("user.chat.aiTool"),
            description: data.description || data.translation?.description || "",
            promptPlaceholder: data.prompt_placeholder || "",
            imageUrl: data.image ? `/storage/${data.image}` : "",
            optimizedImageUrl: data.image
                ? `/storage/${data.image}`.replace(/\.(png|jpe?g)$/i, ".webp")
                : "",
        };
    } catch {
        subtool.value = {
            id: null,
            name: t("user.chat.aiTool"),
            description: t("user.chat.startConversation"),
            promptPlaceholder: t("user.chat.inputPlaceholder"),
            imageUrl: "",
            optimizedImageUrl: "",
        };
    } finally {
        toolLoading.value = false;
    }
};

const handleEmailWriterSubmit = async (text, options = {}) => {
    const submitOptions = {
        regenerate: false,
        previousOutput: "",
        forceNewIdempotency: false,
        ...options,
    };
    const initialInputText = String(text || "").trim();
    const initialStatePurpose = String(emailWriterState.value.purpose || "").trim();

    if ((!initialInputText && !initialStatePurpose) || sendingMessage.value || streamingAssistant.value || conversationLimitExceeded.value) {
        return;
    }

    if (!(await requireAuth())) return;

    sendingMessage.value = true;
    const conversation = await ensureConversation();

    if (!conversation?.uuid) {
        sendingMessage.value = false;
        await addAssistantLocalMessage("تعذر إنشاء المحادثة. حاول مرة أخرى.", {
            is_error: true,
        });
        return;
    }

    const requestState = resolveEmailWriterStateForSubmit(conversation.uuid);
    const inputText = initialInputText || String(requestState.purpose || "").trim();
    const idempotencyKey = resolveIdempotencyKey(conversation.uuid, inputText, {
        forceNew: submitOptions.forceNewIdempotency,
    });

    await addUserLocalMessage(inputText, {
        sub_tool_id: EMAIL_WRITER_SUB_TOOL_ID,
        metadata: {
            type: "user_input",
            tool: EMAIL_WRITER_TOOL_KEY,
            sub_tool_id: EMAIL_WRITER_SUB_TOOL_ID,
            state: requestState,
        },
    });

    userInput.value = "";
    resetTextarea();
    const typingId = addAssistantTypingMessage();

    try {
        const payload = {
            user_id: resolveCurrentUserId(),
            sub_tool_id: EMAIL_WRITER_SUB_TOOL_ID,
            conversation_uuid: conversation.uuid,
            conversation_id: conversation.id,
            user_message: inputText,
            tool: EMAIL_WRITER_TOOL_KEY,
            model_key: "email_writer",
            state: requestState,
            idempotency_key: idempotencyKey,
            debug: false,
            ...(submitOptions.regenerate
                ? {
                    regenerate: true,
                    previous_output: submitOptions.previousOutput,
                }
                : {}),
        };

        console.log("[EmailWriter] payload before send:", JSON.stringify(payload, null, 2));
        const response = await chatServices.sendMessage(payload);
        console.log("[EmailWriter] raw response:", response);
        removeAssistantTypingMessage(typingId);

        const apiResponse = normalizeEmailWriterApiResponse(response);

        if (!apiResponse) {
            await addAssistantLocalMessage(
                "تعذر قراءة نتيجة كتابة الإيميل. حاول مرة أخرى.",
                {
                    plainText: true,
                    is_error: true,
                    sub_tool_id: EMAIL_WRITER_SUB_TOOL_ID,
                }
            );
            return;
        }

        if (apiResponse.state) {
            emailWriterState.value = mergeEmailWriterState(
                emailWriterState.value,
                apiResponse.state
            );
            saveEmailWriterStateToSession(conversation.uuid, emailWriterState.value);
        }

        const metadata = {
            type: apiResponse.type || "result",
            tool: EMAIL_WRITER_TOOL_KEY,
            sub_tool_id: EMAIL_WRITER_SUB_TOOL_ID,
            provider: apiResponse.provider,
            model_key: apiResponse.model_key,
            results: apiResponse.results,
            count: apiResponse.count,
            state: emailWriterState.value,
            request_id: apiResponse.request_id,
            usage: apiResponse.usage,
            cost: apiResponse.cost,
            subject: apiResponse.results[0]?.subject || null,
        };

        if (apiResponse.success === false || apiResponse.type === "error") {
            console.error("[EmailWriter] API error response:", apiResponse);
            await addAssistantLocalMessage(
                apiResponse.message || "حدث خطأ أثناء كتابة الإيميل.",
                {
                    plainText: true,
                    is_error: true,
                    sub_tool_id: EMAIL_WRITER_SUB_TOOL_ID,
                    metadata: { ...metadata, type: "error" },
                }
            );
            return;
        }

        if (apiResponse.type === "question") {
            await addAssistantLocalMessage(
                buildEmailWriterQuestionMessage(apiResponse),
                {
                    plainText: true,
                    sub_tool_id: EMAIL_WRITER_SUB_TOOL_ID,
                    metadata: { ...metadata, type: "question" },
                }
            );
            await focusChatInput();
            return;
        }

        const outputText = apiResponse.results[0]?.text || "";

        if (!outputText) {
            await addAssistantLocalMessage(
                "تم التنفيذ ولكن لم يتم العثور على إيميل جاهز للعرض.",
                {
                    plainText: true,
                    is_error: true,
                    sub_tool_id: EMAIL_WRITER_SUB_TOOL_ID,
                    metadata: { ...metadata, type: "result_empty" },
                }
            );
            return;
        }

        await addAssistantLocalMessage(outputText, {
            plainText: true,
            sub_tool_id: EMAIL_WRITER_SUB_TOOL_ID,
            metadata: { ...metadata, type: "result" },
        });

        if (!conversations.value.find((item) => item.uuid === conversation.uuid)) {
            conversations.value.unshift(conversation);
        }

        clearPendingSend(conversation.uuid);
        await focusChatInput();
    } catch (error) {
        removeAssistantTypingMessage(typingId);
        console.error("[EmailWriter] frontend send error:", error);
        await addAssistantLocalMessage("حدث خطأ أثناء كتابة الإيميل.", {
            plainText: true,
            is_error: true,
            sub_tool_id: EMAIL_WRITER_SUB_TOOL_ID,
            metadata: {
                type: "frontend_error",
                tool: EMAIL_WRITER_TOOL_KEY,
                sub_tool_id: EMAIL_WRITER_SUB_TOOL_ID,
                error_message: error?.message || String(error),
            },
        });
    } finally {
        removeAssistantTypingMessage(typingId);
        sendingMessage.value = false;
        await scrollToBottom();
    }
};

const handleScriptGeneratorSubmit = async (text, options = {}) => {
    const submitOptions = {
        regenerate: false,
        previousOutput: "",
        forceNewIdempotency: false,
        ...options,
    };
    const initialInputText = String(text || "").trim();
    const initialStateTopic = String(scriptGeneratorState.value.topic || "").trim();

    if ((!initialInputText && !initialStateTopic) || sendingMessage.value || streamingAssistant.value || conversationLimitExceeded.value) {
        return;
    }

    if (!(await requireAuth())) return;

    sendingMessage.value = true;
    const conversation = await ensureConversation();

    if (!conversation?.uuid) {
        sendingMessage.value = false;
        await addAssistantLocalMessage("تعذر إنشاء المحادثة. حاول مرة أخرى.", {
            is_error: true,
        });
        return;
    }

    const localScriptState = resolveScriptGeneratorStateForSubmit(conversation.uuid);
    const inputText = initialInputText || String(localScriptState.topic || "").trim();
    const idempotencyKey = resolveIdempotencyKey(conversation.uuid, inputText, {
        forceNew: submitOptions.forceNewIdempotency,
    });

    await addUserLocalMessage(inputText, {
        sub_tool_id: SCRIPT_GENERATOR_SUB_TOOL_ID,
        metadata: {
            type: "user_input",
            tool: SCRIPT_GENERATOR_TOOL_KEY,
            sub_tool_id: SCRIPT_GENERATOR_SUB_TOOL_ID,
            state: localScriptState,
        },
    });

    userInput.value = "";
    resetTextarea();
    const typingId = addAssistantTypingMessage();

    try {
        const payload = {
            user_id: resolveCurrentUserId(),
            sub_tool_id: SCRIPT_GENERATOR_SUB_TOOL_ID,
            conversation_uuid: conversation.uuid,
            user_message: inputText,
            idempotency_key: idempotencyKey,
            state: hasScriptGeneratorStateValue(localScriptState)
                ? localScriptState
                : createEmptyScriptGeneratorState(),
            debug: false,
            ...(submitOptions.regenerate
                ? {
                    regenerate: true,
                    previous_output: submitOptions.previousOutput,
                }
                : {}),
        };

        console.log("[ScriptGenerator] payload before send:", JSON.stringify(payload, null, 2));
        const response = await chatServices.sendMessage(payload);
        console.log("[ScriptGenerator] raw response:", response);
        removeAssistantTypingMessage(typingId);

        const apiResponse = normalizeScriptGeneratorApiResponse(response);

        if (!apiResponse) {
            await addAssistantLocalMessage(
                "تعذر قراءة نتيجة توليد السكريبت. حاول مرة أخرى.",
                {
                    plainText: true,
                    is_error: true,
                    sub_tool_id: SCRIPT_GENERATOR_SUB_TOOL_ID,
                }
            );
            return;
        }

        if (apiResponse.state) {
            scriptGeneratorState.value = mergeScriptGeneratorState(
                scriptGeneratorState.value,
                apiResponse.state
            );
            saveScriptGeneratorStateToSession(
                conversation.uuid,
                scriptGeneratorState.value
            );
        }

        const metadata = {
            type: apiResponse.type || "result",
            tool: SCRIPT_GENERATOR_TOOL_KEY,
            sub_tool_id: SCRIPT_GENERATOR_SUB_TOOL_ID,
            provider: apiResponse.provider,
            model_key: apiResponse.model_key,
            results: apiResponse.results,
            count: apiResponse.count,
            state: scriptGeneratorState.value,
            request_id: apiResponse.request_id,
            usage: apiResponse.usage,
            cost: apiResponse.cost,
        };

        if (apiResponse.success === false || apiResponse.type === "error") {
            console.error("[ScriptGenerator] API error response:", apiResponse);
            await addAssistantLocalMessage(
                apiResponse.message || "حدث خطأ أثناء توليد السكريبت.",
                {
                    plainText: true,
                    is_error: true,
                    sub_tool_id: SCRIPT_GENERATOR_SUB_TOOL_ID,
                    metadata: { ...metadata, type: "error" },
                }
            );
            return;
        }

        const missingFields = getMissingScriptGeneratorFields(
            apiResponse.state || scriptGeneratorState.value
        );

        if (apiResponse.type === "question" || missingFields.length > 0) {
            await addAssistantLocalMessage(
                buildScriptGeneratorQuestionMessage(apiResponse),
                {
                    plainText: true,
                    sub_tool_id: SCRIPT_GENERATOR_SUB_TOOL_ID,
                    metadata: { ...metadata, type: "question" },
                }
            );
            await focusChatInput();
            return;
        }

        const outputText = apiResponse.results.length
            ? apiResponse.results.map((item) => item.text).join("\n\n")
            : "";

        if (!outputText) {
            await addAssistantLocalMessage(
                "تم التنفيذ ولكن لم يتم العثور على سكريبت جاهز للعرض.",
                {
                    plainText: true,
                    sub_tool_id: SCRIPT_GENERATOR_SUB_TOOL_ID,
                    metadata: { ...metadata, type: "result_empty" },
                }
            );
            return;
        }

        await addAssistantLocalMessage(outputText, {
            plainText: true,
            sub_tool_id: SCRIPT_GENERATOR_SUB_TOOL_ID,
            metadata: { ...metadata, type: "result" },
        });

        if (!conversations.value.find((item) => item.uuid === conversation.uuid)) {
            conversations.value.unshift(conversation);
        }

        clearPendingSend(conversation.uuid);
        await focusChatInput();
    } catch (error) {
        removeAssistantTypingMessage(typingId);
        console.error("[ScriptGenerator] frontend send error:", error);
        await addAssistantLocalMessage("حدث خطأ أثناء توليد السكريبت.", {
            plainText: true,
            is_error: true,
            sub_tool_id: SCRIPT_GENERATOR_SUB_TOOL_ID,
            metadata: {
                type: "frontend_error",
                tool: SCRIPT_GENERATOR_TOOL_KEY,
                sub_tool_id: SCRIPT_GENERATOR_SUB_TOOL_ID,
                error_message: error?.message || String(error),
            },
        });
    } finally {
        removeAssistantTypingMessage(typingId);
        sendingMessage.value = false;
        await scrollToBottom();
    }
};

const handleProductDescriptionSubmit = async (text, options = {}) => {
    const submitOptions = {
        regenerate: false,
        previousOutput: "",
        forceNewIdempotency: false,
        ...options,
    };

    if (sendingMessage.value || streamingAssistant.value || conversationLimitExceeded.value) {
        return;
    }

    if (!(await requireAuth())) return;

    sendingMessage.value = true;
    const conversation = await ensureConversation();

    if (!conversation?.uuid) {
        sendingMessage.value = false;
        await addAssistantLocalMessage(
            isArabic.value ? "تعذر إنشاء المحادثة. حاول مرة أخرى." : "Could not create the conversation. Please try again.",
            { is_error: true }
        );
        return;
    }

    const requestState = resolveProductDescriptionStateForSubmit(conversation.uuid);
    const inputText = String(text || "").trim()
        || String(requestState.product || "").trim();

    if (!inputText) {
        sendingMessage.value = false;
        productOptionsOpen.value = true;
        await addAssistantLocalMessage(
            isArabic.value
                ? "يرجى إدخال اسم المنتج أو وصف المنتج أولًا."
                : "Please enter a product name or product description first.",
            {
                plainText: true,
                is_error: true,
                sub_tool_id: PRODUCT_DESCRIPTION_GENERATOR_SUB_TOOL_ID,
            }
        );
        return;
    }

    await addUserLocalMessage(inputText, {
        sub_tool_id: PRODUCT_DESCRIPTION_GENERATOR_SUB_TOOL_ID,
        metadata: {
            type: "user_input",
            tool: PRODUCT_DESCRIPTION_GENERATOR_TOOL_KEY,
            model_key: PRODUCT_DESCRIPTION_GENERATOR_MODEL_KEY,
            sub_tool_id: PRODUCT_DESCRIPTION_GENERATOR_SUB_TOOL_ID,
            state: requestState,
        },
    });

    userInput.value = "";
    resetTextarea();
    const idempotencyKey = resolveIdempotencyKey(conversation.uuid, inputText, {
        forceNew: submitOptions.forceNewIdempotency,
    });
    const typingId = addAssistantTypingMessage();

    try {
        const response = await chatServices.sendMessage({
            user_id: resolveCurrentUserId(),
            sub_tool_id: PRODUCT_DESCRIPTION_GENERATOR_SUB_TOOL_ID,
            conversation_uuid: conversation.uuid,
            conversation_id: conversation.id,
            user_message: inputText,
            tool: PRODUCT_DESCRIPTION_GENERATOR_TOOL_KEY,
            model_key: PRODUCT_DESCRIPTION_GENERATOR_MODEL_KEY,
            state: requestState,
            idempotency_key: idempotencyKey,
            debug: false,
            ...(submitOptions.regenerate
                ? {
                    regenerate: true,
                    previous_output: submitOptions.previousOutput,
                }
                : {}),
        });
        removeAssistantTypingMessage(typingId);

        const apiResponse = normalizeProductDescriptionApiResponse(response);
        if (!apiResponse) {
            throw new Error("Invalid product description response.");
        }

        if (apiResponse.state) {
            productDescriptionState.value = mergeProductDescriptionState(
                productDescriptionState.value,
                apiResponse.state
            );
            saveProductDescriptionStateToSession(
                conversation.uuid,
                productDescriptionState.value
            );
        }

        const metadata = {
            type: apiResponse.type || "result",
            tool: PRODUCT_DESCRIPTION_GENERATOR_TOOL_KEY,
            model_key: PRODUCT_DESCRIPTION_GENERATOR_MODEL_KEY,
            sub_tool_id: PRODUCT_DESCRIPTION_GENERATOR_SUB_TOOL_ID,
            provider: apiResponse.provider,
            state: productDescriptionState.value,
            results: apiResponse.results,
            count: apiResponse.count,
            request_id: apiResponse.request_id,
            usage: apiResponse.usage,
            cost: apiResponse.cost,
        };

        if (apiResponse.success === false || apiResponse.type === "error") {
            await addAssistantLocalMessage(
                apiResponse.message || (
                    isArabic.value
                        ? "حدث خطأ أثناء توليد وصف المنتج. حاول مرة أخرى."
                        : "An error occurred while generating the product description. Please try again."
                ),
                {
                    plainText: true,
                    is_error: true,
                    sub_tool_id: PRODUCT_DESCRIPTION_GENERATOR_SUB_TOOL_ID,
                    metadata: { ...metadata, type: "error" },
                }
            );
            return;
        }

        const outputText = apiResponse.results[0]?.text
            || apiResponse.state?.last_output
            || "";

        if (!outputText) {
            await addAssistantLocalMessage(
                isArabic.value
                    ? "تم التنفيذ ولكن لم يتم العثور على وصف منتج جاهز للعرض."
                    : "The request completed, but no product description was returned.",
                {
                    plainText: true,
                    is_error: true,
                    sub_tool_id: PRODUCT_DESCRIPTION_GENERATOR_SUB_TOOL_ID,
                    metadata: { ...metadata, type: "result_empty" },
                }
            );
            return;
        }

        await addAssistantLocalMessage(outputText, {
            plainText: true,
            sub_tool_id: PRODUCT_DESCRIPTION_GENERATOR_SUB_TOOL_ID,
            metadata: { ...metadata, type: "result" },
        });

        if (!conversations.value.find((item) => item.uuid === conversation.uuid)) {
            conversations.value.unshift(conversation);
        }

        clearPendingSend(conversation.uuid);
        await focusChatInput();
    } catch (error) {
        removeAssistantTypingMessage(typingId);
        await addAssistantLocalMessage(
            error?.response?.data?.message || (
                isArabic.value
                    ? "حدث خطأ أثناء توليد وصف المنتج. حاول مرة أخرى."
                    : "An error occurred while generating the product description. Please try again."
            ),
            {
                plainText: true,
                is_error: true,
                sub_tool_id: PRODUCT_DESCRIPTION_GENERATOR_SUB_TOOL_ID,
                metadata: {
                    type: "frontend_error",
                    tool: PRODUCT_DESCRIPTION_GENERATOR_TOOL_KEY,
                    model_key: PRODUCT_DESCRIPTION_GENERATOR_MODEL_KEY,
                    sub_tool_id: PRODUCT_DESCRIPTION_GENERATOR_SUB_TOOL_ID,
                },
            }
        );
    } finally {
        removeAssistantTypingMessage(typingId);
        sendingMessage.value = false;
        await scrollToBottom();
    }
};

const loadConversations = async () => {
    if (!isAuthenticated()) {
        conversations.value = [];
        loadingConversations.value = false;
        insufficientPoints.value = false;
        return;
    }

    loadingConversations.value = true;

    try {
        const response = await chatServices.getConversations();
        const rows = Array.isArray(response?.data) ? response.data : [];
        conversations.value = rows.map(formatConversation);
    } finally {
        loadingConversations.value = false;
    }
};

const loadConversationDetails = async (uuid) => {
    clearLocalTypingMessages();

    if (!isAuthenticated()) {
        activeConversation.value = null;
        messages.value = [];
        conversationLimitExceeded.value = false;
        insufficientPoints.value = false;
        resetHeadlineState();
        resetParaphraserState();
        resetSocialPostState();
        resetEmailWriterState();
        resetScriptGeneratorState();
        resetProductDescriptionState();
        return;
    }

    if (!uuid) {
        activeConversation.value = null;
        messages.value = [];
        conversationLimitExceeded.value = false;
        insufficientPoints.value = false;
        resetHeadlineState();
        resetParaphraserState();
        resetSocialPostState();
        resetEmailWriterState();
        resetScriptGeneratorState();
        resetProductDescriptionState();
        return;
    }

    loadingMessages.value = true;

    try {
        const response = await chatServices.getConversation(uuid);
        const apiMessage = response?.message || response?.data?.message || "";

        /**
         * Ù‡Ù†Ø§ ÙÙ‚Ø· Ø§Ù„Ø­Ø¯ Ø§Ù„Ø£Ù‚ØµÙ‰ Ù‡Ùˆ Ø§Ù„Ù„ÙŠ ÙŠÙ‚ÙÙ„ Ø§Ù„Ù…Ø­Ø§Ø¯Ø«Ø©.
         */
        conversationLimitExceeded.value = String(apiMessage)
            .toLowerCase()
            .includes("limit exceeded");

        console.info("[chat] conversation limit state resolved", {
            uuid,
            apiMessage,
            conversationLimitExceeded: conversationLimitExceeded.value,
        });

        const conversation = response?.data || null;
        const rows = Array.isArray(conversation?.message) ? conversation.message : [];

        if (conversation) {
            const formattedConversation = formatConversation({
                ...(conversations.value.find((item) => item.uuid === uuid) || {}),
                ...conversation,
            });

            activeConversation.value = formattedConversation;

            conversations.value = conversations.value.map((item) =>
                item.uuid === uuid
                    ? {
                        ...item,
                        title: formattedConversation.title,
                    }
                    : item
            );
        } else {
            activeConversation.value = null;
        }

        clearLocalTypingMessages();
        messages.value = rows.map((message, index) => mapMessage(message, index));
        hydrateHeadlineStateFromMessages(rows);
        hydrateParaphraserStateFromMessages(rows);
        hydrateSocialPostStateFromMessages(rows);
        hydrateEmailWriterStateFromMessages(rows);
        hydrateScriptGeneratorStateFromMessages(rows);
        hydrateProductDescriptionStateFromMessages(rows);

        resolveInsufficientPointsState(rows);

        await scrollToBottom();
    } finally {
        loadingMessages.value = false;
    }
};

const syncRouteConversation = async () => {
    if (!route.params.uuid) {
        activeConversation.value = null;
        messages.value = [];
        conversationLimitExceeded.value = false;
        insufficientPoints.value = false;
        resetHeadlineState();
        resetParaphraserState();
        resetSocialPostState();
        resetEmailWriterState();
        resetScriptGeneratorState();
        resetProductDescriptionState();
        return;
    }

    const existing = conversations.value.find((item) => item.uuid === route.params.uuid);

    if (existing) {
        activeConversation.value = existing;
    }

    await loadConversationDetails(route.params.uuid);
};

const openConversation = async (conversation) => {
    sidebarOpen.value = false;

    if (!conversation?.uuid) return;

    if (route.params.uuid === conversation.uuid) {
        await loadConversationDetails(conversation.uuid);
        return;
    }

    await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat/${conversation.uuid}`);
};

const startNewChat = async () => {
    if (creatingConversation.value) return;

    if (!(await requireAuth())) return;

    creatingConversation.value = true;

    try {
        const response = await chatServices.createConversation(route.params.slug);
        const conversation = formatConversation(response?.data || {});

        conversations.value = [
            conversation,
            ...conversations.value.filter((item) => item.uuid !== conversation.uuid),
        ];

        activeConversation.value = conversation;
        messages.value = [];
        conversationLimitExceeded.value = false;
        insufficientPoints.value = false;
        sidebarOpen.value = false;
        resetHeadlineState();
        resetParaphraserState();
        resetSocialPostState();
        resetEmailWriterState();
        resetScriptGeneratorState();
        resetProductDescriptionState();

        await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat/${conversation.uuid}`);
    } finally {
        creatingConversation.value = false;
    }
};

const ensureConversation = async () => {
    if (!(await requireAuth())) return null;

    if (activeConversation.value?.id && activeConversation.value?.uuid) {
        return activeConversation.value;
    }

    const response = await chatServices.createConversation(route.params.slug);
    const conversation = formatConversation(response?.data || {});

    conversations.value = [
        conversation,
        ...conversations.value.filter((item) => item.uuid !== conversation.uuid),
    ];

    activeConversation.value = conversation;
    insufficientPoints.value = false;
    resetHeadlineState();
    resetParaphraserState();
    resetSocialPostState();
    resetEmailWriterState();
    resetScriptGeneratorState();

    if (isProductDescriptionGeneratorTool.value) {
        saveProductDescriptionStateToSession(
            conversation.uuid,
            productDescriptionState.value
        );
    } else {
        resetProductDescriptionState();
    }

    await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat/${conversation.uuid}`);

    return conversation;
};

const submitMessage = async (text = userInput.value, options = {}) => {
    const submitOptions = {
        regenerate: false,
        previousOutput: "",
        forceNewIdempotency: false,
        ...options,
    };
    const rawContent = String(text || "").trim();

    /**
     * Ù…Ù‡Ù…:
     * Ù‡Ù†Ø§ Ø´Ù„Ù†Ø§ insufficientPoints Ù…Ù† Ø´Ø±Ø· Ø§Ù„Ù…Ù†Ø¹.
     * ÙŠØ¹Ù†ÙŠ Ù„Ùˆ Ø¢Ø®Ø± Ø±Ø¯ ÙƒØ§Ù† InsufficientØŒ Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù… ÙŠÙ‚Ø¯Ø± ÙŠØ¨Ø¹Øª ØªØ§Ù†ÙŠ.
     * Ø§Ù„Ù‚ÙÙ„ ÙÙ‚Ø· Ø¹Ù†Ø¯ limit exceeded.
     */
    if (conversationLimitExceeded.value) {
        return;
    }

    if (!rawContent || sendingMessage.value || streamingAssistant.value) {
        return;
    }

    if (!(await requireAuth())) {
        return;
    }

    const content = rawContent;

    sendingMessage.value = true;

    const conversation = await ensureConversation();

    if (!conversation) {
        sendingMessage.value = false;
        return;
    }

    const idempotencyKey = resolveIdempotencyKey(conversation.uuid, content, {
        forceNew: submitOptions.forceNewIdempotency,
    });
    const requestSignature = `${conversation.id}:${idempotencyKey}`;

    if (inFlightSignatures.has(requestSignature)) {
        sendingMessage.value = false;
        return;
    }

    inFlightSignatures.add(requestSignature);

    const optimisticMessage = mapMessage(
        {
            content,
            role: "user",
            created_at: new Date().toISOString(),
        },
        messages.value.length
    );

    messages.value.push(optimisticMessage);
    userInput.value = "";
    resetTextarea();

    await scrollToBottom();
    const typingId = addAssistantTypingMessage();

    try {
        const payload = {
            content,
            user_message: content,
            sub_tool_id: Number(subtool.value?.id || 0),
            conversation_uuid: conversation.uuid,
            conversation_id: conversation.id,
            role: "user",
            idempotency_key: idempotencyKey,
            ...(Number(subtool.value?.id) === TEXT_EDITOR_SUB_TOOL_ID
                ? { tool: TEXT_EDITOR_TOOL_KEY }
                : {}),
            task_options: searchEnabled.value
                ? {
                    search_mode: "on",
                    web_search_max_results: 3,
                    web_search_total_results: 5,
                    max_tokens: 2500,
                    temperature: 0.45,
                }
                : {
                    search_mode: "off",
                    max_tokens: 2500,
                    temperature: 0.45,
                },
            ...(submitOptions.regenerate
                ? {
                    regenerate: true,
                    previous_output: submitOptions.previousOutput,
                }
                : {}),
        };

        console.log("Chat payload before send:", JSON.stringify(payload, null, 2));

        const response = await chatServices.sendMessage(payload);

        const savedMessage = response?.data?.message;

        if (savedMessage) {
            const lastIndex = messages.value.findIndex((item) => item.localKey === optimisticMessage.localKey);

            if (lastIndex !== -1) {
                messages.value[lastIndex] = mapMessage(savedMessage, lastIndex);
            }
        }

        if (!conversations.value.find((item) => item.uuid === conversation.uuid)) {
            conversations.value.unshift(conversation);
        }

        clearPendingSend(conversation.uuid);
        removeAssistantTypingMessage(typingId);

        await openAssistantStream(conversation, response?.data?.message_id);
    } catch {
        removeAssistantTypingMessage(typingId);
        messages.value = messages.value.filter((item) => item.localKey !== optimisticMessage.localKey);
    } finally {
        removeAssistantTypingMessage(typingId);
        inFlightSignatures.delete(requestSignature);
        sendingMessage.value = false;
        await scrollToBottom();
    }
};

const onSubmitMessage = async () => {
    if (isTextSummarizerTool.value) {
        await handleTextSummarizerSubmit(userInput.value);
        return;
    }

    if (isHeadlineGeneratorTool.value) {
        await handleHeadlineGeneratorSubmit(userInput.value);
        return;
    }

    if (isParaphraserTool.value) {
        await handleParaphraserSubmit(userInput.value);
        return;
    }

    if (isSocialPostGeneratorTool.value) {
        await handleSocialPostGeneratorSubmit(userInput.value);
        return;
    }

    if (isEmailWriterTool.value) {
        await handleEmailWriterSubmit(userInput.value);
        return;
    }

    if (isScriptGeneratorTool.value) {
        await handleScriptGeneratorSubmit(userInput.value);
        return;
    }

    if (isProductDescriptionGeneratorTool.value) {
        await handleProductDescriptionSubmit(userInput.value);
        return;
    }

    await submitMessage();
};

const removeConversation = async (conversation) => {
    if (!conversation?.uuid || removingConversationUuid.value === conversation.uuid) return;

    removingConversationUuid.value = conversation.uuid;

    try {
        if (streamingConversationUuid.value === conversation.uuid) {
            closeAssistantStream();
        }

        await chatServices.deleteConversation(conversation.uuid);

        conversations.value = conversations.value.filter((item) => item.uuid !== conversation.uuid);

        if (activeConversation.value?.uuid === conversation.uuid || route.params.uuid === conversation.uuid) {
            activeConversation.value = null;
            messages.value = [];
            insufficientPoints.value = false;
            resetHeadlineState();
            resetParaphraserState();
            resetSocialPostState();
            resetEmailWriterState();
            resetScriptGeneratorState();
            resetProductDescriptionState();

            await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat`);
        }
    } finally {
        removingConversationUuid.value = "";
    }
};

const handleLangChanged = async () => {
    locale.value = homeService.getLang();

    closeAssistantStream();

    await Promise.all([
        loadSubtool(),
        loadConversations(),
    ]);

    await syncRouteConversation();
};

onMounted(async () => {
    locale.value = homeService.getLang();

    await Promise.all([
        loadSubtool(),
        loadConversations(),
    ]);

    await syncRouteConversation();

    window.addEventListener("lang-changed", handleLangChanged);
});

onUnmounted(() => {
    closeAssistantStream();
    window.removeEventListener("lang-changed", handleLangChanged);
});

watch(
    () => route.params.slug,
    async () => {
        closeAssistantStream();

        await Promise.all([
            loadSubtool(),
            loadConversations(),
        ]);

        await syncRouteConversation();
    }
);

watch(
    () => route.params.uuid,
    async () => {
        closeAssistantStream();
        await syncRouteConversation();
    }
);

watch(
    () => route.params.lang,
    async (nextLang, prevLang) => {
        if (!nextLang || nextLang === prevLang) return;

        await handleLangChanged();
    }
);
</script>

<style scoped>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

.chat-root {
    display: flex;
    height: 100vh;
    overflow: hidden;
    background: linear-gradient(180deg, #f8fbff 0%, #eef7fc 100%);
    font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif;
}

.sidebar {
    width: 320px;
    background: #ffffff;
    border-left: 1px solid rgba(21, 70, 119, 0.1);
    box-shadow: 0 24px 46px rgba(21, 70, 119, 0.08);
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    transition: transform 0.3s ease;
    z-index: 100;
    overflow: hidden;
}

.sidebar-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 22px 18px 18px;
    border-bottom: 1px solid rgba(21, 70, 119, 0.08);
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
}

.brand-icon {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, #154677, #2ba6de);
    color: #ffffff;
    box-shadow: 0 16px 30px rgba(21, 70, 119, 0.16);
}

.brand-name {
    display: block;
    color: #154677;
    font-weight: 800;
    font-size: 1rem;
}

.brand-subtitle {
    margin-top: 4px;
    color: #5f7288;
    font-size: 0.82rem;
}

.mobile-only {
    display: none;
}

.new-chat-btn {
    margin: 16px 14px;
    min-height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: #2ba6de;
    color: #ffffff;
    border: none;
    border-radius: 14px;
    padding: 0 16px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.new-chat-btn:hover:not(:disabled) {
    transform: scale(1.02);
    background: #2398cb;
    box-shadow: 0 20px 36px rgba(21, 70, 119, 0.16);
}

.new-chat-btn:disabled {
    opacity: 0.75;
    cursor: not-allowed;
}

.history-section {
    flex: 1;
    overflow-y: auto;
    padding: 0 14px 18px;
}

.history-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: #94a3b8;
    padding: 8px 4px 12px;
}

.history-skeletons,
.history-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.history-skeleton {
    min-height: 68px;
    border-radius: 16px;
    background: linear-gradient(90deg, #eef5fb 25%, #e5eff7 50%, #eef5fb 75%);
    background-size: 200% 100%;
    animation: shimmer 1.25s linear infinite;
}

.history-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    color: #94a3b8;
    font-size: 13px;
    padding: 28px 0;
}

.history-empty i {
    font-size: 22px;
}

.history-item {
    display: flex;
    align-items: stretch;
    gap: 8px;
}

.history-item-main {
    flex: 1;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    background: #f8fbff;
    color: #5f7288;
    border: 1px solid rgba(21, 70, 119, 0.08);
    border-radius: 16px;
    padding: 14px;
    cursor: pointer;
    text-align: start;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background-color 0.2s ease;
}

.history-item.active .history-item-main,
.history-item-main:hover {
    transform: scale(1.02);
    background: rgba(43, 166, 222, 0.08);
    color: #154677;
    border-color: rgba(43, 166, 222, 0.2);
    box-shadow: 0 18px 32px rgba(21, 70, 119, 0.08);
}

.history-item-main i {
    margin-top: 2px;
    flex-shrink: 0;
    font-size: 15px;
}

.history-item-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    overflow: hidden;
    min-width: 0;
}

.history-item-title {
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.history-item-date {
    font-size: 11px;
    color: #94a3b8;
}

.history-delete {
    width: 42px;
    min-width: 42px;
    border: 1px solid rgba(21, 70, 119, 0.08);
    border-radius: 14px;
    background: #ffffff;
    color: #154677;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.history-delete:hover {
    transform: scale(1.02);
    background: rgba(43, 166, 222, 0.08);
    box-shadow: 0 14px 28px rgba(21, 70, 119, 0.08);
}

.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(21, 70, 119, 0.2);
    backdrop-filter: blur(4px);
    z-index: 99;
}

.main-area {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: transparent;
}

.topbar {
    min-height: 76px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 22px;
    border-bottom: 1px solid rgba(21, 70, 119, 0.08);
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(12px);
    flex-shrink: 0;
}

.topbar-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.topbar-right {
    display: flex;
    align-items: center;
}

.menu-btn {
    display: none;
}

.model-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.model-avatar {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: linear-gradient(135deg, #154677, #2ba6de);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    overflow: hidden;
    flex-shrink: 0;
}

.model-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.model-name {
    font-size: 15px;
    font-weight: 800;
    color: #154677;
}

.model-desc {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 2px;
    max-width: 360px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.model-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(43, 166, 222, 0.1);
    color: #154677;
    padding: 8px 14px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

.messages-wrap {
    flex: 1;
    overflow-y: auto;
    padding: 28px 22px;
    scroll-behavior: smooth;
}

.messages-wrap::-webkit-scrollbar {
    width: 6px;
}

.messages-wrap::-webkit-scrollbar-thumb {
    background: rgba(21, 70, 119, 0.14);
    border-radius: 999px;
}

.messages-skeleton,
.messages-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-width: 920px;
    margin: 0 auto;
}

.message-skeleton {
    min-height: 88px;
    border-radius: 20px;
    background: linear-gradient(90deg, #eef5fb 25%, #e5eff7 50%, #eef5fb 75%);
    background-size: 200% 100%;
    animation: shimmer 1.25s linear infinite;
    width: 72%;
}

.message-skeleton.user {
    align-self: flex-start;
}

.message-skeleton.assistant {
    align-self: flex-end;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 60vh;
    text-align: center;
    gap: 12px;
    padding: 24px;
}

.empty-icon {
    width: 76px;
    height: 76px;
    background: linear-gradient(135deg, #154677, #2ba6de);
    border-radius: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 32px;
    margin-bottom: 8px;
    box-shadow: 0 18px 34px rgba(21, 70, 119, 0.18);
}

.empty-title {
    font-size: 24px;
    font-weight: 800;
    color: #154677;
}

.empty-desc {
    font-size: 14px;
    color: #94a3b8;
    max-width: 420px;
    line-height: 1.8;
}

.suggestion-chip {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(43, 166, 222, 0.1);
    color: #154677;
    border: 1px solid rgba(21, 70, 119, 0.1);
    border-radius: 14px;
    padding: 10px 16px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 8px;
    transition: transform 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
}

.suggestion-chip:hover {
    background: rgba(43, 166, 222, 0.16);
    transform: scale(1.02);
    box-shadow: 0 16px 28px rgba(21, 70, 119, 0.08);
}

.message-row {
    display: flex;
    align-items: flex-end;
    gap: 10px;
}

.message-row.user {
    flex-direction: row-reverse;
}

.msg-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #154677, #2ba6de);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
}

.user-avatar {
    background: rgba(43, 166, 222, 0.12);
    color: #154677;
}

.msg-bubble {
    max-width: 72%;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.message-row.user .msg-bubble {
    align-items: flex-end;
}

.msg-content {
    padding: 14px 18px;
    border-radius: 18px;
    font-size: 14px;
    line-height: 1.75;
    word-break: break-word;
    box-shadow: 0 18px 32px rgba(21, 70, 119, 0.08);
}

.markdown-body {
    font-size: 14px;
    line-height: 1.75;
}

.markdown-body :deep(*) {
    word-break: break-word;
}

.markdown-body :deep(p) {
    margin: 0;
}

.markdown-body :deep(p + p) {
    margin-top: 10px;
}

.plain-text-message {
    white-space: pre-line;
}

.markdown-body :deep(h1),
.markdown-body :deep(h2),
.markdown-body :deep(h3) {
    margin: 0 0 8px;
    line-height: 1.35;
    font-weight: 800;
}

.markdown-body :deep(h1) {
    font-size: 1.45rem;
}

.markdown-body :deep(h2) {
    font-size: 1.28rem;
}

.markdown-body :deep(h3) {
    font-size: 1.14rem;
}

.markdown-body :deep(strong) {
    font-weight: 800;
}

.markdown-body :deep(em) {
    font-style: italic;
}

.markdown-body :deep(ul),
.markdown-body :deep(ol) {
    margin: 8px 0;
    padding-inline-start: 20px;
}

.markdown-body :deep(li + li) {
    margin-top: 4px;
}

.markdown-body :deep(code) {
    display: inline-block;
    padding: 1px 7px;
    border-radius: 7px;
    background: rgba(15, 23, 42, 0.08);
    font-size: 0.9em;
    font-family: "Cascadia Code", "Consolas", monospace;
}

.markdown-body :deep(pre) {
    margin: 10px 0;
    padding: 12px;
    border-radius: 12px;
    overflow-x: auto;
    background: #0f172a;
    color: #e2e8f0;
    border: 1px solid rgba(148, 163, 184, 0.24);
}

.markdown-body :deep(pre code) {
    display: block;
    padding: 0;
    background: transparent;
    color: inherit;
    font-size: 0.92em;
}

.markdown-body :deep(blockquote) {
    margin: 10px 0;
    padding: 8px 12px;
    border-inline-start: 3px solid rgba(43, 166, 222, 0.45);
    background: rgba(43, 166, 222, 0.08);
    border-radius: 10px;
}

.markdown-body :deep(a) {
    color: inherit;
    text-decoration: underline;
    text-decoration-thickness: 1.5px;
}

.message-row.user .msg-content {
    background: linear-gradient(135deg, #154677, #2ba6de);
    color: white;
    border-radius: 18px 6px 18px 18px;
}

.message-row.assistant .msg-content {
    background: #ffffff;
    color: #154677;
    border: 1px solid rgba(21, 70, 119, 0.1);
    border-radius: 6px 18px 18px 18px;
}

.message-row.assistant .msg-content.error-message {
    border-color: rgba(220, 38, 38, 0.25);
    background: #fff1f2;
    color: #991b1b;
}

.ai-result-card {
    overflow: hidden;
    border: 1px solid #d6e9f4;
    border-radius: 14px;
    background: #fbfdff;
    box-shadow: 0 18px 32px rgba(21, 70, 119, 0.08);
    word-break: break-word;
}

.ai-result-card.error-message {
    border-color: rgba(220, 38, 38, 0.25);
    background: #fff1f2;
    color: #991b1b;
}

.ai-result-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 13px;
    border-bottom: 1px solid #e2eef5;
    color: var(--navy);
    background: #f0f8fc;
}

.ai-result-title {
    font-size: 13px;
    font-weight: 800;
}

.ai-copy-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 9px;
    border: 0;
    border-radius: 8px;
    color: var(--blue);
    background: #fff;
    font: inherit;
    font-size: 11px;
    font-weight: 800;
    cursor: pointer;
    white-space: nowrap;
    transition: background-color 0.2s ease, transform 0.2s ease;
}

.ai-copy-btn:hover {
    background: rgba(43, 166, 222, 0.08);
    transform: scale(1.02);
}

.ai-result-text {
    padding: 15px;
    color: var(--ink);
    line-height: 1.75;
}

.ai-result-card.error-message .ai-result-header {
    border-bottom-color: rgba(220, 38, 38, 0.18);
    background: rgba(254, 226, 226, 0.8);
    color: #991b1b;
}

.ai-result-card.error-message .ai-result-text {
    color: #991b1b;
}

.typing-bubble {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    max-width: fit-content;
    padding: 12px 16px;
    border-radius: 18px;
    background: rgba(21, 70, 119, 0.08);
    color: #154677;
    font-size: 13px;
    font-weight: 800;
    box-shadow: 0 10px 24px rgba(21, 70, 119, 0.08);
}

.typing-dots {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.typing-dots span {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: #2ba6de;
    animation: typingPulse 1s infinite ease-in-out;
}

.typing-dots span:nth-child(2) {
    animation-delay: 0.15s;
}

.typing-dots span:nth-child(3) {
    animation-delay: 0.3s;
}

.typing-indicator {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    min-width: 42px;
}

.typing-indicator span {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #2ba6de;
    animation: typing-bounce 0.9s infinite ease-in-out;
}

.typing-indicator span:nth-child(2) {
    animation-delay: 0.12s;
}

.typing-indicator span:nth-child(3) {
    animation-delay: 0.24s;
}

.msg-time {
    font-size: 10px;
    color: #94a3b8;
    padding: 0 4px;
}

.result-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 4px;
}

.result-actions button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid rgba(21, 70, 119, 0.12);
    border-radius: 10px;
    background: #ffffff;
    color: #154677;
    padding: 7px 10px;
    font: inherit;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
}

.result-actions button:hover:not(:disabled) {
    background: rgba(43, 166, 222, 0.08);
}

.result-actions button:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.result-usage {
    color: #64748b;
    font-size: 10px;
}

.input-area {
    margin-bottom: 3.5%;
    padding: 16px 22px 20px;
    border-top: 1px solid rgba(21, 70, 119, 0.08);
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(12px);
    flex-shrink: 0;
}

.advanced-options {
    max-width: 920px;
    margin: 0 auto 12px;
    border: 1px solid rgba(21, 70, 119, 0.1);
    border-radius: 16px;
    background: #ffffff;
    box-shadow: 0 12px 28px rgba(21, 70, 119, 0.05);
    overflow: hidden;
}

.advanced-options-toggle {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border: 0;
    background: transparent;
    color: #154677;
    padding: 12px 14px;
    font: inherit;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.advanced-options-toggle span {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.advanced-options-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    padding: 0 14px 14px;
}

.advanced-options-grid > label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    color: #475569;
    font-size: 11px;
    font-weight: 700;
}

.advanced-options-grid input,
.advanced-options-grid textarea,
.advanced-options-grid select {
    width: 100%;
    border: 1px solid rgba(21, 70, 119, 0.14);
    border-radius: 10px;
    background: #f8fbff;
    color: #154677;
    padding: 9px 10px;
    font: inherit;
    font-size: 12px;
    outline: none;
}

.advanced-options-grid textarea {
    resize: vertical;
}

.advanced-options-grid input:focus,
.advanced-options-grid textarea:focus,
.advanced-options-grid select:focus {
    border-color: rgba(43, 166, 222, 0.55);
    box-shadow: 0 0 0 3px rgba(43, 166, 222, 0.1);
}

.wide-field {
    grid-column: 1 / -1;
}

.extra-options-field {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    border: 0;
}

.extra-options-field legend {
    width: 100%;
    margin-bottom: 6px;
    color: #475569;
    font-size: 11px;
    font-weight: 800;
}

.check-option {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid rgba(21, 70, 119, 0.1);
    border-radius: 999px;
    padding: 7px 10px;
    color: #475569;
    font-size: 11px;
    cursor: pointer;
}

.check-option input {
    width: auto;
    padding: 0;
}

.limit-warning {
    max-width: 920px;
    margin: 0 auto 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 16px;
    background: #fff7ed;
    border: 1px solid rgba(234, 88, 12, 0.18);
    color: #9a3412;
    box-shadow: 0 14px 28px rgba(154, 52, 18, 0.08);
}

.limit-warning-icon {
    width: 36px;
    height: 36px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: rgba(234, 88, 12, 0.12);
    color: #ea580c;
    flex-shrink: 0;
}

.limit-warning-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 13px;
    line-height: 1.6;
}

.limit-warning-content strong {
    font-size: 14px;
    font-weight: 800;
}

.limit-warning-content span {
    color: #b45309;
}

.limit-warning-action {
    border: none;
    background: #ea580c;
    color: #ffffff;
    border-radius: 12px;
    padding: 9px 12px;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    white-space: nowrap;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.limit-warning-action:hover:not(:disabled) {
    background: #c2410c;
    transform: scale(1.03);
    box-shadow: 0 12px 22px rgba(234, 88, 12, 0.18);
}

.limit-warning-action:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.points-warning {
    max-width: 920px;
    margin: 0 auto 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 16px;
    background: #fff1f2;
    border: 1px solid rgba(220, 38, 38, 0.18);
    color: #991b1b;
    box-shadow: 0 14px 28px rgba(153, 27, 27, 0.08);
}

.points-warning-icon {
    width: 36px;
    height: 36px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: rgba(220, 38, 38, 0.12);
    color: #dc2626;
    flex-shrink: 0;
}

.points-warning-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 13px;
    line-height: 1.6;
}

.points-warning-content strong {
    font-size: 14px;
    font-weight: 800;
}

.points-warning-action {
    border: none;
    background: #dc2626;
    color: #ffffff;
    border-radius: 12px;
    padding: 9px 12px;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    white-space: nowrap;
}

.points-warning-action:hover:not(:disabled) {
    background: #b91c1c;
}

.input-box {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    background: #ffffff;
    border: 1px solid rgba(21, 70, 119, 0.1);
    border-radius: 18px;
    padding: 12px 14px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
    max-width: 920px;
    margin: 0 auto;
    box-shadow: 0 18px 34px rgba(21, 70, 119, 0.06);
}

.input-box.focused {
    border-color: rgba(43, 166, 222, 0.4);
    box-shadow: 0 0 0 4px rgba(43, 166, 222, 0.12);
    transform: scale(1.005);
}

.chat-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    resize: none;
    font-size: 14px;
    color: #154677;
    line-height: 1.7;
    font-family: inherit;
    min-height: 24px;
    max-height: 180px;
}

.chat-root[dir="rtl"] .chat-input {
    direction: rtl;
}

.chat-root[dir="ltr"] .chat-input {
    direction: ltr;
}

.chat-input::placeholder {
    color: #94a3b8;
}

.input-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.char-count {
    font-size: 11px;
    color: #94a3b8;
}

.search-toggle-btn {
    width: 38px;
    height: 38px;
    border: 0;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    color: #64748b;
    cursor: pointer;
    transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.search-toggle-btn:hover:not(:disabled) {
    background: rgba(15, 23, 42, 0.08);
    color: #0f172a;
}

.search-toggle-btn.active {
    background: rgba(37, 99, 235, 0.12);
    color: #2563eb;
}

.search-toggle-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.send-btn {
    width: 42px;
    height: 42px;
    background: #2ba6de;
    color: white;
    border: none;
    border-radius: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.send-btn:hover:not(:disabled) {
    background: #2398cb;
    transform: scale(1.05);
    box-shadow: 0 18px 34px rgba(21, 70, 119, 0.16);
}

.send-btn:disabled {
    background: #cfe6f5;
    cursor: not-allowed;
}

.input-hint {
    text-align: center;
    font-size: 11px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    max-width: 920px;
    margin: 8px auto 0;
}

.icon-btn {
    width: 36px;
    height: 36px;
    background: transparent;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: #94a3b8;
    transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.icon-btn:hover {
    background: rgba(43, 166, 222, 0.08);
    color: #154677;
    transform: scale(1.02);
}

.skeleton-box {
    background: #e2e8f0;
    animation: pulse 1.5s ease-in-out infinite;
}

.skel-line {
    height: 12px;
    background: #e2e8f0;
    border-radius: 999px;
    animation: pulse 1.5s ease-in-out infinite;
}

.w-32 {
    width: 128px;
}

.w-48 {
    width: 192px;
}

.mt-1 {
    margin-top: 4px;
}

.msg-enter-active,
.history-item-enter-active {
    transition: all 0.25s ease;
}

.msg-enter-from {
    opacity: 0;
    transform: translateY(10px);
}

.history-item-enter-from {
    opacity: 0;
    transform: translateX(8px);
}

@keyframes pulse {

    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0.55;
    }
}

@keyframes shimmer {
    0% {
        background-position: 200% 0;
    }

    100% {
        background-position: -200% 0;
    }
}

@keyframes typing-bounce {

    0%,
    80%,
    100% {
        opacity: 0.35;
        transform: translateY(0);
    }

    40% {
        opacity: 1;
        transform: translateY(-4px);
    }
}

@keyframes typingPulse {
    0%, 80%, 100% {
        opacity: 0.35;
        transform: translateY(0);
    }

    40% {
        opacity: 1;
        transform: translateY(-3px);
    }
}

@media (max-width: 900px) {
    .sidebar {
        position: fixed;
        top: 0;
        right: 0;
        height: 100%;
        transform: translateX(100%);
    }

    .sidebar.sidebar-open {
        transform: translateX(0);
    }

    .sidebar-overlay {
        display: block;
    }

    .mobile-only,
    .menu-btn {
        display: flex !important;
    }

    .model-desc {
        display: none;
    }
}

@media (max-width: 720px) {

    .topbar,
    .input-area,
    .messages-wrap {
        padding-inline: 14px;
    }

    .limit-warning {
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .limit-warning-action {
        width: 100%;
    }

    .points-warning {
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .points-warning-action {
        width: 100%;
    }

    .msg-bubble {
        max-width: 88%;
    }

    .advanced-options-grid {
        grid-template-columns: 1fr;
    }

    .wide-field {
        grid-column: auto;
    }
}

@media (max-width: 640px) {
    .typing-bubble {
        padding: 10px 13px;
        font-size: 12px;
        border-radius: 16px;
    }
}
</style>
