# Prompt Generator Result Display

- The display change is in `resources/js/views/home/chat2.vue`.
- Result extraction lives in `resources/js/utils/promptGeneratorResults.js`.
- Prompt Generator responses now use `response.results` as the primary source.
- Every result renders `item.text`; optional `item.title` and `item.subject` are also displayed.
- The fallback order is `result`, `message`, `state.last_output`, then the stored message content.
- JSON strings in `state.last_output` are parsed when they contain a `results` array.
- A single nested JSON wrapper inside `results[].text` is unwrapped into the inner result cards.
- Raw JSON and duplicate result text are suppressed from the normal message content.
- Copy buttons copy only the normalized result `text`.
- Non-Prompt Generator messages are not converted into result cards.
- `resources/js/views/home/chat.vue` was not modified.

## Verification

- `node --test tests/Frontend/prompt-generator-results.test.mjs`: passed, 4 tests.
- `php artisan test --filter=Chat2PromptGeneratorPageTest`: passed.
- `npm run build`: passed.
- The existing PHP feature test uses `Http::fake`; no real provider request is made.
