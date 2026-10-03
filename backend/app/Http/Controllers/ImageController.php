<?php

namespace App\Http\Controllers;

use App\Support\Images;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImageController extends Controller
{
    /** Generates a preset variant on first request; afterwards the web server serves the cached file directly. */
    public function __invoke(string $preset, int $width, string $path): BinaryFileResponse
    {
        abort_unless(in_array($width, config("site.images.presets.{$preset}.widths", []), true), 404);
        $source = collect(['webp', 'jpg', 'jpeg', 'png'])
            ->map(fn ($ext) => preg_replace('/\.webp$/', ".{$ext}", $path))
            ->first(fn ($p) => Images::localPath($p) && \Storage::disk('public')->exists($p));
        abort_unless($source, 404);

        $file = Images::variant($preset, $width, $source);
        abort_unless($file, 404);

        return response()->file($file, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
