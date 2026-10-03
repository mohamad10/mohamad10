<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/** Chat Completions API used by Gemini, Groq, OpenRouter, Mistral, Ollama, OpenAI, … */
class OpenAiCompatibleDriver implements Driver
{
    public function complete(array $provider, string $system, array $messages, int $maxTokens): string
    {
        $res = $this->http($provider)->post('/chat/completions', [
            'model' => $provider['model'],
            'messages' => [['role' => 'system', 'content' => $system], ...$messages],
            'max_tokens' => $maxTokens,
            'temperature' => 0.4,
        ]);
        if ($res->failed()) {
            throw new AiException($this->error($res->json(), $res->status()));
        }
        $text = $res->json('choices.0.message.content');
        if (! is_string($text) || trim($text) === '') {
            throw new AiException('Empty response');
        }

        return $text;
    }

    public function models(array $provider): array
    {
        $res = $this->http($provider)->get('/models');
        if ($res->failed()) {
            throw new AiException($this->error($res->json(), $res->status()));
        }

        return collect($res->json('data') ?? $res->json() ?? [])
            ->map(fn ($m) => is_array($m) ? ($m['id'] ?? null) : null)
            ->filter()->map(fn ($id) => preg_replace('#^models/#', '', $id))->sort()->values()->all();
    }

    private function http(array $provider): PendingRequest
    {
        $req = Http::baseUrl(rtrim($provider['base_url'], '/'))
            ->timeout(config('ai.timeout'))->acceptJson()
            ->withHeaders(['HTTP-Referer' => config('app.url'), 'X-Title' => config('app.name')]);

        return filled($provider['api_key']) ? $req->withToken($provider['api_key']) : $req;
    }

    private function error(mixed $json, int $status): string
    {
        $msg = is_array($json) ? (data_get($json, 'error.message') ?? data_get($json, '0.error.message') ?? data_get($json, 'message')) : null;

        return "HTTP {$status}".($msg ? ": {$msg}" : '');
    }
}
