<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Autorise l'admin React (navigateur) à appeler l'API.
    | Le mobile Flutter natif n'est pas soumis au CORS navigateur, mais les
    | mêmes origines / headers restent utiles pour tests web / WebView.
    |
    | Auth prévue : Bearer tokens Sanctum (supports_credentials = false).
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
    | Liste d'origines séparées par des virgules dans .env :
    | CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173
    |
    | En local, défaut = origines Vite / Laravel courantes.
    | En production, définir explicitement le domaine de l'admin.
    */
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'CORS_ALLOWED_ORIGINS',
            'http://localhost:5173,http://127.0.0.1:5173,http://localhost:3000,http://127.0.0.1:3000,http://localhost:4173,http://127.0.0.1:4173,http://localhost:8080,http://127.0.0.1:8080'
        ))
    ))),

    'allowed_origins_patterns' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS_PATTERNS', ''))
    ))),

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Authorization'],

    'max_age' => 60 * 60 * 24,

    /*
    | false = adapté aux tokens Bearer (admin + mobile).
    | true uniquement si auth cookie SPA same-site / stateful Sanctum.
    */
    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),

];
