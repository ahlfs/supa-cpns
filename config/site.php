<?php

declare(strict_types=1);

return [
    /*
     * Marketing sites are usually launched behind a preview URL first. Keep this
     * false until the client signs off, then set SITE_INDEXABLE=true to let
     * search engines in.
     */
    'indexable' => env('SITE_INDEXABLE', false),

    /*
     * Third-party measurement IDs. Anything left empty renders no snippet at all,
     * so local development and preview deploys ship no tracking.
     */
    'analytics' => [
        'google_tag_manager' => env('ANALYTICS_GTM_ID'),
        'google_analytics' => env('ANALYTICS_GA_ID'),
        'meta_pixel' => env('ANALYTICS_META_PIXEL_ID'),
        'clarity' => env('ANALYTICS_CLARITY_ID'),
    ],
];
