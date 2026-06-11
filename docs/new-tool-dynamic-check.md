# New Tool Dynamic Check

## Verdict
- **Yes:** a new standard tool can now be added through `sub_tools.config` without changing `MessageController`, `MessageRequest`, or creating a new service.
- **Overall status:** improved Hybrid, not fully dynamic, because legacy tools 3-8 still use specialized handlers.

## Verified Flow
1. `POST /api/v1/message/send` enters `MessageController::sendMessage`.
2. Unknown IDs, including test tool 999, do not match legacy conditions and enter `handleDefaultFlow`.
3. `MessageRequest::rules` loads the sub-tool config through `DynamicToolConfigService::configFor`.
4. `state_schema` becomes per-tool Laravel validation rules through `stateValidationRules`.
5. `handleDefaultFlow` saves request state in `messages.metadata.state` and dispatches `GenerateAssistantReplyJob`.
6. The job detects non-empty config and calls `DynamicToolPayloadBuilder::build`.
7. The builder merges `default_state` with request state and starts from `AIPayloadBuilder`.
8. It adds `tool`, `model_key`, endpoint, state, debug, provider, prompt, response format, and `payload_map` values.
9. `AiArabicWriterService::generateReplyWithUsage` posts the final payload to the configured endpoint.

## New Tool Requirements
- Create the `sub_tools` row and a conversation linked to it.
- Set `config.tool_key`, `config.model_key`, `config.state_schema`, `config.default_state`, and `config.payload_map` as needed.
- Set `config.endpoint`; otherwise `sub_tools.endpoint` must be present.
- No Controller condition is required.
- No MessageRequest edit is required.
- No new service is required for the generic asynchronous request/response flow.

## Important Payload Detail
- `config.tool_key` is currently emitted as payload field `tool`.
- `config.model_key` is emitted as payload field `model_key`.

## Remaining Hardcoded Logic
- `MessageController::sendMessage` still contains legacy branches for IDs 3-8:
  Paraphraser, Headline, Social Post, Email, Script, and Product Description.
- `MessageRequest::LEGACY_SUB_TOOL_IDS` and `legacyStateRules` preserve their old validation.
- These branches remain because they have custom synchronous execution, state inference, billing, persistence, and response normalization.
- A new tool needing similarly custom behavior would still need a handler/service; a standard config-driven tool does not.

## Mock Test
- Added `tests/Feature/DynamicNewToolFlowTest.php`.
- It creates sub-tool 999 with config only and sends the real API request.
- `Http::fake` blocks all external requests.
- It verifies endpoint, `sub_tool_id=999`, config tool value, model key, state, and payload mapping.
- It also verifies that `MessageController.php` contains no `999` condition.
- Result: **1 passed, 4 assertions**.

## Full Test Suite
- Command: `php artisan test`
- Result: **31 passed, 10 failed, 290 assertions**.
- The 10 failures are existing fixture issues: several job tests create sub-tools without endpoints, and three API tests omit the required API key.
- The new dynamic tool test passes and makes no real external API call.
