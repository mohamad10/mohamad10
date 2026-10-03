<?php

namespace App\Services\Ai;

interface Driver
{
    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages
     */
    public function complete(array $provider, string $system, array $messages, int $maxTokens): string;

    /** @return list<string> */
    public function models(array $provider): array;
}
