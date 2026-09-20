<?php

return [
    'main_tool_id' => 7,

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
    ],
];
