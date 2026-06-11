# Prompt Generator Question And Result Display

- Updated `resources/js/utils/promptGeneratorResults.js` and `resources/js/views/home/chat2.vue`.
- `extractPromptGeneratorDisplayItems()` returns strings only and branches on the Prompt Generator response `type`.
- For `type=question`, the real AI `message` is returned and rendered as a normal assistant bubble.
- For `type=result`, only direct or nested `results[].text` values are returned and rendered as `Prompt N` cards.
- A missing result falls back to parsed or plain `state.last_output`, then safe saved content.
- Result success messages in English or Arabic, including `Generated successfully` and `Success`, are never displayed.
- Prompt Generator `title`, `subject`, `description`, `null`, objects, and raw JSON are ignored.
- Copy buttons receive only the result text string.
- Direct responses, saved messages, and SSE completion all use the same normalization rules.
- No backend file or legacy `chat.vue` code was changed.

## Verification

- `node --test tests/Frontend/prompt-generator-results.test.mjs`: 9 tests passed.
- `php artisan test --filter=Chat2PromptGeneratorPageTest`: 2 tests and 27 assertions passed.
- `php artisan test --filter=PromptGeneratorToolTest`: 1 test and 13 assertions passed.
- `npm run build`: passed.
