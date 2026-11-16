<?php

namespace App\Console\Commands;

use App\Models\FeedProduct;
use App\Services\FeedCategoryTiles;
use App\Services\Feeds\AwinFeedImporter;
use App\Services\Feeds\FeedEntityLocalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ImportFeedCommand extends Command
{
    protected $signature = 'feeds:import
        {feed=adidas : Ключ фида из config/feeds.php}
        {--source= : URL или путь к локальному .csv / .csv.gz}
        {--limit=0 : Импортировать только первые N товаров (для безопасного теста)}
        {--no-warm : Пропустить пересборку автокомплита и прогрев кэша в конце}';

    protected $description = 'Импорт партнёрского продуктового фида в таблицу feed_products';

    public function handle(AwinFeedImporter $importer, FeedEntityLocalizer $localizer, FeedCategoryTiles $categoryTiles): int
    {
        $feed = (string) $this->argument('feed');
        $limit = max(0, (int) $this->option('limit'));

        $this->info($limit > 0
            ? "Тестовый импорт фида «{$feed}» (лимит: {$limit} товаров)..."
            : "Импорт фида «{$feed}»...");

        try {
            $stats = $importer->import($feed, $this->option('source') ?: null, $limit);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            Log::error('Feed import failed', ['feed' => $feed, 'error' => $e->getMessage()]);

            return self::FAILURE;
        }

        // Отсекаем нецелевые разделы (Home, посуда, декор, канцелярия) после импорта
        $excludedTops = array_filter((array) config('home_categories.excluded_tops', ['Home']));
        if ($excludedTops !== []) {
            $deactivatedJunk = DB::table('feed_products')
                ->where('feed', $feed)
                ->where('is_active', true)
                ->where(function ($q) use ($excludedTops) {
                    $q->whereIn('category_top', $excludedTops);
                    foreach ($excludedTops as $top) {
                        $q->orWhere('category_path', 'like', $top . ',%')
                          ->orWhere('category_path', $top);
                    }
                })
                ->update(['is_active' => false]);

            if ($deactivatedJunk > 0) {
                $this->info("Отсечено нецелевых товаров ({$feed}): {$deactivatedJunk}.");
            }
        }

        // Сброс кэша главной, брендов, автокомплита и плиток категорий
        Cache::forget("home.category_chips.v2.{$feed}");
        Cache::forget('home.category_tiles.v5.all');
        Cache::forget('home.popular_cards.v2');
        Cache::forget('home.discount_cards.v2');
        Cache::forget('home.top_brands.v2');
        Cache::forget('autocomplete.brands.all.v1');

        foreach ((array) config('home_categories.tiles', []) as $tile) {
            $slug = (string) ($tile['slug'] ?? '');
            if ($slug !== '') {
                Cache::forget("category.subs.v2.{$slug}");
                Cache::forget("category.facets.v5.{$slug}." . md5(json_encode([])));
            }
        }

        $this->table(
            ['Строк прочитано', 'Загружено', 'Пропущено', 'Деактивировано'],
            [[$stats['total'], $stats['imported'], $stats['skipped'], $stats['deactivated']]]
        );

        if ($stats['deactivation_skipped']) {
            $this->warn('Деактивация отсутствующих товаров пропущена (режим теста или малый объём фида).');
        }

        $dispatched = $localizer->dispatchMissingTranslations($feed, false);
        if ($dispatched > 0) {
            $this->info("Поставлено в очередь на перевод новых сущностей: {$dispatched}.");
        }

        if ($this->option('no-warm')) {
            $this->info('Пересборка автокомплита и прогрев кэша пропущены (--no-warm).');

            return self::SUCCESS;
        }

        if (Schema::hasTable('autocomplete_entries')) {
            $this->call('autocomplete:rebuild');
        }

        if (config('scout.driver') === 'meilisearch') {
            $this->call('scout:import', ['model' => FeedProduct::class]);
        }

        // Прогреваем кэш всех категорий и фасетов в Redis
        $this->info('Прогрев кэша категорий и фасетов...');
        $categoryTiles->warmAll();
        $this->info('Кэш категорий успешно обновлён.');

        return self::SUCCESS;
    }
}