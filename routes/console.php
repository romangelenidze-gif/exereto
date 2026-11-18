<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;

Schedule::command('quotes:expire')->hourly();
Schedule::command('autocomplete:sync-hits')->everyTenMinutes();

// Ежедневный ночной запуск (в 04:30)
Schedule::call(function () {
    $feeds = config('feeds.feeds', []);
    
    // 1. Импортируем все включенные фиды из конфига
    foreach ($feeds as $key => $cfg) {
        if (!empty($cfg['enabled'])) {
            Log::info("Авто-запуск импорта для фида: {$key}");
            Artisan::call("feeds:import {$key}");
        }
    }
    
    // 2. После загрузки всех товаров запускаем каскадную склейку дублей (игрушки/техника)
    Log::info("Авто-запуск каскадной дедупликации...");
    Artisan::call('feeds:deduplicate');
    
})->dailyAt('04:30')->withoutOverlapping();

// Ежемесячная чистка и продвижение популярных запросов автокомплита
Schedule::call(function () {
    DB::table('learned_autocomplete')
        ->where('hits', '>', 5)
        ->update(['is_permanent' => 1]);

    DB::table('learned_autocomplete')
        ->where('is_permanent', 0)
        ->where('last_used_at', '<', now()->subMonth())
        ->delete();
})->monthly();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');