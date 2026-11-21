<?php

namespace App\Services\Feeds;

use App\Models\FeedProduct;
use Illuminate\Support\Facades\DB;

class FeedVariantGrouper
{
    public const GENERATED_PREFIX = 'vg-';

    /** @var array<string, true> */
    private const BASE_COLORS = [
        // English & Tech colors
        'black' => true, 'white' => true, 'silver' => true, 'gold' => true, 'gray' => true, 'grey' => true,
        'blue' => true, 'red' => true, 'green' => true, 'yellow' => true, 'orange' => true, 'pink' => true,
        'purple' => true, 'violet' => true, 'brown' => true, 'beige' => true, 'cream' => true, 'ivory' => true,
        'navy' => true, 'teal' => true, 'cyan' => true, 'turquoise' => true, 'aqua' => true, 'mint' => true,
        'coral' => true, 'peach' => true, 'magenta' => true, 'fuchsia' => true, 'lavender' => true, 'lilac' => true,
        'plum' => true, 'burgundy' => true, 'maroon' => true, 'bordeaux' => true, 'crimson' => true, 'indigo' => true,
        'olive' => true, 'khaki' => true, 'lime' => true, 'amber' => true, 'bronze' => true, 'copper' => true,
        'champagne' => true, 'charcoal' => true, 'graphite' => true, 'obsidian' => true, 'slate' => true,
        'starlight' => true, 'midnight' => true, 'titanium' => true, 'porcelain' => true, 'hazel' => true,
        'ultramarine' => true, 'emerald' => true, 'sapphire' => true, 'ruby' => true, 'pearl' => true,
        'anthracite' => true, 'taupe' => true, 'camel' => true, 'mocha' => true, 'chocolate' => true,
        'espresso' => true, 'sand' => true, 'stone' => true, 'sage' => true, 'terracotta' => true,
        'nude' => true, 'blush' => true, 'onyx' => true, 'snow' => true, 'frost' => true, 'clear' => true,
        'transparent' => true, 'multicolor' => true, 'multicolour' => true, 'rainbow' => true, 'ecru' => true,
        'steel' => true, 'cobalt' => true, 'denim' => true, 'sky' => true, 'rose' => true, 'wine' => true,
        'ochre' => true, 'mustard' => true, 'apricot' => true, 'salmon' => true, 'berry' => true, 'cherry' => true,
        'citrus' => true, 'lemon' => true, 'jade' => true, 'pine' => true, 'forest' => true, 'moss' => true,
        'ocean' => true, 'marine' => true, 'azure' => true, 'cerulean' => true, 'periwinkle' => true,
        'mauve' => true, 'orchid' => true, 'magenta' => true, 'ash' => true, 'smoke' => true, 'mist' => true,
        'platinum' => true, 'chrome' => true, 'nickel' => true, 'gunmetal' => true, 'pewter' => true,
        'brass' => true, 'almond' => true, 'vanilla' => true, 'oat' => true, 'linen' => true, 'chestnut' => true,
        'walnut' => true, 'mahogany' => true, 'rust' => true, 'cinnamon' => true, 'caramel' => true, 'honey' => true,
        // Greek colors (lowercase)
        'μαύρο' => true, 'μαυρο' => true, 'λευκό' => true, 'λευκο' => true, 'άσπρο' => true, 'ασπρο' => true,
        'ασημί' => true, 'ασημι' => true, 'χρυσό' => true, 'χρυσο' => true, 'γκρι' => true, 'μπλε' => true,
        'κόκκινο' => true, 'κοκκινο' => true, 'πράσινο' => true, 'πρασινο' => true, 'κίτρινο' => true,
        'κιτρινο' => true, 'πορτοκαλί' => true, 'πορτοκαλι' => true, 'ροζ' => true, 'μωβ' => true,
        'καφέ' => true, 'καφε' => true, 'μπεζ' => true, 'γαλάζιο' => true, 'γαλαζιο' => true,
        'τιρκουάζ' => true, 'τιρκουαζ' => true, 'μπορντό' => true, 'μπορντο' => true, 'χακί' => true,
        'χακι' => true, 'διάφανο' => true, 'διαφανο' => true, 'πολύχρωμο' => true, 'πολυχρωμο' => true,
        'ανθρακί' => true, 'ανθρακι' => true, 'σομόν' => true, 'σομον' => true, 'λιλά' => true, 'λιλα' => true,
    ];

