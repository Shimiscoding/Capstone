<?php

return [
    'active_seconds' => (int) env('OFFICER_LOCATION_ACTIVE_SECONDS', 30),
    'offline_seconds' => (int) env('OFFICER_LOCATION_OFFLINE_SECONDS', 120),
    'history_interval_seconds' => (int) env('OFFICER_LOCATION_HISTORY_INTERVAL_SECONDS', 45),
    'history_minimum_distance_meters' => (float) env('OFFICER_LOCATION_HISTORY_DISTANCE_METERS', 25),
    'low_accuracy_meters' => (float) env('OFFICER_LOCATION_LOW_ACCURACY_METERS', 50),
    'map_center' => [
        'latitude' => (float) env('OFFICER_LOCATION_MAP_LATITUDE', 11.2445),
        'longitude' => (float) env('OFFICER_LOCATION_MAP_LONGITUDE', 125.0031),
    ],
];
