<?php

return [
    /*
    | How sign-in works:
    |   off  - no Mashpia; local email + password only (for testing)
    |   mock - pretend to ask Mashpia; approves the emails/domains below
    |   real - really ask Mashpia (see "method" below)
    | Change MASHPIA_AUTH_MODE in .env to switch.
    */
    'mode' => env('MASHPIA_AUTH_MODE', 'mock'),

    /*
    | Real mode only. How the admin learns who is signed in to Mashpia:
    |   handoff - (recommended) the person clicks "Sign in with Mashpia"; Mashpia's own small
    |             page (handoff_url) sends them back here with a signed note saying who they are.
    |             Works on any subdomain. Needs MASHPIA_HANDOFF_URL and MASHPIA_SHARED_SECRET.
    |   session - Mashpia's login cookie is shared with the admin's subdomain (cookie domain
    |             .mashpia.com) and the admin asks check_url, server to server, with that cookie.
    | Either way Mashpia's answer must include the person's email.
    */
    'method' => env('MASHPIA_AUTH_METHOD', 'handoff'),

    // Mashpia's login check (what the Resources site itself calls). Used by the "session" method.
    'check_url' => env('MASHPIA_CHECK_URL', 'https://mashpia.com/resources/checkAuth.php'),

    // Name of Mashpia's login cookie (session method)
    'session_cookie' => env('MASHPIA_SESSION_COOKIE', 'PHPSESSID'),

    // Mashpia's page that sends people back with a signed note (handoff method)
    'handoff_url' => env('MASHPIA_HANDOFF_URL'),

    // Secret shared with Mashpia, used to sign the note (handoff method). Never put it in the code.
    'shared_secret' => env('MASHPIA_SHARED_SECRET'),

    // How many seconds a signed note stays good
    'handoff_max_age' => (int) env('MASHPIA_HANDOFF_MAX_AGE', 120),

    'timeout' => (int) env('MASHPIA_TIMEOUT', 10),

    // Mock mode: these emails, or any email at these domains, count as "logged in"
    'mock_emails' => array_filter(array_map('trim', explode(',', env('MASHPIA_MOCK_EMAILS', '')))),
    'mock_domains' => array_filter(array_map('trim', explode(',', env('MASHPIA_MOCK_DOMAINS', '')))),
];
