<?php

return [
    'send_rate_per_minute' => (int) env('FREE_AI_CHAT_SEND_RATE_PER_MINUTE', 15),
    'media_send_rate_per_minute' => (int) env('FREE_AI_MEDIA_SEND_RATE_PER_MINUTE', 20),
    'conversation_create_rate_per_minute' => (int) env('FREE_AI_CONVERSATION_CREATE_RATE_PER_MINUTE', 30),
];
