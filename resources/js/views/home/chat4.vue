<template>
    <main class="detector-chat" :class="{ 'sidebar-collapsed': desktopSidebarCollapsed }"
        :dir="isArabic ? 'rtl' : 'ltr'">
        <aside class="sidebar" :class="{ 'sidebar-open': sidebarOpen }">
            <div class="sidebar-brand">
                <span class="brand-icon"><i class="bi bi-shield-check"></i></span>
                <div>
                    <strong>{{ pageTitle }}</strong>
                </div>
                <button class="icon-button sidebar-close-toggle" type="button"
                    :aria-label="isArabic ? 'قفل قائمة المحادثات' : 'Close conversations sidebar'"
                    @click="closeSidebar">
                    <i class="bi bi-layout-sidebar-inset-reverse"></i>
                </button>
            </div>

            <button class="new-chat-button" type="button" :disabled="creatingConversation" @click="startNewChat">
                <i class="bi bi-plus-lg"></i>
                {{ creatingConversation ? labels.creating : labels.newChat }}
            </button>

            <p class="section-label">{{ labels.recent }}</p>

            <div v-if="loadingConversations" class="sidebar-status">{{ labels.loading }}</div>
            <div v-else-if="conversations.length === 0" class="sidebar-status">{{ labels.noChats }}</div>

            <div v-else class="conversation-list history-list">
                <div v-for="conversation in conversations" :key="conversation.uuid"
                    class="conversation-item history-item"
                    :class="{ active: conversation.uuid === activeConversation?.uuid }">
                    <button type="button" class="conversation-open history-item-main"
                        @click="openConversation(conversation)">
                        <i class="bi bi-chat-left-text"></i>

                        <div class="history-item-info">
                            <span class="history-item-title">{{ conversation.title }}</span>
                        </div>
                    </button>

                    <button type="button" class="conversation-delete history-delete"
                        :disabled="deletingUuid === conversation.uuid" :aria-label="labels.deleteChat"
                        @click="deleteConversation(conversation)">
                        <i class="bi bi-trash3"></i>
                    </button>
                </div>
            </div>
        </aside>

        <button v-if="desktopSidebarCollapsed" type="button" class="desktop-sidebar-open-toggle"
            :aria-label="isArabic ? 'فتح قائمة المحادثات' : 'Open conversations sidebar'" @click="openSidebar">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>

        <button v-if="sidebarOpen" class="sidebar-overlay" type="button" @click="sidebarOpen = false"></button>
        <button v-if="!sidebarOpen" class="mobile-sidebar-toggle mobile-only" type="button"
            :aria-label="isArabic ? 'فتح قائمة المحادثات' : 'Open conversations sidebar'" @click="openSidebar">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>

        <section class="workspace">

            <div ref="messagesContainer" class="messages" role="log" aria-live="polite">
                <div v-if="loadingMessages" class="center-status">
                    <span class="spinner"></span>
                    {{ labels.loadingConversation }}
                </div>

                <div v-else-if="messages.length === 0" class="welcome-card">
                    <span class="welcome-icon"><i class="bi bi-shield-check"></i></span>
                    <h2>{{ pageTitle }}</h2>
                    <p>{{ labels.welcome }}</p>
                    <button class="suggestion" type="button" @click="fillExample">
                        {{ examplePrompt }}
                    </button>
                </div>

                <div v-else class="message-list">
                    <article v-for="message in messages" :key="message.localKey" class="message-row"
                        :class="message.role">
                        <div class="avatar">
                            <i :class="message.role === 'assistant' ? 'bi bi-stars' : 'bi bi-person-fill'"></i>
                        </div>

                        <div class="message-body" :class="{
                            error: message.is_error,
                            'card-shell': message.role === 'assistant' && (message.results?.length || message.is_error),
                        }">
                            <template v-if="message.role === 'assistant' && message.results?.length">
                                <template v-if="isBusinessNameMessage(message)">
                                    <div class="business-response-card">
                                        <div class="ai-response-header">
                                            <div class="ai-response-title">
                                                <i class="bi bi-stars"></i>
                                                <span>{{ message.metadata?.title || labels.businessNameResultTitle
                                                    }}</span>
                                            </div>

                                            <button type="button" class="copy-card-button"
                                                @click="copyText(getMessageText(message), `msg-${message.localKey}`)">
                                                <i class="bi bi-copy"></i>
                                                {{ copiedKey === `msg-${message.localKey}` ? labels.copied :
                                                labels.copyAll }}
                                            </button>
                                        </div>

                                        <div class="business-result-list">
                                            <article v-for="(item, itemIndex) in message.results"
                                                :key="`${message.localKey}-${item.id || itemIndex}`"
                                                class="business-result-card">
                                                <div class="result-header">
                                                    <div class="result-title-stack">
                                                        <strong>{{ item.title || `${labels.businessNameResultTitle}
                                                            ${itemIndex + 1}` }}</strong>
                                                        <span>{{ item.subject || labels.businessNameResultTitle
                                                            }}</span>
                                                    </div>

                                                    <button type="button"
                                                        @click="copyText(getResultCopyText(item, getMessageToolId(message)), `result-${message.localKey}-${item.id || itemIndex}`)">
                                                        <i class="bi bi-copy"></i>
                                                        {{ copiedKey === `result-${message.localKey}-${item.id ||
                                                        itemIndex}` ? labels.copied : labels.copyResult }}
                                                    </button>
                                                </div>

                                                <div class="business-result-body">
                                                    <p class="business-name">{{ item.text }}</p>
                                                    <p v-if="item.meta?.slogan" class="business-slogan">{{
                                                        item.meta.slogan }}</p>

                                                    <div v-if="getBusinessDomains(item).length"
                                                        class="business-domain-section">
                                                        <span class="business-domain-label">{{ labels.domainIdeas
                                                            }}</span>
                                                        <div class="business-domain-list">
                                                            <span v-for="domain in getBusinessDomains(item)"
                                                                :key="domain" class="domain-chip">
                                                                {{ domain }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </article>
                                        </div>
                                    </div>
                                </template>

                                <div v-else class="ai-response-card">
                                    <div class="ai-response-header">
                                        <div class="ai-response-title">
                                            <i class="bi bi-stars"></i>
                                            <span>{{ message.metadata?.title || labels.aiResponseTitle }}</span>
                                        </div>

                                        <button type="button" class="copy-card-button"
                                            @click="copyText(getMessageText(message), `msg-${message.localKey}`)">
                                            <i class="bi bi-copy"></i>
                                            {{ copiedKey === `msg-${message.localKey}` ? labels.copied : labels.copy }}
                                        </button>
                                    </div>

                                    <div class="ai-response-content" v-html="formatMessage(getMessageText(message))">
                                    </div>

                                    <div v-if="getMessageDownloadUrl(message)" class="ai-download-section">
                                        <button type="button" class="ai-download-button"
                                            @click="downloadResumeFile(message)">
                                            <i class="bi bi-download"></i>
                                            {{ labels.downloadFile }}
                                            <span v-if="getMessageFilename(message)">{{ getMessageFilename(message)
                                                }}</span>
                                        </button>
                                    </div>

                                    <div v-if="isResumeBuilderMessage(message)" class="ai-response-actions">
                                        <button
                                            type="button"
                                            class="ai-edit-options-button"
                                            :disabled="sendDisabled"
                                            @click="editResumeResponse(message)"
                                        >
                                            <i class="bi bi-sliders"></i>
                                            {{ labels.editOptions }}
                                        </button>
                                    </div>

                                    <div v-if="message.results[0]?.meta?.ai_likelihood_score !== undefined"
                                        class="ai-score-box">
                                        <span>{{ labels.score }}</span>
                                        <strong>{{ message.results[0].meta.ai_likelihood_score }} / 100</strong>
                                    </div>

                                    <div v-if="message.results[0]?.meta?.signals?.length" class="ai-meta-section">
                                        <h4>{{ labels.signals }}</h4>
                                        <ul>
                                            <li v-for="signal in message.results[0].meta.signals" :key="signal">
                                                {{ signal }}
                                            </li>
                                        </ul>
                                    </div>

                                    <div v-if="message.results[0]?.meta?.rewrite_tips?.length" class="ai-meta-section">
                                        <h4>{{ labels.rewriteTips }}</h4>
                                        <ul>
                                            <li v-for="tip in message.results[0].meta.rewrite_tips" :key="tip">
                                                {{ tip }}
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </template>

                            <template v-else-if="message.role === 'assistant' && message.is_error">
                                <div class="clean-error-card">
                                    <i class="bi bi-exclamation-circle"></i>
                                    <span>{{ message.content }}</span>
                                </div>
                            </template>

                            <div
                                v-else-if="message.role === 'user' && getMessageUploadedFile(message)"
                                class="user-message-stack"
                            >
                                <div
                                    v-if="getMessageUploadedFile(message)"
                                    class="user-uploaded-file-card"
                                >
                                    <span class="user-uploaded-file-icon">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </span>

                                    <span class="user-uploaded-file-info">
                                        <strong>{{ getMessageUploadedFile(message).filename || getMessageUploadedFile(message).name }}</strong>
                                        <small>
                                            {{ getMessageUploadedFile(message).label || 'Document' }}
                                            <template v-if="formatFileSize(getMessageUploadedFile(message).size)">
                                                · {{ formatFileSize(getMessageUploadedFile(message).size) }}
                                            </template>
                                        </small>
                                    </span>
                                </div>

                                <div
                                    v-if="message.content"
                                    class="message-content"
                                    v-html="formatMessage(message.content)"
                                ></div>
                            </div>

                            <div v-else-if="message.content" class="message-content"
                                v-html="formatMessage(message.content)"></div>
                        </div>
                    </article>

                    <article v-if="isAssistantTyping" class="message-row assistant assistant-typing-row" dir="rtl">
                        <div class="avatar">
                            <i class="bi bi-stars"></i>
                        </div>

                        <div class="message-body assistant-typing-body">
                            <div class="assistant-typing-content">
                                <span class="assistant-typing-text">{{ typingText }}</span>
                                <span class="typing-dots" aria-hidden="true">
                                    <span class="typing-dot"></span>
                                    <span class="typing-dot animation-delay-150"></span>
                                    <span class="typing-dot animation-delay-300"></span>
                                </span>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

            <footer class="composer">
                <div v-if="errorMessage" class="error-banner">
                    <i class="bi bi-exclamation-circle"></i>
                    {{ errorMessage }}
                </div>

                <details ref="optionsPanelRef" class="options-panel">
                    <summary class="options-panel-header">
                        <span class="options-panel-title">
                            <i class="bi bi-sliders"></i>
                            {{ labels.options }}
                        </span>
                        <span class="options-panel-meta">
                            <span class="options-summary">{{ optionsSummary }}</span>
                            <i class="bi bi-chevron-down options-chevron" aria-hidden="true"></i>
                        </span>
                    </summary>

                    <form class="options-form" @submit.prevent="applyOptions">
                        <template v-if="isBusinessNameTool">
                            <div class="options-basic-grid">
                                <label>
                                    <span>{{ labels.language }}</span>
                                    <select v-model="toolState.language">
                                        <option>Auto Detect</option>
                                        <option>Arabic</option>
                                        <option>English</option>
                                    </select>
                                </label>

                                <label>
                                    <span>{{ labels.tone }}</span>
                                    <select v-model="toolState.tone">
                                        <option>Creative</option>
                                        <option>Professional</option>
                                        <option>Friendly</option>
                                        <option>Luxury</option>
                                        <option>Modern</option>
                                        <option>Simple</option>
                                    </select>
                                </label>

                                <label>
                                    <span>{{ labels.nameStyle }}</span>
                                    <select v-model="toolState.name_style">
                                        <option>Brandable</option>
                                        <option>Descriptive</option>
                                        <option>Short</option>
                                        <option>Modern</option>
                                        <option>Arabic</option>
                                        <option>English</option>
                                        <option>Tech Style</option>
                                    </select>
                                </label>

                                <label>
                                    <span>{{ labels.industry }}</span>
                                    <input v-model="toolState.industry" type="text">
                                </label>

                                <label>
                                    <span>{{ labels.targetAudience }}</span>
                                    <input v-model="toolState.target_audience" type="text">
                                </label>

                                <label>
                                    <span>{{ labels.resultsCount }}</span>
                                    <input v-model.number="toolState.results_count" type="number" min="1" max="30">
                                </label>

                                <label class="wide">
                                    <span>{{ labels.keywords }}</span>
                                    <input :value="serializeList(toolState.keywords)" type="text"
                                        placeholder="AI, marketing, tools"
                                        @input="updateListField('keywords', $event.target.value)">
                                </label>

                                <label class="wide">
                                    <span>{{ labels.avoidWords }}</span>
                                    <input :value="serializeList(toolState.avoid_words)" type="text"
                                        placeholder="cheap, copy, old"
                                        @input="updateListField('avoid_words', $event.target.value)">
                                </label>
                            </div>

                            <fieldset class="option-card checkbox-field">
                                <legend>{{ labels.businessNameOptions }}</legend>
                                <div class="checkbox-options-list">
                                    <label class="checkbox-option">
                                        <input v-model="toolState.include_slogans" type="checkbox">
                                        <span>{{ labels.includeSlogans }}</span>
                                    </label>
                                    <label class="checkbox-option">
                                        <input v-model="toolState.include_domain_ideas" type="checkbox">
                                        <span>{{ labels.includeDomainIdeas }}</span>
                                    </label>
                                </div>
                            </fieldset>
                        </template>

                        <template v-else-if="isResumeBuilder">
                            <div class="options-basic-grid">
                                <label>
                                    <span>{{ labels.targetRole }}</span>
                                    <input v-model="toolState.target_role" type="text">
                                </label>

                                <label>
                                    <span>{{ labels.candidateName }}</span>
                                    <input v-model="toolState.candidate_name" type="text">
                                </label>

                                <label>
                                    <span>{{ labels.language }}</span>
                                    <select v-model="toolState.language">
                                        <option>Auto Detect</option>
                                        <option>Arabic</option>
                                        <option>English</option>
                                    </select>
                                </label>

                                <label>
                                    <span>{{ labels.tone }}</span>
                                    <select v-model="toolState.tone">
                                        <option>Professional</option>
                                        <option>Formal</option>
                                        <option>Confident</option>
                                        <option>Friendly</option>
                                        <option>Modern</option>
                                    </select>
                                </label>

                                <label>
                                    <span>{{ labels.experienceLevel }}</span>
                                    <select v-model="toolState.experience_level">
                                        <option>Entry</option>
                                        <option>Junior</option>
                                        <option>Mid-level</option>
                                        <option>Senior</option>
                                        <option>Lead</option>
                                        <option>Executive</option>
                                    </select>
                                </label>

                                <label>
                                    <span>{{ labels.resumeStyle }}</span>
                                    <select v-model="toolState.resume_style">
                                        <option>ATS-friendly modern</option>
                                        <option>Classic professional</option>
                                        <option>Creative modern</option>
                                        <option>Minimal clean</option>
                                        <option>Executive</option>
                                    </select>
                                </label>

                                <label>
                                    <span>{{ labels.outputFormat }}</span>
                                    <select v-model="toolState.output_format">
                                        <option>docx</option>
                                        <option>pdf</option>
                                        <option>text</option>
                                    </select>
                                </label>
                            </div>

                            <fieldset class="option-card checkbox-field">
                                <legend>{{ labels.sectionsToInclude }}</legend>
                                <div class="checkbox-options-list">
                                    <label v-for="section in resumeSectionChoices" :key="section"
                                        class="checkbox-option">
                                        <input type="checkbox"
                                            :checked="toolState.sections_to_include.includes(section)"
                                            @change="toggleResumeSection(section)">
                                        <span>{{ section }}</span>
                                    </label>
                                </div>
                            </fieldset>

                            <fieldset class="option-card checkbox-field">
                                <legend>{{ labels.resumeFile }}</legend>
                                <div class="resume-file-panel">
                                    <label class="resume-file-button">
                                        <input ref="resumeFileInputRef" type="file" accept=".pdf,.doc,.docx"
                                            @change="handleResumeFileChange">
                                        <i class="bi bi-paperclip"></i>
                                        <span>{{ resumeFile ? labels.replaceFile : labels.uploadResume }}</span>
                                    </label>

                                    <div v-if="resumeFile" class="resume-file-selected">
                                        <span>{{ labels.selectedFile }}: {{ resumeFile.name }}</span>
                                        <button type="button" @click="removeResumeFile">
                                            <i class="bi bi-x-lg"></i>
                                            {{ labels.removeFile }}
                                        </button>
                                    </div>
                                </div>
                            </fieldset>
                        </template>

                        <template v-else-if="isHumanizer">
                            <div class="options-basic-grid">
                                <label>
                                    <span>{{ labels.language }}</span>
                                    <select v-model="toolState.language">
                                        <option>Auto Detect</option>
                                        <option>Arabic</option>
                                        <option>English</option>
                                    </select>
                                </label>

                                <label>
                                    <span>{{ labels.tone }}</span>
                                    <select v-model="toolState.tone">
                                        <option>Natural</option>
                                        <option>Professional</option>
                                        <option>Friendly</option>
                                        <option>Simple</option>
                                        <option>Creative</option>
                                    </select>
                                </label>

                                <label>
                                    <span>{{ labels.audience }}</span>
                                    <select v-model="toolState.audience">
                                        <option>General Audience</option>
                                        <option>Students</option>
                                        <option>Professionals</option>
                                        <option>Customers</option>
                                        <option>Social Media Audience</option>
                                    </select>
                                </label>

                                <label>
                                    <span>{{ labels.humanizeLevel }}</span>
                                    <select v-model="toolState.humanize_level">
                                        <option>Light</option>
                                        <option>Medium</option>
                                        <option>Strong</option>
                                    </select>
                                </label>

                                <label>
                                    <span>{{ labels.resultsCount }}</span>
                                    <input v-model.number="toolState.results_count" type="number" min="1" max="5">
                                </label>
                            </div>

                            <fieldset class="option-card checkbox-field">
                                <legend>{{ labels.humanizerOptions }}</legend>
                                <div class="checkbox-options-list">
                                    <label class="checkbox-option">
                                        <input v-model="toolState.preserve_meaning" type="checkbox">
                                        <span>{{ labels.preserveMeaning }}</span>
                                    </label>
                                    <label class="checkbox-option">
                                        <input v-model="toolState.preserve_keywords" type="checkbox">
                                        <span>{{ labels.preserveKeywords }}</span>
                                    </label>
                                </div>
                            </fieldset>
                        </template>

                        <template v-else>
                            <div class="options-basic-grid">
                                <label>
                                    <span>{{ labels.language }}</span>
                                    <select v-model="toolState.language">
                                        <option>Auto Detect</option>
                                        <option>Arabic</option>
                                        <option>English</option>
                                    </select>
                                </label>

                                <label>
                                    <span>{{ labels.analysisDepth }}</span>
                                    <select v-model="toolState.analysis_depth">
                                        <option>Quick</option>
                                        <option>Medium</option>
                                        <option>Deep</option>
                                    </select>
                                </label>

                                <label class="wide">
                                    <span>{{ labels.detectionFocus }}</span>
                                    <select v-model="toolState.detection_focus">
                                        <option>AI writing signals</option>
                                        <option>Human tone</option>
                                        <option>Repetition and generic wording</option>
                                        <option>Structure and style</option>
                                    </select>
                                </label>
                            </div>

                            <fieldset class="option-card checkbox-field">
                                <legend>{{ labels.outputOptions }}</legend>
                                <div class="checkbox-options-list">
                                    <label class="checkbox-option">
                                        <input v-model="toolState.include_score" type="checkbox">
                                        <span>{{ labels.includeScore }}</span>
                                    </label>
                                    <label class="checkbox-option">
                                        <input v-model="toolState.include_evidence" type="checkbox">
                                        <span>{{ labels.includeEvidence }}</span>
                                    </label>
                                    <label class="checkbox-option">
                                        <input v-model="toolState.include_rewrite_tips" type="checkbox">
                                        <span>{{ labels.includeRewriteTips }}</span>
                                    </label>
                                </div>
                            </fieldset>
                        </template>

                        <fieldset class="option-card checkbox-field">
                            <legend>{{ labels.extraOptions }}</legend>
                            <div class="checkbox-options-list">
                                <label v-for="option in extraOptionChoices" :key="option" class="checkbox-option">
                                    <input type="checkbox" :checked="toolState.extra_options.includes(option)"
                                        @change="toggleExtraOption(option)">
                                    <span>{{ option }}</span>
                                </label>
                            </div>
                        </fieldset>

                        <div class="options-actions">
                            <button type="submit" class="options-submit-button" :disabled="sendDisabled">
                                <i class="bi bi-check2"></i>
                                {{ labels.applyOptions }}
                            </button>
                            <button type="button" class="options-reset-button" :disabled="sendDisabled"
                                @click="resetOptions">
                                <i class="bi bi-arrow-counterclockwise"></i>
                                {{ labels.resetOptions }}
                            </button>
                        </div>
                    </form>
                </details>

                <div class="input-box">
                    <textarea ref="textareaRef" v-model="userMessage" rows="1" :placeholder="labels.placeholder"
                        :disabled="sendDisabled" @input="autoResize"
                        @keydown.enter.exact.prevent="sendMessage"></textarea>

                    <input v-if="isResumeBuilder" ref="composerResumeFileInputRef" class="composer-file-input"
                        type="file" accept=".pdf,.doc,.docx" @change="handleResumeFileChange">

                    <button v-if="isResumeBuilder" type="button" class="composer-file-button"
                        :class="{ 'has-file': resumeFile }" :disabled="sendDisabled"
                        :title="resumeFile ? resumeFile.name : labels.uploadResume"
                        :aria-label="resumeFile ? labels.replaceFile : labels.uploadResume"
                        @click="composerResumeFileInputRef?.click()">
                        <i :class="resumeFile ? 'bi bi-file-earmark-check-fill' : 'bi bi-paperclip'"></i>
                    </button>

                    <button type="button" class="send-button" :disabled="sendDisabled || !canSendMessage"
                        :aria-label="labels.send" @click="sendMessage">
                        <i :class="sendDisabled ? 'bi bi-hourglass-split' : 'bi bi-send-fill'"></i>
                    </button>
                </div>

                <div v-if="isResumeBuilder && resumeFile" class="composer-file-preview">
                    <span>
                        <i class="bi bi-file-earmark-text"></i>
                        {{ resumeFile.name }}
                    </span>

                    <button type="button" :disabled="sendDisabled" @click="removeResumeFile">
                        <i class="bi bi-x-lg"></i>
                        {{ labels.removeFile }}
                    </button>
                </div>

                <p class="composer-hint">{{ labels.hint }}</p>
            </footer>
        </section>
    </main>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "vue-i18n";
import MarkdownIt from "markdown-it";
import DOMPurify from "dompurify";
import chatServices from "@/services/chat/chatServices";
import homeService from "@/services/home/homeService";
import useSeoMeta from "@/composables/useSeoMeta";

const CHAT4_TOOLS = {
    17: {
        sub_tool_id: 17,
        tool_key: "ai_detector",
        model_key: "ai_detector",
        title_ar: "كاشف المحتوى الذكي",
        title_en: "AI Content Detector",
        subtitle_ar: "تحليل احتمالية كتابة المحتوى بالذكاء الاصطناعي",
        subtitle_en: "Focused AI-writing signal analysis",
    },
    18: {
        sub_tool_id: 18,
        tool_key: "ai_humanizer",
        model_key: "ai_humanizer",
        title_ar: "أنسنة النصوص بالذكاء الاصطناعي",
        title_en: "AI Humanizer",
        subtitle_ar: "تحويل النص إلى صياغة طبيعية وأكثر بشرية",
        subtitle_en: "Rewrite AI-like text into natural human language",
    },
    19: {
        sub_tool_id: 19,
        tool_key: "resume_builder",
        model_key: "resume_builder",
        title_ar: "منشئ السيرة الذاتية",
        title_en: "Resume Builder",
        subtitle_ar: "تحسين وإنشاء السيرة الذاتية باحترافية",
        subtitle_en: "Build or improve resumes with ATS-friendly structure",
    },
    20: {
        sub_tool_id: 20,
        tool_key: "business_name_generator",
        model_key: "business_name_generator",
        title_ar: "مولد أسماء المشاريع",
        title_en: "Business Name Generator",
        subtitle_ar: "توليد أسماء مشاريع مميزة مع أفكار شعارات ودومينات",
        subtitle_en: "Generate memorable business names with slogan and domain ideas",
    },
};

const CHAT4_SUB_TOOL_IDS = Object.keys(CHAT4_TOOLS).map(Number);
const DETECTOR_SUB_TOOL_ID = 17;
const HUMANIZER_SUB_TOOL_ID = 18;
const RESUME_BUILDER_SUB_TOOL_ID = 19;
const BUSINESS_NAME_SUB_TOOL_ID = 20;
const RESUME_MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024;

const route = useRoute();
const router = useRouter();
const { locale } = useI18n();

const markdown = new MarkdownIt({
    html: false,
    breaks: true,
    linkify: true,
});

const isArabic = computed(() =>
    String(locale.value || homeService.getLang() || "en").toLowerCase() === "ar"
);

const activeConversation = ref(null);
const currentSubtool = ref(null);
const conversations = ref([]);
const messages = ref([]);
const userMessage = ref("");
const errorMessage = ref("");
const loadingConversations = ref(false);
const loadingMessages = ref(false);
const creatingConversation = ref(false);
const isSending = ref(false);
const isAssistantTyping = ref(false);
const deletingUuid = ref("");
const sidebarOpen = ref(false);
const desktopSidebarCollapsed = ref(false);
const messagesContainer = ref(null);
const textareaRef = ref(null);
const copiedKey = ref("");
const optionsPanelRef = ref(null);
const resumeFile = ref(null);
const resumeFileInputRef = ref(null);
const composerResumeFileInputRef = ref(null);

const activeSubToolId = computed(() => {
    const candidates = [
        activeConversation.value?.sub_tool_id,
        route.params.sub_tool_id,
        currentSubtool.value?.id,
        currentSubtool.value?.sub_tool_id,
    ];

    const matched = candidates
        .map((value) => Number(value || 0))
        .find((value) => CHAT4_SUB_TOOL_IDS.includes(value));

    return matched || DETECTOR_SUB_TOOL_ID;
});

const MOBILE_SIDEBAR_BREAKPOINT = 900;

const isMobileSidebar = () => window.innerWidth <= MOBILE_SIDEBAR_BREAKPOINT;

const closeSidebar = () => {
    if (isMobileSidebar()) {
        sidebarOpen.value = false;
        return;
    }

    desktopSidebarCollapsed.value = true;
};

const openSidebar = () => {
    if (isMobileSidebar()) {
        sidebarOpen.value = true;
        return;
    }

    desktopSidebarCollapsed.value = false;
};

const activeToolConfig = computed(() => CHAT4_TOOLS[activeSubToolId.value] || CHAT4_TOOLS[DETECTOR_SUB_TOOL_ID]);
const isHumanizer = computed(() => Number(activeSubToolId.value) === HUMANIZER_SUB_TOOL_ID);
const isResumeBuilder = computed(() => Number(activeSubToolId.value) === RESUME_BUILDER_SUB_TOOL_ID);
const isBusinessNameTool = computed(() => Number(activeSubToolId.value) === BUSINESS_NAME_SUB_TOOL_ID);

const baseLabels = computed(() => isArabic.value ? {
    title: activeToolConfig.value.title_ar,
    subtitle: activeToolConfig.value.subtitle_ar,
    newChat: "محادثة جديدة",
    creating: "جارٍ الإنشاء...",
    recent: "المحادثات الأخيرة",
    loading: "جارٍ التحميل...",
    noChats: "لا توجد محادثات بعد",
    deleteChat: "حذف المحادثة",
    loadingConversation: "جارٍ تحميل المحادثة...",
    welcome: isBusinessNameTool.value
        ? "اكتب فكرة مشروعك وسنولد لك أسماء مناسبة مع شعارات قصيرة وأفكار دومينات عند الحاجة."
        : isHumanizer.value
            ? "الصق النص الذي تريد أنسنته، وسنحوله إلى صياغة طبيعية وسلسة مع الحفاظ على المعنى."
            : "الصق النص أو اكتب طلبك، وسنحلل مؤشرات الأسلوب والبنية والتكرار بدون ادعاء اليقين.",
    placeholder: isBusinessNameTool.value
        ? "اكتب فكرة المشروع أو صف النشاط الذي تريد توليد أسماء له..."
        : isHumanizer.value
            ? "الصق النص المراد أنسنته أو اكتب طلبك هنا..."
            : "الصق النص المراد تحليله أو اكتب طلبك هنا...",
    send: "إرسال",
    hint: "Enter للإرسال، وShift + Enter لسطر جديد",
    options: isBusinessNameTool.value
        ? "خيارات الأداة"
        : isHumanizer.value
            ? "خيارات الأداة"
            : "خيارات الأداة",
    applyOptions: "تعديل الخيارات",
    resetOptions: "إعادة تعيين الخيارات",
    language: "اللغة",
    analysisDepth: "عمق التحليل",
    detectionFocus: "تركيز الكشف",
    outputOptions: "مخرجات التحليل",
    includeScore: "إظهار درجة احتمالية الذكاء الاصطناعي",
    includeEvidence: "إظهار الأدلة والمؤشرات",
    includeRewriteTips: "إظهار نصائح إعادة الصياغة",
    extraOptions: "خيارات إضافية",
    aiResponseTitle: isBusinessNameTool.value
        ? "أسماء المشاريع المقترحة"
        : isHumanizer.value
            ? "النص بعد الأنسنة"
            : "نتيجة التحليل",
    tone: "النبرة",
    audience: "الجمهور",
    humanizeLevel: "درجة الأنسنة",
    resultsCount: "عدد النتائج",
    preserveMeaning: "الحفاظ على المعنى",
    preserveKeywords: "الحفاظ على الكلمات المفتاحية",
    humanizerOptions: "خيارات الأنسنة",
    humanizerResultTitle: "النص بعد الأنسنة",
    businessNameOptions: "خيارات توليد الأسماء",
    businessNameResultTitle: "أسماء المشاريع المقترحة",
    nameStyle: "أسلوب الاسم",
    industry: "المجال",
    targetAudience: "الجمهور المستهدف",
    keywords: "كلمات مهمة",
    avoidWords: "كلمات يجب تجنبها",
    includeSlogans: "إضافة شعارات قصيرة",
    includeDomainIdeas: "إضافة أفكار دومينات",
    domainIdeas: "أفكار الدومينات",
    score: "درجة احتمال الذكاء",
    signals: "المؤشرات",
    rewriteTips: "نصائح إعادة الصياغة",
    copy: "نسخ",
    copied: "تم النسخ",
    copyResult: "نسخ النتيجة",
    copyAll: "نسخ كل النتائج",
    authRequired: "يجب تسجيل الدخول أولاً.",
    genericError: isBusinessNameTool.value
        ? "تعذر توليد أسماء المشاريع الآن. يرجى المحاولة مرة أخرى."
        : isHumanizer.value
            ? "تعذر أنسنة النص الآن. يرجى المحاولة مرة أخرى."
            : "تعذر تحليل المحتوى الآن. يرجى المحاولة مرة أخرى.",
} : {
    title: activeToolConfig.value.title_en,
    subtitle: activeToolConfig.value.subtitle_en,
    newChat: "New chat",
    creating: "Creating...",
    recent: "Recent chats",
    loading: "Loading...",
    noChats: "No chats yet",
    deleteChat: "Delete chat",
    loadingConversation: "Loading conversation...",
    welcome: isBusinessNameTool.value
        ? "Describe your project and we will generate business names, short slogans, and domain ideas when needed."
        : isHumanizer.value
            ? "Paste text to humanize, and we will rewrite it into a natural, smooth version while preserving the meaning."
            : "Paste text or ask for an analysis. The detector reviews style, structure, repetition, and specificity without claiming certainty.",
    placeholder: isBusinessNameTool.value
        ? "Describe the business idea or startup you want names for..."
        : isHumanizer.value
            ? "Paste text to humanize or type your request..."
            : "Paste text to analyze or type your request...",
    send: "Send",
    hint: "Enter to send, Shift + Enter for a new line",
    options: isBusinessNameTool.value
        ? "Business name options"
        : isHumanizer.value
            ? "Humanizer options"
            : "Detector options",
    applyOptions: "Apply options",
    resetOptions: "Reset options",
    language: "Language",
    analysisDepth: "Analysis depth",
    detectionFocus: "Detection focus",
    outputOptions: "Analysis output",
    includeScore: "Include AI likelihood score",
    includeEvidence: "Include evidence",
    includeRewriteTips: "Include rewrite tips",
    extraOptions: "Extra options",
    aiResponseTitle: isBusinessNameTool.value
        ? "Suggested business names"
        : isHumanizer.value
            ? "Humanized text"
            : "Analysis result",
    tone: "Tone",
    audience: "Audience",
    humanizeLevel: "Humanize level",
    resultsCount: "Results count",
    preserveMeaning: "Preserve meaning",
    preserveKeywords: "Preserve keywords",
    humanizerOptions: "Humanizer options",
    humanizerResultTitle: "Humanized text",
    businessNameOptions: "Business name options",
    businessNameResultTitle: "Suggested business names",
    nameStyle: "Name style",
    industry: "Industry",
    targetAudience: "Target audience",
    keywords: "Important keywords",
    avoidWords: "Words to avoid",
    includeSlogans: "Include slogans",
    includeDomainIdeas: "Include domain ideas",
    domainIdeas: "Domain ideas",
    score: "AI Likelihood Score",
    signals: "Signals",
    rewriteTips: "Rewrite Tips",
    copy: "Copy",
    copied: "Copied",
    copyResult: "Copy result",
    copyAll: "Copy all results",
    authRequired: "Please sign in first.",
    genericError: isBusinessNameTool.value
        ? "Could not generate business names right now. Please try again."
        : isHumanizer.value
            ? "Could not humanize the text right now. Please try again."
            : "Could not analyze the content right now. Please try again.",
});

const labels = computed(() => {
    const base = baseLabels.value;

    if (!isResumeBuilder.value) {
        return base;
    }

    const resumeLabels = isArabic.value ? {
        welcome: "ارفع سيرتك الذاتية أو اكتب طلبك، وسنساعدك على تحسينها بصياغة احترافية مناسبة لأنظمة ATS.",
        placeholder: "اكتب طلبك هنا، مثال: حسّن هذه السيرة الذاتية لوظيفة Senior Laravel Developer...",
        options: "خيارات السيرة الذاتية",
        applyOptions: "تعديل الخيارات",
        resetOptions: "إعادة تعيين الخيارات",
        editOptions: "تعديل الخيارات",
        editPreviousResumePrompt: "عدّل السيرة الذاتية السابقة بناءً على هذه التغييرات: ",
        aiResponseTitle: "السيرة الذاتية المحسنة",
        targetRole: "الوظيفة المستهدفة",
        candidateName: "اسم المرشح",
        experienceLevel: "مستوى الخبرة",
        resumeStyle: "أسلوب السيرة الذاتية",
        outputFormat: "صيغة الإخراج",
        sectionsToInclude: "الأقسام المطلوبة",
        extraOptions: "خيارات إضافية",
        resumeFile: "ملف السيرة الذاتية",
        uploadResume: "رفع ملف السيرة الذاتية",
        replaceFile: "استبدال الملف",
        selectedFile: "الملف المختار",
        removeFile: "حذف الملف",
        fileRequired: "يرجى رفع ملف السيرة الذاتية أولاً",
        resumeBuilderOptions: "خيارات السيرة الذاتية",
        resumeBuilderResultTitle: "السيرة الذاتية المحسنة",
        downloadFile: "تحميل ملف السيرة الذاتية",
        invalidFileType: "يرجى رفع ملف PDF أو DOC أو DOCX.",
        fileTooLarge: "حجم الملف يجب ألا يتجاوز 10MB.",
        targetRoleRequired: "يرجى إدخال الوظيفة المستهدفة.",
        genericError: "تعذر تحسين السيرة الذاتية الآن. يرجى المحاولة مرة أخرى.",
    } : {
        welcome: "Upload an existing resume to improve it, or describe the role and we will build an ATS-friendly resume from scratch.",
        placeholder: "Describe how you want to build or improve the resume...",
        options: "Resume builder options",
        applyOptions: "Apply options",
        resetOptions: "Reset options",
        editOptions: "Edit options",
        editPreviousResumePrompt: "Edit the previous resume based on these changes: ",
        aiResponseTitle: "Improved resume",
        targetRole: "Target role",
        candidateName: "Candidate name",
        experienceLevel: "Experience level",
        resumeStyle: "Resume style",
        outputFormat: "Output format",
        sectionsToInclude: "Sections to include",
        extraOptions: "Extra options",
        resumeFile: "Resume file",
        uploadResume: "Upload resume file",
        replaceFile: "Replace file",
        selectedFile: "Selected file",
        removeFile: "Remove file",
        fileRequired: "Please upload a resume file first.",
        resumeBuilderOptions: "Resume builder options",
        resumeBuilderResultTitle: "Improved resume",
        downloadFile: "Download resume file",
        invalidFileType: "Please upload a PDF, DOC, or DOCX file.",
        fileTooLarge: "File size must be 10MB or less.",
        targetRoleRequired: "Please enter the target role.",
        genericError: "Could not improve the resume right now. Please try again.",
    };

    return {
        ...base,
        ...resumeLabels,
    };
});

const baseExamplePrompt = computed(() => isBusinessNameTool.value
    ? "Generate 10 Arabic business names for a startup that sells AI tools for marketers"
    : isHumanizer.value
        ? "Humanize this text in Arabic: الذكاء الاصطناعي يقوم بتحسين عمليات إنشاء المحتوى بطريقة فعالة للغاية."
        : "Analyze this text and tell me if it sounds AI-written: Artificial intelligence is transforming the way businesses create content and communicate with customers."
);

const examplePrompt = computed(() => isResumeBuilder.value
    ? "Improve this resume for a Senior Laravel Developer role and make it ATS-friendly."
    : baseExamplePrompt.value
);

const detectorExtraOptionChoices = [
    "Be cautious",
    "Do not claim certainty",
    "Highlight suspicious phrases",
    "Give practical rewrite suggestions",
];
const humanizerExtraOptionChoices = [
    "Improve flow",
    "Avoid robotic phrasing",
    "Make it sound natural",
    "Vary sentence structure",
    "Keep original meaning",
    "Simplify wording",
];
const businessNameExtraOptionChoices = [
    "Easy to remember",
    "Avoid duplicates",
    "Brandable names",
    "Short names",
    "Modern sound",
    "Domain-friendly",
    "Avoid hard pronunciation",
];
const resumeSectionChoices = [
    "Summary",
    "Skills",
    "Experience",
    "Education",
    "Certifications",
    "Projects",
    "Languages",
];
const resumeExtraOptionChoices = [
    "Improve clarity",
    "Use strong action verbs",
    "Keep it honest",
    "Do not invent experience",
    "Make it ATS-friendly",
    "Fix grammar",
    "Improve formatting",
    "Highlight achievements",
    "Quantify impact where possible",
];

const extraOptionChoices = computed(() => {
    if (isBusinessNameTool.value) return businessNameExtraOptionChoices;
    if (isResumeBuilder.value) return resumeExtraOptionChoices;
    if (isHumanizer.value) return humanizerExtraOptionChoices;
    return detectorExtraOptionChoices;
});

function createDetectorState() {
    return {
        content: null,
        language: "Auto Detect",
        analysis_depth: "Medium",
        detection_focus: "AI writing signals",
        include_score: true,
        include_evidence: true,
        include_rewrite_tips: true,
        extra_options: ["Be cautious", "Do not claim certainty"],
        last_output: null,
    };
}

function createHumanizerState() {
    return {
        content: null,
        language: "Auto Detect",
        tone: "Natural",
        audience: "General Audience",
        humanize_level: "Medium",
        preserve_meaning: true,
        preserve_keywords: true,
        results_count: 1,
        extra_options: ["Improve flow", "Avoid robotic phrasing"],
        last_output: null,
    };
}

function createBusinessNameState() {
    return {
        business_idea: null,
        industry: null,
        target_audience: null,
        language: "Auto Detect",
        tone: "Creative",
        name_style: "Brandable",
        keywords: [],
        avoid_words: [],
        results_count: 10,
        include_slogans: true,
        include_domain_ideas: true,
        extra_options: ["Easy to remember", "Avoid duplicates", "Brandable names"],
        last_output: null,
    };
}

function createResumeBuilderState() {
    return {
        target_role: "Senior Laravel Developer",
        candidate_name: null,
        language: "English",
        tone: "Professional",
        experience_level: "Senior",
        resume_style: "ATS-friendly modern",
        output_format: "docx",
        sections_to_include: [
            "Summary",
            "Skills",
            "Experience",
            "Education",
            "Certifications",
            "Projects",
            "Languages",
        ],
        extra_options: [
            "Improve clarity",
            "Use strong action verbs",
            "Keep it honest",
            "Do not invent experience",
        ],
        last_output: null,
    };
}

function createDefaultStateForTool(subToolId) {
    if (Number(subToolId) === HUMANIZER_SUB_TOOL_ID) return createHumanizerState();
    if (Number(subToolId) === RESUME_BUILDER_SUB_TOOL_ID) return createResumeBuilderState();
    if (Number(subToolId) === BUSINESS_NAME_SUB_TOOL_ID) return createBusinessNameState();
    return createDetectorState();
}

const toolState = ref(createDefaultStateForTool(DETECTOR_SUB_TOOL_ID));
const pageTitle = computed(() => isArabic.value ? activeToolConfig.value.title_ar : activeToolConfig.value.title_en);
const sendDisabled = computed(() => isSending.value || isAssistantTyping.value);
const canSendMessage = computed(() => {
    const hasText = Boolean(String(userMessage.value || "").trim());

    if (isResumeBuilder.value) {
        return hasText || Boolean(resumeFile.value);
    }

    return hasText;
});

const optionsSummary = computed(() => {
    const state = toolState.value || {};

    if (isBusinessNameTool.value) {
        return [state.language, state.tone, state.name_style, `${state.results_count || 10} results`]
            .filter(Boolean)
            .join(" / ");
    }

    if (isHumanizer.value) {
        return [state.language, state.tone, state.humanize_level, `${state.results_count || 1} result`]
            .filter(Boolean)
            .join(" / ");
    }

    if (isResumeBuilder.value) {
        return [state.language, state.tone, state.experience_level, state.output_format]
            .filter(Boolean)
            .join(" / ");
    }

    return [state.language, state.analysis_depth, state.detection_focus]
        .filter(Boolean)
        .join(" / ");
});

const baseTypingText = computed(() => {
    if (isBusinessNameTool.value) {
        return isArabic.value ? "جاري الكتابة" : "Generating business names";
    }

    if (isHumanizer.value) {
        return isArabic.value ? "جاري الكتابة" : "Humanizing text";
    }

    return isArabic.value ? "جاري الكتابة" : "Analyzing content";
});

const typingText = computed(() => isResumeBuilder.value
    ? (isArabic.value ? "جاري تحسين السيرة الذاتية" : "Improving resume")
    : baseTypingText.value
);

useSeoMeta({
    title: computed(() => `${pageTitle.value} | Ai Pro`),
    description: computed(() => labels.value.welcome),
});

const formatMessage = (value = "") =>
    DOMPurify.sanitize(markdown.render(String(value || "")), { USE_PROFILES: { html: true } });

const createLocalKey = () => `local-${Date.now()}-${Math.random().toString(16).slice(2)}`;

const createIdempotencyKey = () => {
    if (window?.crypto?.randomUUID) return window.crypto.randomUUID();

    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (char) => {
        const random = Math.floor(Math.random() * 16);
        const value = char === "x" ? random : (random & 0x3) | 0x8;
        return value.toString(16);
    });
};

