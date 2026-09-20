<template>
    <main class="free-ai-chat" :dir="isRtl ? 'rtl' : 'ltr'">
        <button
            v-if="sidebarOpen && isMobile"
            type="button"
            class="sidebar-overlay"
            :aria-label="t('freeAiModels.closeSidebar')"
            @click="sidebarOpen = false"
        ></button>

        <aside class="conversation-sidebar" :class="{ open: sidebarOpen, collapsed: desktopSidebarCollapsed }">
            <div class="sidebar-heading">
                <div class="tool-identity">
                    <span class="tool-avatar">
                        <img v-if="mainTool.image_url" :src="mainTool.image_url" :alt="mainTool.name" />
                        <i v-else class="bi bi-stars"></i>
                    </span>
                    <span class="tool-copy">
                        <!-- <small>{{ t("freeAiModels.mainTool") }}</small> -->
                        <strong>{{ mainTool.name || readableSlug }}</strong>
                    </span>
                </div>
                <button type="button" class="icon-button sidebar-close" :aria-label="t('freeAiModels.closeSidebar')" @click="closeSidebar">
                    <i class="bi" :class="isMobile ? 'bi-x-lg' : collapseIcon"></i>
                </button>
            </div>

            <label v-if="isGeneralMediaRoute" class="media-operation-picker">
                <span>{{ t("freeAiModels.mediaToolType") }}</span>
                <select v-model="mediaOperation" :disabled="creatingConversation || sendingMessage" @change="changeMediaOperation">
                    <option v-for="operation in MEDIA_OPERATIONS" :key="operation" :value="operation">
                        {{ mediaOperationLabel(operation) }}
                    </option>
                </select>
            </label>

            <button type="button" class="new-conversation-button" :disabled="creatingConversation" @click="newConversation">
                <span v-if="creatingConversation" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                <i v-else class="bi bi-plus-lg"></i>
                {{ creatingConversation ? t("freeAiModels.creatingConversation") : t("freeAiModels.newConversation") }}
            </button>

            <div class="history-heading">
                <span>{{ t("freeAiModels.recentConversations") }}</span>
                <span>{{ conversations.length }}</span>
            </div>

            <div class="history-list" :aria-label="t('freeAiModels.conversationList')">
                <div v-if="loadingConversations" class="history-state">
                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    {{ t("freeAiModels.loadingConversations") }}
                </div>
                <div v-else-if="!conversations.length" class="history-state">
                    <i class="bi bi-chat-square-text"></i>
                    {{ t("freeAiModels.noConversations") }}
                </div>
                <article
                    v-for="item in conversations"
                    v-else
                    :key="item.uuid"
                    class="history-item"
                    :class="{ active: item.uuid === activeUuid }"
                >
                    <button type="button" class="history-open" @click="openConversation(item)">
                        <i class="bi bi-chat-left-text"></i>
                        <span>
                            <strong>{{ conversationTitle(item) }}</strong>
                            <small>{{ item.selected_model?.name || t("freeAiModels.defaultModel") }}</small>
                        </span>
                    </button>
                    <button
                        type="button"
                        class="history-delete"
                        :disabled="deletingUuid === item.uuid"
                        :aria-label="t('freeAiModels.deleteConversation')"
                        @click="deleteConversation(item)"
                    >
                        <span v-if="deletingUuid === item.uuid" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                        <i v-else class="bi bi-trash3"></i>
                    </button>
                </article>
            </div>
        </aside>

        <section class="chat-workspace">
            <header class="workspace-header">
                <div class="header-leading">
                    <button
                        v-if="isMobile || desktopSidebarCollapsed"
                        type="button"
                        class="icon-button"
                        :aria-label="t('freeAiModels.openSidebar')"
                        @click="openSidebar"
                    >
                        <i class="bi bi-list"></i>
                    </button>
                    <span class="header-avatar">
                        <img v-if="mainTool.image_url" :src="mainTool.image_url" :alt="mainTool.name" />
                        <i v-else class="bi bi-stars"></i>
                    </span>
                    <span class="header-copy">
                        <strong>{{ mainTool.name || readableSlug }}</strong>
                        <small>
                            <span class="status-dot"></span>
                            {{ selectedModel?.name || t("freeAiModels.defaultModel") }}
                            <span v-if="modelSaving"> · {{ t("freeAiModels.modelSaving") }}</span>
                        </small>
                    </span>
                </div>
                <button type="button" class="back-button" @click="backToTool">
                    <i class="bi bi-arrow-left"></i>
                    <span>{{ t("freeAiModels.backToModel") }}</span>
                </button>
            </header>

            <div v-if="loadingConversation" class="conversation-state" aria-live="polite">
                <span class="spinner-border" aria-hidden="true"></span>
                <p>{{ t("freeAiModels.loadingConversation") }}</p>
            </div>
            <div v-else-if="loadError" class="conversation-state error-state">
                <i class="bi bi-exclamation-triangle"></i>
                <h1>{{ t("freeAiModels.conversationUnavailable") }}</h1>
                <p>{{ t("freeAiModels.conversationUnavailableDescription") }}</p>
                <button type="button" class="retry-button" @click="loadConversation">
                    {{ t("freeAiModels.tryAgain") }}
                </button>
            </div>
            <section v-else ref="messagesContainer" class="messages" :aria-label="t('freeAiModels.conversation')">
                <div v-if="!messages.length" class="empty-conversation">
                    <span class="empty-icon"><i class="bi bi-chat-dots"></i></span>
                    <h1>{{ t("freeAiModels.emptyChatTitle", { name: mainTool.name || readableSlug }) }}</h1>
                    <p>{{ canChat ? t(isSpeechToText ? "freeAiModels.transcriptionStartHint" : "freeAiModels.chatStartHint") : t("freeAiModels.emptyChatDescription") }}</p>
                    <span v-if="!canChat" class="pending-pill"><i class="bi bi-clock-history"></i>{{ t("freeAiModels.integrationPending") }}</span>
                </div>
                <div v-else class="chat-message-list">
                    <button v-if="nextMessagesCursor" type="button" class="older-messages-button" :disabled="loadingOlderMessages" @click="loadOlderMessages">
                        {{ loadingOlderMessages ? t("freeAiModels.loadingConversations") : t("freeAiModels.loadOlderMessages") }}
                    </button>
                    <div v-for="item in messages" :key="item.id || item.request_id + item.role" class="chat-message" :class="[item.role, { 'message-error': item.type === 'error' }]">
                        <span class="chat-message-role">{{ item.role === "user" ? t("freeAiModels.you") : t("freeAiModels.assistant") }}</span>
                        <div v-if="item.type === 'audio'" class="audio-attachment">
                            <div class="audio-attachment-heading">
                                <i class="bi bi-file-earmark-music" aria-hidden="true"></i>
                                <span>{{ item.filename }}</span>
                                <a :href="item.url || item.download_url" :download="item.filename" :aria-label="t('freeAiModels.downloadAudio')" :title="t('freeAiModels.downloadAudio')" @click.prevent="downloadAudioAttachment(item)">
                                    <i class="bi bi-download" aria-hidden="true"></i>
                                </a>
                            </div>
                            <audio controls preload="metadata" :src="item.url || undefined" :aria-label="item.filename" @click="prepareAudioForPlayback(item, $event)" @play="prepareAudioForPlayback(item, $event)"></audio>
                        </div>
                        <div v-else-if="isGeneralMedia && item.role === 'assistant'" class="media-result">
                            <p v-if="item.content">{{ item.content }}</p>
                            <div v-if="mediaFiles(item).length" class="media-result-grid">
                                <article v-for="(file, index) in mediaFiles(item)" :key="file.file_id || file.filename || index"
                                    class="media-result-file">
                                    <img v-if="isImageFile(file) && mediaFileUrl(file)" :src="mediaFileUrl(file)" :alt="file.filename || t('freeAiModels.mediaResult')" />
                                    <video v-else-if="isVideoFile(file) && mediaFileUrl(file)" :src="mediaFileUrl(file)" controls preload="metadata"></video>
                                    <span v-else><i class="bi bi-file-earmark-arrow-down"></i>{{ file.filename || t("freeAiModels.downloadMedia") }}</span>
                                    <a v-if="mediaFileUrl(file)" class="media-result-download" :href="mediaFileUrl(file)"
                                        :download="file.filename || true" :aria-label="t('freeAiModels.downloadMedia')" :title="t('freeAiModels.downloadMedia')">
                                        <i class="bi bi-download" aria-hidden="true"></i>
                                    </a>
                                </article>
                            </div>
                        </div>
                        <div v-else-if="isGeneralCode && item.role === 'assistant'" class="code-markdown" v-html="renderCodeMarkdown(item.content)"></div>
                        <p v-else>{{ item.content }}</p>
                    </div>
                    <div v-if="sendingMessage" class="chat-message assistant pending" aria-live="polite">
                        <span class="chat-message-role">{{ t("freeAiModels.assistant") }}</span>
                        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    </div>
                </div>
            </section>

            <footer class="composer-area">
                <div class="composer-shell">
                    <button v-if="isGeneralCode" ref="codeOptionsButton" type="button" class="code-options-trigger"
                        :disabled="sendingMessage" @click="openCodeOptions">
                        <i class="bi bi-sliders"></i>
                        {{ programmingLanguage ? programmingLanguage : t("freeAiModels.codeChooseOptions") }}
                    </button>
                    <button v-if="isGeneralTranslation" ref="translationOptionsButton" type="button" class="code-options-trigger"
                        :disabled="sendingMessage" @click="openTranslationOptions">
                        <i class="bi bi-translate"></i>
                        {{ t("freeAiModels.translationOptionsTitle") }}: {{ sourceLanguage }} → {{ targetLanguage }}
                    </button>
                    <button v-if="isGeneralMedia" type="button" class="code-options-trigger"
                        :disabled="!selectedModel || sendingMessage" @click="mediaSettingsOpen = true">
                        <i class="bi bi-sliders"></i>
                        {{ t("freeAiModels.mediaSettings") }}
                    </button>
                    <div class="composer-box">
                        <FreeAiModelSelector
                            :models="catalogModels"
                            :selected-model="selectedModel"
                            :loading="catalogLoading"
                            :error="catalogError"
                            :disabled="!conversation?.uuid || modelSaving || sendingMessage"
                            @select="selectExecutionModel"
                            @retry="loadCatalog(true)"
                        />
                        <div v-if="isGeneralMedia" class="media-compose">
                            <input ref="mediaFileInput" class="visually-hidden" type="file" accept="image/*"
                                :disabled="sendingMessage" @change="handleMediaFileInput" />
                            <button v-if="mediaRequiresFile" type="button" class="media-file-button"
                                :class="{ selected: selectedMediaFile }" :disabled="sendingMessage" @click="chooseMediaFile">
                                <i class="bi" :class="selectedMediaFile ? 'bi-check-circle-fill' : 'bi-image'"></i>
                                <span>{{ selectedMediaFile?.name || t("freeAiModels.uploadMedia") }}</span>
                                <i v-if="selectedMediaFile" class="bi bi-x-lg" @click.stop="clearSelectedMedia"></i>
                            </button>
                            <textarea v-model="messageDraft" rows="1" :placeholder="t('freeAiModels.mediaPrompt')"
                                :aria-label="t('freeAiModels.mediaPrompt')" :disabled="sendingMessage"
                                @keydown.enter.exact.prevent="sendMessage"></textarea>
                            <button type="button" class="send-button" :disabled="!canSend"
                                :aria-label="t('freeAiModels.runMediaTool')" @click="sendMessage">
                                <span v-if="sendingMessage" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                <i v-else class="bi bi-stars"></i>
                            </button>
                        </div>
                        <div v-else-if="isSpeechToText" class="speech-upload-compose">
                            <input
                                ref="audioFileInput"
                                class="visually-hidden"
                                type="file"
                                accept=".m4a,.mp3,.wav,.webm,audio/mp4,audio/mpeg,audio/wav,audio/webm"
                                :disabled="!canChat || sendingMessage"
                                @change="handleAudioInput"
                            />
                            <button
                                v-if="!selectedAudioFile"
                                type="button"
                                class="audio-dropzone"
                                :class="{ dragging: audioDragging }"
                                :disabled="!canChat || sendingMessage"
                                @click="chooseAudioFile"
                                @dragenter.prevent="audioDragging = true"
                                @dragover.prevent="audioDragging = true"
                                @dragleave.prevent="audioDragging = false"
                                @drop.prevent="handleAudioDrop"
                            >
                                <i class="bi bi-cloud-arrow-up"></i>
                                <span>{{ t("freeAiModels.uploadAudio") }}</span>
                                <small>{{ t("freeAiModels.audioDropHint") }}</small>
                            </button>
                            <div
                                v-else
                                class="selected-audio"
                                :class="{ dragging: audioDragging }"
                                @dragenter.prevent="audioDragging = true"
                                @dragover.prevent="audioDragging = true"
                                @dragleave.prevent="audioDragging = false"
                                @drop.prevent="handleAudioDrop"
                            >
                                <div class="selected-audio-info">
                                    <i class="bi bi-file-earmark-music"></i>
                                    <span>
                                        <strong>{{ selectedAudioFile.name }}</strong>
                                        <small>{{ formatFileSize(selectedAudioFile.size) }}</small>
                                    </span>
                                    <button type="button" :aria-label="t('freeAiModels.removeAudio')" :disabled="sendingMessage" @click="clearSelectedAudio">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                                <audio controls preload="metadata" :src="selectedAudioUrl"></audio>
                                <button type="button" class="replace-audio-button" :disabled="sendingMessage" @click="chooseAudioFile">
                                    {{ t("freeAiModels.replaceAudio") }}
                                </button>
                            </div>
                            <button type="button" class="send-button" :disabled="!canSend" :aria-label="t('freeAiModels.transcribeAudio')" @click="sendMessage">
                                <span v-if="sendingMessage" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                <i v-else class="bi bi-send-fill"></i>
                            </button>
                        </div>
                        <div v-else class="message-compose-row">
                            <textarea
                                v-model="messageDraft"
                                rows="1"
                                :placeholder="canChat ? t('freeAiModels.writeMessage') : t('freeAiModels.inputPlaceholder')"
                                :aria-label="canChat ? t('freeAiModels.writeMessage') : t('freeAiModels.inputPlaceholder')"
                                :disabled="!canChat || !conversation?.uuid || loadingConversation || sendingMessage"
                                @keydown.enter.exact.prevent="sendMessage"
                            ></textarea>
                            <button type="button" class="send-button" :disabled="!canSend" :aria-label="t('freeAiModels.send')" @click="sendMessage">
                                <span v-if="sendingMessage" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                <i v-else class="bi bi-send-fill"></i>
                            </button>
                        </div>
                    </div>
                    <p v-if="sendError" class="composer-hint chat-error" role="alert">
                        {{ sendError }} <span v-if="cooldownSeconds">{{ t("freeAiModels.cooldownRemaining", { seconds: cooldownSeconds }) }}</span>
                    </p>
                    <p v-else-if="cooldownSeconds" class="composer-hint" role="status">
                        {{ t("freeAiModels.cooldownRemaining", { seconds: cooldownSeconds }) }}
                    </p>
                    <p v-else-if="isGeneralCode && !programmingLanguage" class="composer-hint"><i class="bi bi-info-circle"></i>{{ t("freeAiModels.codeOptionsRequired") }}</p>
                    <p v-else-if="canChat" class="composer-hint"><i class="bi bi-wallet2"></i>{{ t("freeAiModels.walletBalance") }}: {{ walletBalance ?? "—" }}</p>
                    <p v-else class="composer-hint"><i class="bi bi-info-circle"></i>{{ t("freeAiModels.composerUnavailableHint") }}</p>
                </div>
            </footer>
        </section>

        <div v-if="codeOptionsOpen && isGeneralCode" class="code-options-overlay" @click.self="closeCodeOptions">
            <section class="code-options-dialog" role="dialog" aria-modal="true" aria-labelledby="code-options-title" @keydown.esc.stop.prevent="closeCodeOptions">
                <div class="code-options-heading">
                    <h2 id="code-options-title">{{ t("freeAiModels.codeOptionsTitle") }}</h2>
                    <button type="button" class="icon-button" :aria-label="t('freeAiModels.closeSidebar')" @click="closeCodeOptions"><i class="bi bi-x-lg"></i></button>
                </div>
                <p class="code-options-description">{{ t("freeAiModels.codeOptionsDescription") }}</p>
                <div class="code-options-grid">
                    <div class="code-options-column">
                        <label for="code-language-search">{{ t("freeAiModels.codeLanguage") }}</label>
                        <input id="code-language-search" ref="codeLanguageSearchInput" v-model="codeLanguageSearch" type="search" :placeholder="t('freeAiModels.codeSearch')" />
                        <div class="code-options-list">
                            <button v-for="language in filteredCodeLanguages" :key="language" type="button"
                                :class="{ selected: selectedCodeLanguage === language }" :aria-pressed="selectedCodeLanguage === language"
                                @click="selectedCodeLanguage = selectedCodeLanguage === language ? '' : language">{{ language }}</button>
                            <span v-if="!filteredCodeLanguages.length" class="code-options-empty">{{ t("freeAiModels.codeNoOptions") }}</span>
                        </div>
                    </div>
                    <div class="code-options-column">
                        <label for="code-framework-search">{{ t("freeAiModels.codeFramework") }}</label>
                        <input id="code-framework-search" v-model="codeFrameworkSearch" type="search" :placeholder="t('freeAiModels.codeSearch')" />
                        <div class="code-options-list">
                            <button v-for="framework in filteredCodeFrameworks" :key="framework" type="button"
                                :class="{ selected: selectedCodeFramework === framework }" :aria-pressed="selectedCodeFramework === framework"
                                @click="selectedCodeFramework = selectedCodeFramework === framework ? '' : framework">{{ framework }}</button>
                            <span v-if="!filteredCodeFrameworks.length" class="code-options-empty">{{ t("freeAiModels.codeNoOptions") }}</span>
                        </div>
                    </div>
                </div>
                <div class="code-options-footer">
                    <span>{{ programmingLanguage || t("freeAiModels.codeOptionsRequired") }}</span>
                    <button type="button" class="code-options-done" :disabled="!programmingLanguage" @click="closeCodeOptions">{{ t("freeAiModels.codeDone") }}</button>
                </div>
            </section>
        </div>
        <div v-if="translationOptionsOpen && isGeneralTranslation" class="code-options-overlay" @click.self="closeTranslationOptions">
            <section class="code-options-dialog" role="dialog" aria-modal="true" aria-labelledby="translation-options-title" @keydown.esc.stop.prevent="closeTranslationOptions">
                <div class="code-options-heading">
                    <h2 id="translation-options-title">{{ t("freeAiModels.translationOptionsTitle") }}</h2>
                    <button type="button" class="icon-button" :aria-label="t('freeAiModels.closeSidebar')" @click="closeTranslationOptions"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="code-options-grid">
                    <div class="code-options-column">
                        <label for="translation-source-search">{{ t("freeAiModels.sourceLanguage") }}</label>
                        <input id="translation-source-search" ref="translationSourceSearchInput" v-model="sourceLanguageSearch" type="search" :placeholder="t('freeAiModels.codeSearch')" />
                        <div class="code-options-list">
                            <button v-for="language in filteredSourceLanguages" :key="language" type="button"
                                :class="{ selected: sourceLanguage === language }" :aria-pressed="sourceLanguage === language"
                                @click="sourceLanguage = language">{{ language }}</button>
                            <span v-if="!filteredSourceLanguages.length" class="code-options-empty">{{ t("freeAiModels.codeNoOptions") }}</span>
                        </div>
                    </div>
                    <div class="code-options-column">
                        <label for="translation-target-search">{{ t("freeAiModels.targetLanguage") }}</label>
                        <input id="translation-target-search" v-model="targetLanguageSearch" type="search" :placeholder="t('freeAiModels.codeSearch')" />
                        <div class="code-options-list">
                            <button v-for="language in filteredTargetLanguages" :key="language" type="button"
                                :class="{ selected: targetLanguage === language }" :aria-pressed="targetLanguage === language"
                                @click="targetLanguage = language">{{ language }}</button>
                            <span v-if="!filteredTargetLanguages.length" class="code-options-empty">{{ t("freeAiModels.codeNoOptions") }}</span>
                        </div>
                    </div>
                </div>
                <div class="code-options-footer">
                    <span>{{ sourceLanguage }} → {{ targetLanguage }}</span>
                    <button type="button" class="code-options-done" @click="closeTranslationOptions">{{ t("freeAiModels.codeDone") }}</button>
                </div>
            </section>
        </div>
        <div v-if="mediaSettingsOpen && isGeneralMedia" class="code-options-overlay" @click.self="mediaSettingsOpen = false">
            <section class="code-options-dialog media-settings-dialog" role="dialog" aria-modal="true"
                aria-labelledby="media-settings-title" @keydown.esc.stop.prevent="mediaSettingsOpen = false">
                <div class="code-options-heading">
                    <h2 id="media-settings-title">{{ t("freeAiModels.mediaSettings") }}</h2>
                    <button type="button" class="icon-button" :aria-label="t('freeAiModels.closeSidebar')"
                        @click="mediaSettingsOpen = false"><i class="bi bi-x-lg"></i></button>
                </div>
                <p v-if="!mediaParameterFields.length" class="code-options-description">{{ t("freeAiModels.noMediaSettings") }}</p>
                <div v-else class="media-settings-grid">
                    <label v-for="field in mediaParameterFields" :key="field.name" class="media-setting-field">
                        <span>{{ parameterLabel(field.name, field.schema) }}<small v-if="field.schema.required">*</small></span>
                        <select v-if="parameterType(field.schema) === 'enum'" v-model="mediaParameters[field.name]">
                            <option v-if="field.schema.nullable" :value="null">{{ t("freeAiModels.notSet") }}</option>
                            <option v-for="value in parameterValues(field.schema)" :key="String(value)" :value="value">{{ value }}</option>
                        </select>
                        <input v-else-if="['integer', 'number', 'float'].includes(parameterType(field.schema))"
                            v-model.number="mediaParameters[field.name]" type="number"
                            :step="parameterType(field.schema) === 'integer' ? 1 : 'any'"
                            :min="field.schema.minimum ?? field.schema.min" :max="field.schema.maximum ?? field.schema.max" />
                        <input v-else-if="parameterType(field.schema) === 'boolean'" v-model="mediaParameters[field.name]" type="checkbox" />
                        <input v-else v-model="mediaParameters[field.name]" type="text" />
                    </label>
                </div>
                <div class="code-options-footer">
                    <span>{{ selectedModel?.name }}</span>
                    <button type="button" class="code-options-done" @click="mediaSettingsOpen = false">{{ t("freeAiModels.codeDone") }}</button>
                </div>
            </section>
        </div>
    </main>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { v4 as uuidv4 } from "uuid";
