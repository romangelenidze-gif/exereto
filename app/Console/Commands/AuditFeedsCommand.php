<?php

namespace App\Console\Commands;

use App\Models\FeedProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AuditFeedsCommand extends Command
{
    protected $signature = 'feeds:audit';

    protected $description = 'Проверка целостности БД, синхронизации с Meilisearch, свежести фидов и качества фильтрации';

    public function handle(): int
    {
        $warnings = [];
        $critical = [];

        $rows = DB::table('feed_products')
            ->selectRaw('feed, COUNT(*) as total, SUM(is_active = 1) as active, SUM(is_active = 0) as inactive, SUM(is_active = 1 AND in_stock = 0) as active_oos, SUM(is_active = 1 AND ean IS NOT NULL) as with_ean, SUM(cascade_hidden = 1) as cascade_cnt, MAX(last_seen_at) as last_seen')
            ->groupBy('feed')
            ->orderByDesc('active')
            ->get();

        $table = [];
        $activeTotal = 0;

        foreach ($rows as $r) {
            $activeTotal += (int) $r->active;
            $table[] = [
                $r->feed,
                $r->total,
                $r->active,
                $r->inactive,
                $r->active_oos,
                $r->with_ean,
                $r->cascade_cnt,
                $r->last_seen,
            ];

            if ($r->last_seen && Carbon::parse($r->last_seen)->lt(now()->subHours(36))) {
                $warnings[] = "Фид {$r->feed} не обновлялся более 36 часов (last_seen: {$r->last_seen})";
            }
        }

        $this->table(
            ['Feed', 'Total', 'Active', 'Inactive', 'Active OOS', 'With EAN', 'Cascade', 'Last Seen'],
            $table
        );

        if (config('scout.driver') === 'meilisearch') {
            try {
                $client = app(\Meilisearch\Client::class);
                $indexName = (new FeedProduct())->searchableAs();
                $stats = $client->index($indexName)->stats();
                $meiliDocs = (int) ($stats['numberOfDocuments'] ?? 0);
                $drift = abs($meiliDocs - $activeTotal);

                $this->info("MySQL active: {$activeTotal} | Meilisearch docs: {$meiliDocs} | Расхождение: {$drift}");
                if ($drift > 500) {
                    $critical[] = "Критический рассинхрон MySQL ({$activeTotal}) vs Meilisearch ({$meiliDocs}) — риск 404!";
                }
            } catch (\Throwable $e) {
                $critical[] = 'Meilisearch недоступен: ' . $e->getMessage();
            }
        }

        $forbiddenTops = ['Είδη σπιτιού', 'Bebe-Βρεφικά Είδη', 'Βρεφικά', 'Τρίκυκλα-Ποδήλατα-Πατίνια', 'Παιδικό Δωμάτιο', 'Certified Refurbished'];
        $junkCount = DB::table('feed_products')
            ->whereIn('feed', ['toysgr', 'moustakas', 'public'])
            ->where('is_active', true)
            ->whereIn('category_top', $forbiddenTops)
            ->count();

        if ($junkCount > 0) {
            $warnings[] = "Найдено {$junkCount} активных товаров из запрещённых категорий в toysgr/moustakas/public.";
        }

        foreach ($warnings as $w) {
            $this->warn("WARN: {$w}");
        }

        if ($critical !== []) {
            foreach ($critical as $c) {
                $this->error("CRITICAL: {$c}");
            }

            return self::FAILURE;
        }

        $this->info('Аудит завершён: критических ошибок нет.');

        return self::SUCCESS;
    }
}