const safeJsonParse = (value) => {
    if (!value || typeof value !== "string") return null;

    try {
        return JSON.parse(value);
    } catch {
        return null;
    }
};

const metadataFrom = (message = {}) => {
    if (message.metadata && typeof message.metadata === "object") return message.metadata;
    if (typeof message.metadata === "string") return safeJsonParse(message.metadata) || {};
    return {};
};

function createUploadedFileMeta(file) {
    if (!file) return null;

    const name = String(file.name || "").trim();
    const extension = name.includes(".")
        ? name.split(".").pop().toLowerCase()
        : "";

    return {
        name,
        filename: name,
        size: Number(file.size || 0),
        mime_type: file.type || "application/octet-stream",
        extension,
        label: "Document",
    };
}

function getMessageUploadedFile(message) {
    const meta = message?.metadata && typeof message.metadata === "object"
        ? message.metadata
        : {};
    const subToolId = Number(meta.sub_tool_id || message?.sub_tool_id || 0);

    if (subToolId !== RESUME_BUILDER_SUB_TOOL_ID) {
        return null;
    }

    const uploadedFile =
        meta.uploaded_file
        || meta.file_upload
        || meta.user_file
        || null;

    if (!uploadedFile || typeof uploadedFile !== "object" || uploadedFile.uploaded === false) {
        return null;
    }

    const name = String(
        uploadedFile.filename
        || uploadedFile.name
        || uploadedFile.original_filename
        || ""
    ).trim();

    if (!name) {
        return null;
    }

    return {
        ...uploadedFile,
        name,
        filename: name,
        label: uploadedFile.label || "Document",
    };
}