    /** @var array<string, true> */
    private const COLOR_MODIFIERS = [
        'light' => true, 'dark' => true, 'deep' => true, 'space' => true, 'natural' => true, 'desert' => true,
        'alpine' => true, 'sierra' => true, 'phantom' => true, 'awesome' => true, 'icy' => true, 'ice' => true,
        'shadow' => true, 'warm' => true, 'cool' => true, 'matte' => true, 'matt' => true, 'glossy' => true,
        'metallic' => true, 'crystal' => true, 'cloud' => true, 'star' => true, 'jet' => true, 'pure' => true,
        'bright' => true, 'soft' => true, 'pale' => true, 'pastel' => true, 'electric' => true, 'neon' => true,
        'classic' => true, 'royal' => true, 'arctic' => true, 'glacier' => true, 'cosmic' => true, 'mystic' => true,
        'lunar' => true, 'solar' => true, 'aurora' => true, 'velvet' => true, 'silk' => true, 'satin' => true,
        'carbon' => true, 'prism' => true, 'ceramic' => true, 'obsidian' => true, 'pacific' => true, 'storm' => true,
        'night' => true, 'sunset' => true, 'sunrise' => true, 'twilight' => true, 'eclipse' => true, 'zen' => true,
        'misty' => true, 'dusty' => true, 'smoky' => true, 'chalk' => true, 'baby' => true, 'hot' => true,
        'vivid' => true, 'rich' => true, 'deepest' => true, 'true' => true, 'ultra' => true, 'neo' => true,
        'ανοιχτό' => true, 'ανοιχτο' => true, 'σκούρο' => true, 'σκουρο' => true, 'μεταλλικό' => true,
        'μεταλλικο' => true, 'ματ' => true,
    ];

    /**
     * Группирует варианты и пересчитывает агрегаты (group_min_price, variants_count, has_price_range)
     * по одному фиду или по всем активным фидам глобально.
     *
     * @return array{
     *   feeds: int,
     *   scanned: int,
     *   grouped_models: int,
     *   grouped_products: int,
     *   colors_extracted: int,
     *   variants_labeled: int,
     *   price_ranges: int,
     *   updated_rows: int,
     *   changed_ids: int[]
     * }
     */
    public function process(?string $onlyFeed = null, bool $dryRun = false): array
    {
        $feeds = $onlyFeed !== null
            ? [$onlyFeed]
            : FeedProduct::query()
                ->where('is_active', true)
                ->distinct()
                ->pluck('feed')
                ->filter()
                ->values()
                ->all();

        $totals = [
            'feeds' => count($feeds),
            'scanned' => 0,
            'grouped_models' => 0,
            'grouped_products' => 0,
            'colors_extracted' => 0,
            'variants_labeled' => 0,
            'price_ranges' => 0,
            'updated_rows' => 0,
            'changed_ids' => [],
        ];

        foreach ($feeds as $feed) {
            $stats = $this->processFeed((string) $feed, $dryRun);
            $totals['scanned'] += $stats['scanned'];
            $totals['grouped_models'] += $stats['grouped_models'];
            $totals['grouped_products'] += $stats['grouped_products'];
            $totals['colors_extracted'] += $stats['colors_extracted'];
            $totals['variants_labeled'] += $stats['variants_labeled'];
            $totals['price_ranges'] += $stats['price_ranges'];
            $totals['updated_rows'] += $stats['updated_rows'];
            if ($stats['changed_ids'] !== []) {
                array_push($totals['changed_ids'], ...$stats['changed_ids']);
            }
        }

        return $totals;
    }

