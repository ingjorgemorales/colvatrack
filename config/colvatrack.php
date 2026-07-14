<?php

return [
    'gps' => [
        'base_url' => env('GPS_SERVICETRACK_BASE_URL', 'http://apis.gservicetrack.com:1880/triplog/'),
        'client' => env('GPS_SERVICETRACK_CLIENTE', 'trackingvip'),
        'api_key' => env('GPS_SERVICETRACK_API_KEY'),
        'interval_seconds' => (int) env('GPS_SERVICETRACK_INTERVAL_SECONDS', 10),
        'daily_limit' => (int) env('GPS_SERVICETRACK_DAILY_LIMIT', 8000),
        'moviles' => env('GPS_SERVICETRACK_MOVILES', ''),
    ],
    'location' => [
        'update_interval_seconds' => (int) env('LOCATION_UPDATE_INTERVAL_SECONDS', 60),
        'max_age_minutes' => (int) env('LOCATION_MAX_AGE_MINUTES', 10),
        'required_roles' => ['Tecnico'],
    ],
];
