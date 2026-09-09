<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CMS image upload max size (kilobytes)
    |--------------------------------------------------------------------------
    | Laravel file max rule uses kilobytes. Default 10 MB.
    | Ensure PHP upload_max_filesize / post_max_size are at least this large.
    */
    'upload_max_kb' => (int) env('CMS_UPLOAD_MAX_KB', 10240),

    /*
    |--------------------------------------------------------------------------
    | Public marketing pages
    |--------------------------------------------------------------------------
    | Flip these back to true to restore /solutions and /blog without
    | re-creating CMS content. Admin CMS and /admin/blog stay available.
    */
    'public_pages' => [
        'solutions' => (bool) env('CMS_PUBLIC_SOLUTIONS', false),
        'blog' => (bool) env('CMS_PUBLIC_BLOG', false),
    ],
];
