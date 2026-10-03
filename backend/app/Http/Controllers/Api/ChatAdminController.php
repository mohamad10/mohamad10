<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatSession;
use Illuminate\Http\JsonResponse;

/** Admin: transcripts of the website chat assistant. */
class ChatAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $sessions = ChatSession::withCount('messages')->with('lead:id,name,email')
            ->has('messages')->latest('updated_at')->limit(100)->get()
            ->map(fn ($s) => [
                ...$s->only('id', 'locale', 'messages_count', 'read_at', 'created_at', 'updated_at'),
                'lead' => $s->lead?->only('name', 'email'),
                'preview' => $s->messages()->where('role', 'user')->value('content'),
            ]);

        return response()->json(['data' => $sessions, 'unread' => ChatSession::has('messages')->whereNull('read_at')->count()]);
    }

    public function show(ChatSession $session): JsonResponse
    {
        $session->update(['read_at' => now()]);

        return response()->json(['messages' => $session->messages()->get(['role', 'content', 'provider', 'created_at'])]);
    }

    public function destroy(ChatSession $session): JsonResponse
    {
        $session->delete();

        return response()->json(['ok' => true]);
    }
}
