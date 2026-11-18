<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeduplicateFeedsCommand extends Command
{
    protected $signature = 'feeds:deduplicate';
    protected $description = 'Каскадная дедупликация игрушек и электроники по артикулу/EAN с учетом приоритетов';

    public function handle()
    {
        $this->info("Старт каскадной дедупликации...");

        $feedsConfig = config('feeds.feeds', []);
        $priorities = [];
        
        foreach ($feedsConfig as $key => $cfg) {
            if (!empty($cfg['cascade_priority'])) {
                $priorities[$key] = (int) $cfg['cascade_priority'];
            }
        }

        if (count($priorities) < 2) {
            $this->info("Каскад не требуется (менее 2 фидов с приоритетом).");
            return 0;
        }

        // Сортируем: 1 - наивысший приоритет (Toys.gr), 3 - низший (Public)
        asort($priorities);
        $feedOrder = array_keys($priorities);
        $this->info("Порядок каскада: " . implode(' -> ', $feedOrder));

        // 1. Восстанавливаем временно скрытые дубли (на случай, если наличие изменилось)
        // Мы используем priority_score = -1 как маркер товара, скрытого именно каскадом
        DB::table('feed_products')
            ->whereIn('feed', $feedOrder)
            ->where('priority_score', -1)
            ->update(['priority_score' => 50, 'is_active' => 1]);

        // 2. Ищем дубли по model_code (EAN / номер набора LEGO)
        $duplicates = DB::table('feed_products')
            ->select('model_code')
            ->whereIn('feed', $feedOrder)
            ->where('is_active', 1)
            ->where('in_stock', 1)
            ->whereNotNull('model_code')
            ->where('model_code', '!=', '')
            ->groupBy('model_code')
            ->havingRaw('COUNT(DISTINCT feed) > 1')
            ->pluck('model_code');

        $hiddenCount = 0;

        foreach ($duplicates as $modelCode) {
            $products = DB::table('feed_products')
                ->where('model_code', $modelCode)
                ->whereIn('feed', $feedOrder)
                ->where('is_active', 1)
                ->where('in_stock', 1)
                ->get(['id', 'feed']);

            if ($products->count() > 1) {
                $bestFeed = null;
                // Ищем лучший фид согласно отсортированному массиву
                foreach ($feedOrder as $feedKey) {
                    if ($products->contains('feed', $feedKey)) {
                        $bestFeed = $feedKey;
                        break;
                    }
                }

                if ($bestFeed) {
                    $idsToHide = $products->where('feed', '!=', $bestFeed)->pluck('id')->toArray();
                    if (!empty($idsToHide)) {
                        DB::table('feed_products')->whereIn('id', $idsToHide)->update([
                            'is_active' => 0,
                            'priority_score' => -1 // Маркер каскадного дубля
                        ]);
                        $hiddenCount += count($idsToHide);
                    }
                }
            }
        }

        $this->info("Успешно скрыто дублей: {$hiddenCount}");

        // Обязательно сбрасываем кэш витрины, так как часть товаров ушла из выдачи
        try {
            app(\App\Services\FeedCategoryTiles::class)->warmAll();
            $this->info("Кэш витрины обновлен.");
        } catch (\Throwable $e) {
            $this->warn("Не удалось прогреть кэш: " . $e->getMessage());
        }
        
        return 0;
    }
}