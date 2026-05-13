https://www.figma.com/make/tPjAeeLRUUrL6Sjy6HYb2W/User-request?p=f&t=4gcM8eRcsgZc9UMW-0&fullscreen=1
في لوحة الأدمن:

إجمالي التوكنز اليوم.
إجمالي التكلفة بالدولار.
إجمالي النقاط المخصومة.
أكثر المستخدمين استهلاكًا.
أكثر tool مستخدم.
آخر أخطاء AI.
محادثات وصلت limit.
إجمالي أرباح PayPal مقابل تكلفة AI.

===================================================

تحسين Memory وQdrant

عندك Qdrant بيخزن الرسائل ويعمل search بالـ vector.
اللي ممكن يتعمل:

تخزين role مع كل رسالة: user/assistant.
تخزين sub_tool_id.
تخزين tokens.
جلب آخر 6 رسائل + أهم 5 رسائل semantic.
تلخيص المحادثة بعد عدد معين من الرسائل.
تخزين conversation summary في DB أو Qdrant.
منع تكرار نفس الرسالة في Qdrant.

الأفضل للـ context:

آخر 6 رسائل + ملخص المحادثة + نتائج Qdrant المهمة

مش آخر 6 بس.

8. Conversation Summary

بدل ما كل مرة تبعت كل التاريخ، اعمل جدول:

conversation_summaries

فيه:

conversation_id
summary
last_message_id
tokens_count

كل 10 رسائل مثلًا، شغل Job يلخص آخر جزء ويحدث الملخص.
ده يقلل التكلفة جدًا ويحسن الذاكرة.

=====================================================

10. نظام Refund لو الرد فشل

لو اتخصم من المستخدم وبعدها الـ assistant message فشل، لازم تعمل refund تلقائي.

مثلاً:

wallet_transactions:
type = debit / credit / refund
reason = ai_generation_failed

ده مهم جدًا لو المشروع هيبقى مدفوع.

====================================================

12. عرض الرصيد في الشات Live

بعد كل رسالة، رجّع:

wallet_balance
points_charged
tokens_used

وفي الواجهة اعرض:

تم خصم 3 نقاط
رصيدك الحالي 994 نقطة

ده يعطي شفافية للمستخدم.



 <section class="ai-overlap-section" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <div class="ai-premium-container">

            <!-- TOP BADGES -->
            <div class="ai-container-badges">
                <span class="ai-main-badge">
                    AI PLATFORM
                </span>

                <span class="ai-soft-badge">
                    <span class="ai-dot"></span>
                    Smart Writing
                </span>
            </div>

            <!-- CONTENT GRID -->
            <div class="ai-container-grid">

                <!-- LEFT CONTENT -->
                <div class="ai-container-content">
                    <div class="ai-brand-line">
                        <div class="ai-mini-logo">
                            <img
                                src="/images/Ai_logo.png"
                                alt="AiPro Logo"
                                width="42"
                                height="42"
                                loading="lazy"
                                decoding="async"
                            />
                        </div>

                        <span>PREMIUM AI PLATFORM</span>
                    </div>

                    <h1 class="ai-container-title">
                        أدوات ذكية لإنجاز أسرع
                    </h1>

                    <p class="ai-container-desc">
                        اكتب، لخص، وحرّر محتواك من مكان واحد بتجربة بسيطة وسريعة.
                    </p>

                    <div class="ai-info-cards">
                        <div class="ai-info-card">
                            <h3>كتابة أذكى</h3>
                            <p>أنشئ محتوى واضحًا ومنظمًا خلال ثوانٍ.</p>
                        </div>

                        <div class="ai-info-card">
                            <h3>تجربة سهلة</h3>
                            <p>اختر الأداة المناسبة وابدأ مباشرة بدون تعقيد.</p>
                        </div>
                    </div>
                </div>

                <!-- RIGHT VISUAL -->
                <div class="ai-container-visual">
                    <div class="ai-feature-chip">
                        <div class="ai-feature-icon">
                            <i class="bi bi-stars"></i>
                        </div>

                        <div>
                            <strong>AI Writer</strong>
                            <span>Smart Content Generation</span>
                        </div>
                    </div>

                    <div class="ai-preview-card">
                        <div class="ai-preview-overlay"></div>

                        <div class="ai-preview-content">
                            <span>AiPro</span>
                            <h2>Smart Writing Assistant</h2>
                            <p>Generate, refine and summarize content with AI.</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>
