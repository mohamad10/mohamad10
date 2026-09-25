<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncSiteRequest;
use App\Services\SiteContent;
use Illuminate\Http\JsonResponse;

class SiteController extends Controller
{
    public function __construct(private SiteContent $content) {}

    /** Public: full site content. */
    public function show(): JsonResponse
    {
        return response()->json($this->content->get());
    }

    /** Admin: replace the whole site content. */
    public function update(SyncSiteRequest $request): JsonResponse
    {
        return response()->json($this->content->sync($request->validated()));
    }
}
