<template>
  <div class="chat-page" :data-theme="theme">

    <!-- Sidebar -->
    <aside class="sidebar" :class="{ collapsed: sidebarCollapsed }">
      <div class="sidebar-header">
        <div class="brand" v-if="!sidebarCollapsed">
          <span class="brand-icon">✦</span>
          <span class="brand-name">NovaMind</span>
        </div>
        <button class="icon-btn collapse-btn" @click="sidebarCollapsed = !sidebarCollapsed">
          {{ sidebarCollapsed ? '▶' : '◀' }}
        </button>
      </div>

      <button class="new-chat-btn" @click="startNewChat">
        <span class="btn-icon">✦</span>
        <span v-if="!sidebarCollapsed">محادثة جديدة</span>
      </button>

      <div class="history-section" v-if="!sidebarCollapsed">
        <p class="history-label">المحادثات السابقة</p>
        <div
          v-for="(session, idx) in chatHistory"
          :key="idx"
          class="history-item"
          :class="{ active: idx === activeSession }"
          @click="loadSession(idx)"
        >
          <span class="history-icon">💬</span>
          <span class="history-title">{{ session.title }}</span>
        </div>
      </div>

      <div class="sidebar-footer" v-if="!sidebarCollapsed">
        <button class="icon-btn theme-btn" @click="toggleTheme">
          {{ theme === 'dark' ? '☀️' : '🌙' }}
          <span>{{ theme === 'dark' ? 'الوضع الفاتح' : 'الوضع الداكن' }}</span>
        </button>
      </div>
    </aside>

    <!-- Main Chat -->
    <main class="chat-main">

      <!-- Top Bar -->
      <header class="chat-topbar">
        <div class="model-selector">
          <span class="model-dot"></span>
          <select v-model="selectedModel" class="model-select">
            <option value="nova-pro">Nova Pro</option>
            <option value="nova-fast">Nova Fast</option>
            <option value="nova-think">Nova Think</option>
          </select>
        </div>
        <div class="topbar-actions">
          <button class="icon-btn" title="مشاركة">⬆</button>
          <button class="icon-btn" title="إعدادات">⚙</button>
        </div>
      </header>

      <!-- Messages Area -->
      <div class="messages-area" ref="messagesArea">

        <!-- Empty State -->
        <div v-if="messages.length === 0" class="empty-state">
          <div class="empty-logo">✦</div>
          <h2 class="empty-title">كيف يمكنني مساعدتك اليوم؟</h2>
          <p class="empty-sub">ابدأ بكتابة سؤالك أو اختر أحد الاقتراحات</p>

          <div class="suggestions-grid">
            <div
              v-for="s in suggestions"
              :key="s.text"
              class="suggestion-card"
              @click="useSuggestion(s.text)"
            >
              <span class="suggestion-icon">{{ s.icon }}</span>
              <span class="suggestion-text">{{ s.text }}</span>
            </div>
          </div>
        </div>

        <!-- Messages -->
        <div
          v-for="(msg, idx) in messages"
          :key="idx"
          class="message-wrapper"
          :class="msg.role"
        >
          <div class="message-avatar">
            <span v-if="msg.role === 'assistant'">✦</span>
            <span v-else>👤</span>
          </div>
          <div class="message-bubble" :class="msg.role">
            <!-- Typing indicator -->
            <div v-if="msg.typing" class="typing-indicator">
              <span></span><span></span><span></span>
            </div>
            <div v-else class="message-text" v-html="renderText(msg.content)"></div>
            <div class="message-time">{{ msg.time }}</div>
          </div>
        </div>
      </div>

      <!-- Input Area -->
      <div class="input-area">
        <div class="input-wrapper" :class="{ focused: inputFocused }">
          <textarea
            ref="inputRef"
            v-model="userInput"
            class="chat-input"
            placeholder="اكتب رسالتك هنا..."
            rows="1"
            dir="auto"
            @keydown.enter.exact.prevent="sendMessage"
            @keydown.shift.enter="addNewLine"
            @focus="inputFocused = true"
            @blur="inputFocused = false"
            @input="autoResize"
          ></textarea>

          <button
            class="send-btn"
            :class="{ active: userInput.trim() }"
            :disabled="!userInput.trim() || isTyping"
            @click="sendMessage"
          >
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M22 2L11 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M22 2L15 22L11 13L2 9L22 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
        </div>
        <p class="input-hint">اضغط Enter للإرسال، Shift+Enter لسطر جديد</p>
      </div>

    </main>
  </div>
