<?php

namespace App\Services\Feeds;

class FeedImportGatekeeper
{
    /**
     * Проверяет, нужно ли импортировать товар из данного фида согласно матрице правил.
     *
     * @param  string  $feedKey  Код фида (например: modivo, epapoutsia, sephora)
     * @param  array<string, mixed>  $row  Массив атрибутов товара (brand, category_top, category_path, name...)
     */
public function shouldImport(string $feedKey, array $row): bool
    {
        $rules = (array) config("feed_rules.stores.{$feedKey}", []);
        if ($rules === []) {
            return true;
        }

        if (isset($rules['enabled']) && $rules['enabled'] === false) {
            return false;
        }

        // 0. Обязательное наличие на складе
        if (!empty($rules['require_in_stock']) && empty($row['in_stock'])) {
            return false;
        }

        // 0.1. Отсечение по префиксам SKU/model_code (например, сторонний маркетплейс MRK в Public)
        $skuLower = mb_strtolower(trim((string) ($row['sku'] ?? '')));
        $modelLower = mb_strtolower(trim((string) ($row['model_code'] ?? '')));
        foreach ((array) ($rules['exclude_sku_prefixes'] ?? []) as $prefix) {
            $p = mb_strtolower(trim((string) $prefix));
            if ($p !== '' && (str_starts_with($skuLower, $p) || str_starts_with($modelLower, $p))) {
                return false;
            }
        }

        $brand = mb_strtolower(trim((string) ($row['brand'] ?? '')));
        $categoryTop = mb_strtolower(trim((string) ($row['category_top'] ?? '')));
        $categoryPath = mb_strtolower(trim((string) ($row['category_path'] ?? '')));
        $pathScope = trim($categoryTop . ' ' . $categoryPath);
        $categoryText = trim($pathScope . ' ' . mb_strtolower(trim((string) ($row['name'] ?? ''))));

        // 1. Черный список брендов
        foreach ((array) ($rules['exclude_brands'] ?? []) as $excludedBrand) {
            $needle = mb_strtolower(trim((string) $excludedBrand));
            if ($needle !== '' && $brand === $needle) {
                return false;
            }
        }

        // 2. Белый список корневой категории (точное совпадение category_top)
        $allowedTops = array_values(array_filter(array_map(
            fn ($t) => mb_strtolower(trim((string) $t)),
            (array) ($rules['allow_top_categories'] ?? [])
        )));
        if ($allowedTops !== [] && !in_array($categoryTop, $allowedTops, true)) {
            return false;
        }

        // 3. Черный список категорий (для коротких латинских аббревиатур вроде ups — строго по границам слова)
        foreach ((array) ($rules['exclude_categories'] ?? []) as $excludedCat) {
            $needle = mb_strtolower(trim((string) $excludedCat));
            if ($needle === '') {
                continue;
            }
            if (preg_match('/^[a-z0-9]{1,4}$/', $needle)) {
                if (preg_match('/\b' . preg_quote($needle, '/') . '\b/u', $categoryText)) {
                    return false;
                }
            } elseif (str_contains($categoryText, $needle)) {
                return false;
            }
        }

        // 4. Белый список строго по дереву категорий (category_top + category_path)
        $allowedPaths = array_filter((array) ($rules['allow_category_paths'] ?? []));
        if ($allowedPaths !== []) {
            $matched = false;
            foreach ($allowedPaths as $allowedPath) {
                $needle = mb_strtolower(trim((string) $allowedPath));
                if ($needle !== '' && str_contains($pathScope, $needle)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        // 5. Существующий белый список категорий (для обратной совместимости с modivo, pharm24 и др.)
        $allowedCategories = array_filter((array) ($rules['allow_categories'] ?? []));
        if ($allowedCategories !== []) {
            $matched = false;
            foreach ($allowedCategories as $allowedCat) {
                $needle = mb_strtolower(trim((string) $allowedCat));
                if ($needle !== '' && str_contains($categoryText, $needle)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    /**
     * Вычисляет итоговый приоритет карточки (priority_score) для дедупликации между магазинами.
     */
    public function calculatePriority(string $feedKey, ?string $brand, int $discountPercent = 0): int
    {
        $rules = (array) config("feed_rules.stores.{$feedKey}", []);
        $score = (int) ($rules['base_priority'] ?? 50);

        $normalizedBrand = mb_strtolower(trim((string) $brand));
        if ($normalizedBrand !== '' && !empty($rules['brand_priorities'])) {
            foreach ((array) $rules['brand_priorities'] as $ruleBrand => $brandScore) {
                if ($normalizedBrand === mb_strtolower(trim((string) $ruleBrand))) {
                    $score = (int) $brandScore;
                    break;
                }
            }
        }

        // Бонус за скидку (например, для Galerie de Beauté против Sephora на распродажах)
        if (!empty($rules['discount_bonus']) && $discountPercent > 0) {
            $score += min(45, (int) round($discountPercent * 0.6));
        }

        return max(1, min(999, $score));
    }

    /**
     * Подставляет английский префикс /en/ как в прямую ссылку, так и внутрь параметра ued= у Awin-ссылок.
     */
    public function localizeUrl(string $feedKey, ?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $modifier = config("feed_rules.stores.{$feedKey}.url_modifier");
        if (empty($modifier['search']) || empty($modifier['replace'])) {
            return $url;
        }

        $searches = (array) $modifier['search'];
        $replaces = (array) $modifier['replace'];

// Поддержка и Awin (?ued=...), и Linkwise (?lnkurl=...)
        if (str_contains($url, 'ued=') || str_contains($url, 'lnkurl=')) {
            return preg_replace_callback('/([?&](?:ued|lnkurl)=)([^&]+)/i', function (array $m) use ($searches, $replaces) {
                $decoded = rawurldecode($m[2]);
                $modified = $this->applyUrlReplacement($decoded, $searches, $replaces);
                return $m[1] . rawurlencode($modified);
            }, $url) ?? $url;
        }

        return $this->applyUrlReplacement($url, $searches, $replaces);
    }

    private function applyUrlReplacement(string $url, array $searches, array $replaces): string
    {
        // Если уже содержит /en/, не дублируем
        if (str_contains($url, '/en/')) {
            return $url;
        }

        foreach ($searches as $idx => $search) {
            $replace = $replaces[$idx] ?? $replaces[0];
            if (str_contains($url, $search)) {
                return str_replace($search, $replace, $url);
            }
        }

        return $url;
    }
}