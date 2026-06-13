<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modo de tenencia
    |--------------------------------------------------------------------------
    | single — una sola BD (desarrollo legacy, sin subdominio).
    | multi  — subdominio por cliente + BD master + BD por tenant.
    */
    'mode' => env('TENANCY_MODE', 'single'),

    'base_domain' => env('TENANT_BASE_DOMAIN', 'localhost'),

    'master_subdomain' => env('TENANT_MASTER_SUBDOMAIN', 'master'),

    'reserved_subdomains' => array_filter(array_map(
        'trim',
        explode(',', env('TENANT_RESERVED_SUBDOMAINS', 'www,api,onboarding,mail'))
    )),

    /*
    | BD con el esquema completo del restaurante (se clona estructura al provisionar).
    */
    'template_database' => env('TENANT_TEMPLATE_DATABASE', env('DB_DATABASE', 'restaurante')),

    'database_prefix' => env('TENANT_DATABASE_PREFIX', 'rest_'),

    'onboarding_token_ttl_hours' => (int) env('TENANT_ONBOARDING_TTL_HOURS', 72),

    /** Meses de licencia al completar onboarding (0 = sin fecha de vencimiento). */
    'default_license_months' => (int) env('TENANT_DEFAULT_LICENSE_MONTHS', 1),

    /** Días antes del vencimiento para avisar al admin del tenant. */
    'license_warning_days' => (int) env('TENANT_LICENSE_WARNING_DAYS', 7),

    'frontend_scheme' => env('TENANT_FRONTEND_SCHEME', 'http'),

    /** Host del frontend en correos (127.0.0.1 o localhost; debe coincidir con npm run dev). */
    'frontend_host' => env('TENANT_FRONTEND_HOST', '127.0.0.1'),

    'frontend_port' => env('TENANT_FRONTEND_PORT'),

];
