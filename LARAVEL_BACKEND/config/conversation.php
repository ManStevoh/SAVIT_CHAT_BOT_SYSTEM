<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default reply routing mode
    |--------------------------------------------------------------------------
    |
    | ai_first — OpenAI answers almost everything; FAQs are context in the
    | system prompt; canned FAQ/keyword shortcuts only when AI is unavailable.
    | balanced — legacy MVP routing (catalog → FAQ → keywords → AI → fallback).
    |
    */
    'default_reply_mode' => env('CONVERSATION_REPLY_MODE', 'ai_first'),

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

    /*
    |--------------------------------------------------------------------------
    | Semantic FAQ matching (embeddings)
    |--------------------------------------------------------------------------
    |
    | Cosine similarity 0–1. Used when lexical score is below the direct
    | answer threshold but a FAQ embedding closely matches the customer message.
    | Runtime value: Admin → Settings → Integrations → AI knowledge & learning.
    |
    */
    'faq_semantic_min_score' => (float) env('FAQ_SEMANTIC_MIN_SCORE', 0.82),

    /*
    |--------------------------------------------------------------------------
    | Conversation learning limits
    |--------------------------------------------------------------------------
    | Runtime value: Admin → Settings → Integrations → AI knowledge & learning.
    */
    'learning_max_samples_per_company' => (int) env('LEARNING_MAX_SAMPLES_PER_COMPANY', 200),
    'learning_min_reply_length' => 20,

];
