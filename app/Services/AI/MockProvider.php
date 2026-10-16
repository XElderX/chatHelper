<?php

namespace App\Services\AI;

class MockProvider implements AiProviderInterface
{
    public function generate(string $task, array $input, string $mode): array
    {
        $rules = ['teh' => ['the', 'spelling'], 'recieve' => ['receive', 'spelling'], 'I is' => ['I am', 'grammar'], 'She go' => ['She goes', 'grammar']];
        if ($task === 'explain') {
            return ['explanation' => 'Mock explanation: review the original and replacement. This is a deterministic demo, not an AI assessment.'];
        }
        if ($task === 'rewrite') {
            $text = str_replace(array_keys($rules), array_column(array_values($rules), 0), $input['text']);
            if ($mode === 'natural' || $input['action'] === 'natural') {
                $text = str_replace('I would like to say that ', '', $text);
            }

            return ['replacement' => $text, 'explanation' => 'Mock preview: only a few demonstration rules are supported. Use OpenAI for real rewriting.'];
        }
        $items = [];
        foreach ($input['blocks'] as $block) {
            foreach ($rules as $word => $rule) {
                $count = substr_count($block['text'], $word);
                for ($i = 0; $i < $count; $i++) {
                    $items[] = ['block_id' => $block['id'], 'original' => $word, 'replacement' => $rule[0], 'occurrence' => $i, 'explanation' => 'Demo correction. Use the OpenAI provider for comprehensive analysis.', 'category' => $rule[1], 'severity' => 'medium'];
                }
            }
        }

        return ['suggestions' => array_slice($items, 0, 40)];
    }
}
