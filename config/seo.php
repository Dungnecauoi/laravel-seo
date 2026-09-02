<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SEO
|--------------------------------------------------------------------------
|
| Every default here is replaceable, and nothing has to be touched for the
| package to work. Sections are added as each milestone lands; see the
| specification for the full map of extension points.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Site identity
    |--------------------------------------------------------------------------
    |
    | Used by the %sitename% and %sep% tokens, and as the Open Graph site name.
    |
    */

    'site_name' => env('SEO_SITE_NAME', env('APP_NAME', 'Laravel')),

    'separator' => env('SEO_SEPARATOR', '-'),

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | 'supported' drives hreflang output. 'fallbacks' maps a locale to what is
    | tried when it has no stored metadata; null means the shared record that
    | applies to every language.
    |
    */

    'locales' => [
        'supported' => [],

        'fallbacks' => [
            // 'en-GB' => ['en', null],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Length limits
    |--------------------------------------------------------------------------
    |
    | Titles are measured in pixels, not characters: Google truncates by
    | rendered width, so "iiiii" and "WWWWW" are the same length by character
    | count and three times apart on screen. This matters more for Vietnamese,
    | where diacritics add bytes without adding width.
    |
    */

    'limits' => [
        'title_pixels' => 580,
        'description_min' => 120,
        'description_max' => 158,
    ],

];