function formatFileSize(bytes) {
    const size = Number(bytes || 0);

    if (!size) return "";

    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

function getMessageToolId(message) {
    return Number(
        message?.metadata?.sub_tool_id
        || message?.responseState?.sub_tool_id
        || activeSubToolId.value
        || DETECTOR_SUB_TOOL_ID
    );
}

function isBusinessNameMessage(message) {
    return getMessageToolId(message) === BUSINESS_NAME_SUB_TOOL_ID;
}

function isResumeBuilderMessage(message) {
    return getMessageToolId(message) === RESUME_BUILDER_SUB_TOOL_ID;
}

function getBusinessDomains(item) {
    return Array.isArray(item?.meta?.domain_ideas)
        ? item.meta.domain_ideas.map((domain) => String(domain || "").trim()).filter(Boolean)
        : [];
}

function getResultCopyText(item, toolId = activeSubToolId.value) {
    if (Number(toolId) === BUSINESS_NAME_SUB_TOOL_ID) {
        const parts = [String(item?.text || "").trim()].filter(Boolean);

        if (item?.meta?.slogan) {
            parts.push(`Slogan: ${String(item.meta.slogan).trim()}`);
        }

        const domains = getBusinessDomains(item);
        if (domains.length) {
            parts.push(`Domains: ${domains.join(", ")}`);
        }

        return parts.join("\n");
    }

    return String(item?.text || item?.content || item?.output || "").trim();
}

function getMessageText(message) {
    if (message?.results?.length) {
        const toolId = getMessageToolId(message);

        return message.results
            .map((item) => getResultCopyText(item, toolId))
            .filter(Boolean)
            .join("\n\n");
    }

    return String(message?.content || "");
}

function getMessageDownloadUrl(message) {
    const result = Array.isArray(message?.results) ? message.results[0] : null;
    const meta = result?.meta && typeof result.meta === "object" ? result.meta : {};
    const messageMeta = message?.metadata && typeof message.metadata === "object" ? message.metadata : {};
    const file = messageMeta.file && typeof messageMeta.file === "object" ? messageMeta.file : {};

    return String(
        meta.download_url
        || meta.file_url
        || file.download_url
        || file.file_url
        || ""
    ).trim();
}

function getMessageFilename(message) {
    const result = Array.isArray(message?.results) ? message.results[0] : null;
    const meta = result?.meta && typeof result.meta === "object" ? result.meta : {};
    const messageMeta = message?.metadata && typeof message.metadata === "object" ? message.metadata : {};
    const file = messageMeta.file && typeof messageMeta.file === "object" ? messageMeta.file : {};

    return String(meta.filename || file.filename || "").trim();
}

function resolveDownloadUrl(url) {
    const value = String(url || "").trim();
    if (!value) return "";

    if (/^https?:\/\//i.test(value)) {
        return value;
    }

    const aiArabicBase =
        import.meta.env.VITE_AIARABIC_PUBLIC_BASE_URL
        || "https://api.aiarabic.com";

    if (value.startsWith("/tasks/resume-builder/download/")) {
        return `${String(aiArabicBase).replace(/\/$/, "")}${value}`;
    }

    if (value.startsWith("tasks/resume-builder/download/")) {
        return `${String(aiArabicBase).replace(/\/$/, "")}/${value}`;
    }

    const apiBase =
        import.meta.env.VITE_API_BASE_URL
        || import.meta.env.VITE_API_URL
        || "https://pro.aiarabic.com/api/v1";

    return `${String(apiBase).replace(/\/$/, "")}/${value.replace(/^\//, "")}`;
}

async function getDownloadErrorMessage(response) {
    const fallback = `${response.status} ${response.statusText}`.trim();

    try {
        const contentType = response.headers.get("content-type") || "";

        if (contentType.includes("application/json")) {
            const data = await response.json();

            const errors = data?.errors && typeof data.errors === "object"
                ? Object.values(data.errors).flat().filter(Boolean).join(" ")
                : "";

            return String(
                errors
                || data?.message
                || data?.detail
                || data?.error
                || data?.provider_message
                || data?.friendly_message
                || fallback
                || labels.value.genericError
            );
        }

        if (contentType.includes("text/html")) {
            return `Download URL is invalid or route not found: ${fallback}`;
        }

        const text = await response.text();

        return String(
            text
            || fallback
            || labels.value.genericError
        );
    } catch {
        return fallback || labels.value.genericError;
    }
}

async function downloadResumeFile(message) {
    const url = resolveDownloadUrl(getMessageDownloadUrl(message));
    if (!url) return;

    try {
        const headers = {
            Accept: "application/json",
        };

        const isAiArabicResumeDownload = url.includes("api.aiarabic.com/tasks/resume-builder/download/");

        if (isAiArabicResumeDownload) {
            headers["x-internal-api-key"] = 'L5W9R2Qx1T7p4Z8Vn6Hj3KcDmBaDsEUy';
        } else {
            const token = localStorage.getItem("auth_token");

            if (token) {
                headers.Authorization = `Bearer ${token}`;
            }
        }

        const response = await fetch(url, { headers });

        if (!response.ok) {
            throw new Error(await getDownloadErrorMessage(response));
        }

        const blob = await response.blob();
        const blobUrl = window.URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = blobUrl;
        link.download = getMessageFilename(message) || "resume.docx";
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(blobUrl);
    } catch (error) {
        errorMessage.value = cleanErrorMessage(error);
    }
}

function getResultTitle(subToolId = activeSubToolId.value) {
    if (Number(subToolId) === RESUME_BUILDER_SUB_TOOL_ID) {
        return isArabic.value ? "السيرة الذاتية المحسنة" : "Improved resume";
    }

    if (Number(subToolId) === BUSINESS_NAME_SUB_TOOL_ID) {
        return isArabic.value ? "أسماء المشاريع المقترحة" : "Suggested business names";
    }

    if (Number(subToolId) === HUMANIZER_SUB_TOOL_ID) {
        return isArabic.value ? "النص بعد الأنسنة" : "Humanized text";
    }

    return isArabic.value ? "نتيجة التحليل" : "Analysis result";
}

function serializeList(list) {
    return Array.isArray(list) ? list.join(", ") : "";
}

function parseCsvList(value) {
    return String(value || "")
        .split(",")
        .map((item) => item.trim())
        .filter(Boolean);
}

function updateListField(key, value) {
    toolState.value[key] = parseCsvList(value);
}

function cleanErrorMessage(error) {
    const raw =
        error?.response?.data?.message
        || error?.response?.data?.detail
        || error?.response?.data?.error
        || error?.message
        || "";

    const text = String(raw);
    const lower = text.toLowerCase();

    if (
        text.includes("429")
        || lower.includes("rate-limited")
        || lower.includes("rate limit")
        || lower.includes("too many requests")
    ) {
        return isArabic.value
            ? "الموديل مشغول مؤقتا بسبب كثرة الطلبات. يرجى المحاولة بعد قليل."
            : "The AI model is temporarily busy due to high demand. Please try again shortly.";
    }

    if (
        lower.includes("openrouter")
        || lower.includes("provider returned error")
        || lower.includes("provider error")
    ) {
        return isArabic.value
            ? "حدث خطأ مؤقت أثناء الاتصال بمزود الذكاء الاصطناعي. يرجى إعادة المحاولة."
            : "A temporary error occurred while connecting to the AI provider. Please try again.";
    }

    if (text.includes("401") || lower.includes("unauthenticated") || lower.includes("unauthorized")) {
        return isArabic.value
            ? "يرجى تسجيل الدخول أولاً للمتابعة."
            : "Please sign in first to continue.";
    }

    if (isResumeBuilder.value) {
        const errors = error?.response?.data?.errors;
        const firstFieldError = errors && typeof errors === "object"
            ? Object.values(errors).flat().find(Boolean)
            : null;
        const serverMessage = firstFieldError || raw;

        if (serverMessage) {
            return String(serverMessage);
        }
    }

    if (text.includes("422") || lower.includes("validation")) {
        return isArabic.value
            ? "يرجى التأكد من إدخال النص المطلوب بشكل صحيح."
            : "Please make sure the required text is entered correctly.";
    }

    return labels.value.genericError;
}

function extractDetectorContent(userMessage) {
    const text = String(userMessage || "").trim();
    const markerPatterns = [
        /AI-written:\s*([\s\S]+)$/i,
        /ai generated:\s*([\s\S]+)$/i,
        /content:\s*([\s\S]+)$/i,
        /text:\s*([\s\S]+)$/i,
        /النص:\s*([\s\S]+)$/i,
        /المحتوى:\s*([\s\S]+)$/i,
    ];

    for (const pattern of markerPatterns) {
        const match = text.match(pattern);
        if (match?.[1]) return match[1].trim();
    }

    return text;
}

function buildDetectorState(userMessage, currentState = {}) {
    const content = currentState.content || extractDetectorContent(userMessage);

    return {
        content,
        language: currentState.language || "Auto Detect",
        analysis_depth: currentState.analysis_depth || "Medium",
        detection_focus: currentState.detection_focus || "AI writing signals",
        include_score: currentState.include_score ?? true,
        include_evidence: currentState.include_evidence ?? true,
        include_rewrite_tips: currentState.include_rewrite_tips ?? true,
        extra_options: currentState.extra_options?.length
            ? currentState.extra_options
            : ["Be cautious", "Do not claim certainty"],
        last_output: null,
    };
}

function extractHumanizerContent(userMessage) {
    const text = String(userMessage || "").trim();
    const markerPatterns = [
        /humanize this text in arabic:\s*([\s\S]+)$/i,
        /humanize this text:\s*([\s\S]+)$/i,
        /humanize:\s*([\s\S]+)$/i,
        /content:\s*([\s\S]+)$/i,
        /text:\s*([\s\S]+)$/i,
        /أنسن هذا النص:\s*([\s\S]+)$/i,
        /حوّل هذا النص:\s*([\s\S]+)$/i,
        /حول هذا النص:\s*([\s\S]+)$/i,
        /النص:\s*([\s\S]+)$/i,
        /المحتوى:\s*([\s\S]+)$/i,
    ];

    for (const pattern of markerPatterns) {
        const match = text.match(pattern);
        if (match?.[1]) return match[1].trim();
    }

    return text;
}

function buildHumanizerState(userMessage, currentState = {}) {
    const text = String(userMessage || "").trim();
    const content = currentState.content || extractHumanizerContent(text);
    const countMatch = text.match(/(?:generate|write|create|اكتب|أنشئ|ولد)\s+(\d+)/i);
    const resultsCount = Math.max(1, Math.min(5, Number(currentState.results_count || countMatch?.[1] || 1)));
    const hasArabic = /arabic|عربي|العربية|بالعربي/i.test(text) || /[\u0600-\u06FF]/.test(content);

    return {
        content,
        language: currentState.language && currentState.language !== "Auto Detect"
            ? currentState.language
            : (hasArabic ? "Arabic" : "Auto Detect"),
        tone: currentState.tone || "Natural",
        audience: currentState.audience || "General Audience",
        humanize_level: currentState.humanize_level || "Medium",
        preserve_meaning: currentState.preserve_meaning ?? true,
        preserve_keywords: currentState.preserve_keywords ?? true,
        results_count: resultsCount,
        extra_options: currentState.extra_options?.length
            ? currentState.extra_options
            : ["Improve flow", "Avoid robotic phrasing"],
        last_output: null,
    };
}

function extractBusinessIdea(userMessage) {
    const text = String(userMessage || "").trim();
    const patterns = [
        /business names?\s+for\s+(?:a\s+|an\s+)?([\s\S]+)$/i,
        /project names?\s+for\s+(?:a\s+|an\s+)?([\s\S]+)$/i,
        /startup names?\s+for\s+(?:a\s+|an\s+)?([\s\S]+)$/i,
        /names?\s+for\s+(?:a\s+|an\s+)?([\s\S]+)$/i,
        /أسماء\s+(?:مشاريع|مشروع|شركات|شركة)\s+(?:عن|لـ|ل)?\s*([\s\S]+)$/i,
        /اسماء\s+(?:مشاريع|مشروع|شركات|شركة)\s+(?:عن|لـ|ل)?\s*([\s\S]+)$/i,
        /فكرة\s+المشروع:\s*([\s\S]+)$/i,
        /business idea:\s*([\s\S]+)$/i,
    ];

    for (const pattern of patterns) {
        const match = text.match(pattern);
        if (match?.[1]) return match[1].trim();
    }

    return text;
}

function extractRequestedCount(text, fallback = 10) {
    const match = String(text || "").match(/(?:generate|write|create|اكتب|ولد|أنشئ)\s+(\d+)/i);
    return Number(match?.[1] || fallback);
}

function detectLanguageFromText(text) {
    const value = String(text || "");
    if (/arabic|عربي|العربية|بالعربي/i.test(value) || /[\u0600-\u06FF]/.test(value)) {
        return "Arabic";
    }
    if (/english|إنجليزي|الإنجليزية/i.test(value)) {
        return "English";
    }
    return "Auto Detect";
}

function extractBusinessNameMeta(userMessage) {
    const text = String(userMessage || "").trim();

    const audienceMatch =
        text.match(/for\s+([a-zA-Z\s]+)$/i)
        || text.match(/target audience:\s*([^.\n]+)/i)
        || text.match(/الجمهور:\s*([^.\n]+)/i);

    let targetAudience = audienceMatch?.[1]?.trim() || null;

    if (/marketers/i.test(text)) {
        targetAudience = "marketers";
    }

    let industry = null;
    if (/AI tools|artificial intelligence|ذكاء اصطناعي|أدوات ذكاء/i.test(text)) {
        industry = "AI tools";
    }

    return {
        industry,
        target_audience: targetAudience,
    };
}

function extractBusinessKeywords(text) {
    const matches = String(text || "").match(/\b[A-Za-z]{2,}\b/g) || [];
    return [...new Set(matches)].slice(0, 5);
}

function buildBusinessNameState(userMessage, currentState = {}) {
    const text = String(userMessage || "").trim();
    const businessIdea = currentState.business_idea || extractBusinessIdea(text);
    const meta = extractBusinessNameMeta(text);
    const keywords = Array.isArray(currentState.keywords) && currentState.keywords.length
        ? currentState.keywords
        : extractBusinessKeywords(text);

    return {
        business_idea: businessIdea || text,
        industry: currentState.industry || meta.industry || "General",
        target_audience: currentState.target_audience || meta.target_audience || "General Audience",
        language: currentState.language || detectLanguageFromText(text),
        tone: currentState.tone || "Creative",
        name_style: currentState.name_style || "Brandable",
        keywords,
        avoid_words: Array.isArray(currentState.avoid_words) ? currentState.avoid_words : [],
        results_count: Math.max(1, Math.min(30, Number(currentState.results_count || extractRequestedCount(text, 10)))),
        include_slogans: currentState.include_slogans ?? true,
        include_domain_ideas: currentState.include_domain_ideas ?? true,
        extra_options: currentState.extra_options?.length
            ? currentState.extra_options
            : ["Easy to remember", "Avoid duplicates", "Brandable names"],
        last_output: null,
    };
}

function buildResumeBuilderState(userMessage, currentState = {}) {
    const defaults = createResumeBuilderState();

    return {
        target_role: String(currentState.target_role || defaults.target_role || "").trim(),
        candidate_name: currentState.candidate_name
            ? String(currentState.candidate_name).trim()
            : null,
        language: currentState.language || defaults.language,
        tone: currentState.tone || defaults.tone,
        experience_level: currentState.experience_level || defaults.experience_level,
        resume_style: currentState.resume_style || defaults.resume_style,
        output_format: currentState.output_format || defaults.output_format,
        sections_to_include: Array.isArray(currentState.sections_to_include) && currentState.sections_to_include.length
            ? currentState.sections_to_include
            : defaults.sections_to_include,
        extra_options: Array.isArray(currentState.extra_options) && currentState.extra_options.length
            ? currentState.extra_options
            : defaults.extra_options,
        last_output: currentState.last_output || null,
    };
}

function buildChat4ToolState(subToolId, userMessage, currentState = {}) {
    if (Number(subToolId) === RESUME_BUILDER_SUB_TOOL_ID) {
        return buildResumeBuilderState(userMessage, currentState);
    }

    if (Number(subToolId) === BUSINESS_NAME_SUB_TOOL_ID) {
        return buildBusinessNameState(userMessage, currentState);
    }

    if (Number(subToolId) === HUMANIZER_SUB_TOOL_ID) {
        return buildHumanizerState(userMessage, currentState);
    }

    return buildDetectorState(userMessage, currentState);
}

function getStateSeedText(state = toolState.value) {
    return String(state?.content || state?.business_idea || state?.target_role || "");
}

function buildChat4Payload(messageText, conversation) {
    const subToolId = Number(activeSubToolId.value);
    const config = CHAT4_TOOLS[subToolId] || CHAT4_TOOLS[DETECTOR_SUB_TOOL_ID];
    const requestState = Number(subToolId) === RESUME_BUILDER_SUB_TOOL_ID
        ? buildResumeBuilderState(messageText, toolState.value)
        : buildChat4ToolState(subToolId, messageText, {
            ...toolState.value,
            content: null,
            business_idea: null,
        });

    return {
        user_id: conversation?.user_id || null,
        sub_tool_id: subToolId,
        conversation_uuid: conversation?.uuid,
        user_message: messageText,
        content: messageText,
        tool: config.tool_key,
        tool_key: config.tool_key,
        model_key: config.model_key,
        state: requestState,
        debug: false,
        idempotency_key: createIdempotencyKey(),
    };
}

function buildResumeBuilderFormData(messageText, conversation, payload) {
    const formData = new FormData();

    formData.append("conversation_uuid", String(payload.conversation_uuid || conversation?.uuid || ""));
    formData.append("user_id", payload.user_id === null || payload.user_id === undefined ? "" : String(payload.user_id));
    formData.append("sub_tool_id", String(payload.sub_tool_id));
    formData.append("user_message", messageText);
    formData.append("state", JSON.stringify(payload.state || {}));
    formData.append("debug", payload.debug ? "1" : "0");
    formData.append("idempotency_key", String(payload.idempotency_key || ""));
    formData.append("tool", String(payload.tool || ""));
    formData.append("tool_key", String(payload.tool_key || ""));
    formData.append("model_key", String(payload.model_key || ""));
    formData.append("content", String(payload.content || messageText || ""));

    if (resumeFile.value) {
        formData.append("file", resumeFile.value);
    }

    return formData;
}

function removeResumeBuilderFailedAttempts() {
    messages.value = messages.value.filter((message) => !message?.metadata?.resume_builder_failed_attempt);
}

function markResumeBuilderAttemptFailed(localKey) {
    if (!localKey) return;

    messages.value = messages.value.map((message) => {
        if (message.localKey !== localKey) return message;

        return {
            ...message,
            is_error: true,
            metadata: {
                ...(message.metadata || {}),
                resume_builder_failed_attempt: true,
            },
        };
    });
}

function normalizeResultText(value) {
    if (value && typeof value === "object") {
        return normalizeResultText(value.text || value.name || value.content || value.output || value.message || "");
    }

    const text = String(value || "").trim();
    if (!text || text === "[object Object]") return "";

    const parsed = safeJsonParse(text.replace(/^```(?:json)?\s*/i, "").replace(/\s*```$/i, ""));
    if (parsed && typeof parsed === "object") {
        const nestedResults = Array.isArray(parsed.results) ? parsed.results : [];
        if (nestedResults.length) {
            return nestedResults
                .map((item) => normalizeResultText(item?.text || item?.name || item?.content || item?.output || ""))
                .filter(Boolean)
                .join("\n\n");
        }

        return normalizeResultText(parsed.text || parsed.name || parsed.content || parsed.output || parsed.message || "");
    }

    if (
        text.includes("OpenRouter")
        || text.includes("https://openrouter.ai")
        || text.includes("Provider returned error")
    ) {
        return "";
    }

    return text;
}

function normalizeChat4Response(response) {
    const results = Array.isArray(response?.results) ? response.results : [];
    const responseMeta = {
        download_url:
            response?.file?.download_url
            || response?.download_url
            || response?.meta?.download_url
            || null,
        file_url:
            response?.file?.file_url
            || response?.file_url
            || response?.meta?.file_url
            || null,
        file_id:
            response?.file?.file_id
            || response?.meta?.file_id
            || null,
        filename:
            response?.file?.filename
            || response?.meta?.filename
            || null,
        content_type:
            response?.file?.content_type
            || response?.meta?.content_type
            || null,
        output_format:
            response?.state?.output_format
            || response?.output_format
            || response?.meta?.output_format
            || null,
    };
    const cleanResponseMeta = Object.fromEntries(Object.entries(responseMeta).filter(([, value]) => value));

    if (results.length) {
        return results.map((item, index) => ({
            id: item.id || index + 1,
            text: normalizeResultText(item.text || item.name || item.content || item.output || ""),
            title: item.title || null,
            subject: item.subject || null,
            meta: {
                ...(item.meta && typeof item.meta === "object" ? item.meta : {}),
                ...Object.fromEntries(Object.entries(cleanResponseMeta).filter(([key]) => {
                    const itemMeta = item.meta && typeof item.meta === "object" ? item.meta : {};
                    return !itemMeta[key];
                })),
            },
        })).filter((item) => item.text.trim() || item.meta.download_url || item.meta.file_url);
    }

    if (response?.state?.last_output) {
        return String(response.state.last_output)
            .split("\n")
            .map((line) => line.trim())
            .filter(Boolean)
            .map((text, index) => ({
                id: index + 1,
                text,
                title: null,
                subject: null,
                meta: cleanResponseMeta,
            }));
    }

    return [];
}

const unwrapApiData = (response) => response?.data || response || {};

const normalizeStateFromResponse = (state = {}, subToolId = activeSubToolId.value) => {
    const baseState = {
        ...createDefaultStateForTool(subToolId),
        ...(state && typeof state === "object" ? state : {}),
    };

    if (Number(subToolId) === RESUME_BUILDER_SUB_TOOL_ID) {
        return buildResumeBuilderState("", baseState);
    }

    return buildChat4ToolState(
        subToolId,
        state.business_idea || state.content || "",
        baseState
    );
};

const mapMessage = (message = {}, index = 0) => {
    const meta = metadataFrom(message);
    const responseResults = normalizeChat4Response({
        ...meta,
        results: meta.normalized_results || meta.results || message.results || [],
        state: meta.state || message.state || {},
        file: meta.file || null,
        usage: meta.usage || null,
        cost: meta.cost || null,
    });
    const role = message.role || "assistant";

    return {
        localKey: message.localKey || message.id || `${role}-${index}-${createLocalKey()}`,
        id: message.id || null,
        role,
        content: String(message.content || ""),
        is_error: Boolean(message.is_error),
        results: role === "assistant" ? responseResults : [],
        responseState: meta.state || message.state || null,
        metadata: meta,
    };
};

const formatConversation = (conversation = {}) => {
    const uuid = conversation.uuid || conversation.conversation_uuid || "";
    const firstUserMessage = Array.isArray(conversation.message)
        ? conversation.message.find((item) => item.role === "user")?.content
        : "";

    return {
        ...conversation,
        uuid,
        user_id: conversation.user_id || null,
        sub_tool_id: Number(conversation.sub_tool_id || DETECTOR_SUB_TOOL_ID),
        title: conversation.title || firstUserMessage || pageTitle.value,
    };
};

const stateStorageKey = (uuid = activeConversation.value?.uuid || route.params.uuid || "draft") =>
    `chat4-tool-state:${activeSubToolId.value}:${uuid}`;

const persistState = (uuid = activeConversation.value?.uuid || route.params.uuid || "draft") => {
    sessionStorage.setItem(stateStorageKey(uuid), JSON.stringify(toolState.value));
};

const restoreState = (uuid = route.params.uuid || "draft") => {
    const stored = safeJsonParse(sessionStorage.getItem(stateStorageKey(uuid)) || "");
    toolState.value = normalizeStateFromResponse(
        stored || createDefaultStateForTool(activeSubToolId.value),
        activeSubToolId.value
    );
    if (isResumeBuilder.value) {
        removeResumeFile();
    }
};

const hydrateStateFromMessages = (rows = []) => {
    const latest = [...rows]
        .reverse()
        .map(mapMessage)
        .find((message) => message.role === "assistant" && message.responseState);

    if (latest?.responseState) {
        toolState.value = normalizeStateFromResponse(latest.responseState, activeSubToolId.value);
        persistState();
    }
};

const requireAuth = async () => {
    if (localStorage.getItem("auth_token")) return true;

    errorMessage.value = labels.value.authRequired;
    return false;
};

const loadSubtool = async () => {
    if (!route.params.slug) {
        currentSubtool.value = null;
        return;
    }

    try {
        const response = await homeService.showSubtool(route.params.slug);
        const data = response?.data || response || {};
        const subToolId = Number(data.id || data.sub_tool_id || 0);

        currentSubtool.value = {
            ...data,
            id: CHAT4_SUB_TOOL_IDS.includes(subToolId) ? subToolId : DETECTOR_SUB_TOOL_ID,
        };
    } catch {
        currentSubtool.value = null;
    }
};

const scrollToBottom = async () => {
    await nextTick();
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
};

const autoResize = () => {
    const el = textareaRef.value;
    if (!el) return;

    el.style.height = "auto";
    el.style.height = `${Math.min(el.scrollHeight, 180)}px`;
};

const resetTextarea = () => {
    nextTick(() => {
        if (textareaRef.value) {
            textareaRef.value.style.height = "auto";
        }
    });
};

const loadConversations = async () => {
    if (!localStorage.getItem("auth_token")) {
        conversations.value = [];
        return;
    }

    loadingConversations.value = true;

    try {
        const response = await chatServices.getConversations();
        const rows = Array.isArray(response?.data) ? response.data : [];
        conversations.value = rows
            .filter((conversation) => Number(conversation.sub_tool_id) === activeSubToolId.value)
            .map(formatConversation);
    } finally {
        loadingConversations.value = false;
    }
};

const loadConversationDetails = async (uuid) => {
    if (!uuid || !localStorage.getItem("auth_token")) {
        activeConversation.value = null;
        messages.value = [];
        return;
    }

    loadingMessages.value = true;

    try {
        const response = await chatServices.getConversation(uuid);
        const conversation = response?.data || null;
        const rows = Array.isArray(conversation?.message) ? conversation.message : [];

        if (conversation) {
            activeConversation.value = formatConversation(conversation);
            const existingIndex = conversations.value.findIndex((item) => item.uuid === uuid);
            if (existingIndex >= 0) {
                conversations.value[existingIndex] = activeConversation.value;
            }
        }

        messages.value = rows.map(mapMessage);
        hydrateStateFromMessages(rows);
        await scrollToBottom();
    } finally {
        loadingMessages.value = false;
    }
};

const ensureConversation = async () => {
    if (!(await requireAuth())) return null;
    if (activeConversation.value?.uuid) return activeConversation.value;

    const response = await chatServices.createConversation(route.params.slug);
    const conversation = formatConversation(response?.data || {});

    activeConversation.value = conversation;
    conversations.value = [
        conversation,
        ...conversations.value.filter((item) => item.uuid !== conversation.uuid),
    ];

    restoreState(conversation.uuid);
    await router.replace(`/${homeService.getLang()}/subtool/${route.params.slug}/chat4/${conversation.uuid}`);

    return conversation;
};

const sendMessage = async () => {
    const text = String(userMessage.value || "").trim();
    const hasPreviousResumeOutput = isResumeBuilder.value && Boolean(String(toolState.value.last_output || "").trim());
    let optimisticUserKey = "";

    if (isSending.value || isAssistantTyping.value) return;
    if (!text && !isResumeBuilder.value) return;
    if (isResumeBuilder.value && !text && !resumeFile.value && !hasPreviousResumeOutput) return;

    const finalText = text || `Improve this resume for ${toolState.value.target_role || "the target role"}.`;
    const resumeValidationMessage = validateResumeBuilderBeforeSend(finalText);
    if (resumeValidationMessage) {
        errorMessage.value = resumeValidationMessage;
        return;
    }

    const uploadedFileMeta = isResumeBuilder.value && resumeFile.value
        ? createUploadedFileMeta(resumeFile.value)
        : null;
    const editingPreviousOutput = isResumeBuilder.value && Boolean(String(toolState.value.last_output || "").trim());

    errorMessage.value = "";
    isSending.value = true;

    try {
        const conversation = await ensureConversation();
        if (!conversation?.uuid) return;

        const payload = buildChat4Payload(finalText, conversation);
        optimisticUserKey = createLocalKey();

        if (isResumeBuilder.value) {
            removeResumeBuilderFailedAttempts();
        }

        messages.value.push(mapMessage({
            localKey: optimisticUserKey,
            role: "user",
            content: finalText,
            metadata: {
                tool: payload.tool,
                tool_key: payload.tool_key,
                model_key: payload.model_key,
                sub_tool_id: payload.sub_tool_id,
                idempotency_key: payload.idempotency_key,
                optimistic: true,
                uploaded_file: uploadedFileMeta,
                editing_previous_output: editingPreviousOutput,
            },
            created_at: new Date().toISOString(),
        }));

        toolState.value = payload.state;
        isAssistantTyping.value = true;
        userMessage.value = "";
        resetTextarea();
        persistState(conversation.uuid);
        await scrollToBottom();

        const response = isResumeBuilder.value
            ? await chatServices.sendMessageFormData(buildResumeBuilderFormData(finalText, conversation, payload))
            : await chatServices.sendMessage(payload);
        const directResponse = unwrapApiData(response);
        const results = normalizeChat4Response(directResponse);

        isAssistantTyping.value = false;

        if (!results.length) {
            throw new Error(labels.value.genericError);
        }

        const responseState = normalizeStateFromResponse(directResponse.state || payload.state, payload.sub_tool_id);
        toolState.value = responseState;
        persistState(conversation.uuid);

        messages.value.push(mapMessage({
            localKey: createLocalKey(),
            role: "assistant",
            content: results.map((item) => item.text).join("\n\n"),
            results,
            state: responseState,
            metadata: {
                type: directResponse.type || "result",
                title: getResultTitle(payload.sub_tool_id),
                tool: directResponse.tool || payload.tool,
                provider: directResponse.provider || null,
                tool_key: payload.tool_key,
                model_key: directResponse.model_key || payload.model_key,
                sub_tool_id: payload.sub_tool_id,
                state: responseState,
                results,
                normalized_results: results,
                file: directResponse.file || null,
                usage: directResponse.usage || null,
                cost: directResponse.cost || null,
                request_id: directResponse.request_id || null,
                request_payload: payload,
                count: results.length,
            },
            created_at: new Date().toISOString(),
        }));

        if (isResumeBuilder.value) {
            removeResumeFile();
        }

        await scrollToBottom();
    } catch (error) {
        isAssistantTyping.value = false;

        if (isResumeBuilder.value) {
            markResumeBuilderAttemptFailed(optimisticUserKey);
        }

        messages.value.push(mapMessage({
            localKey: createLocalKey(),
            role: "assistant",
            is_error: true,
            content: cleanErrorMessage(error),
            metadata: {
                sub_tool_id: activeSubToolId.value,
                resume_builder_failed_attempt: isResumeBuilder.value,
                reply_to_local_key: optimisticUserKey || null,
            },
            created_at: new Date().toISOString(),
        }));
        await scrollToBottom();
    } finally {
        isSending.value = false;
        isAssistantTyping.value = false;
    }
};

const startNewChat = async () => {
    if (creatingConversation.value || !(await requireAuth())) return;

    creatingConversation.value = true;
    errorMessage.value = "";

    try {
        const response = await chatServices.createConversation(route.params.slug);
        const conversation = formatConversation(response?.data || {});

        activeConversation.value = conversation;
        conversations.value = [
            conversation,
            ...conversations.value.filter((item) => item.uuid !== conversation.uuid),
        ];
        messages.value = [];
        toolState.value = createDefaultStateForTool(activeSubToolId.value);
        removeResumeFile();
        persistState(conversation.uuid);
        sidebarOpen.value = false;

        await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat4/${conversation.uuid}`);
    } finally {
        creatingConversation.value = false;
    }
};

const openConversation = async (conversation) => {
    if (!conversation?.uuid) return;

    restoreState(conversation.uuid);

    if (route.params.uuid === conversation.uuid) {
        await loadConversationDetails(conversation.uuid);
        sidebarOpen.value = false;
        return;
    }

    await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat4/${conversation.uuid}`);
    sidebarOpen.value = false;
};

const deleteConversation = async (conversation) => {
    if (!conversation?.uuid || deletingUuid.value) return;

    deletingUuid.value = conversation.uuid;

    try {
        await chatServices.deleteConversation(conversation.uuid);
        conversations.value = conversations.value.filter((item) => item.uuid !== conversation.uuid);

        if (activeConversation.value?.uuid === conversation.uuid) {
            activeConversation.value = null;
            messages.value = [];
            toolState.value = createDefaultStateForTool(activeSubToolId.value);
            removeResumeFile();
            await router.push(`/${homeService.getLang()}/subtool/${route.params.slug}/chat4`);
        }
    } finally {
        deletingUuid.value = "";
    }
};

const fillExample = () => {
    userMessage.value = examplePrompt.value;
    nextTick(() => {
        textareaRef.value?.focus();
        autoResize();
    });
};

const toggleExtraOption = (option) => {
    const selected = Array.isArray(toolState.value.extra_options)
        ? toolState.value.extra_options
        : [];

    toolState.value.extra_options = selected.includes(option)
        ? selected.filter((item) => item !== option)
        : [...selected, option];
};

const toggleResumeSection = (section) => {
    const selected = Array.isArray(toolState.value.sections_to_include)
        ? toolState.value.sections_to_include
        : [];

    toolState.value.sections_to_include = selected.includes(section)
        ? selected.filter((item) => item !== section)
        : [...selected, section];
};

const clearResumeFileInput = () => {
    if (resumeFileInputRef.value) {
        resumeFileInputRef.value.value = "";
    }

    if (composerResumeFileInputRef.value) {
        composerResumeFileInputRef.value.value = "";
    }
};

const validateResumeFile = (file) => {
    if (!file) return "";

    const extension = String(file.name || "").split(".").pop()?.toLowerCase();
    if (!["pdf", "doc", "docx"].includes(extension)) {
        return labels.value.invalidFileType;
    }

    if (Number(file.size || 0) > RESUME_MAX_FILE_SIZE_BYTES) {
        return labels.value.fileTooLarge;
    }

    return "";
};

const handleResumeFileChange = (event) => {
    const file = event?.target?.files?.[0] || null;
    const validationMessage = validateResumeFile(file);

    if (validationMessage) {
        errorMessage.value = validationMessage;
        resumeFile.value = null;
        clearResumeFileInput();
        return;
    }

    resumeFile.value = file;
    errorMessage.value = "";
};

const removeResumeFile = () => {
    resumeFile.value = null;
    clearResumeFileInput();
};

const isImproveResumeRequest = (messageText) => {
    const text = String(messageText || "").toLowerCase();
    return /\b(improve|enhance|rewrite|optimi[sz]e|fix|update|review|polish)\b/.test(text)
        && /\b(resume|cv|curriculum vitae)\b/.test(text);
};

const validateResumeBuilderBeforeSend = (messageText) => {
    if (!isResumeBuilder.value) return "";

    if (!String(toolState.value.target_role || "").trim()) {
        return labels.value.targetRoleRequired;
    }

    const hasPreviousOutput = Boolean(String(toolState.value.last_output || "").trim());

    if (!hasPreviousOutput && !resumeFile.value && isImproveResumeRequest(messageText)) {
        return labels.value.fileRequired;
    }

    return validateResumeFile(resumeFile.value);
};

async function editResumeResponse(message) {
    if (!isResumeBuilderMessage(message)) return;

    const previousOutput = getMessageText(message);
    const meta = message?.metadata && typeof message.metadata === "object"
        ? message.metadata
        : {};
    const responseState = message?.responseState || meta.state || toolState.value || {};

    toolState.value = buildResumeBuilderState("", {
        ...createDefaultStateForTool(RESUME_BUILDER_SUB_TOOL_ID),
        ...responseState,
        last_output: previousOutput,
    });

    persistState(activeConversation.value?.uuid || route.params.uuid || "draft");
    userMessage.value = labels.value.editPreviousResumePrompt;

    await nextTick();

    if (optionsPanelRef.value) {
        optionsPanelRef.value.open = true;
    }

    textareaRef.value?.focus();
    textareaRef.value?.scrollIntoView({ block: "nearest", behavior: "smooth" });
    autoResize();
}

const applyOptions = () => {
    if (isResumeBuilder.value) {
        toolState.value = buildResumeBuilderState("", toolState.value);
    } else {
        toolState.value = buildChat4ToolState(
            activeSubToolId.value,
            getStateSeedText(toolState.value),
            toolState.value
        );
    }

    persistState();
    errorMessage.value = "";

    if (optionsPanelRef.value) {
        optionsPanelRef.value.open = false;
    }
};

const resetOptions = () => {
    toolState.value = createDefaultStateForTool(activeSubToolId.value);
    if (isResumeBuilder.value) {
        removeResumeFile();
    }
    persistState();
};

const copyText = async (text, key) => {
    const value = String(text || "").trim();
    if (!value) return;

    await navigator.clipboard.writeText(value);
    copiedKey.value = key;

    window.setTimeout(() => {
        copiedKey.value = "";
    }, 1200);
};

const initialize = async () => {
    locale.value = homeService.getLang();
    await loadSubtool();
    restoreState(route.params.uuid || "draft");
    await loadConversations();

    if (route.params.uuid) {
        await loadConversationDetails(route.params.uuid);
    }
};

const handleLanguageChange = async () => {
    locale.value = homeService.getLang();
};

onMounted(async () => {
    await initialize();
    window.addEventListener("lang-changed", handleLanguageChange);
});

onUnmounted(() => {
    document.body.style.overflow = "";
    window.removeEventListener("lang-changed", handleLanguageChange);
});

watch(sidebarOpen, (isOpen) => {
    if (window.innerWidth > MOBILE_SIDEBAR_BREAKPOINT) {
        document.body.style.overflow = "";
        return;
    }

    document.body.style.overflow = isOpen ? "hidden" : "";
});

watch(
    () => route.params.uuid,
    async (uuid, previousUuid) => {
        if (uuid === previousUuid) return;

        restoreState(uuid || "draft");
        await loadConversationDetails(uuid);
    }
);

watch(activeSubToolId, (id, previousId) => {
    if (!id || id === previousId) return;

    toolState.value = createDefaultStateForTool(id);
    persistState(route.params.uuid || "draft");
});

watch(
    () => route.params.slug,
    async (slug, previousSlug) => {
        if (slug === previousSlug) return;

        activeConversation.value = null;
        messages.value = [];
        await loadSubtool();
        toolState.value = createDefaultStateForTool(activeSubToolId.value);
        await loadConversations();
    }
);
</script>

<style scoped>
.detector-chat {
    --navy: #0d4d97;
    --blue: #1f87c9;
    --cyan: #35b8dc;
    --ink: #15324b;
    --muted: #687b8e;
    --line: #d8e6f7;
    height: calc(100vh - 70px);
    min-height: calc(100vh - 70px);
    display: grid;
    grid-template-columns: 280px minmax(0, 1fr);
    transition: grid-template-columns 0.25s ease;
    overflow: hidden;
    background: #f5f8fc;
    color: var(--ink);
}

button,
input,
select,
textarea {
    font: inherit;
}

button {
    cursor: pointer;
}

button:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.sidebar {
    position: relative;
    z-index: 20;
    display: flex;
    flex-direction: column;
    min-height: 0;
    padding: 24px 18px;
    border-inline-end: 1px solid var(--line);
    background: #fff;
    pointer-events: auto;
    overflow: hidden;
    transition: width 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
}

.sidebar-brand {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 22px;
}

.sidebar-close-toggle {
    flex-shrink: 0;
    margin-inline-start: auto;
    pointer-events: auto;
}

.sidebar-brand strong,
.sidebar-brand small {
    display: block;
}

.sidebar-brand small {
    margin-top: 2px;
    color: var(--muted);
    font-size: 12px;
}

.brand-icon,
.welcome-icon {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    border-radius: 14px;
    color: #fff;
    background: linear-gradient(145deg, var(--navy), var(--blue));
}

.icon-button,
.conversation-delete {
    border: 1px solid #d3e2ef;
    background: #fff;
}

.new-chat-button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: calc(100% - 20px);
    margin: 16px 10px;
    min-height: 48px;
    padding: 0 16px;
    border: 0;
    border-radius: 14px;
    color: #fff;
    background: #123f6d;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.new-chat-button:hover:not(:disabled) {
    transform: scale(1.02);
    background: #123f6d;
    box-shadow: 0 20px 36px rgba(18, 63, 109, 0.16);
}

