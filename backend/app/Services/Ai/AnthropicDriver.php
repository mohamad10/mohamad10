<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\NotFoundException;
use Anthropic\Core\Exceptions\RateLimitException;

/** Claude through the official Anthropic PHP SDK. */
class AnthropicDriver implements Driver
{
    /** Models that accept the server-side refusal fallback ("default" routing). */
    private const FALLBACK_MODELS = ['claude-fable-5-1', 'claude-opus-5-5', 'claude-opus-5', 'claude-sonnet-5-5'];

    public function complete(array $provider, string $system, array $messages, int $maxTokens): string
    {
        $client = $this->client($provider);
        $model = $provider['model'];

        try {
            $message = in_array($model, self::FALLBACK_MODELS, true)
                ? $client->beta->messages->create(
                    maxTokens: $maxTokens, model: $model, system: $system, messages: $messages,
                    outputConfig: ['effort' => 'low'],
                    fallbacks: 'default', betas: ['server-side-fallback-2026-07-01'],
                )
                : $client->messages->create(maxTokens: $maxTokens, model: $model, system: $system, messages: $messages);
        } catch (NotFoundException $e) {
            throw new AiException("Model not found: {$model}");
        } catch (AuthenticationException $e) {
            throw new AiException('Invalid API key');
        } catch (RateLimitException $e) {
            throw new AiException('Rate limited');
        } catch (APIStatusException $e) {
            throw new AiException($e->getMessage());
        } catch (APIConnectionException $e) {
            throw new AiException('Connection failed: '.$e->getMessage());
        }

        if ($message->stopReason === 'refusal') {
            throw new AiException('The model declined this request');
        }
        $text = collect($message->content)->filter(fn ($b) => $b->type === 'text')->map(fn ($b) => $b->text)->implode('');
        if (trim($text) === '') {
            throw new AiException('Empty response');
        }

        return $text;
    }

    public function models(array $provider): array
    {
        try {
            $ids = [];
            foreach ($this->client($provider)->models->list(limit: 100)->pagingEachItem() as $m) {
                $ids[] = $m->id;
            }

            return $ids;
        } catch (APIStatusException|APIConnectionException $e) {
            throw new AiException($e->getMessage());
        }
    }

    private function client(array $provider): Client
    {
        return new Client(apiKey: $provider['api_key'], baseUrl: $provider['base_url'] ?: null);
    }
}
