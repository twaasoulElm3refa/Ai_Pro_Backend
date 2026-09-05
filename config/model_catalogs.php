<?php

$codeToolSlug = trim((string) env('FREE_AI_GENERAL_CODE_TOOL_SLUG', ''));

return [
    'free_ai_tools' => [
        'chat-writing' => 'general_chat',
        'programming-technology' => 'general_code',
        // Additional verified tool slugs can be mapped to sources here.
    ] + ($codeToolSlug !== '' ? [$codeToolSlug => 'general_code'] : []),

    'sources' => [
        'general_chat' => [
            'endpoint' => env(
                'MODEL_CATALOG_GENERAL_CHAT_URL',
                rtrim(env('AIARABIC_BASE_URL', 'https://api.aiarabic.com'), '/')
                    .'/tasks/general-tools/general_chat/models'
            ),
            'requires_internal_key' => true,
            'internal_key_config' => 'services.aiarabic.internal_api_key',
        ],
        'general_code' => [
            'endpoint' => env(
                'MODEL_CATALOG_GENERAL_CODE_URL',
                rtrim(env('AIARABIC_BASE_URL', 'https://api.aiarabic.com'), '/')
                    .'/tasks/general-tools/general_code/models'
            ),
            'requires_internal_key' => true,
            'internal_key_config' => 'services.aiarabic.internal_api_key',
        ],
    ],

    'timeout' => (int) env('MODEL_CATALOG_TIMEOUT', 20),
];