.section-label {
    margin: 18px 0 10px;
    color: var(--muted);
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.sidebar-status {
    padding: 18px 10px;
    border: 1px dashed #d8e6f7;
    border-radius: 12px;
    color: var(--muted);
    text-align: center;
}

.desktop-sidebar-open-toggle {
    position: fixed;
    top: 84px;
    inset-inline-start: 12px;
    z-index: 120;
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    border: 1px solid rgba(18, 63, 109, 0.10);
    border-radius: 14px;
    background: #ffffff;
    color: var(--navy);
    box-shadow: 0 14px 30px rgba(18, 63, 109, 0.14);
    pointer-events: auto;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.desktop-sidebar-open-toggle:hover {
    transform: scale(1.04);
    background: rgba(31, 135, 201, 0.08);
    box-shadow: 0 18px 36px rgba(18, 63, 109, 0.18);
}

@media (min-width: 901px) {
    .detector-chat.sidebar-collapsed {
        grid-template-columns: 0 minmax(0, 1fr);
    }

    .detector-chat.sidebar-collapsed .sidebar {
        width: 0;
        padding-inline: 0;
        border-color: transparent;
        box-shadow: none;
        pointer-events: none;
    }

    .detector-chat.sidebar-collapsed .sidebar>* {
        visibility: hidden;
        opacity: 0;
        pointer-events: none;
    }
}

.conversation-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    overflow-y: auto;
}

.conversation-item {
    display: flex;
    align-items: stretch;
    gap: 8px;
}

.conversation-open {
    flex: 1;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    min-width: 0;
    padding: 14px;
    border: 1px solid rgba(18, 63, 109, 0.08);
    border-radius: 16px;
    color: var(--muted);
    background: #fbfdff;
    text-align: start;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background-color 0.2s ease;
}

.conversation-item.active .conversation-open,
.conversation-open:hover {
    transform: scale(1.02);
    border-color: rgba(31, 135, 201, 0.20);
    background: rgba(31, 135, 201, 0.08);
    color: var(--navy);
    box-shadow: 0 18px 32px rgba(18, 63, 109, 0.08);
}

.conversation-open i {
    margin-top: 2px;
    flex-shrink: 0;
    font-size: 15px;
}

.history-item-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
    overflow: hidden;
}

