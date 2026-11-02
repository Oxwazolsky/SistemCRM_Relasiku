<?php

return [
    'enabled' => (bool) env('WHATSAPP_CLOUD_ENABLED', false),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'app_secret' => env('WHATSAPP_APP_SECRET'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),
    'baileys_enabled' => (bool) env('BAILEYS_DEMO_ENABLED', false),
    'baileys_secret' => env('BAILEYS_DEMO_SECRET'),
];
