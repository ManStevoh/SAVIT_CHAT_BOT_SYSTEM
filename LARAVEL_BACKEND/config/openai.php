<?php

return [
    'api_key' => env('OPENAI_API_KEY', ''),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 512),
    // Fallback only when platform AI learning settings are unset — configure in Admin → Settings → Integrations.
    'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    'max_prompt_tokens' => (int) env('OPENAI_MAX_PROMPT_TOKENS', 12000),
];