</template>

<script setup>
import { ref, nextTick, onMounted } from 'vue'

// ── State ─────────────────────────────────────────────────
const theme            = ref(localStorage.getItem('theme') || 'dark')
const sidebarCollapsed = ref(false)
const selectedModel    = ref('nova-pro')
const userInput        = ref('')
const inputFocused     = ref(false)
const isTyping         = ref(false)
const messages         = ref([])
const messagesArea     = ref(null)
const inputRef         = ref(null)
const activeSession    = ref(0)

const chatHistory = ref([
  { title: 'كيفية تعلم البرمجة', messages: [] },
  { title: 'أفضل مطاعم القاهرة',  messages: [] },
  { title: 'خطة تسويق المنتج',    messages: [] },
])

const suggestions = [
  { icon: '💡', text: 'اشرح لي كيف يعمل الذكاء الاصطناعي' },
  { icon: '✍️', text: 'اكتب لي بريد إلكتروني احترافي' },
  { icon: '🔧', text: 'ساعدني في إصلاح كود برمجي' },
  { icon: '🌍', text: 'ترجم هذا النص إلى الإنجليزية' },
]

// ── Helpers ───────────────────────────────────────────────
const toggleTheme = () => {
  theme.value = theme.value === 'dark' ? 'light' : 'dark'
  localStorage.setItem('theme', theme.value)
}

const getTime = () =>
  new Date().toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' })

const scrollToBottom = async () => {
  await nextTick()
  if (messagesArea.value)
    messagesArea.value.scrollTop = messagesArea.value.scrollHeight
}

const autoResize = () => {
  const el = inputRef.value
  if (!el) return
  el.style.height = 'auto'
  el.style.height = Math.min(el.scrollHeight, 180) + 'px'
}

const addNewLine = () => { userInput.value += '\n'; autoResize() }

const renderText = (text) =>
  text
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/`(.*?)`/g, '<code>$1</code>')
    .replace(/\n/g, '<br>')

// ── Actions ───────────────────────────────────────────────
const startNewChat = () => {
  messages.value = []
  userInput.value = ''
}

const loadSession = (idx) => {
  activeSession.value = idx
  messages.value = chatHistory.value[idx].messages
}

const useSuggestion = (text) => {
  userInput.value = text
  inputRef.value?.focus()
}

const sendMessage = async () => {
  const text = userInput.value.trim()
  if (!text || isTyping.value) return

  // Push user message
  messages.value.push({ role: 'user', content: text, time: getTime() })
  userInput.value = ''
  if (inputRef.value) { inputRef.value.style.height = 'auto' }
  await scrollToBottom()

  // Push typing indicator
  isTyping.value = true
  const typingMsg = { role: 'assistant', content: '', time: '', typing: true }
  messages.value.push(typingMsg)
  await scrollToBottom()

  // Simulate AI response
  await new Promise(r => setTimeout(r, 1200 + Math.random() * 800))

  const reply = generateReply(text)
  const lastIdx = messages.value.length - 1
  messages.value[lastIdx] = {
    role: 'assistant',
    content: reply,
    time: getTime(),
    typing: false,
  }
  isTyping.value = false
  await scrollToBottom()
}

