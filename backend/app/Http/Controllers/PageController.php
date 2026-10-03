<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiManager;
use App\Services\SiteContent;
use App\Support\Locales;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(SiteContent $content, AiManager $ai, ?string $locale = null): View
    {
        app()->setLocale($locale ?? Locales::default());
        $chat = $ai->settings()['chat'];

        return view('site.home', [
            'site' => $content->get(),
            'chat' => $chat['enabled'] && $ai->enabled() ? [
                'name' => Locales::pick($chat['name'] ?? []) ?: Locales::pick($content->get()['team']['name'] ?? ''),
                'greeting' => Locales::pick($chat['greeting'] ?? []) ?: __('site.chat.greeting'),
            ] : null,
        ]);
    }

    public function sitemap(): Response
    {
        return response()->view('site.sitemap', [], 200, ['Content-Type' => 'application/xml']);
    }
}
