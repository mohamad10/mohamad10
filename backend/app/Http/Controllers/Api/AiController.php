<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\Ai\AiException;
use App\Services\Ai\AiManager;
use App\Services\Ai\Assistant;
use App\Services\SiteContent;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/** Admin: AI provider settings and writing tools. */
class AiController extends Controller
{
    public function __construct(private AiManager $ai, private Assistant $assistant) {}

    public function settings(): JsonResponse
    {
        return response()->json($this->ai->publicSettings());
    }

    public function update(Request $request): JsonResponse
    {
        $codes = implode(',', array_keys(config('ai.providers')));
        $data = $request->validate([
            'chain' => ['sometimes', 'array'], 'chain.*' => ["in:$codes"],
            'providers' => ['sometimes', 'array:'.$codes],
            'providers.*.api_key' => ['nullable', 'string', 'max:500'],
            'providers.*.remove_key' => ['sometimes', 'boolean'],
            'providers.*.model' => ['nullable', 'string', 'max:200'],
            'providers.*.base_url' => ['nullable', 'url:http,https', 'max:300'],
            'chat' => ['sometimes', 'array'],
            'chat.enabled' => ['boolean'], 'chat.leads' => ['boolean'],
            'chat.name' => ['nullable', 'array'], 'chat.name.*' => ['nullable', 'string', 'max:60'],
            'chat.greeting' => ['nullable', 'array'], 'chat.greeting.*' => ['nullable', 'string', 'max:500'],
            'chat.instructions' => ['nullable', 'string', 'max:3000'],
        ]);
        return response()->json($this->ai->update($data));
    }

    public function test(Request $request): JsonResponse
    {
        $code = $request->validate(['provider' => ['required', 'in:'.implode(',', array_keys(config('ai.providers')))]])['provider'];
        $start = microtime(true);

        return $this->attempt(fn () => [
            'reply' => $this->ai->complete('Reply with one short friendly sentence.', [['role' => 'user', 'content' => 'Say hello in English and Persian.']], 100, $code)['text'],
            'ms' => (int) ((microtime(true) - $start) * 1000),
        ]);
    }

    public function models(string $provider): JsonResponse
    {
        abort_unless(config("ai.providers.$provider"), 404);

        return $this->attempt(fn () => ['models' => $this->ai->models($provider)]);
    }

    public function assist(Request $request): JsonResponse
    {
        $locales = implode(',', Locales::codes());
        $data = $request->validate([
            'task' => ['required', 'in:translate,improve,generate,skills,reply'],
            'text' => ['nullable', 'string', 'max:8000'],
            'from' => ["nullable", "in:$locales"], 'to' => ["nullable", "in:$locales"],
            'lang' => ["nullable", "in:$locales"],
            'field' => ['nullable', 'string', 'max:60'],
            'context' => ['nullable', 'array'],
            'message_id' => ['nullable', 'integer'],
        ]);
        $lang = $data['lang'] ?? Locales::default();

        return $this->attempt(fn () => match ($data['task']) {
            'translate' => ['text' => $this->assistant->translate($this->need($data, 'text'), $data['from'] ?? Locales::default(), $data['to'] ?? $lang)],
            'improve' => ['text' => $this->assistant->improve($this->need($data, 'text'), $lang, $data['field'] ?? '')],
            'generate' => ['text' => $this->assistant->generate($data['field'] ?? 'description', $data['context'] ?? [], $lang)],
            'skills' => ['skills' => $this->assistant->suggestSkills($data['context'] ?? [])],
            'reply' => ['text' => $this->assistant->replyDraft(
                ContactMessage::findOrFail($data['message_id'] ?? 0)->only('name', 'email', 'subject', 'body'),
                Locales::pick(app(SiteContent::class)->get()['team']['name'] ?? ''),
            )],
        });
    }

    /** Translate every missing translatable field of the site into one language and save. */
    public function translateMissing(Request $request, SiteContent $content): JsonResponse
    {
        $to = $request->validate(['locale' => ['required', 'in:'.implode(',', Locales::prefixed())]])['locale'];
        $from = Locales::default();
        $site = $content->get();
        $todo = [];
        $walk = function ($node, $path) use (&$walk, &$todo, $from, $to) {
            if (! is_array($node)) {
                return;
            }
            if ($node && array_diff(array_keys($node), Locales::codes()) === [] && ! array_is_list($node)) {
                if (filled($node[$from] ?? null) && blank($node[$to] ?? null)) {
                    $todo[$path] = $node[$from];
                }

                return;
            }
            foreach ($node as $k => $v) {
                $walk($v, $path === '' ? (string) $k : "$path.$k");
            }
        };
        $walk($site, '');
        if (! $todo) {
            return response()->json(['translated' => 0, 'site' => $site]);
        }

        return $this->attempt(function () use ($todo, $from, $to, $site, $content) {
            $done = $this->assistant->translateMany($todo, $from, $to);
            foreach ($done as $path => $text) {
                Arr::set($site, "$path.$to", $text);
            }

            return ['translated' => count($done), 'site' => $content->sync($site)];
        });
    }

    private function need(array $data, string $key): string
    {
        abort_if(blank($data[$key] ?? null), 422, "The $key field is required.");

        return $data[$key];
    }

    private function attempt(callable $fn): JsonResponse
    {
        try {
            return response()->json($fn());
        } catch (AiException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }
}
