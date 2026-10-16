<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RichText implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $count = 0;
        if (! is_array($value) || ($value['type'] ?? '') !== 'doc' || strlen(json_encode($value)) > 250000 || ! $this->node($value, 0, $count)) {
            $fail('Content must be a valid supported rich-text document (maximum 250 KB).');
        }
    }

    private function node(mixed $n, int $depth, int &$count): bool
    {
        if (! is_array($n) || $depth > 12 || ++$count > 4000 || array_diff(array_keys($n), ['type', 'content', 'text', 'marks', 'attrs'])) {
            return false;
        }
        $type = $n['type'] ?? '';
        if (! in_array($type, ['doc', 'paragraph', 'heading', 'blockquote', 'bulletList', 'orderedList', 'listItem', 'text', 'hardBreak', 'horizontalRule'])) {
            return false;
        }
        if ($type === 'text' && (! is_string($n['text'] ?? null) || $n['text'] === '')) {
            return false;
        }
        if ($type !== 'text' && isset($n['text'])) {
            return false;
        }
        if (isset($n['attrs'])) {
            if (! is_array($n['attrs'])) {
                return false;
            }
            $allowed = match ($type) {
                'heading' => ['level'],'orderedList' => ['start', 'type'],default => []
            };
            if (array_diff(array_keys($n['attrs']), $allowed)) {
                return false;
            }
            if ($type === 'heading' && ! in_array($n['attrs']['level'] ?? 0, [1, 2, 3], true)) {
                return false;
            }
            if ($type === 'orderedList' && (! is_int($n['attrs']['start'] ?? 1) || ($n['attrs']['start'] ?? 1) < 1)) {
                return false;
            }
        }
        if (isset($n['marks']) && (! is_array($n['marks']) || ! array_is_list($n['marks']))) {
            return false;
        }
        foreach ($n['marks'] ?? [] as $m) {
            if (! is_array($m) || array_diff(array_keys($m), ['type']) || ! in_array($m['type'] ?? '', ['bold', 'italic', 'strike', 'code', 'underline'])) {
                return false;
            }
        }
        $children = $n['content'] ?? [];
        if (! is_array($children) || ! array_is_list($children)) {
            return false;
        }
        if (in_array($type, ['text', 'hardBreak', 'horizontalRule']) && count($children)) {
            return false;
        }
        foreach ($children as $child) {
            $childType = is_array($child) ? ($child['type'] ?? '') : '';
            $allowed = match ($type) {
                'paragraph','heading' => ['text', 'hardBreak'],'bulletList','orderedList' => ['listItem'],'listItem' => ['paragraph', 'bulletList', 'orderedList', 'blockquote'],'doc','blockquote' => ['paragraph', 'heading', 'blockquote', 'bulletList', 'orderedList', 'horizontalRule'],default => []
            };
            if (! in_array($childType, $allowed) || ! $this->node($child, $depth + 1, $count)) {
                return false;
            }
        }
        if (in_array($type, ['doc', 'listItem', 'bulletList', 'orderedList', 'blockquote']) && ! $children) {
            return false;
        }
        if ($type === 'listItem' && ($children[0]['type'] ?? '') !== 'paragraph') {
            return false;
        }

        return true;
    }
}