// Mock reply generator (replace with real API call)
const generateReply = (input) => {
  const replies = [
    `شكراً على سؤالك! **"${input.slice(0, 30)}..."**\n\nهذا موضوع مثير للاهتمام. يمكنني مساعدتك في فهمه بشكل أعمق. هل تريد أن أشرح لك بالتفصيل؟`,
    `بناءً على ما ذكرته، يمكنني القول:\n\n- النقطة الأولى: تحليل شامل\n- النقطة الثانية: توصيات عملية\n- النقطة الثالثة: خطوات تنفيذية\n\nهل تحتاج مزيداً من التفاصيل؟`,
    `سؤال ممتاز! دعني أوضح لك:\n\nالإجابة المختصرة هي **نعم**، والسبب في ذلك يعود إلى عدة عوامل مهمة يمكننا مناقشتها.`,
    `فهمت ما تقصده. \`النتيجة\` التي ستحصل عليها ستعتمد على المدخلات التي توفرها. هل يمكنك إعطائي مزيداً من التفاصيل؟`,
  ]
  return replies[Math.floor(Math.random() * replies.length)]
}

onMounted(() => inputRef.value?.focus())
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&display=swap');

/* ── Reset & Root ── */
* { box-sizing: border-box; margin: 0; padding: 0; }

.chat-page {
  display: flex;
  height: 100vh;
  font-family: 'Cairo', sans-serif;
  direction: rtl;
  overflow: hidden;
  transition: background 0.4s ease;
}

/* ═══════════════════════════════════════
   THEME TOKENS
═══════════════════════════════════════ */
[data-theme='dark'] {
  --bg:           #0D1117;
  --sidebar-bg:   #161B22;
  --surface:      #1C2128;
  --surface-h:    #21262D;
  --border:       rgba(255,255,255,0.08);
  --border-h:     rgba(255,255,255,0.18);
  --text:         #e6edf3;
  --text-muted:   #7d8590;
  --text-dim:     #484f58;
  --gold:         #FBBF24;
  --gold-dim:     rgba(251,191,36,0.12);
  --gold-glow:    rgba(251,191,36,0.25);
  --user-bubble:  #1f6feb;
  --user-text:    #ffffff;
  --ai-bubble:    #1C2128;
  --ai-text:      #e6edf3;
  --input-bg:     #1C2128;
  --scrollbar:    #30363d;
  --send-bg:      #FBBF24;
  --send-text:    #0D1117;
  --placeholder:  #484f58;
}

[data-theme='light'] {
  --bg:           #f6f8fa;
  --sidebar-bg:   #ffffff;
  --surface:      #ffffff;
  --surface-h:    #f6f8fa;
  --border:       #d0d7de;
  --border-h:     #8c959f;
  --text:         #1f2328;
  --text-muted:   #6e7781;
  --text-dim:     #9198a1;
  --gold:         #0969da;
  --gold-dim:     rgba(9,105,218,0.08);
  --gold-glow:    rgba(9,105,218,0.20);
  --user-bubble:  #0969da;
  --user-text:    #ffffff;
  --ai-bubble:    #ffffff;
  --ai-text:      #1f2328;
  --input-bg:     #ffffff;
  --scrollbar:    #d0d7de;
  --send-bg:      #0969da;
  --send-text:    #ffffff;
  --placeholder:  #9198a1;
}

/* ═══════════════════════════════════════
   SIDEBAR
═══════════════════════════════════════ */
.sidebar {
  width: 260px;
  min-width: 260px;
  background: var(--sidebar-bg);
  border-left: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  padding: 1rem;
  gap: 0.75rem;
  transition: width 0.3s ease, min-width 0.3s ease;
  overflow: hidden;
}

.sidebar.collapsed {
  width: 64px;
  min-width: 64px;
  padding: 1rem 0.6rem;
}

.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.brand {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.brand-icon {
  font-size: 1.4rem;
  color: var(--gold);
}

.brand-name {
  font-size: 1.1rem;
  font-weight: 900;
  color: var(--text);
  letter-spacing: -0.02em;
}

.icon-btn {
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--text-muted);
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 0.7rem;
  transition: all 0.2s;
}
.icon-btn:hover {
  border-color: var(--border-h);
  color: var(--text);
  background: var(--surface-h);
}

