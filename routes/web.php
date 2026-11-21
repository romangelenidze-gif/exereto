<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

// Редирект со старых ссылок /en и /en/... на чистый URL без префикса
Route::get('/en/{any?}', function (?string $any = null) {
    $target = '/' . ltrim((string) $any, '/');
    $qs = request()->getQueryString();

    return redirect($target . ($qs ? '?' . $qs : ''), 301);
})->where('any', '.*')->name('redirect.en');

// EN — основной язык без префикса
Route::middleware(SetLocale::class . ':en')
    ->group(base_path('routes/localized.php'));

// RU — /ru/...
Route::prefix('ru')
    ->name('ru.')
    ->middleware(SetLocale::class . ':ru')
    ->group(base_path('routes/localized.php'));

// KA — /ka/...
Route::prefix('ka')
    ->name('ka.')
    ->middleware(SetLocale::class . ':ka')
    ->group(base_path('routes/localized.php'));