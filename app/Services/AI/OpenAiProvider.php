<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenAiProvider implements AiProviderInterface
{
    public function __construct(private PromptBuilder $prompts) {}

    public function generate(string $task, array $input, string $mode): array
    {
        if (! config('writing.key')) {
            throw new AiException('Add OPENAI_API_KEY to .env, or explicitly select AI_PROVIDER=mock for the local demo.', 503);
        }
        $body = [
            'model' => config('writing.model'), 'store' => false,
            'instructions' => $this->prompts->instructions($task, $mode),
            'input' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'max_output_tokens' => 6000,
            'text' => ['format' => ['type' => 'json_schema', 'name' => 'writing_'.$task, 'strict' => true, 'schema' => $this->prompts->schema($task)]],
        ];
        // Optional parameters are opt-in; deployments must select options supported by their model.
        if (config('writing.temperature') !== null && config('writing.temperature') !== '') {
            $body['temperature'] = (float) config('writing.temperature');
        }
        if (config('writing.reasoning')) {
            $body['reasoning'] = ['effort' => config('writing.reasoning')];
        }
        try {
            $response = Http::withToken(config('writing.key'))->acceptJson()->connectTimeout(10)->timeout(config('writing.timeout'))->post('https://api.openai.com/v1/responses', $body);
        } catch (ConnectionException) {
            throw new AiException('The AI request timed out or could not connect. Please try again.', 504);
        }
        if (in_array($response->status(), [401, 403])) {
            throw new AiException('OpenAI rejected the configured credentials or model access. Check server configuration.', 503);
        }
        if ($response->status() === 429) {
            throw new AiException('OpenAI is rate limiting requests or the API quota is exhausted. Check billing and try later.', 429);
        }
        if (! $response->successful()) {
            throw new AiException('The AI provider could not process this request. Check the configured model and options.');
        }
        if ($response->json('status') !== 'completed') {
            throw new AiException('The AI response was incomplete. Try a shorter passage.');
        }
        $text = '';
        foreach ($response->json('output', []) as $item) {
            foreach (($item['content'] ?? []) as $part) {
                if (($part['type'] ?? '') === 'refusal') {
                    throw new AiException('The AI provider declined this request.', 422);
                }
                if (($part['type'] ?? '') === 'output_text') {
                    $text .= $part['text'];
                }
            }
        }
        try {
            $result = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new AiException('The AI returned invalid structured data. Please retry.');
        }
        if (! is_array($result)) {
            throw new AiException('The AI returned an invalid response.');
        }

        return $result;
    }
}
