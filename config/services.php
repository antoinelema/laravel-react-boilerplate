<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],


    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],


    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'Prospecto/1.0'),
    ],


    'hunter' => [
        'api_key' => env('HUNTER_API_KEY'),
        'base_url' => env('HUNTER_BASE_URL', 'https://api.hunter.io/v2'),
    ],

    // Configuration des services d'enrichissement web
    'web_enrichment' => [
        'enable_duckduckgo' => env('WEB_ENRICHMENT_ENABLE_DUCKDUCKGO', true),
        'enable_google_search' => env('WEB_ENRICHMENT_ENABLE_GOOGLE_SEARCH', false),
        'enable_universal_scraper' => env('WEB_ENRICHMENT_ENABLE_UNIVERSAL_SCRAPER', true),
        'timeout' => env('WEB_ENRICHMENT_TIMEOUT', 30),
        // Configuration d'éligibilité
        'refresh_after_days' => env('WEB_ENRICHMENT_REFRESH_AFTER_DAYS', 1), // Réduit à 1 jour pour les tests
        'min_completeness_score' => env('WEB_ENRICHMENT_MIN_COMPLETENESS_SCORE', 60), // Réduit de 80 à 60
        'max_attempts' => env('WEB_ENRICHMENT_MAX_ATTEMPTS', 3),
    ],

    // Configuration DuckDuckGo Search (gratuit)
    'duckduckgo' => [
        'base_url' => env('DUCKDUCKGO_BASE_URL', 'https://html.duckduckgo.com'),
        'timeout' => env('DUCKDUCKGO_TIMEOUT', 30),
    ],

    // Configuration Google Search avec Selenium (nécessite serveur Selenium)
    'google_search' => [
        'selenium_host' => env('GOOGLE_SEARCH_SELENIUM_HOST', 'http://localhost:4444'),
        'timeout' => env('GOOGLE_SEARCH_TIMEOUT', 30),
    ],

    // Configuration Universal Scraper
    'universal_scraper' => [
        'timeout' => env('UNIVERSAL_SCRAPER_TIMEOUT', 30),
        'user_agent' => env('UNIVERSAL_SCRAPER_USER_AGENT', 'Mozilla/5.0 (compatible; Prospecto/1.0)'),
    ],

];
