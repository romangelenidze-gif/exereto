<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($appUrl = config('app.url')) {
            URL::forceRootUrl($appUrl);
        }

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // --- ДОБАВИТЬ ЭТОТ БЛОК ---
        // Автоматически подставляем язык во все вызовы route() по всему сайту и API
        $locale = request()->segment(1);
        if (!in_array($locale, ['ru', 'en', 'ka'], true)) {
            $locale = 'ru'; // Ваш язык по умолчанию
        }
        URL::defaults(['locale' => $locale]);
        // --------------------------

        Order::observe(OrderObserver::class);
    }
}