.history-item-title {
    font-size: 13px;
    font-weight: 700;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.conversation-delete {
    display: grid;
    place-items: center;
    width: 42px;
    min-width: 42px;
    border: 1px solid rgba(18, 63, 109, 0.08);
    border-radius: 14px;
    color: var(--navy);
    background: #ffffff;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.conversation-delete:hover {
    transform: scale(1.02);
    background: rgba(31, 135, 201, 0.08);
    box-shadow: 0 14px 28px rgba(18, 63, 109, 0.08);
}

.icon-button {
    position: relative;
    z-index: 3;
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    color: var(--navy);
    pointer-events: auto;
}

.workspace {
    position: relative;
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
    height: 100%;
    overflow: hidden;
}

.workspace-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 24px max(24px, calc((100% - 900px) / 2));
    border-bottom: 1px solid var(--line);
    background: #fff;
}

.workspace-header h1 {
    margin: 4px 0 0;
    font-size: 28px;
    line-height: 1.2;
}

.eyebrow {
    margin: 0;
    color: var(--blue);
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.tool-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.tool-badges span {
    padding: 6px 10px;
    border: 1px solid #d5e4ef;
    border-radius: 999px;
    color: var(--navy);
    background: #f8fbfe;
    font-size: 12px;
}

.messages {
    flex: 1;
    min-height: 0;
    padding: 26px max(24px, calc((100% - 900px) / 2)) 34px;
    overflow-y: auto;
    overflow-x: hidden;
    scroll-behavior: smooth;
}

.center-status,
.welcome-card {
    display: grid;
    place-items: center;
    text-align: center;
}

.center-status {
    gap: 10px;
    min-height: 220px;
    color: var(--muted);
}

.spinner {
    width: 24px;
    height: 24px;
    border: 2px solid #cfe3ef;
    border-top-color: var(--blue);
    border-radius: 999px;
    animation: spin 0.8s linear infinite;
}

.welcome-card {
    gap: 16px;
    max-width: 680px;
    margin: 40px auto 0;
    padding: 30px 24px;
    border: 1px solid #d7e6f2;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 16px 36px rgba(18, 63, 109, 0.08);
}

.welcome-card h2,
.welcome-card p {
    margin: 0;
}

.welcome-card p {
    color: var(--muted);
    line-height: 1.8;
}

.suggestion {
    padding: 11px 14px;
    border: 1px dashed #b9d7ed;
    border-radius: 12px;
    color: var(--navy);
    background: #f7fcff;
}

.message-list {
    display: grid;
    gap: 18px;
}

.message-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.message-row.user {
    flex-direction: row-reverse;
}

.avatar {
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    border-radius: 12px;
    color: #fff;
    background: linear-gradient(145deg, var(--navy), var(--blue));
    box-shadow: 0 8px 18px rgba(18, 63, 109, 0.18);
}

.message-body {
    min-width: 0;
    max-width: min(760px, 100%);
}

.message-content {
    padding: 13px 15px;
    border: 1px solid #dce7f1;
    border-radius: 16px;
    background: #fff;
    line-height: 1.85;
    box-shadow: 0 12px 26px rgba(18, 63, 109, 0.06);
}

.user .message-content {
    color: #fff;
    border-color: transparent;
    background: linear-gradient(145deg, var(--navy), var(--blue));
}

.user-message-stack {
    display: grid;
    gap: 8px;
    justify-items: end;
}

[dir="rtl"] .user-message-stack {
    justify-items: start;
}

.user-uploaded-file-card {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    max-width: min(360px, 76vw);
    padding: 10px 12px;
    border: 1px solid #d8e6f7;
    border-radius: 14px;
    background: #ffffff;
    color: var(--ink);
    box-shadow: 0 10px 24px rgba(18, 63, 109, 0.08);
}

.user-uploaded-file-icon {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    flex: 0 0 40px;
    border-radius: 10px;
    color: #ffffff;
    background: linear-gradient(145deg, var(--navy), var(--blue));
}

.user-uploaded-file-info {
    display: grid;
    gap: 2px;
    min-width: 0;
    text-align: start;
}

.user-uploaded-file-info strong {
    max-width: 260px;
    overflow: hidden;
    color: var(--ink);
    font-size: 13px;
    font-weight: 800;
    line-height: 1.35;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-uploaded-file-info small {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.3;
}

.message-content :deep(p) {
    margin: 0 0 0.9em;
}

.message-content :deep(p:last-child) {
    margin-bottom: 0;
}

.assistant-typing-row {
    animation: typing-fade-in 0.16s ease-out;
}

.assistant-typing-body {
    width: fit-content;
    max-width: min(420px, 75%);
}

.assistant-typing-content {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 13px 15px;
    border: 1px solid #d8e6f7;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 12px 26px rgba(18, 63, 109, 0.06);
}

.assistant-typing-text {
    color: var(--navy);
    font-size: 14px;
    font-weight: 700;
    line-height: 1.5;
}

.typing-dots {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.typing-dot {
    width: 6px;
    height: 6px;
    border-radius: 9999px;
    background: #0d4d97;
    display: inline-block;
    animation: typing-bounce 0.8s infinite ease-in-out;
}

.animation-delay-150 {
    animation-delay: 0.15s;
}

.animation-delay-300 {
    animation-delay: 0.3s;
}

.ai-response-card,
.business-response-card {
    overflow: hidden;
    min-width: min(620px, 68vw);
    border: 1px solid #d6e9f4;
    border-radius: 12px;
    background: #fbfdff;
    box-shadow: 0 10px 28px rgba(18, 63, 109, 0.08);
}

.ai-response-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 11px 14px;
    border-bottom: 1px solid #e2eef5;
    background: #f0f8fc;
}

.ai-response-title {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--navy);
    font-size: 14px;
    font-weight: 800;
}

.copy-card-button,
.result-header button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border: 1px solid #cfe3ef;
    border-radius: 8px;
    color: var(--blue);
    background: #fff;
    font-size: 12px;
}

