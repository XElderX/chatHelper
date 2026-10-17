<?php

namespace App\Services\Connections;

use App\Models\ChatGptAccount;
use App\Services\AI\AiException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ChatGptAuth
{
    public const RESOURCE = 'https://api.openai.com/v1';

    public const SCOPES = 'openid profile email offline_access resource.invoke chatgpt.tokens.use.direct';

    public function __construct(private ConnectionManager $connections, private OpenAiIdentity $identity) {}

    public function locked(callable $fn): mixed
    {
        try {
            return Cache::lock('chatgpt:credentials', 90)->block(5, $fn);
        } catch (LockTimeoutException) {
            throw new AiException('Another account action is in progress. Try again shortly.', 409);
        }
    }

    public function start(Request $r, ?int $accountId): string
    {
        if ($r->getHost() !== '127.0.0.1' || $r->getScheme() !== 'http') {
            throw new AiException('Open chatHelper at http://127.0.0.1 with the same port before signing in. The official callback requires this host.', 422);
        }

        return $this->locked(function () use ($r, $accountId) {
            $settings = $this->connections->settings();
            if (! $settings->host_id) {
                $settings->update(['host_id' => 'urn:uuid:'.Str::uuid()]);
            }
            $account = $accountId ? ChatGptAccount::findOrFail($accountId) : null;
            $state = Str::random(64);
            $nonce = Str::random(64);
            $verifier = Str::random(64);
            $callback = $r->getSchemeAndHttpHost().'/auth/chatgpt/callback';
            $pending = ['session' => hash('sha256', $r->session()->getId()), 'nonce' => $nonce, 'verifier' => $verifier, 'callback' => $callback, 'account_id' => $account?->id, 'client_id' => $account?->client_id, 'epoch' => $account?->auth_epoch, 'host_id' => $settings->host_id];
            Cache::put('chatgpt:attempt:'.hash('sha256', $state), Crypt::encryptString(json_encode($pending)), 600);
            $query = ['client_id' => $account?->client_id ?? 'dynamic_agent_client', 'ext_agent_host_id' => $settings->host_id, 'response_type' => 'code', 'redirect_uri' => $callback, 'scope' => self::SCOPES, 'resource' => self::RESOURCE, 'state' => $state, 'nonce' => $nonce, 'code_challenge_method' => 'S256', 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')];
            if (! $account) {
                $query['agent_name_hint'] = 'chatHelper';
            }

            // Avoid sending retained ID tokens through app JSON/URLs; account selection remains explicit.
            return $this->identity->discovery()['authorization_endpoint'].'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        });
    }

    public function finish(Request $r): ChatGptAccount
    {
        $state = $r->query('state');
        if (! is_string($state) || strlen($state) !== 64) {
            throw new AiException('Sign-in state is missing or invalid. Please start again.', 422);
        }

        return $this->locked(function () use ($r, $state) {
            $key = 'chatgpt:attempt:'.hash('sha256', $state);
            $encrypted = Cache::get($key);
            if (! $encrypted) {
                throw new AiException('This sign-in attempt expired or was already used. Start again.', 422);
            }
            $pending = json_decode(Crypt::decryptString($encrypted), true);
            if (! hash_equals($pending['session'], hash('sha256', $r->session()->getId()))) {
                throw new AiException('Sign-in belongs to a different browser session. Start again in this browser.', 422);
            }
            Cache::forget($key); // Atomic one-use consumption under the credentials lock.
            if ($r->query('error')) {
                throw new AiException('ChatGPT authorization was cancelled or declined. Your existing connection was not changed.', 422);
            }
            $code = $r->query('code');
            $client = $r->query('client_id', $pending['client_id']);
            if (! is_string($code) || $code === '' || strlen($code) > 8192 || ! is_string($client) || ! preg_match('/^oaiapp_[A-Za-z0-9_-]{1,200}$/D', $client)) {
                throw new AiException('OpenAI did not return a complete app registration. Start again.', 422);
            }
            if ($pending['client_id'] && $pending['client_id'] !== $client) {
                throw new AiException('The returned client registration did not match. Nothing was changed.', 422);
            }
            $existing = $pending['account_id'] ? ChatGptAccount::findOrFail($pending['account_id']) : null;
            if ($existing && $existing->auth_epoch !== $pending['epoch']) {
                throw new AiException('This account changed during sign-in. Please start again.', 409);
            }
            $tokens = $this->tokenRequest(['grant_type' => 'authorization_code', 'client_id' => $client, 'code' => $code, 'code_verifier' => $pending['verifier'], 'redirect_uri' => $pending['callback'], 'resource' => self::RESOURCE]);
            $credentials = $this->credentials($tokens);
            $claims = $this->identity->verify($credentials['id_token'] ?? '', $client, $pending['nonce'], $existing?->subject);
            $duplicate = ChatGptAccount::where('client_id', $client)->first();
            if ($duplicate && $duplicate->subject !== $claims['sub']) {
                throw new AiException('This registration has a different account identity.', 422);
            }
            $account = $existing ?? $duplicate ?? new ChatGptAccount;
            $account->fill(['client_id' => $client, 'subject' => $claims['sub'], 'label' => is_string($claims['email'] ?? null) ? mb_substr($claims['email'], 0, 180) : 'ChatGPT account', 'credentials' => $credentials, 'auth_epoch' => (string) Str::uuid(), 'model' => null])->save();
            // A new registration becomes active only after signature and nonce validation.
            $this->connections->settings()->update(['provider' => 'chatgpt', 'chatgpt_account_id' => $account->id]);
            $r->session()->regenerate();

            return $account;
        });
    }

    private function tokenRequest(array $body): array
    {
        try {
            $r = Http::asForm()->acceptJson()->timeout(20)->withoutRedirecting()->post($this->identity->discovery()['token_endpoint'], $body);
        } catch (\Throwable) {
            throw new AiException('Cannot reach OpenAI to exchange or refresh credentials. Please retry sign-in.', 503);
        }
        if (! $r->successful()) {
            throw new AiException($r->status() === 400 ? 'OpenAI could not renew or complete this sign-in. Please connect again.' : 'OpenAI sign-in is temporarily unavailable. Please try again.', $r->status() === 400 ? 401 : 503);
        }
        if (! is_array($r->json())) {
            throw new AiException('OpenAI returned invalid credentials.', 502);
        }

        return $r->json();
    }

    private function credentials(array $tokens, ?array $old = null): array
    {
        foreach (['token_type', 'refresh_token', 'id_token'] as $field) {
            if (isset($tokens[$field]) && ! is_string($tokens[$field])) {
                throw new AiException('OpenAI returned invalid credentials.', 502);
            }
        }
        if (! is_string($tokens['access_token'] ?? null) || $tokens['access_token'] === '' || strtolower($tokens['token_type'] ?? '') !== 'bearer' || ! is_numeric($tokens['expires_in'] ?? null) || $tokens['expires_in'] <= 0) {
            throw new AiException('OpenAI returned incomplete credentials. Sign in again.', 502);
        }
        $scope = $tokens['scope'] ?? null;
        if ($scope !== null && ! is_string($scope)) {
            throw new AiException('OpenAI returned invalid permissions.', 502);
        }

        return ['access_token' => $tokens['access_token'], 'refresh_token' => $tokens['refresh_token'] ?? $old['refresh_token'] ?? null, 'id_token' => $tokens['id_token'] ?? $old['id_token'] ?? null, 'expires_at' => time() + (int) $tokens['expires_in'], 'scopes' => $scope !== null ? preg_split('/\s+/', trim($scope)) : ($old['scopes'] ?? [])];
    }

    public function usableAccount(?int $id = null): ChatGptAccount
    {
        return $this->locked(function () use ($id) {
            $a = $id ? ChatGptAccount::find($id) : $this->connections->account();
            $c = $a?->credentials;
            if (! $a || ! $c) {
                throw new AiException('Connect your ChatGPT account in Settings.', 401);
            }
            if (! in_array('chatgpt.tokens.use.direct', $c['scopes'] ?? []) || ! in_array('resource.invoke', $c['scopes'] ?? [])) {
                throw new AiException('ChatGPT plan usage is not authorized. Reconnect and approve subscription access.', 403);
            }
            if (($c['expires_at'] ?? 0) <= time() + 60) {
                if (empty($c['refresh_token'])) {
                    throw new AiException('Your ChatGPT session expired. Connect again in Settings.', 401);
                }
                try {
                    $tokens = $this->tokenRequest(['grant_type' => 'refresh_token', 'client_id' => $a->client_id, 'refresh_token' => $c['refresh_token'], 'resource' => self::RESOURCE]);
                } catch (AiException $e) {
                    if ($e->status === 401) {
                        $a->update(['credentials' => null, 'auth_epoch' => (string) Str::uuid()]);
                    }throw $e;
                }
                $c = $this->credentials($tokens, $c);
                if (isset($tokens['id_token'])) {
                    $this->identity->verify($tokens['id_token'], $a->client_id, null, $a->subject);
                }$a->update(['credentials' => $c]);
                if (! in_array('chatgpt.tokens.use.direct', $c['scopes']) || ! in_array('resource.invoke', $c['scopes'])) {
                    throw new AiException('ChatGPT plan permission is no longer granted. Reconnect.', 403);
                }
            }

            return $a->fresh();
        });
    }

    public function disconnect(int $id): bool
    {
        return $this->locked(function () use ($id) {
            $a = ChatGptAccount::findOrFail($id);
            $token = $a->credentials['refresh_token'] ?? null;
            $confirmed = false;
            if ($token) {
                for ($attempt = 0; $attempt < 2; $attempt++) {
                    try {
                        $r = Http::asForm()->timeout(10)->withoutRedirecting()->post($this->identity->discovery()['revocation_endpoint'], ['token' => $token, 'token_type_hint' => 'refresh_token', 'client_id' => $a->client_id]);
                        $confirmed = $r->status() === 200;
                        if ($confirmed || $r->status() < 500) {
                            break;
                        }
                    } catch (\Throwable) {
                    }
                    if ($attempt === 0) {
                        usleep(150000);
                    }
                }
            }
            $a->update(['credentials' => null, 'model' => null, 'auth_epoch' => (string) Str::uuid()]);

            // Retain mapping and host ID. Do not silently switch billing to API key.
            return $confirmed;
        });
    }
}
