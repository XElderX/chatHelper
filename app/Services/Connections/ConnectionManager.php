<?php

namespace App\Services\Connections;

use App\Models\ChatGptAccount;
use App\Models\WritingSetting;
use App\Services\AI\AiException;

class ConnectionManager
{
    public function settings(): WritingSetting
    {
        return WritingSetting::firstOrCreate(['id' => 1], ['live_analysis' => config('writing.live')]);
    }

    public function provider(): string
    {
        return $this->settings()->provider ?: config('writing.provider');
    }

    public function account(): ?ChatGptAccount
    {
        return ChatGptAccount::find($this->settings()->chatgpt_account_id);
    }

    public function identityKey(): array
    {
        $a = $this->provider() === 'chatgpt' ? $this->account() : null;

        return [$this->provider(), $a?->id, $a?->auth_epoch, $a?->model, config('writing.model'), config('writing.temperature'), config('writing.reasoning')];
    }

    public function status(): array
    {
        $active = $this->account();
        $c = $active?->credentials;

        return [
            'provider' => $this->provider(),
            'configured' => match ($this->provider()) {
                'mock' => true,'openai' => (bool) config('writing.key'),'chatgpt' => $c && in_array('chatgpt.tokens.use.direct', $c['scopes'] ?? []) && (bool) $active?->model,default => false
            },
            'api_key_configured' => (bool) config('writing.key'),
            'accounts' => ChatGptAccount::orderBy('id')->get()->map(fn ($a) => [
                'id' => $a->id, 'label' => $a->label, 'client_id' => $a->client_id, 'connected' => (bool) $a->credentials,
                'plan_authorized' => in_array('chatgpt.tokens.use.direct', $a->credentials['scopes'] ?? []),
                'model' => $a->model,
            ])->all(),
            'active_account_id' => $active?->id,
        ];
    }

    public function choose(string $provider, ?int $accountId): void
    {
        if ($provider === 'chatgpt') {
            $a = ChatGptAccount::find($accountId ?: $this->settings()->chatgpt_account_id);
            if (! $a || ! $a->credentials) {
                throw new AiException('Connect a ChatGPT account before selecting subscription mode.', 422);
            }
            if (! in_array('chatgpt.tokens.use.direct', $a->credentials['scopes'] ?? [])) {
                throw new AiException('This account has not authorized ChatGPT plan usage. Reconnect and approve that permission.', 422);
            }
            $this->settings()->update(['provider' => $provider, 'chatgpt_account_id' => $a->id]);
        } else {
            $this->settings()->update(['provider' => $provider]);
        }
    }
}
