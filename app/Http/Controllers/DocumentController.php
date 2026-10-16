<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Models\Document;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index()
    {
        return Document::select('id', 'title', 'version', 'updated_at')->orderByDesc('updated_at')->orderByDesc('id')->paginate(50);
    }

    public function store(DocumentRequest $r)
    {
        return response()->json(Document::create($r->validated())->fresh(), 201);
    }

    public function show(Document $document)
    {
        return $document;
    }

    public function update(DocumentRequest $r, Document $document)
    {
        $d = $r->validated();
        $changed = Document::whereKey($document->id)->where('version', $d['version'])->update(['title' => $d['title'], 'content' => json_encode($d['content'], JSON_THROW_ON_ERROR), 'version' => $d['version'] + 1, 'updated_at' => now()]);
        abort_unless($changed, 409, 'This document changed in another tab. Copy your unsaved text before reloading.');

        return $document->fresh();
    }

    public function destroy(Request $r, Document $document)
    {
        $d = $r->validate(['version' => 'required|integer|min:1']);
        abort_unless(Document::whereKey($document->id)->where('version', $d['version'])->delete(), 409, 'This document changed in another tab. Reload before deleting.');

        return response()->noContent();
    }
}
