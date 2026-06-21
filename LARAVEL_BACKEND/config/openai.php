<?php

return [
    'api_key' => env('OPENAI_API_KEY', ''),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 512),
];
