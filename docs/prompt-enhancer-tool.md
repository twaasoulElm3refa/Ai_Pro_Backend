# Prompt Enhancer Tool

## Implementation

- Added dynamic sub-tool `10` with tool key `ai_prompt_enhancer`, model key
  `prompt_enhancer`, provider `openrouter`, and endpoint `/enhance/prompt`.
- `PromptEnhancerSeeder` stores the system prompt, schema, defaults, payload map,
  response format, error message, and Arabic/English translations.
- The dynamic payload builder now supports config-driven defaults for null/empty
  state values and config-driven extraction of `original_prompt` from
  `user_message`.
- No condition for tool 10 was added to `MessageController`.

## Response Handling

- Result responses save and display only `results[].text`.
- `state.last_output` is updated with the cleaned final output.
- Control separators such as `\u001e` are converted to readable hyphens.
- Structured provider errors and exhausted transport failures are persisted as
  assistant error messages with tool/provider/model metadata.
- `debug=true` stores the final payload, state, raw response, usage, and cost.

## Chat

- Sub-tools 9 and 10 open the existing prompt-tools page at `chat2.vue`.
- Tool 10 sends its own state contract through `/api/v1/message/send`.
- Arabic displays `محسن البرومبتات`; results use `البرومبت المحسن` or numbered
  `النسخة 1`, `النسخة 2`, and so on.
- Each result card copies only its cleaned text.

## Verification

- Prompt Enhancer feature tests: 5 passed, 39 assertions.
- Prompt Generator page tests: 2 passed, 28 assertions.
- Dynamic tool regression tests: 7 passed, 28 assertions.
- Frontend helper tests: 13 passed.
- Vite production build: passed.
- Full Laravel suite: 40 passed, 10 pre-existing failures caused by unrelated
  test fixtures missing endpoints or authentication.