.ai-response-content {
    padding: 16px;
    color: var(--ink);
    font-size: 14px;
    line-height: 1.9;
}

.ai-response-content :deep(p) {
    margin: 0 0 0.9em;
}

.ai-response-content :deep(p:last-child) {
    margin-bottom: 0;
}

.ai-score-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin: 0 16px 14px;
    padding: 12px 14px;
    border-radius: 10px;
    color: var(--navy);
    background: #eef6ff;
    font-size: 14px;
    font-weight: 700;
}

.ai-meta-section {
    margin: 0 16px 16px;
    padding: 13px 14px;
    border: 1px solid #e2eef5;
    border-radius: 10px;
    background: #fff;
}

.ai-meta-section h4 {
    margin: 0 0 8px;
    color: var(--navy);
    font-size: 14px;
}

.ai-meta-section ul {
    margin: 0;
    padding-inline-start: 20px;
    line-height: 1.75;
}

.ai-download-section {
    margin-top: 16px;
    padding: 0 16px 16px;
}

.ai-download-button,
.ai-download-section a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 42px;
    padding: 0 16px;
    border: 0;
    border-radius: 12px;
    color: #fff;
    background: linear-gradient(145deg, var(--navy), var(--blue));
    font-size: 14px;
    font-weight: 800;
    text-decoration: none;
    box-shadow: 0 14px 28px rgba(18, 63, 109, 0.14);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.ai-download-button span {
    max-width: 260px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ai-download-button:hover,
.ai-download-section a:hover {
    transform: translateY(-1px);
    box-shadow: 0 18px 34px rgba(18, 63, 109, 0.18);
}

.ai-response-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 0 16px 16px;
}