    /**
     * @return array{
     *   scanned: int,
     *   grouped_models: int,
     *   grouped_products: int,
     *   colors_extracted: int,
     *   variants_labeled: int,
     *   price_ranges: int,
     *   updated_rows: int,
     *   changed_ids: int[]
     * }
     */
    public function processFeed(string $feed, bool $dryRun = false): array
    {
        $rows = DB::table('feed_products')
            ->where('feed', $feed)
            ->where('is_active', true)
            ->select([
                'id',
                'sku',
                'model_code',
                'brand',
                'name',
                'colour',
                'variant_label',
                'category_id',
                'gender',
                'price',
                'in_stock',
                'group_min_price',
                'variants_count',
                'has_price_range',
            ])
            ->get();

        $stats = [
            'scanned' => $rows->count(),
            'grouped_models' => 0,
            'grouped_products' => 0,
            'colors_extracted' => 0,
            'variants_labeled' => 0,
            'price_ranges' => 0,
            'updated_rows' => 0,
            'changed_ids' => [],
        ];

        if ($rows->isEmpty()) {
            return $stats;
        }

        // 1. Считаем частоты уже существующих «родных» model_code (не vg-)
        $nativeModelCounts = [];
        foreach ($rows as $row) {
            $mc = trim((string) ($row->model_code ?? ''));
            if ($mc !== '' && !str_starts_with($mc, self::GENERATED_PREFIX)) {
                $nativeModelCounts[$mc] = ($nativeModelCounts[$mc] ?? 0) + 1;
            }
        }

        // 2. Разбираем названия для товаров без существующей нативной группы (count <= 1 или уже vg-)
        $parsedById = [];
        $buckets = [];

        foreach ($rows as $row) {
            $id = (int) $row->id;
            $mc = trim((string) ($row->model_code ?? ''));
            $hasNativeGroup = $mc !== '' && !str_starts_with($mc, self::GENERATED_PREFIX) && ($nativeModelCounts[$mc] ?? 0) >= 2;

            $parsed = $this->parseName((string) $row->name, $row->colour !== null ? trim((string) $row->colour) : null);
            $parsedById[$id] = $parsed;

            if ($hasNativeGroup) {
                continue;
            }

            // Группируем только если реально извлечена вариативность (цвет или конфигурация/объём)
            // и осталось осмысленное базовое имя модели (>= 4 символов)
            if (($parsed['variant_label'] !== null || $parsed['extracted_colour'] !== null) && mb_strlen($parsed['base_key']) >= 4) {
                $brandKey = mb_strtolower(trim((string) ($row->brand ?? '')));
                $catKey = (string) ($row->category_id ?? 0);
                $genderKey = (string) ($row->gender ?? 'unisex');
                $sig = "{$feed}|{$brandKey}|{$catKey}|{$genderKey}|{$parsed['base_key']}";
                $buckets[$sig][] = $id;
            }
        }

        // 3. Назначаем детерминированный model_code (vg-...) для групп из >= 2 товаров
        $newModelCodeById = [];
        foreach ($buckets as $sig => $ids) {
            if (count($ids) < 2) {
                continue;
            }

            $generatedCode = self::GENERATED_PREFIX . substr(sha1($sig), 0, 14);
            $stats['grouped_models']++;
            $stats['grouped_products'] += count($ids);

            foreach ($ids as $id) {
                $newModelCodeById[$id] = $generatedCode;
            }
        }

        // 4. Группируем все товары фида по итоговому model_code и считаем min/max цены и количество вариантов
        $finalGroups = [];
        $finalMetaById = [];

        foreach ($rows as $row) {
            $id = (int) $row->id;
            $parsed = $parsedById[$id];
            $existingMc = trim((string) ($row->model_code ?? ''));
            $finalMc = $newModelCodeById[$id] ?? $existingMc;

            // Если ранее был vg- код, а теперь товар остался один — сбрасываем на sku, чтобы не висел мёртвый vg-
            if (!isset($newModelCodeById[$id]) && str_starts_with($finalMc, self::GENERATED_PREFIX)) {
                $finalMc = (string) $row->sku;
            }

            $existingColour = trim((string) ($row->colour ?? ''));
            $finalColour = $existingColour !== '' ? $existingColour : $parsed['extracted_colour'];
            if ($existingColour === '' && $finalColour !== null) {
                $stats['colors_extracted']++;
            }

            $finalVariant = $parsed['variant_label'];
            if ($finalVariant !== null) {
                $stats['variants_labeled']++;
            }

            $groupKey = $finalMc !== '' ? $finalMc : (string) $row->sku;
            $finalGroups[$groupKey][] = [
                'id' => $id,
                'price' => (float) $row->price,
                'in_stock' => (bool) $row->in_stock,
            ];

            $finalMetaById[$id] = [
                'row' => $row,
                'model_code' => $finalMc !== '' ? mb_substr($finalMc, 0, 64) : null,
                'colour' => $finalColour !== null ? mb_substr($finalColour, 0, 255) : null,
                'variant_label' => $finalVariant !== null ? mb_substr($finalVariant, 0, 120) : null,
                'group_key' => $groupKey,
            ];
        }

        $groupAgg = [];
        foreach ($finalGroups as $gKey => $members) {
            $inStockMembers = array_values(array_filter($members, fn (array $m) => $m['in_stock'] && $m['price'] > 0));
            $pool = $inStockMembers !== [] ? $inStockMembers : array_values(array_filter($members, fn (array $m) => $m['price'] > 0));

            $prices = array_column($pool, 'price');
            $minPrice = $prices !== [] ? round((float) min($prices), 2) : null;
            $maxPrice = $prices !== [] ? round((float) max($prices), 2) : null;
            $count = count($inStockMembers !== [] ? $inStockMembers : $members);
            $hasRange = $minPrice !== null && $maxPrice !== null && ($maxPrice - $minPrice) >= 0.50;

            if ($hasRange) {
                $stats['price_ranges']++;
            }

            $groupAgg[$gKey] = [
                'min_price' => $minPrice,
                'count' => min(65000, max(1, $count)),
                'has_range' => $hasRange,
            ];
        }

        // 5. Вычисляем diff — обновляем в БД только реально изменившиеся строки
        $updates = [];
        foreach ($finalMetaById as $id => $meta) {
            $row = $meta['row'];
            $agg = $groupAgg[$meta['group_key']];

            $oldMc = $row->model_code !== null ? (string) $row->model_code : null;
            $oldColour = $row->colour !== null ? (string) $row->colour : null;
            $oldVar = $row->variant_label !== null ? (string) $row->variant_label : null;
            $oldMin = $row->group_min_price !== null ? round((float) $row->group_min_price, 2) : null;
            $oldCnt = (int) ($row->variants_count ?? 1);
            $oldRange = (bool) ($row->has_price_range ?? false);

            $changed = $oldMc !== $meta['model_code']
                || $oldColour !== $meta['colour']
                || $oldVar !== $meta['variant_label']
                || $oldMin !== $agg['min_price']
                || $oldCnt !== $agg['count']
                || $oldRange !== $agg['has_range'];

            if (!$changed) {
                continue;
            }

            $updates[] = [
                'id' => $id,
                'model_code' => $meta['model_code'],
                'colour' => $meta['colour'],
                'variant_label' => $meta['variant_label'],
                'group_min_price' => $agg['min_price'],
                'variants_count' => $agg['count'],
                'has_price_range' => $agg['has_range'] ? 1 : 0,
            ];

            // В Meilisearch переиндексируем, если изменились model_code или colour
            if ($oldMc !== $meta['model_code'] || $oldColour !== $meta['colour']) {
                $stats['changed_ids'][] = $id;
            }
        }

        $stats['updated_rows'] = count($updates);

        if (!$dryRun && $updates !== []) {
            $this->bulkUpdate($updates);
        }

        return $stats;
    }

