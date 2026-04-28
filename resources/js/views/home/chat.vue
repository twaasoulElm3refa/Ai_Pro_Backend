<template>
    <div class="chat-root">

        <!-- SIDEBAR -->
        <aside class="sidebar" :class="{ 'sidebar-open': sidebarOpen }">
            <div class="sidebar-header">
                <div class="brand">
                    <div class="brand-icon"><i class="bi bi-cpu-fill"></i></div>
                    <span class="brand-name">AI Tools</span>
                </div>
                <button class="icon-btn" @click="sidebarOpen = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <button class="new-chat-btn" @click="startNewChat">
                <i class="bi bi-plus-lg"></i>
                محادثة جديدة
            </button>

            <div class="history-section">
                <p class="history-label">المحادثات السابقة</p>

                <div v-if="history.length === 0" class="history-empty">
                    <i class="bi bi-chat-dots"></i>
                    <span>لا توجد محادثات بعد</span>
                </div>

                <TransitionGroup name="history-item" tag="div" class="history-list">
                    <button
                        v-for="session in history"
                        :key="session.id"
                        class="history-item"
                        :class="{ active: currentSessionId === session.id }"
                        @click="loadSession(session)"
                    >
                        <i class="bi bi-chat-left-text"></i>
                        <div class="history-item-info">
                            <span class="history-item-title">{{ session.title }}</span>
                            <span class="history-item-date">{{ session.date }}</span>
                        </div>
                    </button>
                </TransitionGroup>
            </div>
        </aside>

        <!-- OVERLAY -->
        <div v-if="sidebarOpen" class="sidebar-overlay" @click="sidebarOpen = false"></div>

        <!-- MAIN AREA -->
        <div class="main-area">

            <!-- TOP BAR -->
            <header class="topbar">
                <div class="topbar-left">
                    <button class="icon-btn menu-btn" @click="sidebarOpen = true">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="model-info" v-if="!toolLoading">
                        <div class="model-avatar">
                            <img v-if="subtool.imageUrl" :src="subtool.imageUrl" :alt="subtool.name" />
                            <i v-else class="bi bi-stars"></i>
                        </div>
                        <div>
                            <p class="model-name">{{ subtool.name }}</p>
                            <p class="model-desc">{{ subtool.description }}</p>
                        </div>
                    </div>
                    <div v-else class="model-info">
                        <div class="model-avatar skeleton-box"></div>
                        <div>
                            <div class="skel-line w-32"></div>
                            <div class="skel-line w-48 mt-1"></div>
                        </div>
                    </div>
                </div>

                <div class="topbar-right">
                    <div class="model-badge">
                        <i class="bi bi-cpu"></i>
                        AI Model
                    </div>
                </div>
            </header>

            <!-- MESSAGES AREA -->
            <div class="messages-wrap" ref="messagesContainer">

                <!-- EMPTY STATE -->
                <div v-if="messages.length === 0 && !toolLoading" class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-stars"></i>
                    </div>
                    <h2 class="empty-title">{{ subtool.name }}</h2>
                    <p class="empty-desc">{{ subtool.description }}</p>
                    <div v-if="subtool.promptPlaceholder" class="suggestion-chip" @click="fillPlaceholder">
                        <i class="bi bi-lightning-charge-fill"></i>
                        {{ subtool.promptPlaceholder }}
                    </div>
                </div>

                <!-- MESSAGES -->
                <TransitionGroup name="msg" tag="div" class="messages-list">
                    <div
                        v-for="msg in messages"
                        :key="msg.id"
                        class="message-row"
                        :class="msg.role"
                    >
                        <div class="msg-avatar" v-if="msg.role === 'assistant'">
                            <i class="bi bi-stars"></i>
                        </div>

                        <div class="msg-bubble">
                            <div class="msg-content" v-html="formatMessage(msg.content)"></div>
                            <span class="msg-time">{{ msg.time }}</span>
                        </div>

                        <div class="msg-avatar user-avatar" v-if="msg.role === 'user'">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    </div>
                </TransitionGroup>

                <!-- TYPING INDICATOR -->
                <div v-if="isTyping" class="message-row assistant">
                    <div class="msg-avatar">
                        <i class="bi bi-stars"></i>
                    </div>
                    <div class="msg-bubble">
                        <div class="typing-dots">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- INPUT AREA -->
            <div class="input-area">
                <div class="input-box" :class="{ focused: inputFocused }">
                    <textarea
                        ref="textareaRef"
                        v-model="userInput"
                        class="chat-input"
                        :placeholder="subtool.promptPlaceholder || 'اكتب رسالتك هنا...'"
                        rows="1"
                        @focus="inputFocused = true"
                        @blur="inputFocused = false"
                        @keydown.enter.exact.prevent="submitMessage"
                        @keydown.shift.enter.exact="newLine"
                        @input="autoResize"
                    ></textarea>

                    <div class="input-actions">
                        <span class="char-count">{{ userInput.length }}</span>
                        <button
                            class="send-btn"
                            :disabled="!userInput.trim() || isTyping"
                            @click="submitMessage"
                        >
                            <i v-if="!isTyping" class="bi bi-send-fill"></i>
                            <i v-else class="bi bi-stop-fill"></i>
                        </button>
                    </div>
                </div>
                <p class="input-hint">
                    <i class="bi bi-info-circle"></i>
                    Enter للإرسال &nbsp;·&nbsp; Shift+Enter لسطر جديد
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, nextTick, watch } from "vue";
import { useRoute } from "vue-router";
import homeService from "@/services/home/homeService";

