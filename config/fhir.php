<?php

return [
    'base_url' => env('FHIR_BASE_URL', 'https://hapi.fhir.org/baseR4'),
    'bearer_token' => env('FHIR_BEARER_TOKEN'),
    'connect_timeout' => 5,
    'timeout' => 15,
];
