<?php

namespace Tests\Feature;

use App\Services\AI\SuggestionValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WritingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['writing.provider' => 'mock', 'writing.key' => 'test-key', 'writing.rate_limit' => 1000]);
        Http::preventStrayRequests();
    }

    private function payload(string $text = 'I is happy.'): array
    {
        return ['blocks' => [['id' => '0', 'text' => $text]], 'mode' => 'grammar', 'revision' => 7];
    }

    private function providerResponse(array $data): array
    {
        return ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($data)]]]]];
    }

    public function test_mock_analysis_returns_validated_anchors_and_revision(): void
    {
        $this->postJson('/api/writing/analyze', $this->payload('😊 teh teh'))->assertOk()->assertJsonPath('suggestions.0.start', 3)->assertJsonPath('suggestions.1.start', 7)->assertJsonPath('suggestions.1.revision', 7);
    }

    public function test_empty_text_returns_no_suggestions(): void
    {
        $this->postJson('/api/writing/analyze', $this->payload(''))->assertOk()->assertJsonPath('suggestions', []);
    }

    public function test_whitespace_is_preserved_for_anchors(): void
    {
        $this->postJson('/api/writing/analyze', $this->payload('  teh '))->assertOk()->assertJsonPath('suggestions.0.start', 2);
    }

    public function test_unknown_modes_and_duplicate_blocks_are_rejected(): void
    {
        $d = $this->payload();
        $d['mode'] = 'made-up';
        $this->postJson('/api/writing/analyze', $d)->assertUnprocessable();
        $d = $this->payload();
        $d['blocks'][] = $d['blocks'][0];
        $this->postJson('/api/writing/analyze', $d)->assertUnprocessable();
    }

    public function test_total_input_limit_is_enforced(): void
    {
        $d = $this->payload(str_repeat('x', 6001));
        $d['blocks'][] = ['id' => '1', 'text' => str_repeat('x', 6000)];
        $this->postJson('/api/writing/analyze', $d)->assertUnprocessable();
    }

    public function test_all_modes_are_available_and_persist(): void
    {
        $this->getJson('/api/writing/modes')->assertOk()->assertJsonCount(8);
        foreach (array_keys(config('writing.modes')) as $mode) {
            $this->putJson('/api/writing/settings', ['mode' => $mode])->assertOk()->assertJsonPath('settings.mode', $mode);
        }
        $this->putJson('/api/writing/settings', ['live_analysis' => false, 'dark_mode' => true])->assertOk();
        $this->getJson('/api/writing/settings')->assertJsonPath('settings.live_analysis', false)->assertJsonPath('settings.dark_mode', true);
    }

    public function test_version_is_canonical_and_secrets_are_absent(): void
    {
        $response = $this->getJson('/api/writing/settings')->assertOk()->assertJsonPath('version', trim(file_get_contents(base_path('VERSION'))));
        $this->assertStringNotContainsString('test-key', $response->getContent());
    }

    public function test_real_provider_uses_responses_api_and_structured_schema(): void
    {
        config(['writing.provider' => 'openai']);
        Http::fake(['api.openai.com/*' => Http::response($this->providerResponse(['suggestions' => []]))]);
        $this->postJson('/api/writing/analyze', $this->payload())->assertOk();
        Http::assertSent(fn ($r) => $r->url() === 'https://api.openai.com/v1/responses' && $r['text']['format']['type'] === 'json_schema' && $r['text']['format']['strict'] === true && $r['store'] === false && ! isset($r['temperature']) && $r->hasHeader('Authorization', 'Bearer test-key'));
    }

    public function test_analysis_cache_rebinds_revision_without_second_provider_call(): void
    {
        config(['writing.provider' => 'openai']);
        Http::fake(['api.openai.com/*' => Http::response($this->providerResponse(['suggestions' => []]))]);
        $d = $this->payload();
        $this->postJson('/api/writing/analyze', $d)->assertOk();
        $d['revision'] = 9;
        $this->postJson('/api/writing/analyze', $d)->assertJsonPath('revision', 9);
        Http::assertSentCount(1);
    }

    public function test_provider_errors_are_safe(): void
    {
        config(['writing.provider' => 'openai']);
        $sequence = Http::fakeSequence('api.openai.com/*');
        foreach ([401, 403, 429, 500] as $code) {
            $sequence->push(['error' => ['message' => 'SECRET_CONTENT']], $code);
        }
        foreach ([401 => 503, 403 => 503, 429 => 429, 500 => 502] as $upstream => $status) {
            Cache::flush();
            $response = $this->postJson('/api/writing/analyze', $this->payload())->assertStatus($status);
            $this->assertStringNotContainsString('SECRET_CONTENT', $response->getContent());
        }
    }

    public function test_missing_key_returns_actionable_error(): void
    {
        config(['writing.provider' => 'openai', 'writing.key' => '']);
        $this->postJson('/api/writing/analyze', $this->payload())->assertStatus(503)->assertJsonFragment(['message' => 'Add OPENAI_API_KEY to .env, or explicitly select AI_PROVIDER=mock for the local demo.']);
        Http::assertNothingSent();
    }

    public function test_invalid_json_and_schema_are_rejected(): void
    {
        config(['writing.provider' => 'openai']);
        Http::fakeSequence('api.openai.com/*')->push(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => 'not JSON']]]]])->push($this->providerResponse(['suggestions' => [['original' => 'x']]]));
        $this->postJson('/api/writing/analyze', $this->payload())->assertStatus(502);
        $this->postJson('/api/writing/analyze', $this->payload())->assertStatus(502);
    }

    public function test_refusal_and_incomplete_responses_are_handled(): void
    {
        config(['writing.provider' => 'openai']);
        Http::fakeSequence('api.openai.com/*')->push(['status' => 'incomplete'])->push(['status' => 'completed', 'output' => [['content' => [['type' => 'refusal']]]]]);
        $this->postJson('/api/writing/analyze', $this->payload())->assertStatus(502);
        $this->postJson('/api/writing/analyze', $this->payload())->assertUnprocessable();
    }

    public function test_timeout_is_reported(): void
    {
        config(['writing.provider' => 'openai']);
        Http::fake(fn () => throw new ConnectionException);
        $this->postJson('/api/writing/analyze', $this->payload())->assertStatus(504);
    }

    public function test_rewrite_and_explain(): void
    {
        $this->postJson('/api/writing/rewrite', ['text' => 'I is ready.', 'mode' => 'natural', 'action' => 'natural', 'revision' => 5])->assertOk()->assertJsonPath('replacement', 'I am ready.')->assertJsonPath('revision', 5);
        $this->postJson('/api/writing/explain', ['original' => 'teh', 'replacement' => 'the'])->assertOk()->assertJsonStructure(['explanation']);
    }

    public function test_anchor_validation_drops_hallucinated_and_overlapping_quotes(): void
    {
        $s = ['block_id' => '0', 'original' => 'teh', 'replacement' => 'the', 'occurrence' => 0, 'explanation' => 'Spelling', 'category' => 'spelling', 'severity' => 'low'];
        $items = app(SuggestionValidator::class)->validate(['suggestions' => [$s, $s, [...$s, 'original' => 'missing'], [...$s, 'occurrence' => 1]]], [['id' => '0', 'text' => 'teh teh']]);
        $this->assertCount(2, $items);
        $this->assertSame(4, $items[1]['start']);
    }

    public function test_deletion_suggestions_and_combining_characters(): void
    {
        $s = ['block_id' => '0', 'original' => 'very', 'replacement' => '', 'occurrence' => 0, 'explanation' => 'Redundant', 'category' => 'clarity', 'severity' => 'low'];
        $result = app(SuggestionValidator::class)->validate(['suggestions' => [$s]], [['id' => '0', 'text' => "e\u{0301} very nice"]]);
        $this->assertSame(3, $result[0]['start']);
        $this->assertSame('', $result[0]['replacement']);
    }

    public function test_remote_and_cross_origin_requests_are_denied(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])->getJson('/api/documents')->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->withHeader('Origin', 'https://evil.example')->getJson('/api/documents')->assertForbidden();
    }

    public function test_rate_limiting(): void
    {
        config(['writing.rate_limit' => 1]);
        $this->postJson('/api/writing/analyze', $this->payload())->assertOk();
        $this->postJson('/api/writing/analyze', $this->payload())->assertStatus(429);
    }
}