const route = useRoute();

// ─── STATE ─────────────────────────────────────────────
const toolLoading       = ref(true);
const subtool           = ref({ name: "", description: "", promptPlaceholder: "", imageUrl: "" });
const messages          = ref([]);
const userInput         = ref("");
const isTyping          = ref(false);
const inputFocused      = ref(false);
const sidebarOpen       = ref(false);
const messagesContainer = ref(null);
const textareaRef       = ref(null);
const currentSessionId  = ref(null);
const history           = ref([]);

// ─── LOAD SUBTOOL ───────────────────────────────────────
const loadSubtool = async () => {
    toolLoading.value = true;
    try {
        const res  = await homeService.showSubtool(route.params.slug);
        const data = res?.data || {};
        subtool.value = {
            name:              data.name              || data.translation?.name        || "أداة ذكاء اصطناعي",
            description:       data.description       || data.translation?.description || "",
            promptPlaceholder: data.prompt_placeholder || "",
            imageUrl:          data.image ? `/storage/${data.image}` : "",
        };
    } catch {
        subtool.value = {
            name:              "أداة ذكاء اصطناعي",
            description:       "مساعدك الذكي",
            promptPlaceholder: "اكتب رسالتك...",
            imageUrl:          "",
        };
    } finally {
        toolLoading.value = false;
    }
};

// ─── HELPERS ────────────────────────────────────────────
const now = () => {
    const d = new Date();
    return `${d.getHours()}:${String(d.getMinutes()).padStart(2, "0")}`;
};

const formatMessage = (text) =>
    text
        .replace(/\n/g, "<br>")
        .replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>")
        .replace(/\*(.*?)\*/g, "<em>$1</em>")
        .replace(/`(.*?)`/g, "<code>$1</code>");

const scrollToBottom = async () => {
    await nextTick();
    if (messagesContainer.value)
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
};

const autoResize = () => {
    const el = textareaRef.value;
    if (!el) return;
    el.style.height = "auto";
    el.style.height = Math.min(el.scrollHeight, 160) + "px";
};

const resetTextarea = () => {
    if (textareaRef.value) textareaRef.value.style.height = "auto";
};

const newLine = () => { userInput.value += "\n"; nextTick(autoResize); };

