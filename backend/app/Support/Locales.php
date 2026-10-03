<?php

namespace App\Support;

class Locales
{
    /** @return array<string, array> */
    public static function all(): array
    {
        return config('site.locales');
    }

    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function default(): string
    {
        return config('site.default_locale');
    }

    /** Locales served under a "/{code}" prefix (every locale except the default). */
    public static function prefixed(): array
    {
        return array_values(array_diff(self::codes(), [self::default()]));
    }

    public static function current(): array
    {
        return self::all()[app()->getLocale()] ?? self::all()[self::default()];
    }

    public static function url(string $locale): string
    {
        return $locale === self::default() ? url('/') : url('/'.$locale);
    }

    /**
     * Resolve a translatable value ({"en": "...", "fa": "..."}) for a locale,
     * falling back to the default locale and then to any non-empty translation.
     */
    public static function pick(mixed $value, ?string $locale = null): string
    {
        if (! is_array($value)) {
            return (string) $value;
        }
        $locale ??= app()->getLocale();
        foreach ([$locale, self::default()] as $code) {
            if (filled($value[$code] ?? null)) {
                return $value[$code];
            }
        }

        return (string) collect($value)->first(fn ($v) => filled($v), '');
    }
}
