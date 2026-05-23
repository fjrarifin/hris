<?php

return [
    'base_url' => env('FINGERSPOT_BASE_URL', 'https://developer.fingerspot.io/api'),
    'api_token' => env('FINGERSPOT_API_TOKEN'),
    'default_cloud_id' => env('FINGERSPOT_CLOUD_ID'),

    'clouds' => array_values(array_filter([
        [
            'id' => env('FINGERSPOT_CLOUD_ID_OFFICE'),
            'name' => 'Hompim Office',
        ],
        [
            'id' => env('FINGERSPOT_CLOUD_ID_PLAY'),
            'name' => 'Hompim Play',
        ],
        [
            'id' => env('FINGERSPOT_CLOUD_ID_TJIMANOEK_43'),
            'name' => 'Tjimanoek 43',
        ],
    ], fn (array $cloud) => filled($cloud['id']))),
];
