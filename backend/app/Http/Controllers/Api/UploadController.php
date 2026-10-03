<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Images;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    /** Any accepted image is converted to WebP; sized variants are produced per section on demand. */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240']]);
        $path = Images::store($request->file('file'));

        return response()->json(['path' => $path, 'preview' => Images::url($path, 'thumb')], 201);
    }
}
