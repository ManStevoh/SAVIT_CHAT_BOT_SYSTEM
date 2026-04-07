<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Conversation routing log
    |--------------------------------------------------------------------------
    |
    | When true, successful resolution paths are logged with structured context
    | (company_id, chat_id, route, scores) for tuning and analytics.
    |
    */
    'log_routing' => env('CONVERSATION_LOG_ROUTING', true),

    /*
    |--------------------------------------------------------------------------
    | FAQ direct answer threshold
    |--------------------------------------------------------------------------
    |
    | Lexical score 0–100. Only FAQ answers at or above this score are sent
    | automatically; weaker matches fall through to OpenAI (which still sees
    | FAQs in the system prompt) to avoid wrong canned replies.
    |
    */
    'faq_direct_answer_min_score' => (float) env('FAQ_DIRECT_MIN_SCORE', 72),

    /*
    |--------------------------------------------------------------------------
    | Minimum question length for loose substring matching
    |--------------------------------------------------------------------------
    */
    'faq_min_substring_length' => 8,

];
