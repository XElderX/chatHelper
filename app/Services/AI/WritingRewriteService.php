<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Validator;

class WritingRewriteService
{
    public function __construct(private AiProviderInterface $provider) {}

    public function run(string $task, array $input, string $mode): array
    {
        $result = $this->provider->generate($task, $input, $mode);
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
