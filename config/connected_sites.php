<?php

return [
    /*
    | Push catalog events to WordPress during automated tests. Leave false so
    | ordinary catalog writes do not HTTP-post to a bound site URL.
    */
    'deliver_in_tests' => (bool) env('CONNECTED_SITES_DELIVER_IN_TESTS', false),

    'event_replay_window_seconds' => 300,

    'delivery_timeout_seconds' => 8,

    'max_delivery_attempts' => 10,
];
