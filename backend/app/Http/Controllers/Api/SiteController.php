<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncSiteRequest;
use App\Services\SiteContent;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;

class SiteController extends Controller
{
    public function __construct(private SiteContent $content) {}

    /** Public: full site content with every translation, plus the language list. */
    public function show(): JsonResponse
    {
        return response()->json([...$this->content->get(), 'meta' => $this->meta()]);
    }

    /** Admin: replace the whole site content. */
    public function update(SyncSiteRequest $request): JsonResponse
    {
        return response()->json([...$this->content->sync($request->validated()), 'meta' => $this->meta()]);
    }

    private function meta(): array
    {
        return [
            'default_locale' => Locales::default(),
            'locales' => collect(Locales::all())->map(fn ($l, $code) => ['code' => $code, 'native' => $l['native'], 'dir' => $l['dir'], 'url' => Locales::url($code)])->values(),
            'image_base' => asset('storage'),
        ];
    }
}
