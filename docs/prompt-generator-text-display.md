# Prompt Generator Text Display

- The result cards were updated in `resources/js/views/home/chat2.vue`.
- Text extraction is handled by `extractPromptGeneratorTexts()` in `resources/js/utils/promptGeneratorResults.js`.
- The helper returns an array of strings only, sourced from `results[].text`.
- Each result is displayed under an automatic `Prompt 1`, `Prompt 2`, and so on label.
- Prompt Generator `title`, `subject`, `meta`, and `null` values are never passed to the display.
- If a result text contains one JSON wrapper with inner `results`, the helper displays only the inner `text` values.
- If direct results are absent, JSON `state.last_output` is checked, followed by saved message content.
- Raw JSON and complete objects are not rendered.
- The copy button receives and copies the extracted text string only.
- The backend and legacy `resources/js/views/home/chat.vue` were not modified.

## Verification

- `node --test tests/Frontend/prompt-generator-results.test.mjs`: passed, 5 tests.
- `php artisan test --filter=Chat2PromptGeneratorPageTest`: passed.
- `npm run build`: passed.
