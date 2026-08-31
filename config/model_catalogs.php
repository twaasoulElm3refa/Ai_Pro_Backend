<?php

return [
    'free_ai_tools' => [
        'chat-writing' => 'general_chat',
    ],

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
    ],

    'timeout' => (int) env('MODEL_CATALOG_TIMEOUT', 20),
];
