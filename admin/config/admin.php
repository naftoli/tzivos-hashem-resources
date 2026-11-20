<?php

return [
    /*
    | Owner accounts: always HQ admin. Nobody can change their role,
    | not in the User roles screen and not from code (tinker, seeders).
    | Comma-separated in .env as ADMIN_OWNER_EMAILS.
    */
    'owners' => array_values(array_filter(array_map(
        fn ($e) => strtolower(trim($e)),
        explode(',', env('ADMIN_OWNER_EMAILS', 'admin@example.com'))
    ))),

    // Times typed in the admin (file visibility windows) are New York time
    'timezone' => env('ADMIN_TIMEZONE', 'America/New_York'),

    // The Resources site's address; "Open site" fills in addresses like {site_url}#/campaign
    'site_url' => env('RESOURCES_SITE_URL', 'https://tzivoshashem.com/resources/site/'),

    // Secret the live site's server sends (Authorization: Bearer ...) to read page data from /api/...
    // Empty = no token access; then only signed-in HQ admin / HQ staff can read it.
    'api_token' => env('RESOURCES_API_TOKEN', ''),
];
