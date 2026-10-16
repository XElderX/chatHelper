<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['title' => 'My draft', 'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello 😊', 'marks' => [['type' => 'bold']]]]]]]];
    }

    public function test_crud_and_optimistic_locking(): void
    {
        $d = $this->payload();
        $r = $this->postJson('/api/documents', $d)->assertCreated();
        $id = $r->json('id');
        $this->getJson('/api/documents')->assertOk()->assertJsonPath('data.0.title', 'My draft');
        $d['title'] = 'Renamed';
        $d['version'] = 1;
        $this->putJson('/api/documents/'.$id, $d)->assertOk()->assertJsonPath('version', 2);
        $this->putJson('/api/documents/'.$id, $d)->assertConflict();
        $this->getJson('/api/documents/'.$id)->assertJsonPath('content.content.0.content.0.text', 'Hello 😊');
        $this->deleteJson('/api/documents/'.$id, ['version' => 1])->assertConflict();
        $this->deleteJson('/api/documents/'.$id, ['version' => 2])->assertNoContent();
        $this->getJson('/api/documents/'.$id)->assertNotFound();
    }

    public function test_unsafe_rich_text_is_rejected(): void
    {
        $d = $this->payload();
        $d['content']['content'][0]['content'][0]['marks'] = [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]];
        $this->postJson('/api/documents', $d)->assertUnprocessable();
        $d = $this->payload();
        $d['content']['content'][0]['type'] = 'script';
        $this->postJson('/api/documents', $d)->assertUnprocessable();
    }

    public function test_document_content_shape_is_validated(): void
    {
        $d = $this->payload();
        $d['content']['content'] = [['type' => 'text', 'text' => 'bad root']];
        $this->postJson('/api/documents', $d)->assertUnprocessable();
    }

    public function test_long_title_rejected(): void
    {
        $d = $this->payload();
        $d['title'] = str_repeat('x', 161);
        $this->postJson('/api/documents', $d)->assertUnprocessable();
    }
}
