<?php

namespace App\Services\AI;

use App\Services\Connections\ChatGptAuth;
use App\Services\Connections\ConnectionManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class WritingAnalysisService
{
    public function __construct(private AiProviderInterface $provider, private SuggestionValidator $validator, private ConnectionManager $connections, private ChatGptAuth $auth) {}

    public function analyze(array $blocks, string $mode, int $revision): array
    {
        if (! array_filter($blocks, fn ($b) => trim($b['text']) !== '')) {
            return ['revision' => $revision, 'suggestions' => []];
        }
        if ($this->connections->provider() === 'chatgpt') {
            $this->auth->usableAccount();
        }
        $identity = $this->connections->identityKey();
        $key = 'analysis:'.hash('sha256', json_encode([$identity, $mode, $blocks]));
        $encrypted = Cache::remember($key, config('writing.cache_ttl'), function () use ($blocks, $mode) {
            return Crypt::encryptString(json_encode($this->validator->validate($this->provider->generate('analyze', ['blocks' => $blocks], $mode), $blocks), JSON_THROW_ON_ERROR));
        });
        if ($identity !== $this->connections->identityKey()) {
            throw new AiException('The AI connection changed. Please analyze again.', 409);
        }
        $suggestions = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);

        return ['revision' => $revision, 'suggestions' => array_map(fn ($s) => [...$s, 'revision' => $revision], $suggestions)];
    }
}
