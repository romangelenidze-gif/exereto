<?php

namespace App\Services\Feeds;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AwinFeedImporter
{
    protected const BATCH = 500;
    protected const REQUIRED_COLUMNS_AWIN = ['aw_deep_link', 'product_name', 'merchant_product_id', 'search_price'];
    protected const REQUIRED_COLUMNS_LINKWISE = ['tracking_url', 'product_name', 'price'];

    protected const UPDATE_COLUMNS = [
        'image_urls', 'model_code', 'ean', 'brand', 'name', 'colour', 'category_path', 'category_top', 'gender', 'price',
        'old_price', 'discount_percent', 'currency', 'rating', 'in_stock', 'priority_score', 'sizes', 'specs',
        'image_url', 'deep_link', 'merchant_url', 'delivery_cost', 'search_text', 'is_active', 'cascade_hidden',
        'last_seen_at', 'updated_at',
    ];

    protected const SIZE_ORDER = [
        'xxxs' => 1, '3xs' => 1,
        'xxs' => 2, '2xs' => 2,
        'xs' => 3,
        'xs/s' => 4,
        's' => 5,
        's/m' => 6,
        'm' => 7,
        'm/l' => 8,
        'l' => 9,
        'l/xl' => 10,
        'xl' => 11,
        'xl/xxl' => 12,
        'xxl' => 13, '2xl' => 13,
        'xxxl' => 14, '3xl' => 14,
        '4xl' => 15,
        '5xl' => 16,
        '6xl' => 17,
        'os' => 99, 'one size' => 99, 'onesize' => 99,
    ];

    public function __construct(
        protected FeedImportGatekeeper $gatekeeper
    ) {}

    /**
     * @return array{total:int, imported:int, skipped:int, deactivated:int, deactivation_skipped:bool}
     */
    public function import(string $feedKey, ?string $source = null, int $limit = 0, bool $forceDeactivate = false): array
    {
        $cfg = config("feeds.feeds.{$feedKey}");

        if (!is_array($cfg)) {
            throw new \InvalidArgumentException("Неизвестный фид: {$feedKey}");
        }

        if (($cfg['enabled'] ?? true) === false) {
            throw new \RuntimeException("Фид «{$feedKey}» отключён в config/feeds.php (enabled = false).");
        }

        $source = $source ?: ($cfg['url'] ?? null);

        if (!$source) {
            $envPrefix = strtoupper((string) ($cfg['network'] ?? 'AWIN'));
            throw new \RuntimeException("Не задан URL фида: добавьте {$envPrefix}_" . strtoupper($feedKey) . '_FEED_URL в .env или передайте --source.');
        }

        $startedAt = now()->toDateTimeString();
        [$path, $temporary] = $this->resolveSource($source);

        try {
            $stats = $this->process($feedKey, $cfg, $path, $startedAt, max(0, $limit), $forceDeactivate);
            Cache::forget("home.category_chips.v2.{$feedKey}");
            Cache::forget('autocomplete.brands.all.v1');

            return $stats;
        } finally {
            if ($temporary) {
                @unlink($path);
            }
        }
    }

