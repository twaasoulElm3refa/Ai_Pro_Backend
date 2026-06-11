# Prompt Generator Chat Page

- Original chat page: `resources/js/views/home/chat.vue`.
- New page: `resources/js/views/home/chat2.vue`.
- `chat.vue` was not changed and remains the chat page for legacy tools.
- The project uses Vue Router, not Inertia, for these pages.
- The existing route `/:lang/subtool/:slug/chat2/:uuid?` loads `chat2.vue`.
- `resources/js/views/home/show.vue` now selects `chat2` when the loaded main tool ID is `2`; all other main tools still select `chat`.
- `chat2.vue` creates and loads conversations through the existing `chatServices` methods.
- Every Prompt Generator send uses `POST /api/v1/message/send`.
- The payload fixes `sub_tool_id` to `9`, includes the current conversation user ID and UUID, sends the full Prompt Generator state, and sets `debug` to `false`.
- The default state contains all Prompt Generator fields, including `results_count`, `extra_options`, and `last_output`.
- Responses support `results`, `state.last_output`, JSON text fallback, `usage`, and `cost`.
- Structured `results` are rendered as separate cards to avoid duplicating the same content as plain text.
- No condition for tool `9` was added to `MessageController`; the backend remains driven by the dynamic sub-tool config.

## Files Changed

- `resources/js/views/home/chat2.vue`
- `resources/js/views/home/show.vue`
- `tests/Feature/Chat2PromptGeneratorPageTest.php`
- `docs/chat2-prompt-generator.md`

## Verification

- `npm run build`: passed.
- `php artisan test --filter=Chat2PromptGeneratorPageTest`: passed, 2 tests and 17 assertions.
- The provider request in the feature test is intercepted with `Http::fake`; no real external request is made.
