<?php

namespace App\Console\Commands;

use App\Services\FeedCategoryTiles;
use App\Services\Feeds\FeedDeduplicator;
use Illuminate\Console\Command;

class DeduplicateFeedsCommand extends Command
{
    protected $signature = 'feeds:deduplicate {--no-warm : Не прогревать кэш категорий}';

    protected $description = 'Каскадная дедупликация по EAN-13 и номерам наборов с синхронизацией Meilisearch';

    public function handle(FeedDeduplicator $deduplicator, FeedCategoryTiles $tiles): int
    {
        $stats = $deduplicator->run(true);

        if ($stats['groups'] === []) {
            $this->warn('Каскадные группы в config/feed_rules.php не настроены.');

            return self::SUCCESS;
        }

        foreach ($stats['groups'] as $group => $order) {
            $this->info("Группа «{$group}»: " . implode(' -> ', $order));
        }
        $this->info("Восстановлено: {$stats['restored']} | Скрыто дублей: {$stats['hidden']}");

        if (!$this->option('no-warm')) {
            $tiles->warmAll();
            $this->info('Кэш витрины прогрет.');
        }

        return self::SUCCESS;
    }
}