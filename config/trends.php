<?php

return [
    'main_tool_id' => 7,

    'rate_limits' => [
        'requests_per_minute' => 30,
        'burst_per_second' => 5,
        'fallback_requests_per_minute' => 10,
    ],

    'tools' => [
        'cup-lifting-moment' => [
            'sub_tool_id' => 28,
            'slugs' => ['cup-lifting-moment', 'cup-lift'],
            'endpoint' => 'tasks/trends/cup-lift',
            'selected_model_id' => 46,
            'tool_key' => 'trend_cup_lift',
            'result_message' => 'Your Cup Lift image is ready.',
        ],
        'locker-room' => [
            'sub_tool_id' => 29,
            'slugs' => ['locker-room'],
            'endpoint' => 'tasks/trends/locker-room',
            'selected_model_id' => 46,
            'tool_key' => 'trend_locker_room',
            'result_message' => 'Your Locker Room image is ready.',
        ],
        'players-tunnel' => [
            'sub_tool_id' => 30,
            'slugs' => ['players-tunnel'],
            'endpoint' => 'tasks/trends/players-tunnel',
            'selected_model_id' => 46,
            'tool_key' => 'trend_players-tunnel',
            'result_message' => 'Your Players Tunnel image is ready.',
        ],
        'paparazzi' => [
            'sub_tool_id' => 31,
            'slugs' => ['paparazzi'],
            'endpoint' => 'tasks/trends/paparazzi',
            'selected_model_id' => 46,
            'tool_key' => 'trend_paparazzi',
            'result_message' => 'Your Paparazzi image is ready.',
        ],
        'meet-past-self' => [
            'sub_tool_id' => 33,
            'slugs' => ['interview-your-past-self', 'meet-past-self'],
            'endpoint' => 'tasks/trends/meet-past-self',
            'selected_model_id' => 46,
            'provider' => 'runware',
            'model' => 'bfl:5@1',
            'operation' => 'image_edit',
            'category' => 'AI Image Tools',
            'task' => 'تحرير الصور وإنشاء صور بالذكاء الاصطناعي.',
            'tool_key' => 'trend_meet-past-self',
            'result_message' => 'Your Meet Your Past Self image is ready.',
            'strict_result_response' => true,
            'send_trend_parameter' => true,
        ],
    ],
];
