<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalysisRequest;
use App\Http\Requests\RewriteRequest;
use App\Services\AI\WritingAnalysisService;
use App\Services\AI\WritingRewriteService;
use Illuminate\Http\Request;

class WritingController extends Controller
{
    public function analyze(AnalysisRequest $r, WritingAnalysisService $service)
    {
        $d = $r->validated();

        return $service->analyze($d['blocks'], $d['mode'], $d['revision']);
    }

    public function rewrite(RewriteRequest $r, WritingRewriteService $service)
    {
        $d = $r->validated();

        return [...$service->run('rewrite', ['text' => $d['text'], 'action' => $d['action']], $d['mode']), 'revision' => $d['revision']];
    }

    public function explain(Request $r, WritingRewriteService $service)
    {
        $d = $r->validate(['original' => 'required|string|max:6000', 'replacement' => 'present|string|max:12000']);

        return $service->run('explain', $d, 'grammar');
    }
}
