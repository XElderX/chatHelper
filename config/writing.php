<?php

return [
    'version' => trim(file_get_contents(base_path('VERSION'))),
    'provider' => env('AI_PROVIDER', 'openai'),
    'key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
    'timeout' => (int) env('OPENAI_TIMEOUT', 30),
    'temperature' => env('OPENAI_TEMPERATURE'),
    'reasoning' => env('OPENAI_REASONING_EFFORT'),
    'debounce' => max(300, (int) env('AI_ANALYSIS_DEBOUNCE_MS', 700)),
    'live' => (bool) env('AI_LIVE_ANALYSIS_ENABLED', true),
    'cache_ttl' => (int) env('AI_CACHE_TTL', 300),
    'rate_limit' => (int) env('AI_RATE_LIMIT', 30),
    'modes' => [
        'grammar' => ['label' => 'Grammar Only', 'instruction' => 'Fix grammar, spelling and punctuation only. Preserve wording.'],
        'natural' => ['label' => 'Natural', 'instruction' => 'Improve naturalness and fluency, preserving meaning.'],
        'professional' => ['label' => 'Professional', 'instruction' => 'Use clear, professional language suitable for work.'],
        'casual' => ['label' => 'Casual', 'instruction' => 'Use relaxed, conversational wording.'],
        'friendly' => ['label' => 'Friendly', 'instruction' => 'Use warm, approachable wording.'],
        'romantic' => ['label' => 'Romantic', 'instruction' => 'Use warmer, affectionate wording without inventing feelings or changing intent.'],
        'concise' => ['label' => 'Concise', 'instruction' => 'Remove unnecessary words while preserving meaning.'],
        'fluent' => ['label' => 'Fluent English', 'instruction' => 'Use fluent, idiomatic English while preserving intent.'],
    ],
];
