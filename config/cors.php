<?php

$fromEnv = array_filter(array_map('trim', explode(
    ',',
    (string) env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:5173'))
)));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
     * Incluir https://*.vercel.app para previsualizaciones y despliegues (patrón con * soportado
     * por fruitcake/php-cors). Los orígenes concretos siguen pudiéndose definir en CORS_ALLOWED_ORIGINS.
     */
    'allowed_origins' => array_values(array_unique(array_merge(
        $fromEnv,
        [
            'https://*.vercel.app',
        ],
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
     * Con false: cabecera Access-Control-Allow-Origin puede reflejarse con menos restricciones
     * para SPAs que solo usan Authorization Bearer (sin cookies al API).
     * Si necesitas cookies de Sanctum en el subdominio del front, pon CORS_SUPPORTS_CREDENTIALS=true.
     */
    'supports_credentials' => filter_var(
        env('CORS_SUPPORTS_CREDENTIALS', 'false'),
        FILTER_VALIDATE_BOOL
    ),
];
