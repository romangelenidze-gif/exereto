<?php

namespace App\Console\Commands;

use App\Models\FeedProduct;
use App\Services\Feeds\FeedVariantGrouper;
use Illuminate\Console\Command;

class GroupFeedVariantsCommand extends Command
{
    protected $signature = 'feeds:group-variants
                            {--feed= : Обработать только указанный фид}
                            {--dry-run : Показать статистику без изменений в БД}
                            {--sync-meili : Переиндексировать изменённые товары в Meilisearch}';

    protected $description = 'Глобальная группировка вариантов (память, связь, цвета, объёмы) и расчёт цен «от» по всем фидам';

    public function handle(FeedVariantGrouper $grouper): int
    {
        $started = microtime(true);
        $onlyFeed = $this->option('feed') ? (string) $this->option('feed') : null;
        $dryRun = (bool) $this->option('dry-run');

        $stats = $grouper->process($onlyFeed, $dryRun);

        $mode = $dryRun ? '[DRY-RUN] ' : '';
        $elapsed = round(microtime(true) - $started, 2);

        $this->info("{$mode}Группировка вариантов завершена за {$elapsed} сек:");
        $this->line(sprintf(
            '  Фидов: %d | Товаров: %d | Сгруппировано моделей: %d (%d товаров) | Цветов извлечено: %d | Конфигураций: %d | Диапазонов цен: %d | Обновлено строк: %d',
            $stats['feeds'],
            $stats['scanned'],
            $stats['grouped_models'],
            $stats['grouped_products'],
            $stats['colors_extracted'],
            $stats['variants_labeled'],
            $stats['price_ranges'],
            $stats['updated_rows'],
        ));

        if (!$dryRun && $this->option('sync-meili') && config('scout.driver') === 'meilisearch') {
            $changedIds = array_values(array_unique($stats['changed_ids']));
            if ($changedIds !== []) {
                $this->info('Синхронизация ' . count($changedIds) . ' изменённых товаров с Meilisearch...');
                foreach (array_chunk($changedIds, 2000) as $idChunk) {
                    FeedProduct::query()
                        ->whereIn('id', $idChunk)
                        ->where('is_active', true)
                        ->get()
                        ->searchable();
                }
            }
        }

        return self::SUCCESS;
    }
}