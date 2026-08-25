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
];
