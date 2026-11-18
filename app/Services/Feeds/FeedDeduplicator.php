<?php

namespace App\Services\Feeds;

use App\Models\FeedProduct;
use Illuminate\Support\Facades\DB;

class FeedDeduplicator
{
    /**
     * @return array{restored:int, hidden:int, groups:array<string, array<int, string>>}
     */
    public function run(bool $syncScout = true): array
    {
        $stores = (array) config('feed_rules.stores', []);
        $groups = [];

        foreach ($stores as $feedKey => $cfg) {
            if (!empty($cfg['enabled']) && !empty($cfg['cascade_group']) && !empty($cfg['cascade_priority'])) {
                $groups[(string) $cfg['cascade_group']][$feedKey] = (int) $cfg['cascade_priority'];
            }
        }

        $restoredTotal = 0;
        $hiddenTotal = 0;
        $orderedGroups = [];

        foreach ($groups as $groupName => $priorities) {
            if (count($priorities) < 2) {
                continue;
            }

            asort($priorities);
            $feedOrder = array_keys($priorities);
            $orderedGroups[$groupName] = $feedOrder;
            $rankMap = array_flip($feedOrder);

            // 1. Восстанавливаем ТОЛЬКО ранее скрытые каскадом товары (stock-aware)
            $restoredIds = DB::table('feed_products')
                ->whereIn('feed', $feedOrder)
                ->where('cascade_hidden', true)
                ->pluck('id')
                ->all();

            if ($restoredIds !== []) {
                foreach (array_chunk($restoredIds, 2000) as $chunk) {
                    DB::table('feed_products')
                        ->whereIn('id', $chunk)
                        ->update(['is_active' => true, 'cascade_hidden' => false]);
                }
                $restoredTotal += count($restoredIds);
            }

            $idsToHide = [];

            // 2. Точная склейка по индексированному EAN-13 (toysgr <-> moustakas)
            $duplicateEans = DB::table('feed_products')
                ->select('ean')
                ->whereIn('feed', $feedOrder)
                ->where('is_active', true)
                ->where('in_stock', true)
                ->whereNotNull('ean')
                ->where('ean', '!=', '')
                ->groupBy('ean')
                ->havingRaw('COUNT(DISTINCT feed) > 1')
                ->pluck('ean');

            if ($duplicateEans->isNotEmpty()) {
                foreach ($duplicateEans->chunk(1000) as $eanChunk) {
                    $rows = DB::table('feed_products')
                        ->whereIn('feed', $feedOrder)
                        ->where('is_active', true)
                        ->where('in_stock', true)
                        ->whereIn('ean', $eanChunk)
                        ->get(['id', 'feed', 'ean']);

                    foreach ($rows->groupBy('ean') as $items) {
                        $bestRank = PHP_INT_MAX;
                        $bestFeed = null;

                        foreach ($items as $item) {
                            $rank = $rankMap[$item->feed] ?? PHP_INT_MAX;
                            if ($rank < $bestRank) {
                                $bestRank = $rank;
                                $bestFeed = $item->feed;
                            }
                        }

                        if ($bestFeed !== null) {
                            foreach ($items as $item) {
                                if ($item->feed !== $bestFeed) {
                                    $idsToHide[$item->id] = true;
                                }
                            }
                        }
                    }
                }
            }

            // 3. Фолбэк для наборов без EAN (LEGO / Playmobil / Funko — закрывает дубли в public)
            $setRows = DB::table('feed_products')
                ->whereIn('feed', $feedOrder)
                ->where('is_active', true)
                ->where('in_stock', true)
                ->whereIn(DB::raw('LOWER(brand)'), ['lego', 'playmobil', 'funko'])
                ->get(['id', 'feed', 'brand', 'name']);

            $bySetKey = [];
            foreach ($setRows as $row) {
                if (isset($idsToHide[$row->id])) {
                    continue;
                }
                $setKey = self::extractToySetKey((string) $row->brand, (string) $row->name);
                if ($setKey !== null) {
                    $bySetKey[$setKey][] = $row;
                }
            }

            foreach ($bySetKey as $items) {
                $distinctFeeds = array_unique(array_map(fn ($i) => $i->feed, $items));
                if (count($distinctFeeds) < 2) {
                    continue;
                }

                usort($items, fn ($a, $b) => ($rankMap[$a->feed] ?? 999) <=> ($rankMap[$b->feed] ?? 999));
                $bestFeed = $items[0]->feed;

                foreach ($items as $item) {
                    if ($item->feed !== $bestFeed) {
                        $idsToHide[$item->id] = true;
                    }
                }
            }

            $hideList = array_keys($idsToHide);
            if ($hideList !== []) {
                foreach (array_chunk($hideList, 2000) as $chunk) {
                    DB::table('feed_products')
                        ->whereIn('id', $chunk)
                        ->update(['is_active' => false, 'cascade_hidden' => true]);
                }
                $hiddenTotal += count($hideList);
            }

            // 4. Точечная синхронизация только изменённых ID в Meilisearch
            if ($syncScout && config('scout.driver') === 'meilisearch') {
                $actuallyRestored = array_diff($restoredIds, $hideList);
                if ($actuallyRestored !== []) {
                    FeedProduct::whereIn('id', $actuallyRestored)
                        ->where('is_active', true)
                        ->chunkById(2000, fn ($m) => $m->searchable());
                }
                if ($hideList !== []) {
                    FeedProduct::whereIn('id', $hideList)
                        ->chunkById(2000, fn ($m) => $m->unsearchable());
                }
            }
        }

        return [
            'restored' => $restoredTotal,
            'hidden' => $hiddenTotal,
            'groups' => $orderedGroups,
        ];
    }

    public static function extractToySetKey(string $brand, string $name): ?string
    {
        $b = mb_strtolower(trim($brand));
        if (!in_array($b, ['lego', 'playmobil', 'funko'], true)) {
            return null;
        }
        if (preg_match('/\b(\d{4,6})\b/', $name, $m)) {
            return $b . ':' . $m[1];
        }

        return null;
    }
}