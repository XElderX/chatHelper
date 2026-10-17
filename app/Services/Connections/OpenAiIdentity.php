<?php

namespace App\Services\Connections;

use App\Services\AI\AiException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OpenAiIdentity
{
    public const ISSUER = 'https://auth.openai.com';

    public function discovery(): array
    {
        return Cache::remember('chatgpt:oidc-discovery', 3600, function () {
            try {
                $r = Http::acceptJson()->timeout(15)->withoutRedirecting()->get(self::ISSUER.'/.well-known/openid-configuration');
            } catch (\Throwable) {
                throw new AiException('Cannot reach OpenAI sign-in discovery. Try again.', 503);
            }
            $d = $r->json();
            if (! $r->successful() || ! is_array($d) || ($d['issuer'] ?? '') !== self::ISSUER) {
                throw new AiException('OpenAI sign-in discovery was invalid.', 502);
            }
            foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri', 'revocation_endpoint'] as $key) {
                $url = parse_url($d[$key] ?? '');
                if (! $url || ($url['scheme'] ?? '') !== 'https' || ($url['host'] ?? '') !== 'auth.openai.com' || isset($url['user']) || isset($url['port'])) {
                    throw new AiException('OpenAI sign-in discovery contained an unexpected endpoint.', 502);
                }
            }

            return $d;
        });
    }

    private function keys(bool $refresh = false): array
    {
        if ($refresh) {
            Cache::forget('chatgpt:oidc-jwks');
        }

        return Cache::remember('chatgpt:oidc-jwks', 3600, function () {
            try {
                $r = Http::acceptJson()->timeout(15)->withoutRedirecting()->get($this->discovery()['jwks_uri']);
            } catch (\Throwable) {
                throw new AiException('Cannot verify OpenAI identity right now. Please sign in again.', 503);
            }
            if (! $r->successful() || ! is_array($r->json('keys'))) {
                throw new AiException('OpenAI signing keys were invalid.', 502);
            }

            return $r->json();
        });
    }

    public function verify(string $token, string $client, ?string $nonce = null, ?string $subject = null): array
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                throw new \Exception;
            }
            $header = json_decode(JWT::urlsafeB64Decode($parts[0]), true, 512, JSON_THROW_ON_ERROR);
            if (($header['alg'] ?? '') !== 'RS256' || ! is_string($header['kid'] ?? null)) {
                throw new \Exception;
            }
            $jwks = $this->keys();
            if (! in_array($header['kid'], array_column($jwks['keys'], 'kid'), true)) {
                $jwks = $this->keys(true);
            }
            $jwks['keys'] = array_values(array_filter($jwks['keys'], fn ($k) => ($k['kty'] ?? '') === 'RSA' && ($k['alg'] ?? 'RS256') === 'RS256' && ($k['use'] ?? 'sig') === 'sig'));
            $claims = (array) JWT::decode($token, JWK::parseKeySet($jwks, 'RS256'));
            if (($claims['iss'] ?? '') !== self::ISSUER || ! in_array($client, (array) ($claims['aud'] ?? []), true)
              || ! is_string($claims['sub'] ?? null) || $claims['sub'] === '' || ! isset($claims['exp'],$claims['iat'])
              || ! is_numeric($claims['exp']) || $claims['exp'] <= time() || ! is_numeric($claims['iat']) || $claims['iat'] > time() + 5
              || (isset($claims['azp']) && $claims['azp'] !== $client)
              || (count((array) $claims['aud']) > 1 && ($claims['azp'] ?? '') !== $client)
              || ($nonce !== null && (! is_string($claims['nonce'] ?? null) || ! hash_equals($nonce, $claims['nonce'])))
              || ($subject !== null && ! hash_equals($subject, $claims['sub']))) {
                throw new \Exception;
            }

            return $claims;
        } catch (\Throwable) {
            throw new AiException('OpenAI identity verification failed. No account was connected. Start sign-in again.',422);
        }
    }
}
