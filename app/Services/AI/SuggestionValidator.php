<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Validator;

class SuggestionValidator
{
    public function validate(array $output, array $blocks): array
    {
        $v = Validator::make($output, [
            'suggestions' => 'present|array|max:40',
            'suggestions.*.block_id' => 'required|string', 'suggestions.*.original' => 'required|string|max:4000',
            'suggestions.*.replacement' => 'present|string|max:6000', 'suggestions.*.occurrence' => 'required|integer|min:0|max:12000',
            'suggestions.*.explanation' => 'required|string|max:2000', 'suggestions.*.category' => 'required|in:spelling,grammar,clarity,style',
            'suggestions.*.severity' => 'required|in:low,medium,high',
        ]);
        if ($v->fails()) {
            throw new AiException('The AI response failed validation. Please retry.');
        }
        $texts = array_column($blocks, 'text', 'id');
        $valid = [];
        $ranges = [];
        foreach ($output['suggestions'] as $s) {
            $text = $texts[$s['block_id']] ?? null;
            if ($text === null || $s['original'] === $s['replacement']) {
                continue;
            }
            $start = 0;
            $found = false;
            for ($i = 0; $i <= $s['occurrence']; $i++) {
                $pos = mb_strpos($text, $s['original'], $start, 'UTF-8');
                if ($pos === false) {
                    $found = false;
                    break;
                }
                $found = $pos;
                $start = $pos + mb_strlen($s['original'], 'UTF-8');
            }
            if ($found === false) {
                continue;
            }
            $from = strlen(mb_convert_encoding(mb_substr($text, 0, $found, 'UTF-8'), 'UTF-16LE', 'UTF-8')) / 2;
            $to = $from + strlen(mb_convert_encoding($s['original'], 'UTF-16LE', 'UTF-8')) / 2;
            $overlap = false;
            foreach ($ranges[$s['block_id']] ?? [] as [$a,$b]) {
                if ($from < $b && $to > $a) {
                    $overlap = true;
                }
            }
            if ($overlap) {
                continue;
            }
            $ranges[$s['block_id']][] = [$from, $to];
            $valid[] = [...$s, 'start' => (int) $from, 'end' => (int) $to, 'id' => hash('sha256', json_encode([$s['block_id'], $from, $to, $s['replacement']]))];
        }

        return $valid;
    }
}