    protected function process(string $feedKey, array $cfg, string $path, string $startedAt, int $limit = 0, bool $forceDeactivate = false): array
    {
        $magic = @file_get_contents($path, false, null, 0, 2);
        $streamPath = ($magic === "\x1f\x8b") ? ('compress.zlib://' . $path) : $path;

        $handle = @fopen($streamPath, 'r');

        if ($handle === false) {
            throw new \RuntimeException('Не удалось открыть файл фида.');
        }

        $network = strtolower((string) ($cfg['network'] ?? 'awin'));
        $requiredColumns = $network === 'linkwise'
            ? self::REQUIRED_COLUMNS_LINKWISE
            : self::REQUIRED_COLUMNS_AWIN;

        try {
            $firstLine = (string) fgets($handle);
            rewind($handle);

            $delimiters = [',' => 0, ';' => 0, "\t" => 0, '|' => 0];
            foreach (array_keys($delimiters) as $d) {
                $delimiters[$d] = substr_count($firstLine, $d);
            }
            arsort($delimiters);
            $delimiter = (string) array_key_first($delimiters);

            $header = fgetcsv($handle, 0, $delimiter, '"', '');

            if (!is_array($header) || count($header) < 5) {
                throw new \RuntimeException('Файл не похож на CSV-фид (пустой заголовок). Проверьте URL и ключ.');
            }

            $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");
            $header = array_map('trim', $header);

            foreach ($requiredColumns as $required) {
                if (!in_array($required, $header, true)) {
                    throw new \RuntimeException("В фиде нет обязательной колонки {$required}. Найдены: " . implode(', ', $header));
                }
            }

            $columns = count($header);
            $activeBefore = DB::table('feed_products')->where('feed', $feedKey)->where('is_active', true)->count();

            $total = 0;
            $validRows = 0;
            $skipped = 0;
            $batch = [];
            $seenSkus = [];
            $mergedMeta = [];

            while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }

                $total++;

                if (count($row) !== $columns) {
                    $row = array_pad(array_slice($row, 0, $columns), $columns, '');
                }

                $mapped = self::mapRow(array_combine($header, $row), $feedKey, $cfg, $startedAt, $this->gatekeeper);

                if ($mapped === null) {
                    $skipped++;
                    continue;
                }

                $validRows++;

                if ($network === 'linkwise' && $mapped['sizes'] !== null) {
                    $mapped = $this->mergeLinkwiseVariant($mapped, $mergedMeta, $feedKey);
                }

                $seenSkus[$mapped['sku']] = true;
                $batch[$mapped['sku']] = $mapped;

                if ($limit > 0 && count($seenSkus) >= $limit) {
                    $this->flush($batch);
                    $batch = [];
                    break;
                }

                if (count($batch) >= self::BATCH) {
                    $this->flush($batch);
                    $batch = [];
                }
            }

