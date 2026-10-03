<?php

return [

    /*
    | Site languages. The default locale is served at "/", every other
    | locale at "/{code}" (e.g. /fa). To add a language: add an entry here
    | and a matching lang/{code}/site.php file with the interface strings.
    |
    | font: CSS font stack used for that language (fonts live in public/fonts).
    */
    'default_locale' => env('SITE_DEFAULT_LOCALE', 'en'),

    'locales' => [
        'en' => [
            'name' => 'English',
            'native' => 'English',
            'dir' => 'ltr',
            'og' => 'en_US',
            'font' => "'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif",
        ],
        'fa' => [
            'name' => 'Persian',
            'native' => 'فارسی',
            'dir' => 'rtl',
            'og' => 'fa_IR',
            // Vazirmatn with Persian digits and no Latin glyphs, so Latin words fall back to Plus Jakarta Sans.
            'font' => "'Vazirmatn FD NL', 'Plus Jakarta Sans', Tahoma, sans-serif",
        ],
    ],

    /*
    | Responsive image presets. Uploaded images are stored once as a
    | high-quality WebP master; every preset width is generated on first
    | request, cached in storage and served as a static file afterwards.
    */
    'images' => [
        'master_max' => 2560,
        'master_quality' => 90,
        'quality' => 84,
        'presets' => [
            'avatar' => ['ratio' => 1, 'widths' => [64, 128, 256]],
            'cover' => ['ratio' => 16 / 10, 'widths' => [480, 800, 1200, 1600]],
            'og' => ['ratio' => 1200 / 630, 'widths' => [1200]],
            'thumb' => ['ratio' => 1, 'widths' => [160]],
        ],
    ],
];
