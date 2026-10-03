<?php

use App\Support\Locales;

if (! function_exists('localized')) {
    /** Resolve a translatable value for the current locale (with fallback). */
    function localized(mixed $value, ?string $locale = null): string
    {
        return Locales::pick($value, $locale);
    }
}

if (! function_exists('asset_v')) {
    /** Asset URL with a cache-busting version based on the file's modification time. */
    function asset_v(string $path): string
    {
        $file = public_path($path);

        return asset($path).(is_file($file) ? '?v='.filemtime($file) : '');
    }
}

if (! function_exists('safe_url')) {
    /** Only allow http(s), mailto, tel and relative links in user-provided URLs. */
    function safe_url(?string $url): string
    {
        $url = trim((string) $url);

        return preg_match('#^(https?:|mailto:|tel:|/|\#)#i', $url) ? $url : '#';
    }
}

if (! function_exists('local_year')) {
    /** Current year in the locale's calendar (Solar Hijri for Persian). */
    function local_year(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        if ($locale === 'fa' && class_exists(IntlDateFormatter::class)) {
            return (new IntlDateFormatter('fa_IR@calendar=persian', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, IntlDateFormatter::TRADITIONAL, 'y'))->format(time());
        }

        return date('Y');
    }
}
