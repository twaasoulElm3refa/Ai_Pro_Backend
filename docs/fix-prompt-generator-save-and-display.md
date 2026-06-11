# Prompt Generator Save and Display Fix

## Root Cause

`AiArabicWriterService::extractContent()` preferred the provider `message` field, so
`GenerateAssistantReplyJob::handle()` received `Prompt generated successfully.` as
`reply` and stored it in `messages.content` before considering `results`.

## Backend Fix

- Added `App\Services\AI\AssistantResponseContentResolver`.
- `GenerateAssistantReplyJob::handle()` now resolves the user-visible content before
  creating the assistant message.
- For Prompt Generator `type=question`, `messages.content` is `response.message`.
- For Prompt Generator `type=result`, `messages.content` is the non-empty
  `response.results[].text` values joined with a blank line.
- Nested JSON result text and `state.last_output` are supported as fallbacks.
- Success/status messages are never saved as result content.
- Dynamic metadata keeps `type`, `message`, `results`, `state`, `usage`, and `cost`.
- No Prompt Generator condition was added to `MessageController`.

## Frontend Fix

`resources/js/views/home/chat2.vue` now uses the shared
`resources/js/utils/promptGeneratorResults.js` helper for normalization:

- Questions render as a normal assistant message.
- Results render as Prompt cards containing only `results[].text`.
- Success messages, title, subject, null values, and raw JSON are not displayed.
- Copy actions continue to receive the normalized prompt text only.

## Verification

- `php artisan test --filter=PromptGenerator`: 4 passed, 47 assertions.
- `node --test tests/Frontend/prompt-generator-results.test.mjs`: 9 passed.
- `npm run build`: passed.
- All provider requests in tests use `Http::fake`; no real API calls were made.
