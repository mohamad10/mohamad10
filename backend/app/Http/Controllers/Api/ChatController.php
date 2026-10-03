<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use App\Services\Ai\AiException;
use App\Services\Ai\AiManager;
use App\Services\Ai\SiteChat;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Public endpoints of the website chat assistant. */
class ChatController extends Controller
{
    public function __construct(private AiManager $ai) {}

    public function send(Request $request, SiteChat $chat): JsonResponse
    {
        abort_unless($this->ai->settings()['chat']['enabled'] && $this->ai->enabled(), 404);
        $data = $request->validate([
            'session_id' => ['nullable', 'uuid'],
            'message' => ['required', 'string', 'max:1000'],
            'locale' => ['nullable', 'in:'.implode(',', Locales::codes())],
        ]);

        $session = ChatSession::find($data['session_id'] ?? null) ?? ChatSession::create([
            'id' => (string) Str::uuid(), 'locale' => $data['locale'] ?? Locales::default(),
            'ip' => $request->ip(), 'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);
        if ($session->messages()->where('role', 'user')->count() >= SiteChat::MAX_TURNS) {
            return response()->json(['message' => 'limit'], 429);
        }
        $session->update(['read_at' => null]);

        try {
            $out = $chat->reply($session, trim($data['message']));
        } catch (AiException $e) {
            report($e);

            return response()->json(['message' => 'unavailable', 'session_id' => $session->id], 503);
        }

        return response()->json(['session_id' => $session->id, ...$out]);
    }

    public function history(ChatSession $session): JsonResponse
    {
        return response()->json(['messages' => $session->messages()->get(['role', 'content'])]);
    }
}