import { useI18n } from "vue-i18n";
import { useRoute, useRouter } from "vue-router";
import useSeoMeta from "@/composables/useSeoMeta";
import homeService from "@/services/home/homeService";
import freeAiModelService from "@/services/freeAiModels/freeAiModelService";
import freeAiMediaService from "@/services/freeAiModels/freeAiMediaService";
import { generateSpeech, downloadAudioFile } from "@/services/audioService";
import { clearAudioChatHistory, readAudioChatHistory, saveAudioChatHistory } from "@/services/audioChatStorage";
import { MEDIA_MESSAGE_COOLDOWN_MS, MESSAGE_COOLDOWN_MS, RATE_LIMIT_FALLBACK_MS, messageRequestSignature, recentChatRequests, rememberSentRequest, retryAfterMilliseconds, wasRecentlySent } from "@/services/freeAiModels/freeAiChatRateControl";
import { getFreeAiCatalogSource } from "@/services/freeAiModels/freeAiCatalogSources";
import modelCatalogService from "@/services/modelCatalog/modelCatalogService";
import { readSelectedCatalogModel, saveSelectedCatalogModel } from "@/services/modelCatalog/selectedModelStorage";
import FreeAiModelSelector from "@/components/free-ai-models/FreeAiModelSelector.vue";
import { PROGRAMMING_LANGUAGES, PROGRAMMING_FRAMEWORKS, programmingLanguageValue } from "@/services/freeAiModels/freeAiCodeOptions";
import MarkdownIt from "markdown-it";
import DOMPurify from "dompurify";