const fillPlaceholder = () => {
    userInput.value = subtool.value.promptPlaceholder;
    textareaRef.value?.focus();
    autoResize();
};

// ─── CHAT ───────────────────────────────────────────────
const buildHistory = () =>
    messages.value
        .slice(0, -1)
        .map(({ role, content }) => ({ role, content }));

const submitMessage = async () => {
    const text = userInput.value.trim();
    if (!text || isTyping.value) return;

    messages.value.push({ id: Date.now(), role: "user", content: text, time: now() });

    if (messages.value.length === 1) {
        const session = {
            id:    Date.now(),
            title: text.length > 40 ? text.slice(0, 40) + "…" : text,
            date:  new Date().toLocaleDateString("ar-EG"),
        };
        currentSessionId.value = session.id;
        history.value.unshift(session);
    }

    userInput.value = "";
    resetTextarea();
    scrollToBottom();
    isTyping.value = true;

    try {
        const res   = await homeService.sendChatMessage(route.params.slug, text, buildHistory());
        const reply = res?.data?.reply || res?.reply || res?.message || "تم استلام رسالتك ✓";
        messages.value.push({ id: Date.now() + 1, role: "assistant", content: reply, time: now() });
    } catch {
        messages.value.push({ id: Date.now() + 1, role: "assistant", content: "حدث خطأ أثناء الاتصال بالخادم. حاول مرة أخرى.", time: now() });
    } finally {
        isTyping.value = false;
        await nextTick();
        scrollToBottom();
    }
};

const startNewChat = () => {
    messages.value         = [];
    currentSessionId.value = null;
    userInput.value        = "";
    sidebarOpen.value      = false;
};

const loadSession = (session) => {
    currentSessionId.value = session.id;
    sidebarOpen.value      = false;
};

// ─── LIFECYCLE ──────────────────────────────────────────
onMounted(loadSubtool);
watch(messages, scrollToBottom, { deep: true });
</script>

<style scoped>
/* ─── RESET & ROOT ─────────────────── */
* { box-sizing: border-box; margin: 0; padding: 0; }

.chat-root {
    display: flex;
    height: 100vh;
    overflow: hidden;
    background: #f8fafc;
    font-family: 'Segoe UI', Tahoma, sans-serif;
    direction: rtl;
}

/* ─── SIDEBAR ───────────────────────── */
.sidebar {
    width: 280px;
    background: #154677;
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    transition: transform 0.3s ease;
    z-index: 100;
    overflow: hidden;
}

.sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.brand { display: flex; align-items: center; gap: 10px; }

.brand-icon {
    width: 34px; height: 34px;
    background: rgba(255,255,255,0.2);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 16px;
}

.brand-name { color: white; font-weight: 700; font-size: 16px; }

.new-chat-btn {
    margin: 14px 12px;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    background: rgba(255,255,255,0.15);
    color: white;
    border: 1px solid rgba(255,255,255,0.25);
    border-radius: 10px;
    padding: 10px 16px;
    font-size: 14px; font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.new-chat-btn:hover { background: rgba(255,255,255,0.25); }

.history-section { flex: 1; overflow-y: auto; padding: 0 12px 16px; }

.history-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: rgba(255,255,255,0.45);
    padding: 8px 4px;
    margin-bottom: 4px;
}

.history-empty {
    display: flex; flex-direction: column; align-items: center; gap: 8px;
    color: rgba(255,255,255,0.4); font-size: 13px; padding: 24px 0;
}
.history-empty i { font-size: 22px; }

.history-list { display: flex; flex-direction: column; gap: 4px; }

.history-item {
    display: flex; align-items: flex-start; gap: 10px;
    background: transparent;
    color: rgba(255,255,255,0.75);
    border: none; border-radius: 8px;
    padding: 10px;
    cursor: pointer; text-align: right;
    transition: background 0.2s, color 0.2s;
    width: 100%;
}
.history-item:hover, .history-item.active {
    background: rgba(255,255,255,0.15);
    color: white;
}
.history-item i { margin-top: 2px; flex-shrink: 0; font-size: 14px; }

