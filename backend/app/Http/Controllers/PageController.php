<?php

namespace App\Http\Controllers;

use App\Services\SiteContent;
use App\Support\Locales;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(SiteContent $content, ?string $locale = null): View
    {
        app()->setLocale($locale ?? Locales::default());

        return view('site.home', ['site' => $content->get()]);
    }

    public function sitemap(): Response
    {
        return response()->view('site.sitemap', [], 200, ['Content-Type' => 'application/xml']);
    }
}
