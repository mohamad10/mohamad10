<?php

use App\Http\Controllers\ImageController;
use App\Http\Controllers\PageController;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;

// Default language lives at "/", the others at "/{code}" (e.g. /fa)
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/{locale}', [PageController::class, 'home'])->whereIn('locale', Locales::prefixed())->name('home.locale');
Route::redirect('/'.Locales::default(), '/', 301);

Route::view('/admin', 'admin')->name('admin');
Route::get('/sitemap.xml', [PageController::class, 'sitemap']);

// Responsive WebP variants, generated on demand and cached as static files
Route::get('/storage/cache/{preset}/{width}/{path}', ImageController::class)
    ->whereIn('preset', array_keys(config('site.images.presets')))
    ->whereNumber('width')
    ->where('path', 'uploads/.+\.webp');