    /**
     * Извлекает из названия товара: базовый ключ модели, цвет и модификацию (память/связь/RAM+SSD/объём).
     *
     * @return array{base_title: string, base_key: string, extracted_colour: ?string, variant_label: ?string}
     */
    public function parseName(string $name, ?string $existingColour = null): array
    {
        $work = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        $extractedColour = null;

        // 1. Хвостовой цвет после " - Color" или " – Color"
        if (preg_match('/^(.*?)\s+[-–—]\s+([^-–—]{2,40})$/u', $work, $m)) {
            $cand = trim($m[2]);
            if ($this->isColorPhrase($cand)) {
                $extractedColour = $cand;
                $work = trim($m[1]);
            }
        }

        // 2. Хвостовой цвет сразу после закрывающей скобки "(...) Sky Blue" (ноутбуки и техника)
        if ($extractedColour === null && preg_match('/^(.*?\))\s+([A-Za-zΑ-Ωα-ωΆ-ώ\s\/]{2,32})$/u', $work, $m)) {
            $cand = trim($m[2]);
            if ($this->isColorPhrase($cand)) {
                $extractedColour = $cand;
                $work = trim($m[1]);
            }
        }

        // 3. Конфигурация (память/связь, RAM+SSD ноутбука или объём парфюма/косметики)
        $variantParts = [];

        // 3a. Конфигурация внутри скобок ноутбука: "(Apple M5/16GB/512GB SSD/MacOS)"
        if (preg_match('/\(([^()]*\/[^()]*)\)/u', $work, $pm)) {
            $inside = $pm[1];
            $segments = array_map('trim', explode('/', $inside));
            $keptSegments = [];
            $laptopSpecs = [];

            foreach ($segments as $seg) {
                if (preg_match('/^\d{1,3}\s*(?:GB|TB)(?:\s*(?:RAM|DDR\d|LPDDR\d\w*|SSD|NVMe|HDD|eMMC|UFS))?$/iu', $seg)) {
                    $laptopSpecs[] = preg_replace('/\s+/u', ' ', strtoupper(trim($seg)));
                } else {
                    $keptSegments[] = $seg;
                }
            }

            if ($laptopSpecs !== []) {
                $variantParts = $laptopSpecs;
                $replacement = $keptSegments !== [] ? '(' . implode('/', $keptSegments) . ')' : '';
                $work = trim(str_replace($pm[0], $replacement, $work));
            }
        }

        // 3b. Смартфоны / планшеты / носители: "8GB/256GB", "128GB", "512GB 5G", "256GB Wi-Fi", "1TB Wi-Fi + Cellular"
        if ($variantParts === []) {
            $storage = null;
            $connectivity = null;

            if (preg_match('/(?<![\p{L}\p{N}])(\d{1,3}\s*GB\s*\/\s*\d{1,4}\s*(?:GB|TB))(?![\p{L}\p{N}])/iu', $work, $sm)) {
                $storage = preg_replace('/\s+/u', '', strtoupper($sm[1]));
                $work = trim(preg_replace('/(?<![\p{L}\p{N}])' . preg_quote($sm[1], '/') . '(?![\p{L}\p{N}])/iu', '', $work, 1) ?? $work);
            } elseif (preg_match('/(?<![\p{L}\p{N}])((?:16|32|64|128|256|512)\s*GB|(?:1|2|4|8)\s*TB)(?![\p{L}\p{N}])/iu', $work, $sm)) {
                $storage = preg_replace('/\s+/u', '', strtoupper($sm[1]));
                $work = trim(preg_replace('/(?<![\p{L}\p{N}])' . preg_quote($sm[1], '/') . '(?![\p{L}\p{N}])/iu', '', $work, 1) ?? $work);
            }

            if (preg_match('/(?<![\p{L}\p{N}])(Wi-?Fi\s*\+\s*(?:Cellular|5G|4G|LTE)|GPS\s*\+\s*Cellular|Wi-?Fi|Cellular|5G|4G\s*LTE|4G|LTE)(?![\p{L}\p{N}])/iu', $work, $cm)) {
                $connRaw = trim($cm[1]);
                $connectivity = $this->normalizeConnectivity($connRaw);
                $work = trim(preg_replace('/(?<![\p{L}\p{N}])' . preg_quote($cm[1], '/') . '(?![\p{L}\p{N}])/iu', '', $work, 1) ?? $work);
            }

            if ($storage !== null) {
                $variantParts[] = $storage;
            }
            if ($connectivity !== null && $storage !== null) {
                // Связь выделяем в модификацию вместе с памятью (или для планшетов/часов), чтобы не отрезать "5G" от названия самой линейки телефонов без памяти
                $variantParts[] = $connectivity;
            } elseif ($connectivity !== null && preg_match('/\b(ipad|tab|watch|pad)\b/iu', $name)) {
                $variantParts[] = $connectivity;
            } else {
                // Вернуть 5G в базовое имя, если не было памяти и это не планшет/часы
                if ($connectivity !== null) {
                    $work = trim($work . ' ' . $connectivity);
                }
            }
        }

        // 3c. Парфюмерия и косметика: объём "30ml", "50 ml", "100ml", "200 ml", "50g"
        if ($variantParts === [] && preg_match('/(?<![\p{L}\p{N}])(\d+(?:[.,]\d+)?)\s*(ml|l|gr|g)(?![\p{L}\p{N}])/iu', $work, $vm)) {
            $num = str_replace(',', '.', $vm[1]);
            $unit = mb_strtolower($vm[2]);
            if ($unit === 'gr') {
                $unit = 'g';
            }
            $variantParts[] = $num . $unit;
            $work = trim(preg_replace('/(?<![\p{L}\p{N}])' . preg_quote($vm[0], '/') . '(?![\p{L}\p{N}])/iu', '', $work, 1) ?? $work);
        }

        $baseTitle = trim(preg_replace('/\s{2,}/u', ' ', trim($work, " \t\n\r\0\x0B-,/|")) ?? $work);
        $baseKey = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $baseTitle) ?? '');
        $baseKey = trim(preg_replace('/\s+/u', ' ', $baseKey) ?? '');

        $variantLabel = $variantParts !== []
            ? implode(count($variantParts) === 2 && str_contains($variantParts[0], 'GB') && str_contains($variantParts[1], 'SSD') ? ' / ' : ' ', $variantParts)
            : null;

        return [
            'base_title' => $baseTitle,
            'base_key' => $baseKey,
            'extracted_colour' => $extractedColour,
            'variant_label' => $variantLabel,
        ];
    }

    /**
     * Очищает название для карточки каталога, когда карточка представляет группу вариантов с ценой «от».
     */
    public static function cleanCardTitle(string $title, ?string $variantLabel, ?string $colour): string
    {
        $self = new self();
        $parsed = $self->parseName($title, $colour);
        $clean = trim($parsed['base_title']);

        if (mb_strlen($clean) >= 4) {
            return $clean;
        }

        return $title;
    }

    /**
     * Сортировка подписей вариантов по возрастанию (64GB -> 128GB -> 256GB -> 512GB -> 1TB; 30ml -> 50ml -> 100ml).
     */
    public static function compareVariantLabels(string $a, string $b): int
    {
        $score = static function (string $label): float {
            $val = 0.0;
            if (preg_match('/(\d+(?:\.\d+)?)\s*TB/i', $label, $m)) {
                $val = (float) $m[1] * 1024.0;
            } elseif (preg_match('/(\d+(?:\.\d+)?)\s*GB(?:\s*(?:SSD|NVMe|HDD))?/i', $label, $m)) {
                // Если пара "16GB / 512GB SSD", берём второе число главным, первое второстепенным
                if (preg_match('/(\d+)\s*GB\s*\/\s*(\d+(?:\.\d+)?)\s*(GB|TB)/i', $label, $pm)) {
                    $storage = (float) $pm[2] * (strtoupper($pm[3]) === 'TB' ? 1024.0 : 1.0);
                    $val = $storage * 1000.0 + (float) $pm[1];
                } else {
                    $val = (float) $m[1];
                }
            } elseif (preg_match('/(\d+(?:\.\d+)?)\s*(ml|l|g)\b/i', $label, $m)) {
                $val = (float) $m[1] * (strtolower($m[2]) === 'l' ? 1000.0 : 1.0);
            }

            // Wi-Fi перед 5G / Cellular при одинаковой памяти
            $connWeight = str_contains(strtolower($label), '5g') || str_contains(strtolower($label), 'cellular') ? 0.5 : 0.0;

            return $val + $connWeight;
        };

        $sa = $score($a);
        $sb = $score($b);

        if ($sa !== $sb) {
            return $sa <=> $sb;
        }

        return strnatcasecmp($a, $b);
    }

    /**
     * Подбирает локализуемую подпись дропдауна на PDP в зависимости от типа модификаций.
     *
     * @param  string[]  $labels
     */
    public static function detectAxisLabel(array $labels): string
    {
        $joined = mb_strtolower(implode(' ', $labels));
        if (preg_match('/\b\d+(?:\.\d+)?\s*(gb|tb)\b/i', $joined)) {
            return __('Память и конфигурация');
        }
        if (preg_match('/\b\d+(?:\.\d+)?\s*(ml|l|g)\b/i', $joined)) {
            return __('Объём');
        }

        return __('Вариант');
    }

    private function isColorPhrase(string $phrase): bool
    {
        $clean = trim(preg_replace('/[^\p{L}\s\/]+/u', ' ', mb_strtolower($phrase)) ?? '');
        $tokens = array_values(array_filter(preg_split('/[\s\/]+/u', $clean) ?: []));

        if ($tokens === [] || count($tokens) > 3) {
            return false;
        }

        $hasBaseColor = false;
        foreach ($tokens as $tok) {
            if (isset(self::BASE_COLORS[$tok])) {
                $hasBaseColor = true;
                continue;
            }
            if (isset(self::COLOR_MODIFIERS[$tok])) {
                continue;
            }

            return false;
        }

        return $hasBaseColor;
    }

    private function normalizeConnectivity(string $raw): string
    {
        $low = mb_strtolower(preg_replace('/\s+/u', ' ', trim($raw)) ?? $raw);

        return match (true) {
            str_contains($low, 'wi') && str_contains($low, 'cellular') => 'Wi-Fi + Cellular',
            str_contains($low, 'gps') && str_contains($low, 'cellular') => 'GPS + Cellular',
            str_contains($low, 'wi') && str_contains($low, '5g') => 'Wi-Fi + 5G',
            str_contains($low, 'wi') => 'Wi-Fi',
            str_contains($low, '5g') => '5G',
            str_contains($low, '4g') => '4G',
            str_contains($low, 'lte') => 'LTE',
            str_contains($low, 'cellular') => 'Cellular',
            default => strtoupper($raw),
        };
    }

    /**
     * Быстрое пакетное обновление (по 500 строк на SQL-запрос).
     *
     * @param  array<int, array<string, mixed>>  $updates
     */
    private function bulkUpdate(array $updates): void
    {
        foreach (array_chunk($updates, 500) as $chunk) {
            $ids = [];
            $cases = [
                'model_code' => [],
                'colour' => [],
                'variant_label' => [],
                'group_min_price' => [],
                'variants_count' => [],
                'has_price_range' => [],
            ];
            $bindings = [];

            foreach (array_keys($cases) as $col) {
                $sqlParts = [];
                foreach ($chunk as $item) {
                    $id = (int) $item['id'];
                    $ids[$id] = $id;
                    $sqlParts[] = "WHEN {$id} THEN ?";
                    $bindings[] = $item[$col];
                }
                $cases[$col] = "`{$col}` = CASE `id` " . implode(' ', $sqlParts) . ' END';
            }

            $idList = implode(',', array_values($ids));
            $sql = 'UPDATE `feed_products` SET ' . implode(', ', $cases) . " WHERE `id` IN ({$idList})";

            DB::update($sql, $bindings);
        }
    }
}