const MOBILE_BREAKPOINT = 900;
const MAX_AUDIO_FILE_SIZE = 25 * 1024 * 1024;
const SUPPORTED_AUDIO_FORMATS = new Set(["m4a", "mp3", "wav", "webm"]);
const MEDIA_OPERATIONS = Object.freeze([
    "image_generation", "background_remove", "image_upscale", "image_edit", "remove_element",
    "restore", "outpaint", "resize", "video_generation",
]);
const MEDIA_FILE_OPERATIONS = new Set([
    "background_remove", "image_upscale", "image_edit", "remove_element", "restore", "outpaint", "resize",
]);
const mediaOperationRequiresFile = (operation) => MEDIA_FILE_OPERATIONS.has(operation);
const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();

const conversation = ref(null);
const conversations = ref([]);
const loadingConversation = ref(true);
const loadingConversations = ref(true);
const loadError = ref(false);
const creatingConversation = ref(false);
const deletingUuid = ref(null);
const catalogModels = ref([]);
const catalogLoading = ref(false);
const catalogError = ref(false);
const selectedModel = ref(null);
const modelSaving = ref(false);
const messages = ref([]);
const audioObjectUrls = new Set();
const pendingAudioDownloads = new WeakMap();
const mediaObjectUrls = new Set();
const pendingMediaDownloads = new WeakMap();
let mediaHydrationGeneration = 0;
const messageDraft = ref("");
const audioFileInput = ref(null);
const selectedAudioFile = ref(null);
const selectedAudioUrl = ref("");
const audioDragging = ref(false);
const initialMediaOperation = String(route.query?.operation || "");
const mediaOperation = ref(MEDIA_OPERATIONS.includes(initialMediaOperation) ? initialMediaOperation : "image_generation");
const mediaSettingsOpen = ref(false);
const mediaParameters = ref({});
const mediaFileInput = ref(null);
const selectedMediaFile = ref(null);
const codeOptionsOpen = ref(false);
const translationOptionsOpen = ref(false);
const translationOptionsButton = ref(null);
const translationSourceSearchInput = ref(null);
const sourceLanguageSearch = ref("");
const targetLanguageSearch = ref("");
const sourceLanguage = ref("Auto");
const targetLanguage = ref("English");
const TRANSLATION_LANGUAGES = Object.freeze([
    "Auto", "Arabic", "English", "French", "Spanish", "German", "Italian",
    "Portuguese", "Turkish", "Chinese", "Japanese", "Korean", "Russian", "Hindi",
]);
const codeOptionsButton = ref(null);
const codeLanguageSearchInput = ref(null);
const codeLanguageSearch = ref("");
const codeFrameworkSearch = ref("");
const selectedCodeLanguage = ref("");
const selectedCodeFramework = ref("");
const sendingMessage = ref(false);
const sendError = ref("");
const cooldownUntil = ref(0);
const cooldownClock = ref(Date.now());
let cooldownTimer = null;
const walletBalance = ref(null);
const walletPayback = ref(0);
const nextMessagesCursor = ref(null);
const loadingOlderMessages = ref(false);
const messagesContainer = ref(null);
const sidebarOpen = ref(false);
const desktopSidebarCollapsed = ref(false);
const viewportWidth = ref(typeof window === "undefined" ? 1200 : window.innerWidth);
let loadedCatalogKey = null;
let catalogRequestId = 0;
let conversationRequestId = 0;
let conversationsRequestId = 0;
let messagesRequestId = 0;
let componentDisposed = false;

const activeUuid = computed(() => String(route.params.uuid || ""));
const pageSlug = computed(() => String(route.params.slug || ""));
const catalogSource = computed(() => getFreeAiCatalogSource(conversation.value));
const isGeneralMediaRoute = computed(() => requiredCatalogSource.value === "general_media");
const isGeneralMedia = computed(() => catalogSource.value === "general_media" && MEDIA_OPERATIONS.includes(catalogOperation.value));
const isGeneralCode = computed(() => catalogSource.value === "general_code" && !catalogOperation.value);
const isGeneralTranslation = computed(() => catalogSource.value === "general_translation" && !catalogOperation.value);
const isTextToSpeech = computed(() => catalogSource.value === "general_audio" && catalogOperation.value === "text_to_speech");
const isSpeechToText = computed(() => catalogSource.value === "general_audio" && catalogOperation.value === "speech_to_text");
const canChat = computed(() => isGeneralMedia.value || isTextToSpeech.value || isSpeechToText.value
    || ((catalogSource.value === "general_chat" || isGeneralCode.value || isGeneralTranslation.value) && !catalogOperation.value));
const mediaRequiresFile = computed(() => isGeneralMedia.value && mediaOperationRequiresFile(catalogOperation.value));
const mediaParameterFields = computed(() => Object.entries(selectedModel.value?.parameterSchema || {})
    .filter(([, schema]) => schema && typeof schema === "object" && !Array.isArray(schema))
    .map(([name, schema]) => ({ name, schema })));
const mediaParametersValid = computed(() => mediaParameterFields.value.every(({ name, schema }) =>
    !schema.required || schema.nullable || (mediaParameters.value[name] !== null && mediaParameters.value[name] !== "" && mediaParameters.value[name] !== undefined)
));
const mediaHasRequiredInput = computed(() => !isGeneralMedia.value
    || ((!["image_generation", "video_generation"].includes(catalogOperation.value) || !!messageDraft.value.trim())
        && (!mediaRequiresFile.value || !!selectedMediaFile.value)
        && mediaParametersValid.value));
const programmingLanguage = computed(() => programmingLanguageValue(selectedCodeLanguage.value, selectedCodeFramework.value));
const filteredCodeLanguages = computed(() => PROGRAMMING_LANGUAGES.filter((language) => language.toLowerCase().includes(codeLanguageSearch.value.trim().toLowerCase())));
const filteredCodeFrameworks = computed(() => PROGRAMMING_FRAMEWORKS.filter((framework) => framework.toLowerCase().includes(codeFrameworkSearch.value.trim().toLowerCase())));
const filteredSourceLanguages = computed(() => TRANSLATION_LANGUAGES.filter((language) => language.toLowerCase().includes(sourceLanguageSearch.value.trim().toLowerCase())));
const filteredTargetLanguages = computed(() => TRANSLATION_LANGUAGES.filter((language) => language.toLowerCase().includes(targetLanguageSearch.value.trim().toLowerCase())));
const cooldownSeconds = computed(() => Math.max(0, Math.ceil((cooldownUntil.value - cooldownClock.value) / 1000)));
const canSend = computed(() => canChat.value && !!conversation.value?.uuid && !!selectedModel.value?.isAvailable
    && !catalogLoading.value && !catalogError.value && !loadingConversation.value
    && !modelSaving.value && !sendingMessage.value && !cooldownSeconds.value
    && (isGeneralMedia.value ? mediaHasRequiredInput.value : (isSpeechToText.value ? !!selectedAudioFile.value : !!messageDraft.value.trim()))
    && (!isGeneralCode.value || !!programmingLanguage.value));
const isMobile = computed(() => viewportWidth.value <= MOBILE_BREAKPOINT);
const isRtl = computed(() => locale.value === "ar");
const readableSlug = computed(() => pageSlug.value.replace(/-/g, " ").replace(/\b\w/g, (letter) => letter.toUpperCase()));
const mainTool = computed(() => conversation.value?.model || {});
const collapseIcon = computed(() => (isRtl.value ? "bi-chevron-right" : "bi-chevron-left"));
const conversationRouteName = computed(() => String(route.name || "free-ai-model.chat"));
const requiredCatalogSource = computed(() => String(route.meta?.catalogSource || "").trim() || null);
const requiredCatalogOperation = computed(() => String(route.meta?.catalogOperation || "").trim() || null);
const requestCatalogOperation = computed(() => isGeneralMediaRoute.value ? mediaOperation.value : requiredCatalogOperation.value);
const catalogOperation = computed(() =>
    String(conversation.value?.catalog_operation || requestCatalogOperation.value || "").trim() || null
);

const seoTitle = computed(() => mainTool.value.meta_title || mainTool.value.name || "AI Pro");
const seoDescription = computed(() => mainTool.value.meta_description || mainTool.value.description || "");
const seoKeywords = computed(() => mainTool.value.seo_keywords || "");
useSeoMeta({ title: seoTitle, description: seoDescription, keywords: seoKeywords });

const modelKey = (model) => String(model?.providerModelId || model?.provider_model_id || model?.id || "");
let codeMarkdown = null;

function renderCodeMarkdown(content) {
    codeMarkdown ||= new MarkdownIt({ html: false, breaks: true, linkify: true });
    return DOMPurify.sanitize(codeMarkdown.render(String(content || "")), { USE_PROFILES: { html: true } });
}

function openCodeOptions() {
    codeOptionsOpen.value = true;
    nextTick(() => codeLanguageSearchInput.value?.focus());
}

function closeCodeOptions() {
    codeOptionsOpen.value = false;
    nextTick(() => codeOptionsButton.value?.focus());
}

function openTranslationOptions() {
    translationOptionsOpen.value = true;
    nextTick(() => translationSourceSearchInput.value?.focus());
}

function closeTranslationOptions() {
    translationOptionsOpen.value = false;
    nextTick(() => translationOptionsButton.value?.focus());
}

function mediaOperationLabel(operation) {
    return String(operation || "")
        .split("_")
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(" ");
}

function parameterLabel(name, schema = {}) {
    return String(schema.label || schema.title || mediaOperationLabel(name));
}

function parameterType(schema) {
    if (Array.isArray(schema?.values) || Array.isArray(schema?.enum)) return "enum";
    return String(schema?.type || "string").toLowerCase();
}

function parameterValues(schema) {
    const values = schema?.values ?? schema?.enum;
    return Array.isArray(values) ? values : [];
}

function resetMediaParameters() {
    const recommended = selectedModel.value?.recommendedParameters || {};
    const parameters = {};
    for (const { name, schema } of mediaParameterFields.value) {
        if (Object.hasOwn(recommended, name)) parameters[name] = recommended[name];
        else if (Object.hasOwn(schema, "default")) parameters[name] = schema.default;
        else if (parameterType(schema) === "boolean") parameters[name] = false;
        else parameters[name] = schema.nullable ? null : "";
    }
    mediaParameters.value = parameters;
}

