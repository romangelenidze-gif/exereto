<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::command('quotes:expire')->hourly();
Schedule::command('autocomplete:sync-hits')->everyTenMinutes();

// Ежедневный ночной цикл обновления всех фидов, каскада, Meilisearch и кэша (04:30)
Schedule::command('feeds:sync-all')->dailyAt('04:30')->withoutOverlapping();

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