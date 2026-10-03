<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * Upload pipeline: every upload is converted to a WebP master; responsive
 * variants per preset are rendered on demand (see ImageController) and cached
 * under storage/app/public/cache/{preset}/{width}/{path}.
 */
class Images
{
    public static function manager(): ImageManager
    {
        return extension_loaded('imagick') ? ImageManager::imagick() : ImageManager::gd();
    }

    /** Store an upload as a WebP master and return its storage path (relative to the public disk). */
    public static function store(UploadedFile $file): string
    {
        $cfg = config('site.images');
        $image = self::manager()->read($file->getRealPath())->orient()->scaleDown($cfg['master_max'], $cfg['master_max']);
        $path = 'uploads/'.now()->format('Y/m').'/'.Str::lower(Str::ulid()).'.webp';
        Storage::disk('public')->put($path, (string) $image->toWebp(quality: $cfg['master_quality'], strip: true));

        return $path;
    }

    /** Render (or reuse) a preset variant and return its absolute file path. */
    public static function variant(string $preset, int $width, string $path): ?string
    {
        $disk = Storage::disk('public');
        $cache = "cache/{$preset}/{$width}/".preg_replace('/\.\w+$/', '.webp', $path);
        if ($disk->exists($cache)) {
            return $disk->path($cache);
        }
        if (! $disk->exists($path)) {
            return null;
        }

        $ratio = config("site.images.presets.{$preset}.ratio");
        $image = self::manager()->read($disk->path($path));
        // Never upscale: when the master is smaller than the requested width, keep its size.
        $w = min($width, $image->width(), (int) round($image->height() * $ratio));
        $image->cover($w, (int) round($w / $ratio));
        $disk->put($cache, (string) $image->toWebp(quality: config('site.images.quality'), strip: true));

        return $disk->path($cache);
    }

    /** Local storage path for a stored image value, or null for external URLs / empty values. */
    public static function localPath(?string $src): ?string
    {
        if (blank($src)) {
            return null;
        }
        $src = preg_replace('#^'.preg_quote(url('/storage'), '#').'/#', '', $src);
        $src = preg_replace('#^/?storage/#', '', $src);

        return Str::startsWith($src, 'uploads/') && ! str_contains($src, '..') ? $src : null;
    }

    public static function url(?string $src, string $preset, ?int $width = null): ?string
    {
        if (blank($src)) {
            return null;
        }
        $path = self::localPath($src);
        if (! $path) {
            return preg_match('#^(https?:)?//#i', $src) ? $src : null;
        }
        $widths = config("site.images.presets.{$preset}.widths");
        $width ??= $widths[intdiv(count($widths), 2)];

        return asset("storage/cache/{$preset}/{$width}/".preg_replace('/\.\w+$/', '.webp', $path));
    }

    /** srcset string for a stored image, or null for external images. */
    public static function srcset(?string $src, string $preset): ?string
    {
        if (! self::localPath($src)) {
            return null;
        }

        return collect(config("site.images.presets.{$preset}.widths"))
            ->map(fn ($w) => self::url($src, $preset, $w)." {$w}w")->implode(', ');
    }
}
