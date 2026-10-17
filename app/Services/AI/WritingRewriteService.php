<?php

namespace App\Services\AI;

use App\Services\Connections\ConnectionManager;
use Illuminate\Support\Facades\Validator;

class WritingRewriteService
{
    public function __construct(private AiProviderInterface $provider, private ConnectionManager $connections) {}

    public function run(string $task, array $input, string $mode): array
    {
        $identity = $this->connections->identityKey();
        $result = $this->provider->generate($task, $input, $mode);
        if ($identity !== $this->connections->identityKey()) {
            throw new AiException('The AI connection changed during the request. Please try again.', 409);
        }
        $rules = ['explanation' => 'required|string|max:3000'];
        if ($task === 'rewrite') {
            $rules['replacement'] = 'required|string|max:12000';
        }
        if (Validator::make($result, $rules)->fails()) {
            throw new AiException('The AI response failed validation.');
        }

        return array_intersect_key($result, $rules);
    }
}
