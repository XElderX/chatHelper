<?php

namespace App\Services\AI;

use App\Services\Connections\ChatGptAuth;
use App\Services\Connections\ConnectionManager;
use Illuminate\Support\Facades\Http;

class ChatGptProvider implements AiProviderInterface
{
    public function __construct(private ChatGptAuth $auth, private PromptBuilder $prompts, private ResponseDecoder $decoder, private ConnectionManager $connections) {}

    public function models(?int $id = null): array
    {
        $a = $this->auth->usableAccount($id);
        try {
            $r = Http::withToken($a->credentials['access_token'])->acceptJson()->timeout(20)->withoutRedirecting()->get('https://api.openai.com/v1/models');
        } catch (\Throwable) {
            throw new AiException('Could not load models for this ChatGPT account. Try again.', 503);
        }
        $this->checkStatus($r->status());
        $models = $r->json('models');
        if (! is_array($models)) {
            throw new AiException('The ChatGPT model catalog was invalid.');
        }
        $result = [];
        foreach ($models as $m) {
            if (($m['visibility'] ?? '') === 'list' && is_string($m['slug'] ?? null) && is_string($m['display_name'] ?? null)) {
                $result[] = ['id' => $m['slug'], 'label' => $m['display_name']];
            }
        }

        if (! $a->model && $result) {
            $chosen = $this->fastestModel($result);
            $this->auth->locked(function () use ($a, $chosen) {
                $fresh = $a->fresh();
                if ($fresh && $fresh->auth_epoch === $a->auth_epoch && $fresh->credentials && ! $fresh->model) {
                    $fresh->update(['model' => $chosen['id']]);
                }
            });
        }

        return $result;
    }

    /** Choose the lowest-latency model from the available catalog by documented name hints. */
    public function fastestModel(array $models): ?array
    {
        if (! $models) {
            return null;
        }

        $rank = function (array $model): int {
            $id = strtolower($model['id'] ?? '');
            $score = 50;
            if (preg_match('/nano/', $id)) {
                $score -= 40;
            } elseif (preg_match('/mini|instant|flash|small/', $id)) {
                $score -= 30;
            }
            if (preg_match('/pro|reasoning|\bo[1-9]/', $id)) {
                $score += 30;
            }

            return $score;
        };
        usort($models, fn (array $left, array $right) => $rank($left) <=> $rank($right));

        return $models[0];
    }

    private function checkStatus(int $status): void
    {
        if ($status === 401) {
            throw new AiException('Your ChatGPT session is no longer accepted. Reconnect in Settings.', 401);
        }
        if ($status === 403) {
            throw new AiException('ChatGPT plan access is not enabled for this account or model. Review permissions or select another model.', 403);
        }
        if ($status === 429) {
            throw new AiException('ChatGPT usage is currently limited. Check ChatGPT Settings → Usage. API billing was not used.', 429);
        }
        if ($status < 200 || $status >= 300) {
            throw new AiException('ChatGPT could not process this request. Check model support and try again.');
        }
    }

    public function generate(string $task, array $input, string $mode): array
    {
        $a = $this->auth->usableAccount();
        if (! $a->model) {
            // Loading the catalog also persists the fastest available model for first use.
            $this->models($a->id);
            $a = $a->fresh();
        }
        if (! $a?->model) {
            throw new AiException('No usable ChatGPT model is available for this account.', 422);
        }
        $body = ['model' => $a->model, 'store' => false, 'stream' => true, 'instructions' => $this->prompts->instructions($task, $mode),
            'input' => [['role' => 'user', 'content' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]],
            'text' => ['format' => ['type' => 'json_schema', 'name' => 'writing_'.$task, 'strict' => true, 'schema' => $this->prompts->schema($task)]]];
        // Subscription route intentionally omits max_output_tokens, temperature and other unsupported fields.
        try {
            $r = Http::withToken($a->credentials['access_token'])->withHeaders(['Accept' => 'text/event-stream'])->timeout(config('writing.timeout'))->connectTimeout(10)->withoutRedirecting()->withOptions(['stream' => true, 'read_timeout' => config('writing.timeout')])->post('https://api.openai.com/v1/responses', $body);
        } catch (\Throwable) {
            throw new AiException('The ChatGPT request timed out or could not connect.', 504);
        }
        $this->checkStatus($r->status());
        $result = $this->decoder->stream($r->toPsrResponse()->getBody());
        $latest = $this->connections->account();
        if ($this->connections->provider() !== 'chatgpt' || $latest?->id !== $a->id || $latest?->auth_epoch !== $a->auth_epoch || $latest?->model !== $a->model) {
            throw new AiException('The AI connection changed during the request. Please analyze again.', 409);
        }

        return $result;
    }
}