.ai-edit-options-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 40px;
    padding: 0 14px;
    border: 1px solid #cfe3ef;
    border-radius: 12px;
    color: var(--navy);
    background: #ffffff;
    font-size: 13px;
    font-weight: 800;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.ai-edit-options-button:hover:not(:disabled) {
    transform: translateY(-1px);
    background: rgba(31, 135, 201, 0.08);
    box-shadow: 0 12px 24px rgba(18, 63, 109, 0.10);
}

.business-result-list {
    display: grid;
    gap: 12px;
    padding: 14px;
}

.business-result-card {
    overflow: hidden;
    border: 1px solid #d6e9f4;
    border-radius: 10px;
    background: #fff;
}

.result-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 13px;
    border-bottom: 1px solid #e2eef5;
    color: var(--navy);
    background: #f8fbfe;
}

.result-title-stack {
    display: grid;
    gap: 2px;
}

.result-title-stack strong {
    font-size: 14px;
}

.result-title-stack span {
    color: var(--muted);
    font-size: 12px;
}

.business-result-body {
    display: grid;
    gap: 12px;
    padding: 16px;
}

.business-name {
    margin: 0;
    color: var(--navy);
    font-size: 22px;
    font-weight: 800;
    line-height: 1.4;
}

.business-slogan {
    margin: 0;
    color: var(--ink);
    font-size: 14px;
    line-height: 1.8;
}

