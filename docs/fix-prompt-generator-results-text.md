# Prompt Generator Results Text Fix

- Updated `resources/js/utils/promptGeneratorResults.js` and the assistant rendering path in `resources/js/views/home/chat2.vue`.
- The main issue was in both places: wrapped response layers needed stronger extraction, and saved/streamed status content needed explicit suppression.
- `extractPromptGeneratorTexts()` now checks `payload.results`, `payload.data.results`, metadata results, and JSON `state.last_output`.
- A JSON string inside `results[].text` is parsed once and only inner `results[].text` strings are returned.
- `response.message`, `title`, `subject`, `description`, objects, `null`, and raw JSON are never returned as prompt results.
- English and Arabic success messages are classified as status text and cannot be used as fallback output.
- `chat2.vue` adds assistant cards from `directResponse.results` only and suppresses status content during saved-message and SSE rendering.
- Copy buttons continue to receive only the extracted prompt string.
- No backend file or legacy `chat.vue` code was changed.

## Verification

- `node --test tests/Frontend/prompt-generator-results.test.mjs`: passed.
- `php artisan test --filter=Chat2PromptGeneratorPageTest`: passed.
- `php artisan test --filter=PromptGeneratorToolTest`: passed.
- `npm run build`: passed.
