# Prompt Generator Display Fix

- The assistant message path was corrected in `resources/js/views/home/chat2.vue`.
- Text extraction uses `extractPromptGeneratorTexts()` from `resources/js/utils/promptGeneratorResults.js`.
- Prompt Generator `response.message` is no longer added to assistant message content or displayed.
- Direct `results[].text` values are displayed as independent `Prompt N` cards.
- When `results[0].text` is a JSON string, one nested `results` array is parsed and only its `text` values are returned.
- `title`, `subject`, `null`, complete objects, and raw JSON are excluded from display.
- Copy buttons receive only the extracted prompt text.
- Saved message content is used only when no direct or nested result text exists.
- No backend file or legacy `chat.vue` code was changed.

## Verification

- `node --test tests/Frontend/prompt-generator-results.test.mjs`: passed.
- `php artisan test --filter=Chat2PromptGeneratorPageTest`: passed.
- `npm run build`: passed.