.business-domain-section {
    display: grid;
    gap: 8px;
}

.business-domain-label {
    color: var(--muted);
    font-size: 12px;
    font-weight: 700;
}

.business-domain-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.domain-chip {
    padding: 6px 10px;
    border: 1px solid #d3e6f3;
    border-radius: 999px;
    color: var(--blue);
    background: #eef8fd;
    font-size: 12px;
}

.clean-error-card {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    max-width: min(620px, 75vw);
    padding: 13px 15px;
    border: 1px solid #f1b9b9;
    border-radius: 12px;
    color: #8b2525;
    background: #fff4f4;
    font-size: 14px;
    line-height: 1.7;
}

.composer {
    position: sticky;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 40;
    flex-shrink: 0;
    padding: 12px max(24px, calc((100% - 900px) / 2)) 14px;
    border-top: 1px solid var(--line);
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(14px);
    box-shadow: 0 -14px 35px rgba(18, 63, 109, 0.08);
}

.options-panel {
    max-width: 920px;
    margin: 0 auto 12px;
    border: 1px solid rgba(18, 63, 109, 0.10);
    border-radius: 16px;
    background: #ffffff;
    box-shadow: 0 12px 28px rgba(18, 63, 109, 0.05);
    overflow: hidden;
}

.options-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    color: var(--navy);
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    list-style: none;
}

.options-panel-header::-webkit-details-marker {
    display: none;
}

.options-panel-header:focus-visible {
    outline: 0;
    box-shadow: inset 0 0 0 3px rgba(31, 135, 201, 0.12);
}

.options-panel-title,
.options-panel-meta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.options-panel-title {
    flex: 0 0 auto;
}

.options-panel-meta {
    justify-content: flex-end;
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
}

.options-summary {
    max-width: min(52vw, 420px);
    overflow: hidden;
    color: var(--muted);
    font-size: 12px;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.options-chevron {
    flex: 0 0 auto;
    transition: transform 0.18s ease;
}

.options-panel[open] .options-chevron {
    transform: rotate(180deg);
}

.options-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 0 14px 14px;
}

.options-basic-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.options-basic-grid>label {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 6px;
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
}

.options-basic-grid input,
.options-basic-grid select {
    width: 100%;
    min-width: 0;
    min-height: 40px;
    padding: 9px 10px;
    border: 1px solid rgba(18, 63, 109, 0.14);
    border-radius: 10px;
    color: var(--navy);
    background: #fbfdff;
    font: inherit;
    font-size: 12px;
    outline: none;
}

.options-basic-grid input:focus,
.options-basic-grid select:focus {
    border-color: rgba(31, 135, 201, 0.55);
    box-shadow: 0 0 0 3px rgba(31, 135, 201, 0.1);
}

.wide {
    grid-column: 1 / -1;
}

[dir="rtl"] .options-basic-grid input,
[dir="rtl"] .options-basic-grid select {
    text-align: right;
}

[dir="ltr"] .options-basic-grid input,
[dir="ltr"] .options-basic-grid select {
    text-align: left;
}

.option-card {
    min-width: 0;
    margin: 0;
    padding: 12px;
    border: 1px solid rgba(18, 63, 109, 0.10);
    border-radius: 12px;
    background: #fbfdff;
}

.checkbox-field {
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
}

.checkbox-field legend {
    padding: 0 4px;
    color: var(--muted);
    font-size: 11px;
    font-weight: 800;
}

.checkbox-options-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.checkbox-option {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    max-width: 100%;
    padding: 7px 10px;
    border: 1px solid rgba(18, 63, 109, 0.10);
    border-radius: 999px;
    color: var(--muted);
    background: #ffffff;
    font-size: 11px;
    line-height: 1.4;
    cursor: pointer;
}

.checkbox-option input {
    width: 14px;
    height: 14px;
    flex: 0 0 14px;
    margin: 0;
    padding: 0;
    accent-color: var(--blue);
}

.checkbox-option span {
    min-width: 0;
    overflow-wrap: anywhere;
}

.resume-file-panel {
    display: grid;
    gap: 10px;
    margin-top: 8px;
}

.resume-file-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: fit-content;
    max-width: 100%;
    min-height: 40px;
    padding: 9px 12px;
    border: 1px solid rgba(18, 63, 109, 0.14);
    border-radius: 10px;
    color: var(--navy);
    background: #ffffff;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
}

.resume-file-button input {
    display: none;
}

.resume-file-selected {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    color: var(--navy);
    font-size: 12px;
}

.resume-file-selected button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border: 1px solid #f1b9b9;
    border-radius: 8px;
    color: #8b2525;
    background: #fff4f4;
    font-size: 11px;
    font-weight: 800;
}

.options-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
    padding-top: 2px;
}

.options-actions button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 40px;
    padding: 10px 14px;
    border-radius: 12px;
    font: inherit;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    transition: background-color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
}

.options-submit-button {
    border: 0;
    color: #fff;
    background: var(--blue);
    box-shadow: 0 12px 22px rgba(31, 135, 201, 0.18);
}

.options-reset-button {
    border: 1px solid rgba(18, 63, 109, 0.14);
    color: var(--navy);
    background: #fff;
}

.options-submit-button:hover:not(:disabled) {
    background: #166da4;
}

.options-reset-button:hover:not(:disabled) {
    border-color: rgba(31, 135, 201, 0.32);
    box-shadow: 0 10px 20px rgba(18, 63, 109, 0.06);
}

.options-actions button:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.input-box {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    padding: 9px;
    border: 1px solid #cadce7;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 8px 28px rgba(18, 63, 109, 0.08);
}

.input-box:focus-within {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(31, 135, 201, 0.1);
}

.input-box textarea {
    min-height: 42px;
    max-height: 180px;
    flex: 1;
    min-width: 0;
    resize: none;
    padding: 10px;
    border: 0;
    color: var(--ink);
    background: transparent;
    outline: 0;
}

.composer-file-input {
    display: none;
}

.composer-file-button {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 46px;
    height: 46px;
    border: 1px solid #d3e2ef;
    border-radius: 14px;
    color: var(--navy);
    background: #ffffff;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
}

.composer-file-button:hover:not(:disabled) {
    transform: scale(1.03);
    background: rgba(31, 135, 201, 0.08);
    box-shadow: 0 14px 28px rgba(18, 63, 109, 0.08);
}

.composer-file-button.has-file {
    color: #ffffff;
    border-color: var(--blue);
    background: linear-gradient(145deg, var(--navy), var(--blue));
}

.send-button {
    display: grid;
    place-items: center;
    width: 43px;
    height: 43px;
    flex: 0 0 43px;
    border: 0;
    border-radius: 13px;
    color: #fff;
    background: linear-gradient(145deg, var(--navy), var(--blue));
}

.composer-file-preview {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 10px;
    padding: 10px 12px;
    border: 1px solid #d8e6f7;
    border-radius: 14px;
    background: #f8fbfe;
    color: var(--ink);
    font-size: 13px;
}

.composer-file-preview span {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.composer-file-preview button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    border: 0;
    background: transparent;
    color: var(--navy);
    font-weight: 700;
}

.composer-hint {
    margin: 7px 4px 0;
    color: var(--muted);
    font-size: 11px;
    text-align: center;
}

.error-banner {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 9px;
    padding: 9px 12px;
    border: 1px solid #f1b9b9;
    border-radius: 8px;
    color: #8b2525;
    background: #fff4f4;
}

.mobile-only,
.sidebar-overlay {
    display: none;
}

@keyframes typing-bounce {

    0%,
    80%,
    100% {
        transform: translateY(0);
        opacity: 0.45;
    }

    40% {
        transform: translateY(-6px);
        opacity: 1;
    }
}

@keyframes typing-fade-in {
    from {
        opacity: 0;
        transform: translateY(4px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 900px) {
    .detector-chat {
        grid-template-columns: 1fr;
    }

    .sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        width: min(300px, 84vw);
        z-index: 60;
        transform: translateX(-105%);
        transition: transform 0.22s ease;
        box-shadow: 0 12px 40px rgba(18, 63, 109, 0.15);
    }

    [dir="rtl"] .sidebar {
        inset: 0 0 0 auto;
        transform: translateX(105%);
    }

    .sidebar.sidebar-open {
        transform: translateX(0);
    }

    .mobile-only,
    .sidebar-overlay {
        display: block;
    }

    .sidebar-overlay {
        position: fixed;
        inset: 0;
        z-index: 50;
        border: 0;
        background: rgba(16, 35, 53, 0.35);
        pointer-events: auto;
    }

    .mobile-sidebar-toggle {
        position: fixed;
        top: 12px;
        inset-inline-start: 12px;
        z-index: 45;
        width: 42px;
        height: 42px;
        display: grid;
        place-items: center;
        border: 1px solid rgba(18, 63, 109, 0.10);
        border-radius: 14px;
        color: var(--navy);
        background: #ffffff;
        box-shadow: 0 14px 30px rgba(18, 63, 109, 0.14);
        pointer-events: auto;
    }

    .desktop-sidebar-open-toggle {
        display: none;
    }

    .workspace-header,
    .messages,
    .composer {
        padding-inline: 18px;
    }

    .workspace-header {
        align-items: flex-start;
    }

    .workspace-header h1 {
        font-size: 24px;
    }

    .tool-badges {
        justify-content: flex-end;
    }

    .wide {
        grid-column: 1 / -1;
    }

    .ai-response-card,
    .business-response-card {
        min-width: 0;
    }
}

@media (max-width: 640px) {
    .options-panel-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .options-panel-meta {
        width: 100%;
        justify-content: space-between;
    }

    .options-summary {
        max-width: calc(100% - 28px);
    }

    .options-basic-grid {
        grid-template-columns: 1fr;
    }

    .wide {
        grid-column: 1 / -1;
    }

    .options-actions button {
        flex: 1 1 100%;
    }

    .message-row,
    .message-row.user {
        flex-direction: column;
    }

    .avatar {
        width: 34px;
        height: 34px;
        flex-basis: 34px;
    }

    .message-body {
        max-width: 100%;
    }

    .business-name {
        font-size: 19px;
    }
}
</style>
