<?php

namespace App\Console\Commands;

use App\Http\Controllers\HomeController;
use App\Models\FeedProduct;
use App\Services\FeedCategoryTiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncAllFeedsCommand extends Command
{
    protected $signature = 'feeds:sync-all {--skip-import : Пропустить загрузку фидов (только каскад, автокомплит и прогрев)}';

    protected $description = 'Единый ночной цикл: изолированный импорт фидов -> точечная синхронизация Meilisearch -> каскад -> автокомплит -> прогрев кэша';

    public function handle(FeedCategoryTiles $categoryTiles): int
    {
        $started = microtime(true);
        $feeds = (array) config('feeds.feeds', []);
        $failed = [];
        $syncedFeeds = [];

        if (!$this->option('skip-import')) {
            foreach ($feeds as $key => $cfg) {
                $ruleEnabled = config("feed_rules.stores.{$key}.enabled", true);
                if (empty($cfg['enabled']) || !$ruleEnabled || empty($cfg['url'])) {
                    continue;
                }

                $this->info("=== Импорт: {$key} ===");
                try {
                    $code = $this->call('feeds:import', ['feed' => $key, '--no-warm' => true]);
                    if ($code !== self::SUCCESS) {
                        $failed[] = $key;
                        continue;
                    }

                    $syncedFeeds[] = $key;

                    if (config('scout.driver') === 'meilisearch') {
                        $this->info("Синхронизация Meilisearch для «{$key}»...");
                        FeedProduct::query()
                            ->where('feed', $key)
                            ->where('is_active', false)
                            ->chunkById(5000, fn ($models) => $models->unsearchable());

                        FeedProduct::query()
                            ->where('feed', $key)
                            ->where('is_active', true)
                            ->chunkById(5000, fn ($models) => $models->searchable());
                    }
                } catch (\Throwable $e) {
                    $failed[] = $key;
                    Log::error("SyncAllFeeds: ошибка фида {$key}", ['error' => $e->getMessage()]);
                    $this->error("Ошибка {$key}: {$e->getMessage()}");
                }
            }
        }

        $this->info('=== Каскадная дедупликация ===');
        try {
            $this->call('feeds:deduplicate', ['--no-warm' => true]);
        } catch (\Throwable $e) {
            Log::error('SyncAllFeeds: ошибка дедупликации', ['error' => $e->getMessage()]);
            $this->error('Ошибка дедупликации: ' . $e->getMessage());
        }

        if (Schema::hasTable('autocomplete_entries')) {
            $this->info('=== Пересборка автокомплита ===');
            try {
                $this->call('autocomplete:rebuild');
            } catch (\Throwable $e) {
                Log::error('SyncAllFeeds: ошибка autocomplete:rebuild', ['error' => $e->getMessage()]);
                $this->error('Ошибка автокомплита: ' . $e->getMessage());
            }
        }

        $this->info('=== Прогрев кэша категорий (EN / RU / KA) ===');
        $categoryTiles->warmAll();

        $this->info('=== Прогрев тематических блоков главной (EN / RU / KA / EL) ===');
        Cache::forget('home.thematic_blocks.raw.v2');
        $origLocale = app()->getLocale();
        foreach (['en', 'ru', 'ka', 'el'] as $loc) {
            Cache::forget("home.thematic_blocks.v2.{$loc}");
            app()->setLocale($loc);
            app()->call([app(HomeController::class), 'index']);
        }
        app()->setLocale($origLocale);

        $elapsed = round(microtime(true) - $started, 1);
        $this->info("Ночной цикл завершён за {$elapsed} сек. Успешно: " . count($syncedFeeds) . ' | Ошибок: ' . count($failed));

        $this->call('feeds:audit');

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}