.new-chat-btn {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  background: var(--gold-dim);
  border: 1px solid var(--border);
  border-radius: 10px;
  color: var(--gold);
  font-family: 'Cairo', sans-serif;
  font-weight: 700;
  font-size: 0.85rem;
  padding: 0.55rem 0.9rem;
  cursor: pointer;
  transition: all 0.25s;
  white-space: nowrap;
}
.new-chat-btn:hover {
  background: var(--gold);
  color: var(--send-text);
  border-color: var(--gold);
  box-shadow: 0 4px 16px var(--gold-glow);
}

.btn-icon { font-size: 0.9rem; }

.history-section { flex: 1; overflow-y: auto; }
.history-label {
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--text-dim);
  letter-spacing: 0.08em;
  text-transform: uppercase;
  margin-bottom: 0.5rem;
  padding: 0 0.25rem;
}

.history-item {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.5rem 0.6rem;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  color: var(--text-muted);
  font-size: 0.82rem;
}
.history-item:hover { background: var(--surface-h); color: var(--text); }
.history-item.active { background: var(--gold-dim); color: var(--gold); }

.history-icon { font-size: 0.8rem; flex-shrink: 0; }
.history-title {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar-footer { margin-top: auto; }

.theme-btn {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.6rem;
  font-size: 0.82rem;
  color: var(--text-muted);
  justify-content: flex-start;
  border-radius: 8px;
}
.theme-btn span { font-size: 0.82rem; }

/* ═══════════════════════════════════════
   MAIN CHAT
═══════════════════════════════════════ */
.chat-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  background: var(--bg);
  overflow: hidden;
}

/* Topbar */
.chat-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1.5rem;
  border-bottom: 1px solid var(--border);
  background: var(--bg);
  flex-shrink: 0;
}

.model-selector {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.model-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: #3fb950;
  box-shadow: 0 0 6px #3fb950;
  flex-shrink: 0;
}

.model-select {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--text);
  font-family: 'Cairo', sans-serif;
  font-size: 0.85rem;
  font-weight: 600;
  padding: 0.3rem 0.75rem;
  cursor: pointer;
  outline: none;
  transition: border-color 0.2s;
}
.model-select:hover { border-color: var(--border-h); }

.topbar-actions {
  display: flex;
  gap: 0.4rem;
}

/* ═══════════════════════════════════════
   MESSAGES
═══════════════════════════════════════ */
.messages-area {
  flex: 1;
  overflow-y: auto;
  padding: 2rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  scrollbar-width: thin;
  scrollbar-color: var(--scrollbar) transparent;
}
.messages-area::-webkit-scrollbar { width: 5px; }
.messages-area::-webkit-scrollbar-thumb {
  background: var(--scrollbar);
  border-radius: 10px;
}

/* Empty State */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex: 1;
  text-align: center;
  padding: 3rem 1rem;
  animation: fadeIn 0.5s ease;
}

.empty-logo {
  font-size: 3rem;
  color: var(--gold);
  margin-bottom: 1rem;
  animation: pulse 2.5s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.1); opacity: 0.8; }
}

.empty-title {
  font-size: 1.6rem;
  font-weight: 900;
  color: var(--text);
  margin-bottom: 0.5rem;
}

.empty-sub {
  font-size: 0.9rem;
  color: var(--text-muted);
  margin-bottom: 2rem;
}

.suggestions-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.75rem;
  max-width: 520px;
  width: 100%;
}

.suggestion-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 1rem;
  cursor: pointer;
  text-align: right;
  transition: all 0.25s;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.suggestion-card:hover {
  border-color: var(--border-h);
  background: var(--surface-h);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(0,0,0,0.12);
}

