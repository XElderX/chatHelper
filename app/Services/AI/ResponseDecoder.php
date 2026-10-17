<?php

namespace App\Services\AI;

use Psr\Http\Message\StreamInterface;

class ResponseDecoder
{
    public function decode(array $response): array
    {
        if (($response['status'] ?? '') !== 'completed') {
            throw new AiException('The AI response was incomplete. Try a shorter passage.');
        }
        $text = '';
        foreach ($response['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $part) {
                if (($part['type'] ?? '') === 'refusal') {
                    throw new AiException('The AI provider declined this request.', 422);
                }
                if (($part['type'] ?? '') === 'output_text') {
                    $text .= $part['text'] ?? '';
                }
            }
        }
        try {
            $data = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new AiException('The AI returned invalid structured data. Please retry.');
        }
        if (! is_array($data)) {
            throw new AiException('The AI returned an invalid response.');
        }

        return $data;
    }

    // Reads incrementally, bounded even if the provider never sends a completion.
    public function stream(StreamInterface $stream): array
    {
        $buffer = '';
        $bytes = 0;
        $completed = null;
        try {
            while (! $stream->eof()) {
                $chunk = $stream->read(8192);
                $bytes += strlen($chunk);
                if ($bytes > 2_000_000) {
                    throw new AiException('The AI response exceeded the safe response size.');
                }
                $buffer .= $chunk;
                $buffer = str_replace("\r\n", "\n", $buffer);
                while (($end = strpos($buffer, "\n\n")) !== false) {
                    $frame = substr($buffer, 0, $end);
                    $buffer = substr($buffer, $end + 2);
                    $lines = [];
                    foreach (explode("\n", $frame) as $line) {
                        if (str_starts_with($line, 'data:')) {
                            $lines[] = ltrim(substr($line, 5), ' ');
                        }
                    }
                    if (! $lines) {
                        continue;
                    }$raw = implode("\n", $lines);
                    if ($raw === '[DONE]') {
                        continue;
                    }
                    try {
                        $event = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                    } catch (\Throwable) {
                        throw new AiException('OpenAI returned an invalid stream event.');
                    }
                    $type = $event['type'] ?? '';
                    if (in_array($type, ['response.failed', 'response.incomplete', 'error'])) {
                        $code = $event['response']['error']['code'] ?? $event['error']['code'] ?? $event['code'] ?? '';
                        if (in_array($code, ['subscription_sharing_usage_limit_exceeded', 'subscription_sharing_usage_unavailable'])) {
                            throw new AiException('ChatGPT plan usage is unavailable or its limit has been reached. Check ChatGPT Settings → Usage. API billing was not used.', 429);
                        }
                        throw new AiException('The ChatGPT response failed or was incomplete. No text was changed.');
                    }
                    if ($type === 'response.completed') {
                        $completed = $event['response'] ?? null;
                        break 2;
                    }
                }
            }
        } catch (AiException $e) {
            throw $e;
        } catch (\Throwable) {
            throw new AiException('The ChatGPT stream was interrupted. Please try again.', 504);
        } finally {
            $stream->close();
        }
        if (! is_array($completed)) {
            throw new AiException('The ChatGPT stream ended without a completed response.');
        }

        return $this->decode($completed);
    }
}
