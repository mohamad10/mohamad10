<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /** Public: visitor sends a message from the site. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['nullable', 'string', 'max:190'],
            'body' => ['required', 'string', 'min:5', 'max:5000'],
            'website' => ['prohibited'], // honeypot
        ]);
        ContactMessage::create([...$data, 'ip' => $request->ip()]);

        return response()->json(['ok' => true], 201);
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ContactMessage::latest()->limit(200)->get(),
            'unread' => ContactMessage::whereNull('read_at')->count(),
        ]);
    }

    public function markRead(ContactMessage $message): JsonResponse
    {
        $message->update(['read_at' => $message->read_at ? null : now()]);

        return response()->json($message);
    }

    public function destroy(ContactMessage $message): JsonResponse
    {
        $message->delete();

        return response()->json(['ok' => true]);
    }
}