            $this->flush($batch);
            $imported = count($seenSkus);
        } finally {
            fclose($handle);
        }

        if ($imported === 0) {
            throw new \RuntimeException('В фиде не нашлось ни одного пригодного товара: база не изменена.');
        }

        $minRatio = (float) config('feeds.min_import_ratio', 0.5);
        $deactivationSkipped = $limit > 0 || (!$forceDeactivate && $activeBefore > 0 && max($imported, $validRows) < $activeBefore * $minRatio);
        $deactivated = 0;

        if ($deactivationSkipped) {
            Log::warning('Feed import: деактивация пропущена (тестовый лимит или малый объём фида)', [
                'feed' => $feedKey, 'imported' => $imported, 'valid_rows' => $validRows, 'active_before' => $activeBefore, 'limit' => $limit,
            ]);
        } else {
            $deactivated = DB::table('feed_products')
                ->where('feed', $feedKey)
                ->where('is_active', true)
                ->where('last_seen_at', '<', $startedAt)
                ->update(['is_active' => false, 'updated_at' => $startedAt]);
        }

        Log::info('Feed import finished', [
            'feed' => $feedKey, 'total' => $total, 'valid_rows' => $validRows, 'imported' => $imported, 'skipped' => $skipped, 'deactivated' => $deactivated,
        ]);

        return [
            'total' => $total,
            'imported' => $imported,
            'skipped' => $skipped,
            'deactivated' => $deactivated,
            'deactivation_skipped' => $deactivationSkipped,
        ];
    }

    /**
     * Схлопывает поразмерные строки одного цвета/модели Linkwise в единый массив sizes,
     * сохраняя минимальную цену в наличии и самый полный набор фото.
     *
     * @param  array<string, mixed>  $mapped
     * @param  array<string, array<string, mixed>>  $mergedMeta
     * @return array<string, mixed>
     */
    protected function mergeLinkwiseVariant(array $mapped, array &$mergedMeta, string $feedKey): array
    {
        $sku = (string) $mapped['sku'];
        $incomingSizes = json_decode((string) $mapped['sizes'], true);
        if (!is_array($incomingSizes)) {
            $incomingSizes = [];
        }

        $incomingImages = $mapped['image_urls'] !== null ? json_decode((string) $mapped['image_urls'], true) : [];
        $incomingImageCount = is_array($incomingImages) ? count($incomingImages) : 0;

        if (!isset($mergedMeta[$sku])) {
            $sizeMap = [];
            foreach ($incomingSizes as $entry) {
                $label = trim((string) ($entry['size'] ?? ''));
                if ($label !== '') {
                    $sizeMap[mb_strtolower($label)] = $entry;
                }
            }

            $mergedMeta[$sku] = [
                'sizes' => $sizeMap,
                'in_stock' => (int) $mapped['in_stock'],
                'price' => (float) $mapped['price'],
                'old_price' => $mapped['old_price'],
                'discount_percent' => (int) $mapped['discount_percent'],
                'deep_link' => $mapped['deep_link'],
                'ean' => $mapped['ean'],
                'image_url' => $mapped['image_url'],
                'image_urls' => $mapped['image_urls'],
                'image_count' => $incomingImageCount,
            ];

            return $mapped;
        }

        $meta = &$mergedMeta[$sku];

        foreach ($incomingSizes as $entry) {
            $label = trim((string) ($entry['size'] ?? ''));
            if ($label === '') {
                continue;
            }
            $k = mb_strtolower($label);
            if (!isset($meta['sizes'][$k]) || (empty($meta['sizes'][$k]['ean']) && !empty($entry['ean']))) {
                $meta['sizes'][$k] = $entry;
            }
        }

        $incomingInStock = (int) $mapped['in_stock'];
        $incomingPrice = (float) $mapped['price'];

        if (
            ($incomingInStock === 1 && ($meta['in_stock'] === 0 || $incomingPrice < $meta['price']))
            || ($incomingInStock === 0 && $meta['in_stock'] === 0 && $incomingPrice < $meta['price'])
        ) {
            $meta['in_stock'] = $incomingInStock;
            $meta['price'] = $incomingPrice;
            $meta['old_price'] = $mapped['old_price'];
            $meta['discount_percent'] = (int) $mapped['discount_percent'];
            $meta['deep_link'] = $mapped['deep_link'];
        } elseif ($incomingInStock === 1) {
            $meta['in_stock'] = 1;
        }

        if ($meta['ean'] === null && $mapped['ean'] !== null) {
            $meta['ean'] = $mapped['ean'];
        }

        if ($incomingImageCount > $meta['image_count']) {
            $meta['image_url'] = $mapped['image_url'];
            $meta['image_urls'] = $mapped['image_urls'];
            $meta['image_count'] = $incomingImageCount;
        }

        $sortedSizes = self::sortSizes(array_values($meta['sizes']));

        $mapped['sizes'] = $sortedSizes === [] ? null : json_encode($sortedSizes, JSON_UNESCAPED_UNICODE);
        $mapped['in_stock'] = $meta['in_stock'];
        $mapped['price'] = $meta['price'];
        $mapped['old_price'] = $meta['old_price'];
        $mapped['discount_percent'] = $meta['discount_percent'];
        $mapped['deep_link'] = $meta['deep_link'];
        $mapped['ean'] = $meta['ean'];
        $mapped['image_url'] = $meta['image_url'];
        $mapped['image_urls'] = $meta['image_urls'];
        $mapped['priority_score'] = $this->gatekeeper->calculatePriority(
            $feedKey,
            $mapped['brand'] ?? null,
            (int) $mapped['discount_percent']
        );

        return $mapped;
    }

    protected function flush(array $batch): int
    {
        if ($batch === []) {
            return 0;
        }

        $rows = array_values($batch);

        DB::table('feed_products')->upsert($rows, ['feed', 'sku'], self::UPDATE_COLUMNS);

        return count($rows);
    }

    /**
     * @return array{0: string, 1: bool}
     */
    protected function resolveSource(string $source): array
    {
        if (!preg_match('#^https?://#i', $source)) {
            if (!is_file($source)) {
                throw new \RuntimeException("Файл не найден: {$source}");
            }

            return [$source, false];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'feed_');

        try {
            Http::timeout(300)->withOptions(['sink' => $tmp])->get($source)->throw();
        } catch (RequestException $e) {
            @unlink($tmp);
            throw new \RuntimeException('Не удалось скачать фид: HTTP ' . $e->response->status());
        } catch (ConnectionException) {
            @unlink($tmp);
            throw new \RuntimeException('Не удалось скачать фид: нет соединения с сервером.');
        }

        return [$tmp, true];
    }

    public static function mapRow(
        array $r,
        string $feedKey,
        array $cfg,
        string $seenAt,
        ?FeedImportGatekeeper $gatekeeper = null
    ): ?array {
        $gatekeeper ??= app(FeedImportGatekeeper::class);
        $network = strtolower((string) ($cfg['network'] ?? 'awin'));

        $mapped = $network === 'linkwise'
            ? self::mapRowLinkwise($r, $feedKey, $cfg, $seenAt)
            : self::mapRowAwin($r, $feedKey, $cfg, $seenAt);

        if ($mapped === null) {
            return null;
        }

        if (!$gatekeeper->shouldImport($feedKey, $mapped)) {
            return null;
        }

        $mapped['priority_score'] = $gatekeeper->calculatePriority(
            $feedKey,
            $mapped['brand'] ?? null,
            (int) ($mapped['discount_percent'] ?? 0)
        );

        return $mapped;
    }

    protected static function mapRowLinkwise(array $r, string $feedKey, array $cfg, string $seenAt): ?array
    {
        $get = fn (string $key): string => trim((string) ($r[$key] ?? ''));

        $rawSku = $get('sku') ?: ($get('lw_product_id') ?: $get('product_id'));
        $sku = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $rawSku), '-');
        $name = html_entity_decode($get('product_name'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $deepLink = $get('tracking_url');
        $price = self::decimal($get('price'));

        if ($sku === '' || $name === '' || $price === null || $price <= 0 || !filter_var($deepLink, FILTER_VALIDATE_URL)) {
            return null;
        }

        $old = self::decimal($get('full_price'));
        $oldPrice = ($old !== null && $old > $price) ? $old : null;
        $discount = $oldPrice !== null
            ? (int) max(1, min(99, round(($oldPrice - $price) / $oldPrice * 100)))
            : max(0, min(99, (int) $get('discount')));

        // В Linkwise пустой in_stock означает "в наличии"; отсекаем только явный Out of Stock
        $rawStock = strtoupper($get('in_stock'));
        $rawAvail = mb_strtolower($get('availability'));
        $inStock = !in_array($rawStock, ['N', '0', 'FALSE', 'NO', 'OUT OF STOCK'], true)
            && !str_contains($rawAvail, 'out of stock')
            && !str_contains($rawAvail, 'εξαντλη');

        $rawCategory = html_entity_decode($get('category'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $catPattern = str_contains($rawCategory, '>') ? '/\s*>\s*/u' : '/\s*(?:\/|\s-\s)\s*/u';
        $catSegments = array_values(array_filter(
            array_map('trim', preg_split($catPattern, $rawCategory) ?: []),
            fn ($s) => $s !== '' && mb_strtolower($s) !== mb_strtolower($feedKey)
        ));
        $categoryPath = implode(', ', $catSegments);
        $categoryTop = $catSegments[0] ?? '';

        $forcedGender = config("feed_rules.stores.{$feedKey}.force_gender");
        $gender = $forcedGender ?: self::resolveGender('', $categoryPath, $name);
        $colour = html_entity_decode($get('colour') ?: $get('color'), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Безопасное разделение extra_images: не рвём запятые внутри URL Modivo (...jpg,jpg)/...
        $rawExtra = $get('extra_images');
        $extraImages = $rawExtra !== ''
            ? (preg_split('/(?:\s*\|\s*|\s*,\s*(?=https?:\/\/)|\s+(?=https?:\/\/))/i', $rawExtra) ?: [])
            : [];
        $primaryImage = $get('image_url');
        $thumbImage = $get('thumb_url');
        $imageCandidates = self::cleanImageUrls(array_merge(
            $primaryImage !== '' ? [$primaryImage] : ($thumbImage !== '' ? [$thumbImage] : []),
            $extraImages
        ));

        $imageUrlsJson = $imageCandidates !== [] ? json_encode($imageCandidates, JSON_UNESCAPED_SLASHES) : null;
        $image = $imageCandidates[0] ?? null;

        $imgEan = '';
        if ($image !== null && preg_match('/\/(\d{12,14})(?:_\d+)?\.(?:jpe?g|png|webp)(?:\?.*)?$/i', $image, $m)) {
            $imgEan = $m[1];
        }
        $ean = self::extractEan($get('gtin'), $get('ean'), $get('sku'), $imgEan);

        $sizeList = self::list($get('size'));
        $sizes = array_map(fn (string $s) => [
            'size' => $s,
            'stock' => null,
            'ean' => $ean,
        ], $sizeList);

        $merchantUrl = filter_var($get('site_url'), FILTER_VALIDATE_URL) ?: null;
        if ($merchantUrl === null) {
            parse_str((string) parse_url($deepLink, PHP_URL_QUERY), $linkQuery);
            if (!empty($linkQuery['lnkurl']) && filter_var($linkQuery['lnkurl'], FILTER_VALIDATE_URL)) {
                $merchantUrl = (string) $linkQuery['lnkurl'];
            }
        }

        $specs = [];
        if ($ean !== null) {
            $specs[] = 'GTIN: ' . $ean;
        }

        $brandFromModel = (bool) config("feed_rules.stores.{$feedKey}.brand_from_model", false);
        $rawBrand = html_entity_decode($get('brand_name'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($rawBrand === '' && $brandFromModel && $get('model_name') !== '') {
            $rawBrand = html_entity_decode($get('model_name'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $brand = $rawBrand !== '' ? mb_substr($rawBrand, 0, 64) : ($cfg['brand'] ?? null);
        $model = $brandFromModel
            ? ($get('product_id') ?: $get('mpn'))
            : ($get('model_name') ?: ($get('product_id') ?: $get('mpn')));

        // Нормализация SKU / model_code для схлопывания поразмерных дублей (modivo, epapoutsia, notos, stradivarius, energiers)
        $canonicalSku = $sku;
        if (count($sizeList) === 1) {
            $singleSize = $sizeList[0];
            $strippedSku = self::stripTrailingSizeFromCode($sku, $singleSize, '[-_]');
            if ($strippedSku !== null) {
                $canonicalSku = $strippedSku;
                if ($model !== '') {
                    $strippedModel = self::stripTrailingSizeFromCode($model, $singleSize, '[-_|]');
                    if ($strippedModel !== null) {
                        $model = $strippedModel;
                    }
                }
            } elseif (in_array($feedKey, ['stradivarius', 'energiers'], true)) {
                $name = self::stripTrailingSizeFromName($name, $singleSize);
                $groupSeed = mb_strtolower(trim(($brand ?? '') . '|' . $name . '|' . $colour));
                if ($groupSeed !== '||') {
                    $canonicalSku = $feedKey . '-' . substr(sha1($groupSeed), 0, 16);
                }
            }
        }

        $searchText = ' ' . FeedText::normalize(implode(' ', array_filter([$brand, $name, $colour, $categoryPath, $canonicalSku, $rawSku, $model, $ean]))) . ' ';

        return [
            'feed' => $feedKey,
            'sku' => mb_substr($canonicalSku, 0, 64),
            'model_code' => $model !== '' ? mb_substr($model, 0, 64) : null,
            'ean' => $ean,
            'brand' => $brand,
            'name' => mb_substr($name, 0, 255),
            'colour' => $colour !== '' ? mb_substr($colour, 0, 255) : null,
            'category_path' => $categoryPath !== '' ? mb_substr($categoryPath, 0, 500) : null,
            'category_top' => $categoryTop !== '' ? mb_substr($categoryTop, 0, 64) : null,
            'gender' => $gender,
            'price' => $price,
            'old_price' => $oldPrice,
            'discount_percent' => $discount,
            'currency' => strtoupper(substr($get('currency') ?: 'EUR', 0, 3)),
            'rating' => null,
            'in_stock' => $inStock ? 1 : 0,
            'priority_score' => 50,
            'sizes' => $sizes === [] ? null : json_encode($sizes, JSON_UNESCAPED_UNICODE),
            'specs' => $specs === [] ? null : json_encode($specs, JSON_UNESCAPED_UNICODE),
            'image_url' => $image,
            'image_urls' => $imageUrlsJson,
            'deep_link' => $deepLink,
            'merchant_url' => $merchantUrl,
            'delivery_cost' => self::decimal($get('shipping')),
            'search_text' => $searchText,
            'is_active' => 1,
            'cascade_hidden' => 0,
            'last_seen_at' => $seenAt,
            'created_at' => $seenAt,
            'updated_at' => $seenAt,
        ];
    }

    protected static function mapRowAwin(array $r, string $feedKey, array $cfg, string $seenAt): ?array
    {
        $get = fn (string $key): string => trim((string) ($r[$key] ?? ''));

        $sku = $get('merchant_product_id');
        $name = $get('product_name');
        $deepLink = $get('aw_deep_link');
        $price = self::decimal($get('search_price'));

        if ($sku === '' || $name === '' || $price === null || $price <= 0 || !filter_var($deepLink, FILTER_VALIDATE_URL)) {
            return null;
        }

        $old = self::decimal($get('product_price_old'));
        $oldPrice = ($old !== null && $old > $price) ? $old : null;
        $discount = $oldPrice !== null ? (int) max(1, min(99, round(($oldPrice - $price) / $oldPrice * 100))) : 0;

        $sizeList = self::list($get('Fashion:size') ?: $get('size'));
        $stockList = self::list($get('stock_quantity'));
        $eanList = self::list($get('ean'));
        $sizes = [];

        foreach ($sizeList as $i => $size) {
            $sizes[] = [
                'size' => $size,
                'stock' => count($stockList) === count($sizeList) ? max(0, (int) $stockList[$i]) : null,
                'ean' => count($eanList) === count($sizeList) ? $eanList[$i] : null,
            ];
        }

        $hasStockInfo = $sizes !== [] && count($stockList) === count($sizeList);
        $inStock = $hasStockInfo
            ? (bool) array_filter($sizes, fn ($s) => ($s['stock'] ?? 0) > 0)
            : $get('in_stock') !== '0';

        $specs = array_values(array_filter(array_map('trim', explode('|', $get('specifications'))), fn ($s) => $s !== ''));

        $rawCat = $get('merchant_category') ?: $get('category_name');
        $catSegments = array_values(array_filter(
            array_map('trim', preg_split('/\s*(?:>|\/|,)\s*/u', $rawCat) ?: []),
            fn ($s) => $s !== ''
        ));
        $categoryPath = implode(', ', $catSegments);
        $categoryTop = $catSegments[0] ?? '';

        $gender = self::resolveGender($get('Fashion:suitable_for'), $categoryPath, $name);
        $colour = $get('colour') ?: $get('color');
        $model = $get('mpn') ?: $get('model_number');
        $rating = self::decimal($get('rating'));
        $ean = self::extractEan($get('gtin'), $eanList[0] ?? '');

        // Убираем Awin-прокси 200x200 (images2.productserve.com), если есть прямые фото бренда
        $imageCandidates = self::cleanImageUrls([
            $get('merchant_image_url'),
            $get('large_image'),
            $get('alternate_image'),
            $get('alternate_image_two'),
            $get('alternate_image_three'),
            $get('alternate_image_four'),
            $get('aw_image_url'),
        ]);

        $imageUrlsJson = $imageCandidates !== [] ? json_encode($imageCandidates, JSON_UNESCAPED_SLASHES) : null;
        $image = $imageCandidates[0] ?? null;

        $brand = $get('brand_name') !== '' ? mb_substr($get('brand_name'), 0, 64) : ($cfg['brand'] ?? null);
        $searchText = ' ' . FeedText::normalize(implode(' ', array_filter([$brand, $name, $colour, $categoryPath, $sku, $model, $ean]))) . ' ';

        return [
            'feed' => $feedKey,
            'sku' => mb_substr($sku, 0, 64),
            'model_code' => $model !== '' ? mb_substr($model, 0, 64) : null,
            'ean' => $ean,
            'brand' => $brand,
            'name' => mb_substr($name, 0, 255),
            'colour' => $colour !== '' ? mb_substr($colour, 0, 255) : null,
            'category_path' => $categoryPath !== '' ? mb_substr($categoryPath, 0, 500) : null,
            'category_top' => $categoryTop !== '' ? mb_substr($categoryTop, 0, 64) : null,
            'gender' => $gender,
            'price' => $price,
            'old_price' => $oldPrice,
            'discount_percent' => $discount,
            'currency' => strtoupper(substr($get('currency') ?: 'EUR', 0, 3)),
            'rating' => ($rating !== null && $rating > 0) ? min(5.0, $rating) : null,
            'in_stock' => $inStock ? 1 : 0,
            'priority_score' => 50,
            'sizes' => $sizes === [] ? null : json_encode($sizes, JSON_UNESCAPED_UNICODE),
            'specs' => $specs === [] ? null : json_encode($specs, JSON_UNESCAPED_UNICODE),
            'image_url' => $image,
            'image_urls' => $imageUrlsJson,
            'deep_link' => $deepLink,
            'merchant_url' => filter_var($get('merchant_deep_link'), FILTER_VALIDATE_URL) ?: null,
            'delivery_cost' => self::decimal($get('delivery_cost')),
            'search_text' => $searchText,
            'is_active' => 1,
            'cascade_hidden' => 0,
            'last_seen_at' => $seenAt,
            'created_at' => $seenAt,
            'updated_at' => $seenAt,
        ];
    }

    /**
     * Валидирует, дедуплицирует и очищает массив URL картинок (отбрасывает обрезанные URL и прокси productserve.com).
     *
     * @param  string[]  $rawUrls
     * @return string[]
     */
    protected static function cleanImageUrls(array $rawUrls): array
    {
        $direct = [];
        $fallback = [];

        foreach ($rawUrls as $url) {
            $u = trim($url);
            if ($u === '' || !preg_match('#^https?://#i', $u) || !filter_var($u, FILTER_VALIDATE_URL)) {
                continue;
            }
            // Отсекаем обрезанные URL со скобкой без закрывающей пары (например, сломанные Modivo CDN)
            if (substr_count($u, '(') !== substr_count($u, ')')) {
                continue;
            }

            $host = strtolower((string) parse_url($u, PHP_URL_HOST));
            if (str_contains($host, 'productserve.com')) {
                $fallback[$u] = true;
            } else {
                $direct[$u] = true;
            }
        }

        return array_keys($direct !== [] ? $direct : $fallback);
    }

    protected static function stripTrailingSizeFromCode(string $code, string $size, string $sepRegex = '[-_]'): ?string
    {
        $tokens = preg_split('/[^A-Za-z0-9]+/u', trim($size), -1, PREG_SPLIT_NO_EMPTY);
        if (!$tokens) {
            return null;
        }

        $sizePattern = implode('[-_.]?', array_map(static fn (string $t): string => preg_quote($t, '/'), $tokens));
        $stripped = preg_replace('/' . $sepRegex . $sizePattern . '$/iu', '', $code, 1, $count);

        if ($count === 0 || !is_string($stripped)) {
            return null;
        }

        $stripped = rtrim($stripped, '-_|');
        if (strlen($stripped) < 6) {
            return null;
        }

        return $stripped;
    }

    protected static function stripTrailingSizeFromName(string $name, string $size): string
    {
        $s = trim($size);
        if ($s === '') {
            return $name;
        }

        $cleaned = preg_replace('/[\s\-|]+' . preg_quote($s, '/') . '$/iu', '', $name);

        return is_string($cleaned) && trim($cleaned) !== '' ? trim($cleaned) : $name;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sizes
     * @return array<int, array<string, mixed>>
     */
    protected static function sortSizes(array $sizes): array
    {
        usort($sizes, static function (array $a, array $b): int {
            $sa = mb_strtolower(trim((string) ($a['size'] ?? '')));
            $sb = mb_strtolower(trim((string) ($b['size'] ?? '')));

            $oa = self::SIZE_ORDER[$sa] ?? null;
            $ob = self::SIZE_ORDER[$sb] ?? null;

            if ($oa !== null && $ob !== null) {
                return $oa <=> $ob;
            }
            if ($oa !== null) {
                return -1;
            }
            if ($ob !== null) {
                return 1;
            }

            return strnatcasecmp($sa, $sb);
        });

        return $sizes;
    }

    protected static function resolveGender(string $suitableFor, string $categoryPath, string $name): ?string
    {
        foreach ([$suitableFor, $categoryPath, $name] as $idx => $source) {
            $clean = $source;
            if ($idx === 2) {
                $clean = (string) preg_replace('/\b(spider[\s\-]?man|iron[\s\-]?man|super[\s\-]?man|bat[\s\-]?man|pac[\s\-]?man|x[\s\-]?men)\b/iu', ' ', $clean);
            }

            $norm = FeedText::normalize($clean);
            if ($norm === '') {
                continue;
            }

            if (preg_match('/\bunisex\b/u', $norm)) {
                return 'unisex';
            }

            $tokens = explode(' ', $norm);
            $found = [];
            foreach ($tokens as $token) {
                $detected = FeedText::detectGender($token);
                if ($detected !== null) {
                    $found[$detected] = true;
                }
            }

            foreach (['kids', 'women', 'men'] as $priority) {
                if (isset($found[$priority])) {
                    return $priority;
                }
            }
        }

        return null;
    }

    protected static function decimal(string $value): ?float
    {
        $value = str_replace(',', '.', trim($value));

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /** @return string[] */
    protected static function list(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_map('trim', explode(',', $value));
    }

    public static function extractEan(?string ...$candidates): ?string
    {
        foreach ($candidates as $raw) {
            $val = trim((string) $raw);
            if ($val === '' || !preg_match('/^\d{12,14}$/', $val)) {
                continue;
            }
            $normalized = strlen($val) === 12 ? ('0' . $val) : $val;
            if (self::isValidGtin($normalized)) {
                return $normalized;
            }
        }

        return null;
    }

    protected static function isValidGtin(string $digits): bool
    {
        $len = strlen($digits);
        if ($len !== 13 && $len !== 14) {
            return false;
        }

        $sum = 0;
        for ($i = $len - 2, $pos = 0; $i >= 0; $i--, $pos++) {
            $sum += ((int) $digits[$i]) * ($pos % 2 === 0 ? 3 : 1);
        }

        return ((10 - ($sum % 10)) % 10) === (int) $digits[$len - 1];
    }
}