.history-item-info { display: flex; flex-direction: column; gap: 2px; overflow: hidden; }

.history-item-title {
    font-size: 13px; font-weight: 500;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.history-item-date { font-size: 11px; color: rgba(255,255,255,0.4); }

.sidebar-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.4);
    z-index: 99;
}

/* ─── MAIN AREA ─────────────────────── */
.main-area {
    flex: 1;
    display: flex; flex-direction: column;
    overflow: hidden;
    background: #ffffff;
}

/* ─── TOPBAR ────────────────────────── */
.topbar {
    height: 70px;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 20px;
    border-bottom: 1px solid #e2e8f0;
    background: #ffffff;
    flex-shrink: 0;
}

.topbar-left { display: flex; align-items: center; gap: 14px; }

.menu-btn { display: none; }

.model-info { display: flex; align-items: center; gap: 12px; }

.model-avatar {
    width: 42px; height: 42px;
    border-radius: 10px;
    background: #154677;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 18px;
    overflow: hidden; flex-shrink: 0;
}
.model-avatar img { width: 100%; height: 100%; object-fit: cover; }

.model-name { font-size: 15px; font-weight: 700; color: #154677; }
.model-desc {
    font-size: 12px; color: #94a3b8; margin-top: 1px;
    max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.model-badge {
    display: flex; align-items: center; gap: 6px;
    background: #e8f0f9; color: #154677;
    padding: 6px 12px; border-radius: 999px;
    font-size: 12px; font-weight: 600;
}

/* ─── MESSAGES ──────────────────────── */
.messages-wrap {
    flex: 1; overflow-y: auto;
    padding: 24px 20px;
    scroll-behavior: smooth;
}
.messages-wrap::-webkit-scrollbar { width: 5px; }
.messages-wrap::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 3px; }

/* EMPTY STATE */
.empty-state {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    min-height: 60vh; text-align: center; gap: 12px; padding: 24px;
}

.empty-icon {
    width: 72px; height: 72px;
    background: linear-gradient(135deg, #154677, #2ba6de);
    border-radius: 20px;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 32px;
    margin-bottom: 8px;
    box-shadow: 0 8px 24px rgba(7,67,119,0.25);
}

.empty-title { font-size: 22px; font-weight: 700; color: #154677; }
.empty-desc  { font-size: 14px; color: #94a3b8; max-width: 360px; line-height: 1.6; }

.suggestion-chip {
    display: flex; align-items: center; gap: 8px;
    background: #e8f0f9; color: #154677;
    border: 1px solid #c3d9f0; border-radius: 10px;
    padding: 10px 16px;
    font-size: 13px; font-weight: 500;
    cursor: pointer; margin-top: 8px;
    transition: background 0.2s, transform 0.15s;
}
.suggestion-chip:hover { background: #d0e4f5; transform: translateY(-2px); }

/* MESSAGES LIST */
.messages-list {
    display: flex; flex-direction: column; gap: 20px;
    max-width: 820px; margin: 0 auto;
}

.message-row { display: flex; align-items: flex-end; gap: 10px; }
.message-row.user { flex-direction: row-reverse; }

.msg-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: #154677; color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; flex-shrink: 0;
}
.user-avatar { background: #e8f0f9; color: #154677; }

.msg-bubble { max-width: 72%; display: flex; flex-direction: column; gap: 4px; }
.message-row.user .msg-bubble { align-items: flex-end; }

.msg-content {
    padding: 12px 16px; border-radius: 16px;
    font-size: 14px; line-height: 1.65; word-break: break-word;
}
.message-row.user .msg-content {
    background: #154677; color: white;
    border-radius: 16px 4px 16px 16px;
}
.message-row.assistant .msg-content {
    background: #f8fafc; color: #1e293b;
    border: 1px solid #e2e8f0;
    border-radius: 4px 16px 16px 16px;
}

.msg-time { font-size: 10px; color: #94a3b8; padding: 0 4px; }

/* TYPING */
.typing-dots {
    display: flex; gap: 5px; align-items: center;
    padding: 14px 18px;
    background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 4px 16px 16px 16px;
}
.typing-dots span {
    width: 7px; height: 7px;
    background: #154677; border-radius: 50%;
    animation: bounce 1.2s infinite;
    opacity: 0.7;
}
.typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.typing-dots span:nth-child(3) { animation-delay: 0.4s; }

@keyframes bounce {
    0%, 80%, 100% { transform: translateY(0); }
    40%            { transform: translateY(-6px); }
}

/* ─── INPUT AREA ────────────────────── */
.input-area {
    padding: 14px 20px 18px;
    border-top: 1px solid #e2e8f0;
    background: #ffffff; flex-shrink: 0;
}

.input-box {
    display: flex; align-items: flex-end; gap: 10px;
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 14px;
    padding: 10px 12px;
    transition: border-color 0.2s, box-shadow 0.2s;
    max-width: 820px; margin: 0 auto;
}
.input-box.focused {
    border-color: #154677;
    box-shadow: 0 0 0 3px rgba(7,67,119,0.08);
}

.chat-input {
    flex: 1;
    background: transparent; border: none; outline: none;
    resize: none;
    font-size: 14px; color: #1e293b; line-height: 1.6;
    font-family: inherit;
    direction: rtl;
    min-height: 24px; max-height: 160px;
}
.chat-input::placeholder { color: #94a3b8; }

.input-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }

.char-count { font-size: 11px; color: #94a3b8; }

.send-btn {
    width: 38px; height: 38px;
    background: #154677; color: white;
    border: none; border-radius: 10px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px;
    transition: background 0.2s, transform 0.15s;
    flex-shrink: 0;
}
.send-btn:hover:not(:disabled) { background: #053660; transform: scale(1.05); }
.send-btn:disabled { background: #c3d9f0; cursor: not-allowed; }

.input-hint {
    text-align: center; font-size: 11px; color: #94a3b8;
    display: flex; align-items: center; justify-content: center; gap: 5px;
    max-width: 820px; margin: 8px auto 0;
}

/* ─── UTILITIES ─────────────────────── */
.icon-btn {
    width: 34px; height: 34px;
    background: transparent; border: none; border-radius: 8px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; color: rgba(255,255,255,0.8);
    transition: background 0.2s;
}
.icon-btn:hover { background: rgba(255,255,255,0.15); }

/* SKELETON */
.skeleton-box {
    background: #e2e8f0;
    animation: pulse 1.5s ease-in-out infinite;
}
.skel-line {
    height: 12px; background: #e2e8f0; border-radius: 6px;
    animation: pulse 1.5s ease-in-out infinite;
}
.w-32 { width: 128px; }
.w-48 { width: 192px; }
.mt-1 { margin-top: 4px; }

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.5; }
}

/* ─── TRANSITIONS ───────────────────── */
.msg-enter-active { transition: all 0.3s ease; }
.msg-enter-from   { opacity: 0; transform: translateY(10px); }

.history-item-enter-active { transition: all 0.2s ease; }
.history-item-enter-from   { opacity: 0; transform: translateX(8px); }

/* ─── RESPONSIVE ────────────────────── */
@media (max-width: 768px) {
    .sidebar {
        position: fixed;
        top: 0; right: 0; height: 100%;
        transform: translateX(100%);
    }
    .sidebar.sidebar-open { transform: translateX(0); }

    .sidebar-overlay { display: block; }

    .menu-btn {
        display: flex !important;
        color: #154677;
    }
    .icon-btn.menu-btn:hover { background: #e8f0f9; }

    .model-desc { display: none; }

    .msg-bubble { max-width: 88%; }
}
</style>
