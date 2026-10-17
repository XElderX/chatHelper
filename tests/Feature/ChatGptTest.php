<?php

namespace Tests\Feature;

use App\Models\ChatGptAccount;
use App\Services\AI\AiException;
use App\Services\AI\ChatGptProvider;
use App\Services\AI\ResponseDecoder;
use App\Services\Connections\ChatGptAuth;
use App\Services\Connections\ConnectionManager;
use App\Services\Connections\OpenAiIdentity;
use Firebase\JWT\JWT;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatGptTest extends TestCase
{
    use RefreshDatabase;

    private $key;

    private array $jwk;

    protected function setUp(): void
    {
        parent::setUp();
        config(['writing.provider' => 'mock', 'writing.key' => 'paid-secret', 'writing.rate_limit' => 1000]);
        Cache::flush();
        Http::preventStrayRequests();
        $this->key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $rsa = openssl_pkey_get_details($this->key)['rsa'];
        $this->jwk = ['kty' => 'RSA', 'kid' => 'test', 'use' => 'sig', 'alg' => 'RS256', 'n' => JWT::urlsafeB64Encode($rsa['n']), 'e' => JWT::urlsafeB64Encode($rsa['e'])];
        Cache::put('chatgpt:oidc-discovery', ['issuer' => OpenAiIdentity::ISSUER, 'authorization_endpoint' => OpenAiIdentity::ISSUER.'/api/accounts/authorize', 'token_endpoint' => OpenAiIdentity::ISSUER.'/api/accounts/oauth/token', 'jwks_uri' => OpenAiIdentity::ISSUER.'/.well-known/jwks.json', 'revocation_endpoint' => OpenAiIdentity::ISSUER.'/oauth/revoke'], 3600);
        Cache::put('chatgpt:oidc-jwks', ['keys' => [$this->jwk]], 3600);
    }

    private function request(array $query = []): Request
    {
        $r = Request::create('http://127.0.0.1:8000/auth/chatgpt/callback', 'GET', $query);
        $r->setLaravelSession(app('session.store'));

        return $r;
    }

    private function begin(?int $id = null): array
    {
        $url = app(ChatGptAuth::class)->start($this->request(), $id);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);

        return $q;
    }

    private function jwt(array $q, array $overrides = []): string
    {
        return JWT::encode(array_replace(['iss' => OpenAiIdentity::ISSUER, 'aud' => 'oaiapp_test', 'sub' => 'subject-one', 'email' => 'person@example.test', 'nonce' => $q['nonce'], 'iat' => time(), 'exp' => time() + 3600], $overrides), $this->key, 'RS256', 'test');
    }

    private function tokens(string $id): array
    {
        return ['access_token' => 'subscription-secret', 'refresh_token' => 'refresh-secret', 'id_token' => $id, 'expires_in' => 3600, 'token_type' => 'Bearer', 'scope' => ChatGptAuth::SCOPES];
    }

    private function account(array $extra = []): ChatGptAccount
    {
        $a = ChatGptAccount::create(array_replace(['client_id' => 'oaiapp_test', 'subject' => 'subject-one', 'label' => 'Account', 'auth_epoch' => 'epoch-one', 'model' => 'eligible-model', 'credentials' => ['access_token' => 'subscription-secret', 'refresh_token' => 'refresh-secret', 'expires_at' => time() + 3600, 'scopes' => explode(' ', ChatGptAuth::SCOPES)]], $extra));
        app(ConnectionManager::class)->choose('chatgpt', $a->id);

        return $a;
    }

    private function finish(array $q): ChatGptAccount
    {
        return app(ChatGptAuth::class)->finish($this->request(['state' => $q['state'], 'code' => 'code', 'client_id' => 'oaiapp_test']));
    }

    public function test_pkce_registration_and_encrypted_storage(): void
    {
        $q = $this->begin();
        $next = $this->begin();
        $this->assertSame($q['ext_agent_host_id'], $next['ext_agent_host_id']);
        $this->assertNotSame($q['state'], $next['state']);
        $this->assertSame('dynamic_agent_client', $q['client_id']);
        $this->assertSame('S256', $q['code_challenge_method']);
        Http::fake(['auth.openai.com/*' => Http::response($this->tokens($this->jwt($q)))]);
        $a = $this->finish($q);
        $this->assertSame('subject-one', $a->subject);
        Http::assertSent(fn ($r) => $r['client_id'] === 'oaiapp_test' && $r['redirect_uri'] === $q['redirect_uri'] && JWT::urlsafeB64Encode(hash('sha256', $r['code_verifier'], true)) === $q['code_challenge']);
        $this->assertStringNotContainsString('subscription-secret', DB::table('chatgpt_accounts')->value('credentials'));
        $json = $this->getJson('/api/connections')->assertOk()->assertJsonPath('provider', 'chatgpt')->getContent();
        $this->assertStringNotContainsString('secret', $json);
        $this->assertStringNotContainsString('subject-one', $json);
        $this->expectException(AiException::class);
        $this->finish($q);
    }

    public function test_bad_nonce_does_not_connect_or_change_billing(): void
    {
        $q = $this->begin();
        Http::fake(['auth.openai.com/*' => Http::response($this->tokens($this->jwt($q, ['nonce' => 'wrong'])))]);
        try {
            $this->finish($q);
            $this->fail('Accepted bad nonce');
        } catch (AiException $e) {
            $this->assertSame(422, $e->status);
        }
        $this->assertDatabaseCount('chatgpt_accounts', 0);
        $this->assertSame('mock', app(ConnectionManager::class)->provider());
    }

    public function test_identity_rejects_wrong_audience_expiry_issuer_and_signature(): void
    {
        $q = $this->begin();
        foreach ([['aud' => 'other'], ['exp' => time() - 10], ['iss' => 'https://evil.test'], ['sub' => '']] as $bad) {
            try {
                app(OpenAiIdentity::class)->verify($this->jwt($q, $bad), 'oaiapp_test', $q['nonce']);
                $this->fail('Invalid identity accepted');
            } catch (AiException $e) {
                $this->assertSame(422, $e->status);
            }
        }
        $token = $this->jwt($q);
        $parts = explode('.', $token);
        $parts[2] = str_repeat('A', strlen($parts[2]));
        $this->expectException(AiException::class);
        app(OpenAiIdentity::class)->verify(implode('.', $parts), 'oaiapp_test', $q['nonce']);
    }

    public function test_state_and_browser_binding_reject_without_token_exchange(): void
    {
        $q = $this->begin();
        app('session.store')->setId(str_repeat('a', 40));
        try {
            $this->finish($q);
            $this->fail('Accepted different browser');
        } catch (AiException $e) {
            $this->assertSame(422, $e->status);
        }
        Http::assertNothingSent();
    }

    public function test_reauthorization_preserves_registration_and_subject(): void
    {
        $a = $this->account();
        $q = $this->begin($a->id);
        $this->assertSame($a->client_id, $q['client_id']);
        $this->assertArrayNotHasKey('agent_name_hint', $q);
        Http::fake(['auth.openai.com/*' => Http::response($this->tokens($this->jwt($q, ['sub' => 'other-person'])))]);
        try {
            $this->finish($q);
            $this->fail('Changed account subject');
        } catch (AiException $e) {
            $this->assertSame(422, $e->status);
        }
        $this->assertSame('subject-one', $a->fresh()->subject);
        $this->assertSame('epoch-one', $a->fresh()->auth_epoch);
    }

    public function test_refresh_rotates_tokens_and_uses_issued_client_without_scope(): void
    {
        $a = $this->account();
        $c = $a->credentials;
        $c['expires_at'] = time() - 1;
        $a->update(['credentials' => $c]);
        Http::fake(['auth.openai.com/*' => Http::response(['access_token' => 'new-access', 'refresh_token' => 'new-refresh', 'expires_in' => 3600, 'token_type' => 'Bearer'])]);
        $fresh = app(ChatGptAuth::class)->usableAccount();
        $this->assertSame('new-refresh', $fresh->credentials['refresh_token']);
        app(ChatGptAuth::class)->usableAccount();
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r['grant_type'] === 'refresh_token' && $r['client_id'] === 'oaiapp_test' && ! isset($r['scope']));
    }

    public function test_models_and_subscription_stream_never_use_api_key(): void
    {
        $a = $this->account();
        $response = ['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => '{"suggestions":[]}']]]]];
        $sse = 'data: '.json_encode(['type' => 'response.completed', 'response' => $response])."\n\n";
        Http::fake(['api.openai.com/v1/models' => Http::response(['models' => [['slug' => 'hidden', 'display_name' => 'Hidden', 'visibility' => 'hide'], ['slug' => 'eligible-model', 'display_name' => 'Eligible', 'visibility' => 'list']]]), 'api.openai.com/v1/responses' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);
        $this->getJson('/api/connections/chatgpt/models?account_id='.$a->id)->assertOk()->assertJsonCount(1, 'models')->assertJsonPath('models.0.id', 'eligible-model');
        $this->putJson('/api/connections/chatgpt/model', ['account_id' => $a->id, 'model' => 'hidden'])->assertUnprocessable();
        $this->postJson('/api/writing/analyze', ['blocks' => [['id' => '0', 'text' => 'Hello']], 'revision' => 1, 'mode' => 'grammar'])->assertOk()->assertJsonPath('suggestions', []);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.openai.com/v1/responses' && $r->hasHeader('Authorization', 'Bearer subscription-secret') && $r['stream'] === true && $r['store'] === false && is_array($r['input']) && ! isset($r['max_output_tokens']) && ! isset($r['temperature']));
        Http::assertNotSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer paid-secret'));
    }

    public function test_missing_model_selects_fastest_available_model_automatically(): void
    {
        $a = $this->account(['model' => null]);
        Http::fake([
            'api.openai.com/v1/models' => Http::response(['models' => [
                ['slug' => 'gpt-5-pro', 'display_name' => 'GPT-5 Pro', 'visibility' => 'list'],
                ['slug' => 'gpt-5-mini', 'display_name' => 'GPT-5 mini', 'visibility' => 'list'],
                ['slug' => 'gpt-5-nano', 'display_name' => 'GPT-5 nano', 'visibility' => 'list'],
            ]]),
        ]);

        $this->getJson('/api/connections/chatgpt/models?account_id='.$a->id)
            ->assertOk()
            ->assertJsonCount(3, 'models');

        $this->assertSame('gpt-5-nano', $a->fresh()->model);
        $this->assertSame('gpt-5-nano', app(ChatGptProvider::class)->fastestModel([
            ['id' => 'gpt-5-pro'], ['id' => 'gpt-5-mini'], ['id' => 'gpt-5-nano'],
        ])['id']);
    }

    public function test_late_usage_error_and_interrupted_stream_do_not_return_partial_text(): void
    {
        foreach (["data: {\"type\":\"response.output_text.delta\",\"delta\":\"partial\"}\n\n", "data: {\"type\":\"response.failed\",\"response\":{\"error\":{\"code\":\"subscription_sharing_usage_limit_exceeded\"}}}\n\n"] as $index => $stream) {
            try {
                app(ResponseDecoder::class)->stream(Utils::streamFor($stream));
                $this->fail('Accepted unfinished stream');
            } catch (AiException $e) {
                $this->assertSame($index === 0 ? 502 : 429, $e->status);
            }
        }
    }

    public function test_stream_decoder_uses_output_text_deltas_when_completed_event_has_no_output_items(): void
    {
        $stream = "data: {\"type\":\"response.output_text.delta\",\"delta\":\"{\\\"suggestions\\\":[]}\"}\n\n"
            ."data: {\"type\":\"response.completed\",\"response\":{\"status\":\"completed\",\"output\":[]}}\n\n";

        $this->assertSame([], app(ResponseDecoder::class)->stream(Utils::streamFor($stream))['suggestions']);
    }

    public function test_signout_clears_credentials_preserves_mapping_and_does_not_fallback(): void
    {
        $a = $this->account();
        Http::fake(['auth.openai.com/*' => Http::response('', 500)]);
        $this->postJson('/api/connections/chatgpt/disconnect', ['account_id' => $a->id])->assertOk()->assertJsonPath('revocation_confirmed', false);
        $this->assertNull($a->fresh()->credentials);
        $this->assertSame('oaiapp_test', $a->fresh()->client_id);
        $this->assertSame('chatgpt', app(ConnectionManager::class)->provider());
        $this->postJson('/api/writing/analyze', ['blocks' => [['id' => '0', 'text' => 'Hello']], 'revision' => 1, 'mode' => 'grammar'])->assertUnauthorized();
        Http::assertSentCount(2);
    }

    public function test_missing_plan_scope_blocks_inference(): void
    {
        $a = $this->account();
        $c = $a->credentials;
        $c['scopes'] = ['resource.invoke'];
        $a->update(['credentials' => $c]);
        $this->postJson('/api/writing/analyze', ['blocks' => [['id' => '0', 'text' => 'Hello']], 'revision' => 1, 'mode' => 'grammar'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_login_requires_loopback_ip_and_selection_is_explicit(): void
    {
        $this->postJson('http://localhost/api/connections/chatgpt/start')->assertUnprocessable();
        $this->putJson('/api/connections', ['provider' => 'openai'])->assertOk()->assertJsonPath('provider', 'openai');
        $this->putJson('/api/connections', ['provider' => 'chatgpt'])->assertUnprocessable();
        $this->assertSame('openai', app(ConnectionManager::class)->provider());
    }

    public function test_http_callback_completes_in_same_session_and_rejects_replay(): void
    {
        $start = $this->withSession(['test_session' => true])->postJson('http://127.0.0.1:8000/api/connections/chatgpt/start')->assertOk();
        parse_str(parse_url($start->json('url'), PHP_URL_QUERY), $q);
        Http::fake(['auth.openai.com/*' => Http::response($this->tokens($this->jwt($q)))]);
        $callback = 'http://127.0.0.1:8000/auth/chatgpt/callback?'.http_build_query(['state' => $q['state'], 'code' => 'code', 'client_id' => 'oaiapp_test']);
        $this->withCookie(config('session.cookie'), app('session.store')->getId())->get($callback)->assertRedirect('/?connection=return');
        $this->assertDatabaseCount('chatgpt_accounts', 1);
        $this->withCookie(config('session.cookie'), app('session.store')->getId())->get($callback)->assertRedirect('/?connection=return');
        Http::assertSentCount(1);
    }

    public function test_account_switch_does_not_reuse_another_accounts_analysis_cache(): void
    {
        $a = $this->account();
        $sse = 'data: '.json_encode(['type' => 'response.completed', 'response' => ['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => '{"suggestions":[]}']]]]]])."\n\n";
        Http::fake(['api.openai.com/*' => fn () => Http::response($sse)]);
        $body = ['blocks' => [['id' => '0', 'text' => 'Hello']], 'revision' => 1, 'mode' => 'grammar'];
        $this->postJson('/api/writing/analyze', $body)->assertOk();
        $this->postJson('/api/writing/analyze', $body)->assertOk();
        $b = $this->account(['client_id' => 'oaiapp_two', 'subject' => 'subject-two', 'auth_epoch' => 'epoch-two']);
        $this->postJson('/api/writing/analyze', $body)->assertOk();
        Http::assertSentCount(2);
    }
}