function mediaFiles(item) {
    if (Array.isArray(item?.attachments)) return item.attachments;
    return Array.isArray(item?.metadata?.files) ? item.metadata.files : [];
}

function mediaFileUrl(file) {
    const value = String(file?.object_url || "").trim();
    if (!value) return "";
    try {
        const parsed = new URL(value, window.location.origin);
        return parsed.protocol === "blob:" ? parsed.href : "";
    } catch {
        return "";
    }
}

function mediaProxyEndpoint(file) {
    const endpoint = String(file?.url || "").trim();
    if (/^\/(?:api\/v1\/)?free-ai-model-files\//.test(endpoint)) return endpoint;

    const fileId = String(file?.file_id || "").trim();
    return fileId ? `/api/v1/free-ai-model-files/${encodeURIComponent(fileId)}/content` : "";
}

function mediaFileType(file) {
    return String(file?.content_type || file?.mime_type || file?.type || "").toLowerCase();
}

function isImageFile(file) {
    return mediaFileType(file).startsWith("image/") || /\.(png|jpe?g|webp|gif|avif)(?:\?|$)/i.test(mediaFileUrl(file));
}

function isVideoFile(file) {
    return mediaFileType(file).startsWith("video/") || /\.(mp4|webm|mov)(?:\?|$)/i.test(mediaFileUrl(file));
}

async function hydrateMediaFile(file) {
    if (!file || typeof file !== "object" || file.object_url || pendingMediaDownloads.has(file)) return;
    const endpoint = mediaProxyEndpoint(file);
    if (!endpoint) return;

    const generation = mediaHydrationGeneration;
    const pending = freeAiMediaService.downloadGeneratedFile(endpoint)
        .then((blob) => {
            if (componentDisposed || generation !== mediaHydrationGeneration || !(blob instanceof Blob)) return;
            const objectUrl = URL.createObjectURL(blob);
            mediaObjectUrls.add(objectUrl);
            file.object_url = objectUrl;
        })
        .catch(() => {})
        .finally(() => pendingMediaDownloads.delete(file));
    pendingMediaDownloads.set(file, pending);
    await pending;
}

async function hydrateMediaMessages(items) {
    await Promise.allSettled((Array.isArray(items) ? items : [])
        .flatMap((item) => mediaFiles(item))
        .map((file) => hydrateMediaFile(file)));
}

function clearMediaObjectUrls() {
    mediaHydrationGeneration++;
    for (const url of mediaObjectUrls) URL.revokeObjectURL(url);
    mediaObjectUrls.clear();
}

function chooseMediaFile() {
    if (!sendingMessage.value) mediaFileInput.value?.click();
}

function selectMediaFile(file) {
    sendError.value = "";
    if (!file || (file.type && !file.type.startsWith("image/"))) {
        sendError.value = t("freeAiModels.mediaInvalidFile");
        return;
    }
    if (file.size > 50 * 1024 * 1024) {
        sendError.value = t("freeAiModels.mediaFileTooLarge");
        return;
    }
    selectedMediaFile.value = file;
}

function handleMediaFileInput(event) {
    selectMediaFile(event?.target?.files?.[0]);
    if (event?.target) event.target.value = "";
}

function clearSelectedMedia() {
    selectedMediaFile.value = null;
    if (mediaFileInput.value) mediaFileInput.value.value = "";
}

async function changeMediaOperation() {
    if (!isGeneralMediaRoute.value || creatingConversation.value) return;
    conversationRequestId++;
    conversationsRequestId++;
    messagesRequestId++;
    clearSelectedMedia();
    mediaSettingsOpen.value = false;
    loadedCatalogKey = null;
    conversation.value = null;
    catalogModels.value = [];
    selectedModel.value = null;
    messages.value = [];
    await newConversation();
    await loadConversations();
}

function catalogMatch(selection) {
    if (!selection) return null;
    return catalogModels.value.find((model) => {
        const sameId = String(model.id ?? "") === String(selection.id ?? "");
        const requestedProvider = selection.provider_model_id || selection.providerModelId;
        return sameId && (!requestedProvider || model.providerModelId === requestedProvider);
    }) || null;
}

function selectedSnapshot(selection) {
    if (!selection?.name) return null;
    return {
        id: selection.id ?? null,
        name: selection.name,
        providerModelId: selection.provider_model_id || selection.providerModelId || "",
        tier: "standard",
        isFree: false,
        isAvailable: true,
        isRecommended: false,
    };
}

function defaultCatalogModel() {
    if (!catalogSource.value) return null;
    const remembered = readSelectedCatalogModel(catalogSource.value, pageSlug.value, catalogOperation.value);
    const rememberedMatch = catalogMatch(remembered);
    if (rememberedMatch?.isAvailable) return rememberedMatch;
    return catalogModels.value.find((model) => model.isAvailable && model.isRecommended)
        || catalogModels.value.find((model) => model.isAvailable)
        || null;
}

function syncSelectedModel() {
    const persisted = conversation.value?.selected_model;
    const persistedMatch = catalogMatch(persisted);
    selectedModel.value = persistedMatch?.isAvailable ? persistedMatch : defaultCatalogModel();
    if (isGeneralMedia.value) resetMediaParameters();
}

async function loadCatalog(force = false) {
    const source = catalogSource.value;
    const operation = catalogOperation.value;
    const slug = pageSlug.value;
    const operationMismatch = requestCatalogOperation.value
        && conversation.value?.catalog_operation
        && conversation.value.catalog_operation !== requestCatalogOperation.value;
    if (!source || (requiredCatalogSource.value && source !== requiredCatalogSource.value) || operationMismatch) {
        catalogRequestId++;
        catalogModels.value = [];
        catalogLoading.value = false;
        catalogError.value = true;
        selectedModel.value = selectedSnapshot(conversation.value?.selected_model);
        return;
    }
    const catalogKey = `${source}:${operation || "default"}`;
    if (!force && loadedCatalogKey === catalogKey && catalogModels.value.length) return;

    const requestId = ++catalogRequestId;
    catalogLoading.value = true;
    catalogError.value = false;
    try {
        const result = source === "general_media"
            ? await freeAiMediaService.getMediaModels(operation, {
                fallbackDescription: t("freeAiModels.modelDescriptionFallback"),
            })
            : await modelCatalogService.getModels(source, {
            fallbackDescription: t("freeAiModels.modelDescriptionFallback"),
            operation,
        });
        if (requestId !== catalogRequestId || slug !== pageSlug.value || source !== catalogSource.value || operation !== catalogOperation.value) return;
        catalogModels.value = source === "general_code"
            ? result.models.filter((model) => model.toolKey === "general_code" && model.operation === "text_generation")
            : source === "general_translation"
                ? result.models.filter((model) => model.toolKey === "general_translation" && (!model.operation || model.operation === "text_generation"))
                : source === "general_media"
                    ? result.models.filter((model) => model.toolKey === "general_media" && model.operation === operation)
                    : result.models;
        loadedCatalogKey = catalogKey;
        syncSelectedModel();
    } catch {
        if (requestId !== catalogRequestId || slug !== pageSlug.value || source !== catalogSource.value || operation !== catalogOperation.value) return;
        catalogModels.value = [];
        catalogError.value = true;
        selectedModel.value = selectedSnapshot(conversation.value?.selected_model);
    } finally {
        if (requestId === catalogRequestId) catalogLoading.value = false;
    }
}

async function loadConversation() {
    const requestId = ++conversationRequestId;
    const slug = pageSlug.value;
    const uuid = activeUuid.value;
    const isCurrent = () => requestId === conversationRequestId && slug === pageSlug.value && uuid === activeUuid.value;
    loadingConversation.value = true;
    loadError.value = false;
    try {
        const response = await freeAiModelService.getConversation(slug, uuid, requestCatalogOperation.value);
        if (!isCurrent()) return;
        conversation.value = response?.data || null;
        if (conversation.value?.catalog_source === "general_media" && MEDIA_OPERATIONS.includes(conversation.value?.catalog_operation)) {
            mediaOperation.value = conversation.value.catalog_operation;
        }
        syncSelectedModel();
        loadCatalog();
        if (canChat.value) {
            const messageLoad = isTextToSpeech.value ? restoreSpeechMessages(uuid) : loadMessages();
            await Promise.all([messageLoad, refreshWallet().catch(() => {})]);
        }
        else messages.value = [];
    } catch {
        if (!isCurrent()) return;
        conversation.value = null;
        loadError.value = true;
    } finally {
        if (isCurrent()) loadingConversation.value = false;
    }
}

async function loadMessages(showError = true) {
    const requestId = ++messagesRequestId;
    const slug = pageSlug.value;
    const uuid = activeUuid.value;
    try {
        const response = await freeAiModelService.getMessages(slug, uuid, null, requestCatalogOperation.value);
        if (requestId !== messagesRequestId || slug !== pageSlug.value || uuid !== activeUuid.value) return;
        clearMediaObjectUrls();
        messages.value = response?.data?.items || [];
        nextMessagesCursor.value = response?.data?.next_cursor || null;
        await hydrateMediaMessages(messages.value);
        await scrollToBottom();
    } catch {
        if (showError && requestId === messagesRequestId) sendError.value = t("freeAiModels.messagesLoadFailed");
    }
}

async function loadOlderMessages() {
    if (!nextMessagesCursor.value || loadingOlderMessages.value) return;
    const requestId = messagesRequestId;
    const slug = pageSlug.value;
    const uuid = activeUuid.value;
    const cursor = nextMessagesCursor.value;
    loadingOlderMessages.value = true;
    try {
        const response = await freeAiModelService.getMessages(slug, uuid, cursor, requestCatalogOperation.value);
        if (requestId !== messagesRequestId || slug !== pageSlug.value || uuid !== activeUuid.value) return;
        const olderMessages = response?.data?.items || [];
        messages.value = [...olderMessages, ...messages.value];
        nextMessagesCursor.value = response?.data?.next_cursor || null;
        await hydrateMediaMessages(olderMessages);
    } catch {
        if (requestId === messagesRequestId) sendError.value = t("freeAiModels.messagesLoadFailed");
    } finally {
        loadingOlderMessages.value = false;
    }
}

async function scrollToBottom() {
    await nextTick();
    if (messagesContainer.value) messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
}

async function restoreSpeechMessages(uuid) {
    messages.value = readAudioChatHistory(uuid).map((message, index) => ({
        ...message,
        id: `restored-${index}-${message.role}`,
        ...(message.type === "audio" ? { url: "", mimeType: message.content_type } : {}),
    }));
    nextMessagesCursor.value = null;
    await scrollToBottom();
}

function persistSpeechMessages(uuid = activeUuid.value) {
    saveAudioChatHistory(uuid, messages.value);
}

async function refreshWallet() {
    const response = await freeAiModelService.getWallet();
    walletBalance.value = Number(response?.data?.balance ?? 0);
    walletPayback.value = Number(response?.data?.payback_balance ?? 0);
    return walletBalance.value;
}

function audioExtension(file) {
    return String(file?.name || "").split(".").pop()?.toLowerCase() || "";
}

function chooseAudioFile() {
    if (!sendingMessage.value) audioFileInput.value?.click();
}

function selectAudioFile(file) {
    audioDragging.value = false;
    sendError.value = "";
    if (!file) {
        sendError.value = t("freeAiModels.audioFileRequired");
        return;
    }
    if (!SUPPORTED_AUDIO_FORMATS.has(audioExtension(file))) {
        sendError.value = t("freeAiModels.audioUnsupportedFormat");
        return;
    }
    if (file.size > MAX_AUDIO_FILE_SIZE) {
        sendError.value = t("freeAiModels.audioTooLarge");
        return;
    }
    if (file.type && !file.type.startsWith("audio/") && file.type !== "video/mp4") {
        sendError.value = t("freeAiModels.audioUnsupportedFormat");
        return;
    }

    clearSelectedAudio();
    selectedAudioFile.value = file;
    selectedAudioUrl.value = URL.createObjectURL(file);
}

function handleAudioInput(event) {
    selectAudioFile(event?.target?.files?.[0]);
    if (event?.target) event.target.value = "";
}

function handleAudioDrop(event) {
    if (sendingMessage.value) return;
    selectAudioFile(event?.dataTransfer?.files?.[0]);
}

function clearSelectedAudio() {
    if (selectedAudioUrl.value) URL.revokeObjectURL(selectedAudioUrl.value);
    selectedAudioUrl.value = "";
    selectedAudioFile.value = null;
    audioDragging.value = false;
    if (audioFileInput.value) audioFileInput.value.value = "";
}

function formatFileSize(bytes) {
    const size = Number(bytes || 0);
    if (size < 1024 * 1024) return `${Math.max(1, Math.ceil(size / 1024))} KB`;
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

function startCooldown(milliseconds) {
    const now = Date.now();
    cooldownUntil.value = Math.max(cooldownUntil.value, now + Math.max(0, Math.ceil(milliseconds)));
    cooldownClock.value = now;
    if (cooldownTimer !== null) return;

    cooldownTimer = window.setInterval(() => {
        cooldownClock.value = Date.now();
        if (cooldownClock.value < cooldownUntil.value) return;
        window.clearInterval(cooldownTimer);
        cooldownTimer = null;
    }, 250);
}

async function sendMessage() {
    if (!canSend.value) return;
    if (isGeneralMedia.value) {
        await sendMediaMessage();
        return;
    }
    if (isSpeechToText.value) {
        await sendTranscriptionMessage();
        return;
    }
    if (isTextToSpeech.value) {
        await sendSpeechMessage();
        return;
    }
    const message = messageDraft.value.trim();
    const slug = pageSlug.value;
    const uuid = activeUuid.value;
    const codeRequest = isGeneralCode.value;
    const translationRequest = isGeneralTranslation.value;
    const codeLanguage = codeRequest ? programmingLanguage.value : null;
    const translationSource = sourceLanguage.value;
    const translationTarget = targetLanguage.value;
    const signature = messageRequestSignature(uuid, message, selectedModel.value.id);
    if (wasRecentlySent(recentChatRequests, signature)) {
        sendError.value = t("freeAiModels.duplicateRequest");
        return;
    }
    sendError.value = "";
    sendingMessage.value = true;
    try {
        const balance = await refreshWallet();
        if (slug !== pageSlug.value || uuid !== activeUuid.value) return;
        const estimate = Math.max(1, Math.ceil(new TextEncoder().encode(message).length / 4)) + 1;
        if (balance < estimate || walletPayback.value > 0) {
            sendError.value = t(translationRequest ? "freeAiModels.translationInsufficientBalance" : "freeAiModels.insufficientBalance");
            return;
        }

        if (String(conversation.value?.selected_model?.id ?? "") !== String(selectedModel.value?.id ?? "")) {
            const selected = await freeAiModelService.updateConversationModel(
                slug, uuid, selectedModel.value, requestCatalogOperation.value
            );
            if (slug !== pageSlug.value || uuid !== activeUuid.value) return;
            conversation.value = selected?.data || conversation.value;
        }

        const requestId = uuidv4();
        rememberSentRequest(recentChatRequests, signature);
        messageDraft.value = "";
        messages.value.push({ id: `pending-${requestId}`, request_id: requestId, role: "user", content: message });
        await scrollToBottom();
        const response = codeRequest
            ? await freeAiModelService.sendGeneralCodeMessage(slug, uuid, message, requestId, codeLanguage)
            : translationRequest
                ? await freeAiModelService.sendGeneralTranslationMessage(slug, uuid, message, requestId, translationSource, translationTarget)
            : await freeAiModelService.sendMessage(slug, uuid, message, requestId, requestCatalogOperation.value);
        const result = response?.data;
        if (!result?.assistant_message?.content) throw new Error("Invalid chat response");
        startCooldown(MESSAGE_COOLDOWN_MS);
        if (slug !== pageSlug.value || uuid !== activeUuid.value) return;
        const pendingIndex = messages.value.findIndex((item) => item.id === `pending-${requestId}`);
        if (pendingIndex !== -1) messages.value.splice(pendingIndex, 1, result.user_message);
        messages.value.push(result.assistant_message);
        walletBalance.value = result.wallet?.balance ?? walletBalance.value;
        walletPayback.value = result.wallet?.payback_balance ?? walletPayback.value;
        window.dispatchEvent(new CustomEvent("wallet-updated", { detail: result.wallet }));
        try { localStorage.setItem("wallet-updated-at", String(Date.now())); } catch { /* Other tabs refresh on navigation. */ }
        conversation.value = { ...conversation.value, title: conversation.value?.title || message.slice(0, 80) };
        upsertConversationSummary(conversation.value);
        await scrollToBottom();
    } catch (error) {
        const status = error?.response?.status;
        if (status === 429) {
            startCooldown(Math.max(1000, retryAfterMilliseconds(error.response?.headers) ?? RATE_LIMIT_FALLBACK_MS));
        }
        if (slug !== pageSlug.value || uuid !== activeUuid.value) return;
        sendError.value = status === 402 ? t(translationRequest ? "freeAiModels.translationInsufficientBalance" : "freeAiModels.insufficientBalance")
            : status === 429 ? t("freeAiModels.rateLimited")
                : status === 504 || error?.code === "ECONNABORTED" ? t("freeAiModels.chatTimeout")
                    : t("freeAiModels.chatFailed");
        void loadMessages(false);
    } finally {
        sendingMessage.value = false;
    }
}

async function sendMediaMessage() {
    const message = messageDraft.value.trim();
    const slug = pageSlug.value;
    const uuid = activeUuid.value;
    const operation = catalogOperation.value;
    const file = selectedMediaFile.value;
    const displayMessage = message || mediaOperationLabel(operation);
    const signature = messageRequestSignature(
        uuid,
        `${operation}:${message}:${JSON.stringify(mediaParameters.value)}:${file ? `${file.name}:${file.size}:${file.lastModified}` : "no-file"}`,
        selectedModel.value.id
    );
    if (wasRecentlySent(recentChatRequests, signature)) {
        sendError.value = t("freeAiModels.duplicateRequest");
        return;
    }

    sendError.value = "";
    sendingMessage.value = true;
    try {
        const balance = await refreshWallet();
        if (slug !== pageSlug.value || uuid !== activeUuid.value) return;
        if (balance <= 0 || walletPayback.value > 0) {
            sendError.value = t("freeAiModels.insufficientBalance");
            return;
        }

        if (String(conversation.value?.selected_model?.id ?? "") !== String(selectedModel.value?.id ?? "")) {
            const selected = await freeAiModelService.updateConversationModel(
                slug, uuid, selectedModel.value, requestCatalogOperation.value
            );
            if (slug !== pageSlug.value || uuid !== activeUuid.value) return;
            conversation.value = selected?.data || conversation.value;
        }

        const requestId = uuidv4();
        rememberSentRequest(recentChatRequests, signature);
        messageDraft.value = "";
        messages.value.push({
            id: `pending-${requestId}`,
            request_id: requestId,
            role: "user",
            content: displayMessage,
            metadata: {
                operation,
                parameters: { ...mediaParameters.value },
                ...(file ? { original_filename: file.name } : {}),
            },
        });
        await scrollToBottom();

        const response = await freeAiMediaService.sendGeneralMedia({
            slug,
            conversationUuid: uuid,
            requestId,
            userId: conversation.value?.user?.id,
            modelId: conversation.value?.model_id,
            selectedModelId: selectedModel.value?.id,
            operation,
            parameters: mediaParameters.value,
            userMessage: message,
            file,
        });
        const result = response?.data;
        if (!result?.assistant_message || !Array.isArray(result.assistant_message?.attachments)) {
            throw new Error("Invalid media response");
        }
        if (slug !== pageSlug.value || uuid !== activeUuid.value) return;

        const pendingIndex = messages.value.findIndex((item) => item.id === `pending-${requestId}`);
        if (pendingIndex !== -1) messages.value.splice(pendingIndex, 1, result.user_message);
        messages.value.push(result.assistant_message);
        await hydrateMediaMessages([result.assistant_message]);
        walletBalance.value = result.wallet?.balance ?? walletBalance.value;
        walletPayback.value = result.wallet?.payback_balance ?? walletPayback.value;
        window.dispatchEvent(new CustomEvent("wallet-updated", { detail: result.wallet }));
        try { localStorage.setItem("wallet-updated-at", String(Date.now())); } catch { /* Other tabs refresh on navigation. */ }
        conversation.value = { ...conversation.value, title: conversation.value?.title || displayMessage.slice(0, 80) };
        upsertConversationSummary(conversation.value);
        clearSelectedMedia();
        startCooldown(MEDIA_MESSAGE_COOLDOWN_MS);
        await scrollToBottom();
    } catch (error) {
        const status = error?.response?.status;
        if (status === 429) {
            startCooldown(Math.max(1000, retryAfterMilliseconds(error.response?.headers) ?? RATE_LIMIT_FALLBACK_MS));
        }
        if (slug !== pageSlug.value || uuid !== activeUuid.value) return;
        sendError.value = status === 402 ? t("freeAiModels.insufficientBalance")
            : status === 413 ? t("freeAiModels.mediaFileTooLarge")
                : status === 429 ? t("freeAiModels.rateLimited")
                    : status === 504 || error?.code === "ECONNABORTED" ? t("freeAiModels.mediaTimeout")
                        : status === 422 ? t("freeAiModels.mediaInvalidRequest")
                            : t("freeAiModels.mediaFailed");
        void loadMessages(false);
    } finally {
        sendingMessage.value = false;
    }
}

async function sendTranscriptionMessage() {
    const audioFile = selectedAudioFile.value;
    if (!audioFile) {
        sendError.value = t("freeAiModels.audioFileRequired");
        return;
    }

    const slug = pageSlug.value;
    const uuid = activeUuid.value;
    const signature = messageRequestSignature(
        uuid,
        `${audioFile.name}:${audioFile.size}:${audioFile.lastModified}`,
        selectedModel.value.id
    );
    if (wasRecentlySent(recentChatRequests, signature)) {
        sendError.value = t("freeAiModels.duplicateRequest");
        return;
    }

    sendError.value = "";
    sendingMessage.value = true;
    try {
        const balance = await refreshWallet();
        if (slug !== pageSlug.value || uuid !== activeUuid.value) return;
        if (balance <= 0 || walletPayback.value > 0) {
            sendError.value = t("freeAiModels.insufficientBalance");
            return;
        }

        if (String(conversation.value?.selected_model?.id ?? "") !== String(selectedModel.value?.id ?? "")) {
            const selected = await freeAiModelService.updateConversationModel(
                slug, uuid, selectedModel.value, requestCatalogOperation.value
            );
            if (slug !== pageSlug.value || uuid !== activeUuid.value) return;
            conversation.value = selected?.data || conversation.value;
        }

        const requestId = uuidv4();
        rememberSentRequest(recentChatRequests, signature);
        messages.value.push({
            id: `pending-${requestId}`,
            request_id: requestId,
            role: "user",
            content: "حوّل الصوت إلى نص",
        });
        await scrollToBottom();

        const response = await freeAiModelService.sendSpeechToTextMessage(
            slug,
            uuid,
            audioFile,
            requestId,
            {
                userId: conversation.value?.user?.id,
                modelId: conversation.value?.model_id,
                selectedModelId: selectedModel.value?.id,
            }
        );
        const result = response?.data;
        if (!result?.assistant_message?.content) throw new Error("Invalid transcription response");
        if (slug !== pageSlug.value || uuid !== activeUuid.value) return;

        const pendingIndex = messages.value.findIndex((item) => item.id === `pending-${requestId}`);
        if (pendingIndex !== -1) messages.value.splice(pendingIndex, 1, result.user_message);
        messages.value.push(result.assistant_message);
        walletBalance.value = result.wallet?.balance ?? walletBalance.value;
        walletPayback.value = result.wallet?.payback_balance ?? walletPayback.value;
        window.dispatchEvent(new CustomEvent("wallet-updated", { detail: result.wallet }));
        try { localStorage.setItem("wallet-updated-at", String(Date.now())); } catch { /* Other tabs refresh on navigation. */ }
        conversation.value = { ...conversation.value, title: conversation.value?.title || "حوّل الصوت إلى نص" };
        upsertConversationSummary(conversation.value);
        clearSelectedAudio();
        startCooldown(MESSAGE_COOLDOWN_MS);
        await scrollToBottom();
    } catch (error) {
        const status = error?.response?.status;
        if (status === 429) {
            startCooldown(Math.max(1000, retryAfterMilliseconds(error.response?.headers) ?? RATE_LIMIT_FALLBACK_MS));
        }
        if (slug !== pageSlug.value || uuid !== activeUuid.value) return;
        sendError.value = status === 402 ? t("freeAiModels.insufficientBalance")
            : status === 413 ? t("freeAiModels.audioTooLarge")
                : status === 429 ? t("freeAiModels.rateLimited")
                    : status === 504 || error?.code === "ECONNABORTED" ? t("freeAiModels.transcriptionTimeout")
                        : status === 422 ? t("freeAiModels.audioInvalidRequest")
                            : t("freeAiModels.transcriptionFailed");
        void loadMessages(false);
    } finally {
        sendingMessage.value = false;
    }
}

function audioErrorMessage(error) {
    const key = {
        missing_key: "speechKeyMissing",
        unauthorized: "speechUnauthorized",
        invalid_response: "speechInvalidResponse",
        missing_files: "speechMissingFiles",
        download_failed: "speechDownloadFailed",
        generation_failed: "speechGenerationFailed",
    }[error?.code] || "speechGenerationFailed";
    return t(`freeAiModels.${key}`);
}

async function sendSpeechMessage() {
    const message = messageDraft.value.trim();
    const slug = pageSlug.value;
    const uuid = activeUuid.value;
    const requestId = uuidv4();
    const signature = messageRequestSignature(uuid, message, selectedModel.value.id);
    if (wasRecentlySent(recentChatRequests, signature)) {
        sendError.value = t("freeAiModels.duplicateRequest");
        return;
    }
    sendError.value = "";
    sendingMessage.value = true;
    rememberSentRequest(recentChatRequests, signature);
    messageDraft.value = "";
    messages.value.push({ id: `user-${requestId}`, role: "user", type: "text", content: message });
    persistSpeechMessages(uuid);
    await scrollToBottom();
    try {
        if (String(conversation.value?.selected_model?.id ?? "") !== String(selectedModel.value?.id ?? "")) {
            const selected = await freeAiModelService.updateConversationModel(
                slug, uuid, selectedModel.value, requestCatalogOperation.value
            );
            if (slug !== pageSlug.value || uuid !== activeUuid.value) return;
            conversation.value = selected?.data || conversation.value;
        }

        const file = await generateSpeech({
            userId: Number(conversation.value?.user?.id),
            modelId: Number(conversation.value?.model_id),
            selectedModelId: Number(selectedModel.value?.id),
            conversationUuid: uuid,
            message,
        });
        const blob = await downloadAudioFile(file);
        if (slug !== pageSlug.value || uuid !== activeUuid.value) return;
        const url = URL.createObjectURL(blob);
        audioObjectUrls.add(url);
        messages.value.push({
            id: `audio-${requestId}`,
            type: "audio",
            role: "assistant",
            content: "Speech generated successfully.",
            url,
            filename: file.filename.split(/[\\/]/).pop(),
            mimeType: blob.type || file.content_type,
            content_type: file.content_type || blob.type || "audio/mpeg",
            download_url: file.download_url,
            file_id: String(file.file_id || ""),
        });
        persistSpeechMessages(uuid);
        startCooldown(MESSAGE_COOLDOWN_MS);
        conversation.value = { ...conversation.value, title: conversation.value?.title || message.slice(0, 80) };
        upsertConversationSummary(conversation.value);
        await scrollToBottom();
    } catch (error) {
        if (slug !== pageSlug.value || uuid !== activeUuid.value) return;
        messages.value.push({ id: `error-${requestId}`, type: "error", role: "assistant", content: audioErrorMessage(error) });
        persistSpeechMessages(uuid);
        await scrollToBottom();
    } finally {
        sendingMessage.value = false;
    }
}

async function ensureAudioObjectUrl(item) {
    if (typeof item?.url === "string" && item.url.startsWith("blob:")) return item.url;
    if (!item || item.type !== "audio") throw new Error("Invalid audio attachment");

    const existingDownload = pendingAudioDownloads.get(item);
    if (existingDownload) return existingDownload;

    const download = downloadAudioFile({
        download_url: item.download_url,
        content_type: item.content_type || item.mimeType || "audio/mpeg",
    }).then((blob) => {
        const url = URL.createObjectURL(blob);
        if (componentDisposed || !messages.value.includes(item)) {
            URL.revokeObjectURL(url);
            return "";
        }
        item.url = url;
        item.mimeType = blob.type || item.content_type;
        audioObjectUrls.add(url);
        return url;
    }).finally(() => pendingAudioDownloads.delete(item));

    pendingAudioDownloads.set(item, download);
    return download;
}

async function prepareAudioForPlayback(item, event) {
    if (item?.url) return;
    const player = event?.currentTarget;
    event?.preventDefault?.();
    sendError.value = "";
    try {
        const url = await ensureAudioObjectUrl(item);
        if (!url || !player) return;
        player.src = url;
        await player.play();
    } catch (error) {
        sendError.value = audioErrorMessage(error);
    }
}

async function downloadAudioAttachment(item) {
    sendError.value = "";
    try {
        const url = await ensureAudioObjectUrl(item);
        if (!url) return;
        const link = document.createElement("a");
        link.href = url;
        link.download = item.filename || "generated-speech.mp3";
        link.click();
    } catch (error) {
        sendError.value = audioErrorMessage(error);
    }
}

function revokeAudioUrls() {
    for (const url of audioObjectUrls) URL.revokeObjectURL(url);
    audioObjectUrls.clear();
}

async function loadConversations() {
    const requestId = ++conversationsRequestId;
    const slug = pageSlug.value;
    const operation = requestCatalogOperation.value;
    loadingConversations.value = true;
    try {
        const response = await freeAiModelService.getConversations(slug, operation);
        if (requestId !== conversationsRequestId || slug !== pageSlug.value || operation !== requestCatalogOperation.value) return;
        conversations.value = Array.isArray(response?.data) ? response.data : [];
    } catch {
        if (requestId !== conversationsRequestId || slug !== pageSlug.value || operation !== requestCatalogOperation.value) return;
        conversations.value = [];
    } finally {
        if (requestId === conversationsRequestId && slug === pageSlug.value && operation === requestCatalogOperation.value) loadingConversations.value = false;
    }
}

function summaryFromConversation(item) {
    return {
        uuid: item.uuid,
        title: item.title || null,
        is_pinned: Boolean(item.is_pinned),
        created_at: item.created_at,
        updated_at: item.updated_at || item.created_at,
        catalog_operation: item.catalog_operation || catalogOperation.value,
        selected_model: item.selected_model || null,
    };
}

function upsertConversationSummary(item) {
    const summary = summaryFromConversation(item);
    conversations.value = [summary, ...conversations.value.filter((entry) => entry.uuid !== summary.uuid)];
}

function conversationTitle(item) {
    return item.title || `${t("freeAiModels.newConversation")} · ${String(item.uuid).slice(0, 6)}`;
}

async function openConversation(item) {
    if (item.uuid === activeUuid.value) {
        sidebarOpen.value = false;
        return;
    }
    sidebarOpen.value = false;
    await router.push({
        name: conversationRouteName.value,
        params: { lang: homeService.getLang(), slug: pageSlug.value, uuid: item.uuid },
        ...(isGeneralMediaRoute.value ? { query: { operation: mediaOperation.value } } : {}),
    });
}

async function newConversation() {
    if (creatingConversation.value) return;
    creatingConversation.value = true;
    try {
        const chosen = selectedModel.value?.isAvailable ? selectedModel.value : null;
        const response = await freeAiModelService.createConversation(
            pageSlug.value,
            chosen,
            requestCatalogOperation.value
        );
        const created = response?.data;
        if (!created?.uuid) throw new Error("Missing conversation UUID");
        upsertConversationSummary(created);
        sidebarOpen.value = false;
        await router.push({
            name: conversationRouteName.value,
            params: { lang: homeService.getLang(), slug: pageSlug.value, uuid: created.uuid },
            ...(isGeneralMediaRoute.value ? { query: { operation: mediaOperation.value } } : {}),
        });
    } catch {
        // ApiClient provides the shared request error feedback.
    } finally {
        creatingConversation.value = false;
    }
}

async function deleteConversation(item) {
    if (deletingUuid.value) return;
    deletingUuid.value = item.uuid;
    try {
        await freeAiModelService.deleteConversation(pageSlug.value, item.uuid, requestCatalogOperation.value);
        clearAudioChatHistory(item.uuid);
        conversations.value = conversations.value.filter((entry) => entry.uuid !== item.uuid);
        if (item.uuid === activeUuid.value) {
            const next = conversations.value[0];
            if (next) await openConversation(next);
            else await newConversation();
        }
    } catch {
        // ApiClient provides the shared request error feedback.
    } finally {
        deletingUuid.value = null;
    }
}

async function selectExecutionModel(model) {
    if (!conversation.value?.uuid || !model?.isAvailable || modelSaving.value || modelKey(model) === modelKey(selectedModel.value)) return;
    const previous = selectedModel.value;
    const slug = pageSlug.value;
    const uuid = activeUuid.value;
    selectedModel.value = model;
    modelSaving.value = true;
    try {
        const response = await freeAiModelService.updateConversationModel(
            slug,
            uuid,
            model,
            requestCatalogOperation.value
        );
        if (slug !== pageSlug.value || uuid !== activeUuid.value) return;
        conversation.value = response?.data || conversation.value;
        syncSelectedModel();
        upsertConversationSummary(conversation.value);
        if (catalogSource.value) {
            saveSelectedCatalogModel(
                catalogSource.value,
                pageSlug.value,
                selectedModel.value,
                catalogOperation.value
            );
        }
    } catch {
        if (slug === pageSlug.value && uuid === activeUuid.value) selectedModel.value = previous;
    } finally {
        modelSaving.value = false;
    }
}

function openSidebar() {
    if (isMobile.value) sidebarOpen.value = true;
    else desktopSidebarCollapsed.value = false;
}

function closeSidebar() {
    if (isMobile.value) sidebarOpen.value = false;
    else desktopSidebarCollapsed.value = true;
}

function backToTool() {
    router.push({ name: "free-ai-model.show", params: { lang: homeService.getLang(), slug: pageSlug.value } });
}

function handleResize() {
    viewportWidth.value = window.innerWidth;
    if (!isMobile.value) sidebarOpen.value = false;
}

function handleLanguageChanged() {
    loadedCatalogKey = null;
    catalogModels.value = [];
    loadCatalog(true);
}

onMounted(() => {
    window.addEventListener("resize", handleResize);
    window.addEventListener("lang-changed", handleLanguageChanged);
    Promise.all([loadConversations(), loadConversation()]);
});

onBeforeUnmount(() => {
    componentDisposed = true;
    clearSelectedAudio();
    clearSelectedMedia();
    revokeAudioUrls();
    clearMediaObjectUrls();
    catalogRequestId++;
    conversationRequestId++;
    conversationsRequestId++;
    messagesRequestId++;
    if (cooldownTimer !== null) window.clearInterval(cooldownTimer);
    codeOptionsOpen.value = false;
    translationOptionsOpen.value = false;
    mediaSettingsOpen.value = false;
    window.removeEventListener("resize", handleResize);
    window.removeEventListener("lang-changed", handleLanguageChanged);
    document.body.style.overflow = "";
});

watch(sidebarOpen, (open) => {
    if (isMobile.value) document.body.style.overflow = open ? "hidden" : "";
});

watch([pageSlug, activeUuid], ([slug, uuid], [previousSlug, previousUuid]) => {
    if (!slug || !uuid || (slug === previousSlug && uuid === previousUuid)) return;
    revokeAudioUrls();
    clearMediaObjectUrls();
    if (slug !== previousSlug) {
        catalogRequestId++;
        conversation.value = null;
        conversations.value = [];
        catalogModels.value = [];
        selectedModel.value = null;
        loadedCatalogKey = null;
        catalogLoading.value = false;
        catalogError.value = false;
        loadConversations();
    }
    messagesRequestId++;
    codeOptionsOpen.value = false;
    translationOptionsOpen.value = false;
    mediaSettingsOpen.value = false;
    messages.value = [];
    nextMessagesCursor.value = null;
    messageDraft.value = "";
    clearSelectedAudio();
    clearSelectedMedia();
    sendError.value = "";
    loadConversation();
});
</script>

<style scoped>
.free-ai-chat {
    --navbar-height: 118px;
    width: 100%;
    height: calc(100dvh - var(--navbar-height));
    min-height: 0;
    display: flex;
    overflow: hidden;
    color: var(--theme-text-primary);
    background: var(--theme-bg);
}

button,
textarea {
    font: inherit;
}

button {
    cursor: pointer;
}

.conversation-sidebar {
    position: relative;
    z-index: 20;
    width: 292px;
    min-width: 292px;
    min-height: 0;
    display: flex;
    flex-direction: column;
    padding: 14px 12px;
    overflow: hidden;
    border-inline-end: 1px solid var(--theme-border);
    background: var(--theme-surface);
    transition: width 0.22s ease, min-width 0.22s ease, padding 0.22s ease;
}

.conversation-sidebar.collapsed {
    width: 0;
    min-width: 0;
    padding-inline: 0;
    border-inline-end: 0;
}

.conversation-sidebar.collapsed > * {
    visibility: hidden;
}

.sidebar-heading,
.tool-identity,
.header-leading {
    min-width: 0;
    display: flex;
    align-items: center;
}

.sidebar-heading {
    justify-content: space-between;
    gap: 8px;
    padding: 3px 2px 13px;
}

.tool-identity,
.header-leading {
    gap: 10px;
}

.tool-avatar,
.header-avatar {
    overflow: hidden;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    color: #fff;
    background: linear-gradient(145deg, #154677, #2ba6de);
}

.tool-avatar {
    width: 40px;
    height: 40px;
    border-radius: 12px;
}

.tool-avatar img,
.header-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.tool-copy,
.header-copy {
    min-width: 0;
}

.tool-copy small,
.tool-copy strong,
.header-copy strong,
.header-copy small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.tool-copy small {
    color: var(--theme-text-muted);
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
}

.tool-copy strong {
    margin-top: 2px;
    font-size: 13px;
}

.icon-button {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    flex: 0 0 38px;
    border: 1px solid var(--theme-border);
    border-radius: 11px;
    color: var(--theme-text-secondary);
    background: var(--theme-surface-secondary);
}

.icon-button:hover {
    color: var(--theme-accent);
    border-color: var(--theme-border-strong);
    background: var(--theme-hover);
}

.new-conversation-button {
    width: 100%;
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 0;
    border-radius: 12px;
    color: #fff;
    background: linear-gradient(135deg, #154677, #2ba6de);
    font-size: 12px;
    font-weight: 800;
    box-shadow: 0 10px 22px rgba(21, 70, 119, 0.2);
}

.new-conversation-button:disabled {
    cursor: wait;
    opacity: 0.65;
}

.history-heading {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding: 20px 6px 8px;
    color: var(--theme-text-muted);
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
}

.history-list {
    min-height: 0;
    flex: 1;
    overflow-y: auto;
    overscroll-behavior: contain;
    scrollbar-width: thin;
    scrollbar-color: var(--theme-border-strong) transparent;
}

.history-state {
    display: grid;
    place-items: center;
    gap: 8px;
    padding: 34px 12px;
    color: var(--theme-text-muted);
    font-size: 11px;
    text-align: center;
}

.history-state i {
    font-size: 23px;
}

.history-item {
    display: flex;
    align-items: center;
    gap: 3px;
    margin-bottom: 4px;
    padding: 3px;
    border: 1px solid transparent;
    border-radius: 11px;
}

.history-item:hover,
.history-item.active {
    border-color: var(--theme-border);
    background: var(--theme-hover);
}

.history-item.active {
    box-shadow: inset 3px 0 0 var(--theme-accent);
}

[dir="rtl"] .history-item.active {
    box-shadow: inset -3px 0 0 var(--theme-accent);
}

.history-open {
    min-width: 0;
    flex: 1;
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 6px;
    border: 0;
    color: var(--theme-text-primary);
    background: transparent;
    text-align: start;
}

.history-open > i {
    flex: 0 0 auto;
    color: var(--theme-text-muted);
}

.history-open > span {
    min-width: 0;
}

.history-open strong,
.history-open small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.history-open strong {
    font-size: 11px;
}

.history-open small {
    margin-top: 3px;
    color: var(--theme-text-muted);
    font-size: 9px;
}

.history-delete {
    width: 31px;
    height: 31px;
    display: grid;
    place-items: center;
    flex: 0 0 31px;
    border: 0;
    border-radius: 9px;
    color: var(--theme-text-muted);
    background: transparent;
    opacity: 0;
}

.history-item:hover .history-delete,
.history-item.active .history-delete,
.history-delete:focus-visible {
    opacity: 1;
}

.history-delete:hover {
    color: var(--app-danger);
    background: var(--theme-surface-secondary);
}

.chat-workspace {
    min-width: 0;
    min-height: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.workspace-header {
    min-height: 68px;
    flex: 0 0 68px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 10px 18px;
    border-bottom: 1px solid var(--theme-border);
    background: var(--theme-surface);
}

.header-avatar {
    width: 42px;
    height: 42px;
    border-radius: 13px;
}

.header-copy strong {
    font-size: 14px;
}

.header-copy small {
    margin-top: 3px;
    color: var(--theme-text-muted);
    font-size: 10px;
}

.status-dot {
    width: 6px;
    height: 6px;
    display: inline-block;
    margin-inline-end: 4px;
    border-radius: 50%;
    background: var(--app-success);
}

.back-button,
.retry-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 38px;
    padding: 8px 12px;
    border: 1px solid var(--theme-border);
    border-radius: 11px;
    color: var(--theme-text-secondary);
    background: var(--theme-surface-secondary);
    font-size: 11px;
    font-weight: 800;
}

[dir="rtl"] .back-button i {
    transform: rotate(180deg);
}

.messages {
    min-height: 0;
    flex: 1;
    overflow-y: auto;
    overscroll-behavior: contain;
    background:
        radial-gradient(circle at 50% 30%, rgba(43, 166, 222, 0.07), transparent 34%),
        var(--theme-bg);
}

.chat-message-list {
    width: min(900px, calc(100% - 28px));
    margin: 0 auto;
    padding: 24px 0;
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.chat-message {
    max-width: min(80%, 700px);
    padding: 13px 17px;
    border-radius: 16px;
    background: var(--theme-surface);
    border: 1px solid var(--theme-border);
    color: var(--theme-text-primary);
    overflow-wrap: anywhere;
}

.chat-message.user {
    align-self: flex-end;
    background: var(--theme-surface-elevated);
}

.chat-message.assistant {
    align-self: flex-start;
}

.chat-message.message-error {
    color: var(--app-danger);
    border-color: var(--app-danger);
}

.chat-message-role {
    display: block;
    margin-bottom: 5px;
    color: var(--theme-text-muted);
    font-size: 11px;
    font-weight: 700;
}

.chat-message p {
    margin: 0;
    white-space: pre-wrap;
    line-height: 1.65;
}

.audio-attachment {
    width: min(360px, 70vw);
    display: grid;
    gap: 10px;
}

.audio-attachment-heading {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 12px;
    font-weight: 700;
}

.audio-attachment-heading > i {
    color: var(--theme-accent);
    font-size: 20px;
}

.audio-attachment-heading span {
    min-width: 0;
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.audio-attachment-heading a {
    color: var(--theme-accent);
    padding: 5px;
}

.audio-attachment audio {
    width: 100%;
    height: 38px;
}

.code-markdown {
    min-width: 0;
    line-height: 1.65;
}

.code-markdown :deep(p) {
    white-space: normal;
    margin: 0 0 0.7em;
}

.code-markdown :deep(p:last-child) {
    margin-bottom: 0;
}

.code-markdown :deep(pre) {
    max-width: 100%;
    overflow-x: auto;
    padding: 12px;
    border-radius: 10px;
    background: var(--theme-surface-secondary);
    white-space: pre;
}

.code-markdown :deep(code) {
    font-family: Consolas, Monaco, monospace;
    font-size: 0.9em;
}

.older-messages-button {
    align-self: center;
    border: 1px solid var(--theme-border);
    border-radius: 10px;
    padding: 7px 14px;
    color: var(--theme-text-primary);
    background: var(--theme-surface);
}

.chat-error { color: var(--app-danger); }

.empty-conversation,
.conversation-state {
    width: min(620px, calc(100% - 32px));
    min-height: 100%;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 36px 0;
    text-align: center;
}

.empty-icon {
    width: 62px;
    height: 62px;
    display: grid;
    place-items: center;
    border: 1px solid var(--theme-border);
    border-radius: 20px;
    color: var(--theme-accent);
    background: var(--theme-surface);
    font-size: 26px;
    box-shadow: 0 16px 36px var(--theme-shadow);
}

.empty-conversation h1,
.conversation-state h1 {
    margin: 19px 0 8px;
    font-size: clamp(1.35rem, 3vw, 2rem);
}

.empty-conversation p,
.conversation-state p {
    max-width: 520px;
    margin: 0;
    color: var(--theme-text-secondary);
    font-size: 13px;
    line-height: 1.7;
}

.pending-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 17px;
    padding: 7px 11px;
    border: 1px solid var(--theme-border);
    border-radius: 999px;
    color: var(--theme-text-muted);
    background: var(--theme-surface);
    font-size: 9px;
    font-weight: 800;
}

.conversation-state {
    flex: 1;
    gap: 10px;
}

.conversation-state > p,
.conversation-state > h1 {
    margin: 0;
}

.error-state > i {
    color: var(--app-danger);
    font-size: 34px;
}

.retry-button {
    margin-top: 8px;
}

.composer-area {
    flex: 0 0 auto;
    padding: 12px 18px 10px;
    border-top: 1px solid var(--theme-border);
    background: var(--theme-surface);
}

.composer-shell {
    width: min(980px, 100%);
    margin: 0 auto;
}

.code-options-trigger {
    max-width: 100%;
    margin-bottom: 8px;
    padding: 7px 11px;
    border: 1px solid var(--theme-border-strong);
    border-radius: 10px;
    color: var(--theme-text-secondary);
    background: var(--theme-surface-elevated);
    font-size: 12px;
    text-align: start;
}

.code-options-trigger:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.code-options-overlay {
    position: fixed;
    z-index: 1100;
    inset: 0;
    display: grid;
    place-items: center;
    padding: 16px;
    background: rgba(0, 0, 0, 0.52);
}

.code-options-dialog {
    width: min(720px, 100%);
    max-height: min(680px, 90dvh);
    display: flex;
    flex-direction: column;
    gap: 12px;
    overflow: hidden;
    padding: 20px;
    border: 1px solid var(--theme-border);
    border-radius: 16px;
    color: var(--theme-text-primary);
    background: var(--theme-surface);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
}

.code-options-heading,
.code-options-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.code-options-heading h2 {
    margin: 0;
    font-size: 18px;
}

.code-options-description,
.code-options-footer span {
    margin: 0;
    color: var(--theme-text-muted);
    font-size: 12px;
}

.code-options-grid {
    min-height: 0;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.code-options-column {
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.code-options-column label {
    font-size: 12px;
    font-weight: 700;
}

.code-options-column input {
    width: 100%;
    padding: 9px 10px;
    border: 1px solid var(--theme-border-strong);
    border-radius: 9px;
    color: var(--theme-text-primary);
    background: var(--theme-surface-elevated);
}

.code-options-list {
    min-height: 0;
    max-height: 320px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.code-options-list button {
    padding: 7px 10px;
    border: 1px solid transparent;
    border-radius: 8px;
    color: var(--theme-text-secondary);
    background: transparent;
    text-align: start;
}

.code-options-list button:hover,
.code-options-list button.selected {
    border-color: var(--theme-border-strong);
    background: var(--theme-hover);
    color: var(--theme-text-primary);
}

.code-options-empty {
    padding: 9px;
    color: var(--theme-text-muted);
    font-size: 12px;
}

.code-options-done {
    padding: 8px 15px;
    border: 0;
    border-radius: 9px;
    color: #fff;
    background: var(--theme-accent);
}

.code-options-done:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.composer-box {
    display: flex;
    align-items: stretch;
    overflow: visible;
    border: 1px solid var(--theme-border-strong);
    border-radius: 16px;
    background: var(--theme-surface-elevated);
    box-shadow: 0 10px 28px var(--theme-shadow);
}

.message-compose-row {
    min-width: 0;
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 7px 6px 10px;
}

.speech-upload-compose {
    min-width: 0;
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px;
}

.audio-dropzone {
    min-width: 0;
    min-height: 60px;
    flex: 1;
    display: grid;
    grid-template-columns: auto 1fr;
    align-items: center;
    column-gap: 9px;
    padding: 9px 12px;
    border: 1px dashed var(--theme-border-strong);
    border-radius: 11px;
    color: var(--theme-text-primary);
    background: transparent;
    text-align: start;
}

.audio-dropzone i {
    grid-row: 1 / 3;
    color: var(--theme-accent);
    font-size: 23px;
}

.audio-dropzone span {
    font-size: 12px;
    font-weight: 800;
}

.audio-dropzone small,
.selected-audio-info small {
    color: var(--theme-text-muted);
    font-size: 9px;
}

.audio-dropzone:hover,
.audio-dropzone.dragging,
.selected-audio.dragging {
    border-color: var(--theme-accent);
    background: var(--theme-hover);
}

.audio-dropzone:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.selected-audio {
    min-width: 0;
    flex: 1;
    display: grid;
    grid-template-columns: minmax(160px, 1fr) minmax(180px, 1fr) auto;
    align-items: center;
    gap: 9px;
    padding: 7px 9px;
    border: 1px dashed transparent;
    border-radius: 11px;
}

.selected-audio-info {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.selected-audio-info > i {
    flex: 0 0 auto;
    color: var(--theme-accent);
    font-size: 21px;
}

.selected-audio-info > span {
    min-width: 0;
    flex: 1;
}

.selected-audio-info strong,
.selected-audio-info small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.selected-audio-info strong {
    font-size: 11px;
}

.selected-audio-info button,
.replace-audio-button {
    border: 0;
    color: var(--theme-text-muted);
    background: transparent;
}

.selected-audio-info button {
    width: 30px;
    height: 30px;
    display: grid;
    place-items: center;
    flex: 0 0 30px;
    border-radius: 8px;
}

.selected-audio-info button:hover {
    color: var(--app-danger);
    background: var(--theme-hover);
}

.selected-audio audio {
    width: 100%;
    height: 36px;
}

.replace-audio-button {
    padding: 6px;
    font-size: 10px;
    font-weight: 700;
}

.replace-audio-button:hover {
    color: var(--theme-accent);
}

.message-compose-row textarea {
    min-width: 0;
    min-height: 38px;
    max-height: 100px;
    flex: 1;
    resize: none;
    padding: 9px 6px;
    border: 0;
    outline: 0;
    color: var(--theme-text-secondary);
    background: transparent;
    font-size: 12px;
}

.message-compose-row textarea:disabled {
    cursor: not-allowed;
    opacity: 0.72;
}

.send-button {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    flex: 0 0 38px;
    border: 0;
    border-radius: 11px;
    color: #fff;
    background: linear-gradient(135deg, #154677, #2ba6de);
}

.send-button:disabled {
    cursor: not-allowed;
    filter: grayscale(0.55);
    opacity: 0.45;
}

.composer-hint {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    margin: 7px 0 0;
    color: var(--theme-text-muted);
    font-size: 8px;
    text-align: center;
}

.sidebar-overlay {
    display: none;
}

.media-operation-picker {
    display: grid;
    gap: 6px;
    margin: 4px 0 12px;
    color: var(--theme-text-secondary);
    font-size: 11px;
    font-weight: 800;
}

.media-operation-picker select,
.media-setting-field select,
.media-setting-field input:not([type="checkbox"]) {
    width: 100%;
    padding: 9px 10px;
    border: 1px solid var(--theme-border-strong);
    border-radius: 9px;
    color: var(--theme-text-primary);
    background: var(--theme-surface-elevated);
    font: inherit;
}

.media-compose {
    min-width: 0;
    flex: 1;
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 6px 7px;
}

.media-compose textarea {
    min-width: 120px;
    min-height: 42px;
    max-height: 130px;
    flex: 1;
    resize: vertical;
    padding: 10px;
    border: 0;
    outline: 0;
    color: var(--theme-text-primary);
    background: transparent;
}

.media-file-button {
    min-width: 0;
    max-width: 220px;
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 9px 10px;
    border: 1px dashed var(--theme-border-strong);
    border-radius: 10px;
    color: var(--theme-text-secondary);
    background: transparent;
}

.media-file-button.selected {
    border-style: solid;
    border-color: var(--theme-accent);
    color: var(--theme-text-primary);
    background: var(--theme-hover);
}

.media-file-button span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 11px;
}

.media-result {
    display: grid;
    gap: 10px;
}

.media-result-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 9px;
}

.media-result-file {
    position: relative;
    min-height: 110px;
    display: grid;
    place-items: center;
    overflow: hidden;
    border: 1px solid var(--theme-border);
    border-radius: 12px;
    color: var(--theme-text-secondary);
    background: var(--theme-surface-elevated);
    text-decoration: none;
}

.media-result-download {
    position: absolute;
    inset-block-start: 8px;
    inset-inline-end: 8px;
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border: 1px solid color-mix(in srgb, var(--theme-border) 75%, transparent);
    border-radius: 999px;
    color: var(--theme-text-primary);
    background: color-mix(in srgb, var(--theme-surface) 88%, transparent);
    text-decoration: none;
    box-shadow: 0 4px 14px var(--theme-shadow);
}

.media-result-file img,
.media-result-file video {
    width: 100%;
    max-height: 420px;
    display: block;
    object-fit: contain;
}

.media-result-file span {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 14px;
}

.media-settings-dialog {
    width: min(640px, 100%);
}

.media-settings-grid {
    min-height: 0;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    overflow-y: auto;
    padding: 2px;
}

.media-setting-field {
    display: grid;
    align-content: start;
    gap: 6px;
    color: var(--theme-text-secondary);
    font-size: 12px;
    font-weight: 700;
}

.media-setting-field > span {
    display: flex;
    gap: 3px;
}

.media-setting-field small {
    color: var(--app-danger);
}

.media-setting-field input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: var(--theme-accent);
}

@media (max-width: 900px) {
    .free-ai-chat {
        --navbar-height: 104px;
    }

    .conversation-sidebar,
    .conversation-sidebar.collapsed {
        position: fixed;
        inset-block-start: var(--navbar-height);
        inset-block-end: 0;
        inset-inline-start: 0;
        z-index: 80;
        width: min(310px, 88vw);
        min-width: min(310px, 88vw);
        padding: 14px 12px;
        border-inline-end: 1px solid var(--theme-border);
        visibility: visible;
        transform: translateX(-105%);
        transition: transform 0.22s ease;
        box-shadow: 12px 0 36px var(--theme-shadow);
    }

    [dir="rtl"] .conversation-sidebar,
    [dir="rtl"] .conversation-sidebar.collapsed {
        transform: translateX(105%);
        box-shadow: -12px 0 36px var(--theme-shadow);
    }

    .conversation-sidebar.open,
    [dir="rtl"] .conversation-sidebar.open {
        transform: translateX(0);
    }

    .conversation-sidebar.collapsed > * {
        visibility: visible;
    }

    .sidebar-overlay {
        position: fixed;
        inset-block-start: var(--navbar-height);
        inset-block-end: 0;
        inset-inline: 0;
        z-index: 70;
        display: block;
        border: 0;
        background: rgba(4, 16, 29, 0.48);
        cursor: default;
    }

    .workspace-header {
        padding-inline: 12px;
    }
}

@media (max-width: 640px) {
    .code-options-grid,
    .media-settings-grid {
        grid-template-columns: 1fr;
        overflow-y: auto;
    }

    .code-options-list {
        max-height: 180px;
    }

    .free-ai-chat {
        --navbar-height: 90px;
    }

    .workspace-header {
        min-height: 62px;
        flex-basis: 62px;
        padding: 8px 9px;
    }

    .header-avatar {
        width: 36px;
        height: 36px;
        border-radius: 11px;
    }

    .header-copy strong {
        max-width: 38vw;
        font-size: 12px;
    }

    .header-copy small {
        max-width: 44vw;
        font-size: 8px;
    }

    .back-button {
        width: 36px;
        min-height: 36px;
        padding: 0;
    }

    .back-button span {
        display: none;
    }

    .composer-area {
        padding: 8px 9px 7px;
    }

    .composer-box {
        flex-direction: column;
        border-radius: 14px;
    }

    .message-compose-row {
        min-height: 50px;
        padding: 5px 6px 5px 9px;
    }

    .speech-upload-compose {
        align-items: flex-end;
    }

    .media-compose {
        flex-wrap: wrap;
    }

    .media-file-button {
        max-width: 100%;
        flex: 1 1 100%;
    }

    .selected-audio {
        grid-template-columns: 1fr;
    }

    .composer-hint {
        margin-top: 5px;
        font-size: 7px;
    }

    .empty-conversation {
        width: calc(100% - 24px);
        padding-block: 26px;
    }
}
</style>
