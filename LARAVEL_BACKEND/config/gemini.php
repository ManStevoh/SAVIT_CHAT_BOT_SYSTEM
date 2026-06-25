<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Gemini API (Nano Banana image generation)
    |--------------------------------------------------------------------------
    |
    | Get a key from https://aistudio.google.com/apikey
    | Nano Banana = Gemini native image models (e.g. gemini-2.5-flash-image).
    |
    */
    'api_key' => env('GEMINI_API_KEY'),

    'api_base_url' => env('GEMINI_API_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

    /*
    | Stable: gemini-2.5-flash-image
    | Faster preview: gemini-3.1-flash-image-preview
    | Pro quality: gemini-3-pro-image-preview
    */
    'image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image'),

    'image_timeout_seconds' => (int) env('GEMINI_IMAGE_TIMEOUT', 90),
];
