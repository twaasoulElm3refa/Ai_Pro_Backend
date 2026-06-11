# Prompt Generator Tool

## Result
- Prompt Generator is running through the existing dynamic tool flow.
- `sub_tool_id=9` now exists in the current database.
- No Prompt Generator condition was added to `MessageController`.
- The route remains `POST /api/v1/message/send`.

## Dynamic Flow
1. `MessageController::sendMessage`
2. `MessageController::handleDefaultFlow`
3. `GenerateAssistantReplyJob`
4. `DynamicToolPayloadBuilder`
5. `AiArabicWriterService`

## Final Config
```json
{
  "tool_key": "ai_prompt_generator",
  "model_key": "prompt_generator",
  "provider": "openrouter",
  "endpoint": "/generate/prompt",
  "state_schema": {
    "task": ["nullable", "string", "max:5000"],
    "target_ai_tool": ["nullable", "string", "max:100"],
    "output_type": ["nullable", "string", "max:100"],
    "language": ["nullable", "string", "max:50"],
    "tone": ["nullable", "string", "max:50"],
    "audience": ["nullable", "string", "max:250"],
    "prompt_style": ["nullable", "string", "max:100"],
    "detail_level": ["nullable", "string", "max:100"],
    "include_constraints": ["nullable", "boolean"],
    "results_count": ["nullable", "integer", "min:1", "max:10"],
    "extra_options": {
      "type": "array",
      "nullable": true,
      "items": ["string", "max:150"]
    },
    "last_output": ["nullable"]
  },
  "default_state": {
    "task": null,
    "target_ai_tool": null,
    "output_type": null,
    "language": null,
    "tone": null,
    "audience": null,
    "prompt_style": null,
    "detail_level": null,
    "include_constraints": null,
    "results_count": null,
    "extra_options": [],
    "last_output": null
  },
  "payload_map": {
    "content": "user_message"
  },
  "response_format": "results"
}
```

## Endpoint Rule
- The seeder preserves an existing `sub_tools.endpoint`.
- When no endpoint exists, it stores and uses `/generate/prompt`.
- The current database row uses `/generate/prompt`.

## Response Handling
- Dynamic responses now store `success`, `type`, `tool`, `provider`, and `model_key` in assistant metadata.
- Provider state is merged with the request/default state.
- `state.last_output` stores the generated assistant text.
- `results` stays as a separate metadata array; it is not JSON-wrapped inside message text.
- Other tools keep their existing response paths.

## Tests
- Command: `php artisan test --filter=PromptGeneratorToolTest`
- Result: **1 passed, 13 assertions**.
- The test uses `Http::fake`; no real provider request is made.
- Legacy tools 3-8 regression set: **16 passed, 207 assertions**.
- The test confirms payload endpoint, tool, model, state fields, results, `last_output`, and absence of an ID 9 condition in `MessageController`.

## Modified Files
- `database/seeders/PromptGeneratorSeeder.php`
- `database/seeders/DatabaseSeeder.php`
- `app/Jobs/GenerateAssistantReplyJob.php`
- `tests/Feature/PromptGeneratorToolTest.php`
- `docs/prompt-generator-tool.md`