.suggestion-icon { font-size: 1.3rem; }
.suggestion-text { font-size: 0.8rem; color: var(--text-muted); line-height: 1.4; }

/* Message rows */
.message-wrapper {
  display: flex;
  gap: 0.75rem;
  max-width: 820px;
  width: 100%;
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

.message-wrapper.user {
  flex-direction: row-reverse;
  align-self: flex-end;
}
.message-wrapper.assistant {
  align-self: flex-start;
}

.message-avatar {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: var(--gold-dim);
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.9rem;
  flex-shrink: 0;
  color: var(--gold);
}

.message-bubble {
  max-width: 72%;
  padding: 0.75rem 1rem;
  border-radius: 16px;
  position: relative;
}

.message-bubble.user {
  background: var(--user-bubble);
  color: var(--user-text);
  border-radius: 16px 4px 16px 16px;
}

.message-bubble.assistant {
  background: var(--ai-bubble);
  color: var(--ai-text);
  border: 1px solid var(--border);
  border-radius: 4px 16px 16px 16px;
}

.message-text {
  font-size: 0.9rem;
  line-height: 1.7;
  word-break: break-word;
}

.message-text :deep(strong) { font-weight: 700; }
.message-text :deep(code) {
  background: rgba(0,0,0,0.15);
  border-radius: 4px;
  padding: 0.1em 0.4em;
  font-size: 0.85em;
  font-family: monospace;
}

.message-time {
  font-size: 0.65rem;
  color: rgba(255,255,255,0.5);
  margin-top: 0.4rem;
  text-align: left;
}

.message-bubble.assistant .message-time {
  color: var(--text-dim);
}

/* Typing Indicator */
.typing-indicator {
  display: flex;
  gap: 5px;
  padding: 0.25rem 0;
  align-items: center;
}

.typing-indicator span {
  width: 7px; height: 7px;
  background: var(--text-muted);
  border-radius: 50%;
  animation: bounce 1.2s ease-in-out infinite;
}
.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

@keyframes bounce {
  0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
  40% { transform: translateY(-6px); opacity: 1; }
}

/* ═══════════════════════════════════════
   INPUT AREA
═══════════════════════════════════════ */
.input-area {
  padding: 1rem 1.5rem 1.25rem;
  background: var(--bg);
  border-top: 1px solid var(--border);
  flex-shrink: 0;
}

.input-wrapper {
  display: flex;
  align-items: flex-end;
  gap: 0.6rem;
  background: var(--input-bg);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 0.6rem 0.6rem 0.6rem 1rem;
  transition: border-color 0.25s, box-shadow 0.25s;
}

.input-wrapper.focused {
  border-color: var(--gold);
  box-shadow: 0 0 0 3px var(--gold-dim);
}

.chat-input {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  color: var(--text);
  font-family: 'Cairo', sans-serif;
  font-size: 0.9rem;
  line-height: 1.6;
  resize: none;
  max-height: 180px;
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: var(--scrollbar) transparent;
}

.chat-input::placeholder { color: var(--placeholder); }

.send-btn {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  border: none;
  background: var(--border);
  color: var(--text-dim);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: not-allowed;
  transition: all 0.25s;
  flex-shrink: 0;
}

.send-btn svg {
  width: 16px; height: 16px;
}

.send-btn.active {
  background: var(--send-bg);
  color: var(--send-text);
  cursor: pointer;
  box-shadow: 0 4px 14px var(--gold-glow);
}

.send-btn.active:hover {
  transform: scale(1.08);
  box-shadow: 0 6px 20px var(--gold-glow);
}

.input-hint {
  text-align: center;
  font-size: 0.7rem;
  color: var(--text-dim);
  margin-top: 0.5rem;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to   { opacity: 1; }
}

/* ── Responsive ── */
@media (max-width: 640px) {
  .sidebar { display: none; }
  .suggestions-grid { grid-template-columns: 1fr; }
  .message-bubble { max-width: 88%; }
}
</style>



