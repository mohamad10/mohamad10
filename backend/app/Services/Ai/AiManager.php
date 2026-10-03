<?php

namespace App\Services\Ai;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Multi-provider AI gateway. Providers are tried in the configured order; when
 * one fails (quota, outage, bad key) the next one is used automatically.
 */
class AiManager
{
    public const SETTING = 'ai';

    /** @var array<string, Driver> */
    private array $drivers = [];

    public function settings(): array
    {
        return array_replace_recursive([
            'chain' => [],
            'providers' => [],
            'chat' => ['enabled' => false, 'leads' => true, 'name' => [], 'greeting' => [], 'instructions' => ''],
        ], Setting::get(self::SETTING, []));
    }

    /** Settings safe to send to the browser: keys are never returned, only whether one is set. */
    public function publicSettings(): array
    {
        $s = $this->settings();
        $providers = [];
        foreach (config('ai.providers') as $code => $def) {
            $p = $this->provider($code);
            $providers[] = [
                'code' => $code, 'label' => $def['label'], 'free' => $def['free'], 'driver' => $def['driver'],
                'key_url' => $def['key_url'], 'keyless' => $def['keyless'] ?? false, 'custom' => $def['custom'] ?? false,
                'default_model' => $def['model'], 'default_base_url' => $def['base_url'],
                'model' => $s['providers'][$code]['model'] ?? '', 'base_url' => $s['providers'][$code]['base_url'] ?? '',
                'has_key' => filled($p['api_key']), 'key_from_env' => blank($s['providers'][$code]['api_key'] ?? null) && filled(env($def['env'])),
                'key_hint' => filled($p['api_key']) ? '…'.substr($p['api_key'], -4) : null,
                'ready' => $this->ready($code),
            ];
        }

        return ['chain' => $s['chain'], 'providers' => $providers, 'chat' => $s['chat'], 'enabled' => $this->enabled()];
    }

    /** Merge admin changes. A blank api_key keeps the stored key; remove_key deletes it. */
    public function update(array $input): array
    {
        $s = $this->settings();
        if (array_key_exists('chain', $input)) {
            $s['chain'] = array_values(array_unique(array_intersect($input['chain'], array_keys(config('ai.providers')))));
        }
        foreach ($input['providers'] ?? [] as $code => $p) {
            if (! config("ai.providers.$code")) {
                continue;
            }
            $cur = $s['providers'][$code] ?? [];
            foreach (['model', 'base_url'] as $k) {
                if (array_key_exists($k, $p)) {
                    $cur[$k] = trim((string) $p[$k]);
                }
            }
            if (! empty($p['remove_key'])) {
                unset($cur['api_key']);
            } elseif (filled($p['api_key'] ?? null)) {
                $cur['api_key'] = Crypt::encryptString(trim($p['api_key']));
            }
            $s['providers'][$code] = $cur;
        }
        if (isset($input['chat'])) {
            $s['chat'] = array_replace($s['chat'], array_intersect_key($input['chat'], $s['chat']));
        }
        Setting::put(self::SETTING, $s);

        return $this->publicSettings();
    }

    /** Resolved provider config: catalog defaults + admin overrides + decrypted key (or env key). */
    public function provider(string $code): array
    {
        $def = config("ai.providers.$code") ?? throw new AiException("Unknown provider: $code");
        $over = $this->settings()['providers'][$code] ?? [];
        $key = null;
        if (filled($over['api_key'] ?? null)) {
            try {
                $key = Crypt::decryptString($over['api_key']);
            } catch (DecryptException) {
                $key = null; // APP_KEY changed; the key must be entered again
            }
        }

        return [
            ...$def, 'code' => $code,
            'model' => ($over['model'] ?? '') ?: $def['model'],
            'base_url' => ($over['base_url'] ?? '') ?: $def['base_url'],
            'api_key' => $key ?? env($def['env']),
        ];
    }

    public function ready(string $code): bool
    {
        $p = $this->provider($code);

        return filled($p['model']) && ($p['driver'] === 'anthropic' || filled($p['base_url']))
            && (($p['keyless'] ?? false) || filled($p['api_key']));
    }

    public function enabled(): bool
    {
        return collect($this->settings()['chain'])->contains(fn ($c) => config("ai.providers.$c") && $this->ready($c));
    }

    /**
     * Run a completion through the provider chain.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{text: string, provider: string, model: string}
     */
    public function complete(string $system, array $messages, int $maxTokens = 1024, ?string $only = null): array
    {
        $chain = $only ? [$only] : collect($this->settings()['chain'])->filter(fn ($c) => config("ai.providers.$c") && $this->ready($c))->values()->all();
        if (! $chain) {
            throw new AiException('No AI provider is configured.');
        }
        $errors = [];
        foreach ($chain as $code) {
            $p = $this->provider($code);
            try {
                return ['text' => trim($this->driver($p['driver'])->complete($p, $system, $messages, $maxTokens)), 'provider' => $code, 'model' => $p['model']];
            } catch (\Throwable $e) {
                $errors[] = "{$p['label']}: ".$e->getMessage();
                Log::warning("AI provider {$code} failed", ['error' => $e->getMessage()]);
            }
        }
        throw new AiException(implode(' | ', $errors));
    }

    public function models(string $code): array
    {
        $p = $this->provider($code);

        return $this->driver($p['driver'])->models($p);
    }

    public function driver(string $name): Driver
    {
        return $this->drivers[$name] ??= match ($name) {
            'anthropic' => app(AnthropicDriver::class),
            default => app(OpenAiCompatibleDriver::class),
        };
    }

    /** Swap a driver (used by tests). */
    public function setDriver(string $name, Driver $driver): void
    {
        $this->drivers[$name] = $driver;
    }
}
