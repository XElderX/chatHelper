<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class WritingAnalysisService
{
    public function __construct(private AiProviderInterface $provider, private SuggestionValidator $validator) {}

    public function analyze(array $blocks, string $mode, int $revision): array
    {
        if (! array_filter($blocks, fn ($b) => trim($b['text']) !== '')) {
            return ['revision' => $revision, 'suggestions' => []];
        }
        $key = 'analysis:'.hash('sha256', json_encode([config('writing.provider'), config('writing.model'), config('writing.temperature'), config('writing.reasoning'), $mode, $blocks]));
        $encrypted = Cache::remember($key, config('writing.cache_ttl'), function () use ($blocks, $mode) {
            return Crypt::encryptString(json_encode($this->validator->validate($this->provider->generate('analyze', ['blocks' => $blocks], $mode), $blocks), JSON_THROW_ON_ERROR));
        });
        $suggestions = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);

        return ['revision' => $revision, 'suggestions' => array_map(fn ($s) => [...$s, 'revision' => $revision], $suggestions)];
    }
}
