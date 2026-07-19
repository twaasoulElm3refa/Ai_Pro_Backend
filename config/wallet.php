<?php

return [
    // One USD currently buys one million internal points. Keep this explicit and versioned.
    'points_per_usd' => (int) env('WALLET_POINTS_PER_USD', 1_000_000),
];
