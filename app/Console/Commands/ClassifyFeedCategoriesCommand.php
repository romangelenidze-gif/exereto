<?php

namespace App\Console\Commands;

use App\Services\Feeds\CategoryTaxonomy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClassifyFeedCategoriesCommand extends Command
{
    protected $signature = 'feeds:classify-categories
        {--feed= : Ограничить классификацию одним фидом}
        {--export-unmapped : Сохранить нераспознанные category_path в storage/app/unmapped_categories.json}
        {--import-mappings= : Загрузить проверенный JSON-маппинг [{"feed":"...","path":"...","slug":"..."}]}';

    protected $description = 'Засевает дерево категорий L1->L2->L3, заполняет feed_category_mappings и обновляет feed_products.category_id';

    public function handle(CategoryTaxonomy $taxonomy): int
    {
        $this->info('1/4. Обновление дерева категорий (L1 -> L2 -> L3)...');
        $slugToId = $taxonomy->seed();
        $this->line('Активных категорий в дереве: ' . count($slugToId));

        if ($importPath = $this->option('import-mappings')) {
            $this->importVerifiedMappings((string) $importPath, $slugToId);
        }

        $feedFilter = $this->option('feed') ? (string) $this->option('feed') : null;

        $this->info('2/4. Классификация уникальных category_path в feed_category_mappings...');
        $existingByKey = [];
        $existingByHash = [];

        $existingQuery = DB::table('feed_category_mappings');
        if ($feedFilter) {
            $existingQuery->where('feed', $feedFilter);
        }
        foreach ($existingQuery->get() as $row) {
            $existingByKey[$row->feed . '|' . $row->raw_category_path] = $row;
            $existingByHash[$row->feed . '|' . $row->path_hash] = $row;
        }

        $pathsQuery = DB::table('feed_products')
            ->select('feed', 'category_path', DB::raw('COUNT(*) as cnt'))
            ->where('is_active', 1)
            ->whereNotNull('category_path')
            ->where('category_path', '!=', '')
            ->groupBy('feed', 'category_path')
            ->orderByDesc('cnt');

        if ($feedFilter) {
            $pathsQuery->where('feed', $feedFilter);
        }

        $pathRows = $pathsQuery->get();
        $now = now();
        $resolvedMap = []; // [feed][raw_path] => category_id
        $unmapped = [];
        $toInsert = [];
        $updated = 0;
        $verifiedKept = 0;

        foreach ($pathRows as $p) {
            $feed = (string) $p->feed;
            $rawPath = (string) $p->category_path;
            $lookupKey = $feed . '|' . $rawPath;
            $hash = md5($feed . '|' . mb_strtolower(trim($rawPath)));
            $existing = $existingByKey[$lookupKey] ?? ($existingByHash[$feed . '|' . $hash] ?? null);

            if ($existing && (int) $existing->is_verified === 1 && $existing->category_id) {
                $resolvedMap[$feed][$rawPath] = (int) $existing->category_id;
                $verifiedKept++;
                continue;
            }

            $slug = $taxonomy->classifySlug($feed, $rawPath);
            $catId = ($slug !== null && isset($slugToId[$slug])) ? $slugToId[$slug] : null;

            if ($catId === null) {
                $unmapped[] = [
                    'feed' => $feed,
                    'path' => $rawPath,
                    'count' => (int) $p->cnt,
                ];
                continue;
            }

            $resolvedMap[$feed][$rawPath] = $catId;

            if ($existing) {
                if ((int) $existing->category_id !== $catId) {
                    DB::table('feed_category_mappings')
                        ->where('id', $existing->id)
                        ->update(['category_id' => $catId, 'updated_at' => $now]);
                    $updated++;
                }
            } else {
                $toInsert[] = [
                    'feed' => $feed,
                    'path_hash' => $hash,
                    'raw_category_path' => mb_substr($rawPath, 0, 500),
                    'category_id' => $catId,
                    'gender_override' => null,
                    'is_verified' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($toInsert) >= 500) {
                    DB::table('feed_category_mappings')->insertOrIgnore($toInsert);
                    $toInsert = [];
                }
            }
        }

        if ($toInsert !== []) {
            DB::table('feed_category_mappings')->insertOrIgnore($toInsert);
        }

        $this->line("Уникальных путей: {$pathRows->count()} | Подтверждённых: {$verifiedKept} | Обновлено: {$updated} | Нераспознанных: " . count($unmapped));

        if ($this->option('export-unmapped') && $unmapped !== []) {
            $outPath = storage_path('app/unmapped_categories.json');
            file_put_contents($outPath, json_encode($unmapped, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->info("Нераспознанные пути сохранены в: {$outPath}");
        }

        $this->info('3/4. Массовое обновление feed_products.category_id...');
        $prodQuery = DB::table('feed_products')
            ->select('id', 'feed', 'category_path', 'name', 'category_id')
            ->where('is_active', 1)
            ->orderBy('id');

        if ($feedFilter) {
            $prodQuery->where('feed', $feedFilter);
        }

        $changedTotal = 0;
        $pendingUpdates = [];

        $flushUpdates = function () use (&$pendingUpdates, &$changedTotal): void {
            if ($pendingUpdates === []) {
                return;
            }
            $cases = [];
            $ids = [];
            foreach ($pendingUpdates as $id => $catId) {
                $id = (int) $id;
                $catId = (int) $catId;
                $cases[] = "WHEN {$id} THEN {$catId}";
                $ids[] = $id;
            }
            $sql = 'UPDATE feed_products SET category_id = CASE id ' . implode(' ', $cases) . ' END WHERE id IN (' . implode(',', $ids) . ')';
            DB::statement($sql);
            $changedTotal += count($pendingUpdates);
            $pendingUpdates = [];
        };

        $prodQuery->chunkById(2000, function ($rows) use ($resolvedMap, $taxonomy, $slugToId, &$pendingUpdates, $flushUpdates): void {
            foreach ($rows as $row) {
                $feed = (string) $row->feed;
                $path = trim((string) ($row->category_path ?? ''));

                if ($path !== '' && isset($resolvedMap[$feed][$path])) {
                    $newCatId = $resolvedMap[$feed][$path];
                } else {
                    // Фолбэк по названию товара (актуально для пустых category_path вроде energiers)
                    $slug = $taxonomy->classifySlug($feed, $path, (string) ($row->name ?? ''));
                    $newCatId = ($slug !== null && isset($slugToId[$slug])) ? $slugToId[$slug] : null;
                }

                if ($newCatId !== null && (int) ($row->category_id ?? 0) !== $newCatId) {
                    $pendingUpdates[(int) $row->id] = $newCatId;
                    if (count($pendingUpdates) >= 500) {
                        $flushUpdates();
                    }
                }
            }
        });

        $flushUpdates();
        $this->line("Обновлено товаров в feed_products: {$changedTotal}");

        $this->info('4/4. Отчёт покрытия по фидам и L1-департаментам:');
        $this->printReport($feedFilter, $unmapped);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $slugToId
     */
    private function importVerifiedMappings(string $path, array $slugToId): void
    {
        $resolved = is_file($path) ? $path : base_path($path);
        if (!is_file($resolved)) {
            $this->warn("Файл маппингов не найден: {$path}");

            return;
        }

        $data = json_decode((string) file_get_contents($resolved), true);
        if (!is_array($data)) {
            $this->warn('Невалидный JSON в файле маппингов.');

            return;
        }

        $now = now();
        $count = 0;

        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            $feed = strtolower(trim((string) ($item['feed'] ?? '')));
            $rawPath = trim((string) ($item['path'] ?? $item['raw_category_path'] ?? ''));
            $slug = trim((string) ($item['slug'] ?? ''));
            if ($feed === '' || $rawPath === '' || !isset($slugToId[$slug])) {
                continue;
            }

            $hash = md5($feed . '|' . mb_strtolower($rawPath));
            $existing = DB::table('feed_category_mappings')
                ->where('feed', $feed)
                ->where('raw_category_path', $rawPath)
                ->first(['id']);

            if ($existing) {
                DB::table('feed_category_mappings')->where('id', $existing->id)->update([
                    'category_id' => $slugToId[$slug],
                    'is_verified' => 1,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('feed_category_mappings')->insertOrIgnore([
                    'feed' => $feed,
                    'path_hash' => $hash,
                    'raw_category_path' => mb_substr($rawPath, 0, 500),
                    'category_id' => $slugToId[$slug],
                    'gender_override' => null,
                    'is_verified' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $count++;
        }

        $this->info("Импортировано подтверждённых маппингов: {$count}");
    }

    /**
     * @param  array<int, array{feed:string, path:string, count:int}>  $unmapped
     */
    private function printReport(?string $feedFilter, array $unmapped): void
    {
        $sql = 'SELECT feed, COUNT(*) as total, SUM(CASE WHEN category_id IS NOT NULL THEN 1 ELSE 0 END) as mapped FROM feed_products WHERE is_active = 1';
        $params = [];
        if ($feedFilter) {
            $sql .= ' AND feed = ?';
            $params[] = $feedFilter;
        }
        $sql .= ' GROUP BY feed ORDER BY total DESC';

        $feedTable = [];
        foreach (DB::select($sql, $params) as $r) {
            $pct = $r->total > 0 ? round($r->mapped / $r->total * 100, 1) : 0;
            $feedTable[] = [$r->feed, $r->total, $r->mapped, $r->total - $r->mapped, $pct . '%'];
        }
        $this->table(['Feed', 'Active', 'Mapped', 'Unmapped', 'Coverage'], $feedTable);

        // Распределение по L1-департаментам
        $l1Rows = DB::select('
            SELECT COALESCE(c1.slug, c2.slug, c3.slug, "(unmapped)") as l1_slug, COUNT(*) as cnt
            FROM feed_products fp
            LEFT JOIN categories c3 ON c3.id = fp.category_id
            LEFT JOIN categories c2 ON c2.id = c3.parent_id
            LEFT JOIN categories c1 ON c1.id = c2.parent_id
            WHERE fp.is_active = 1
            GROUP BY l1_slug
            ORDER BY cnt DESC
        ');
        $this->table(['L1 Department', 'Products'], array_map(fn ($r) => [$r->l1_slug, $r->cnt], $l1Rows));

        if ($unmapped !== []) {
            $this->warn('Топ-15 нераспознанных category_path:');
            foreach (array_slice($unmapped, 0, 15) as $u) {
                $this->line(sprintf('  [%s] %5d | %s', $u['feed'], $u['count'], $u['path']));
            }
        }
    }
}