<?php

namespace App\Services;

use App\Models\FeedProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class FeedCategoryTiles
{
    private const CACHE_MINUTES = 60;

    /**
     * Плитки главных категорий с иконками и количеством товаров по всем активным магазинам.
     *
     * @return Collection<int, array{slug: string, label: string, img: string, count: int, url: string}>
     */
    public function get(): Collection
    {
        $raw = Cache::remember(
            'home.category_tiles.v3.all',
            now()->addMinutes(self::CACHE_MINUTES),
            fn () => $this->build()
        );

        return collect($raw)->map(fn (array $tile) => [
            'slug' => $tile['slug'],
            'label' => __($tile['label']),
            'img' => $tile['img'],
            'count' => $tile['count'],
            'url' => route('feed-category.show', ['slug' => $tile['slug']]),
        ]);
    }

    /** Описание категории по slug или null. */
    public function tile(string $slug): ?array
    {
        foreach ((array) config('home_categories.tiles', []) as $tile) {
            if (($tile['slug'] ?? null) === $slug) {
                return $tile;
            }
        }

        return null;
    }

    /** Базовый запрос товаров категории (по всем активным фидам, исключая мусорные разделы). */
    public function productsQuery(array $tile): Builder
    {
        $query = FeedProduct::query()
            ->where('is_active', true)
            ->where('in_stock', true);

        $feeds = array_filter((array) config('home_categories.feeds', []));
        if ($feeds !== []) {
            $query->whereIn('feed', $feeds);
        }

        $excludedTops = array_filter((array) config('home_categories.excluded_tops', ['Home']));
        if ($excludedTops !== []) {
            $query->where(function (Builder $q) use ($excludedTops) {
                $q->whereNull('category_top')
                  ->orWhereNotIn('category_top', $excludedTops);
            });
        }

        $this->applyFilter($query, (array) ($tile['where'] ?? []));

        return $query;
    }

    /**
     * Подкатегории для верхней карусели страницы категории с реальным фото товара и счётчиком.
     *
     * @return array<int, array{index: int, label: string, count: int, image: ?string}>
     */
    public function buildSubcategories(array $tile): array
    {
        $subs = (array) ($tile['subcategories'] ?? []);
        if ($subs === []) {
            return [];
        }

        $cacheKey = 'category.subs.v1.' . ($tile['slug'] ?? md5(json_encode($tile)));

        $raw = Cache::remember($cacheKey, now()->addMinutes(self::CACHE_MINUTES), function () use ($tile, $subs) {
            $result = [];
            foreach ($subs as $idx => $sub) {
                $q = $this->productsQuery($tile);
                $this->applyFilter($q, (array) ($sub['where'] ?? []));

                $count = (clone $q)->count();
                if ($count === 0) {
                    continue;
                }

                $sampleImage = (clone $q)
                    ->whereNotNull('image_url')
                    ->where('image_url', '!=', '')
                    ->value('image_url');

                $result[] = [
                    'index' => $idx,
                    'label' => (string) ($sub['label'] ?? ''),
                    'count' => $count,
                    'image' => $sampleImage,
                ];
            }

            return $result;
        });

        return array_map(fn (array $item) => [
            'index' => $item['index'],
            'label' => __($item['label']),
            'count' => $item['count'],
            'image' => $item['image'],
        ], $raw);
    }

    /**
     * Фасетные фильтры для левого сайдбара страницы категории (Бренды, Пол, Размеры, Цвета).
     */
    public function buildFacets(array $tile): array
    {
        $cacheKey = 'category.facets.v2.' . ($tile['slug'] ?? md5(json_encode($tile)));

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_MINUTES), function () use ($tile) {
            $base = $this->productsQuery($tile);

            // 1. Топ брендов
            $brands = (clone $base)
                ->whereNotNull('brand')
                ->where('brand', '!=', '')
                ->selectRaw('brand, COUNT(*) as cnt')
                ->groupBy('brand')
                ->orderByDesc('cnt')
                ->limit(25)
                ->get()
                ->map(fn ($r) => ['name' => (string) $r->brand, 'count' => (int) $r->cnt])
                ->all();

            // 2. Пол
            $genders = (clone $base)
                ->whereNotNull('gender')
                ->where('gender', '!=', '')
                ->selectRaw('gender, COUNT(*) as cnt')
                ->groupBy('gender')
                ->orderByDesc('cnt')
                ->get()
                ->map(fn ($r) => ['code' => (string) $r->gender, 'count' => (int) $r->cnt])
                ->all();

            // 3. Популярные размеры и цвета (по выборке до 2500 товаров категории)
            $sample = (clone $base)
                ->select(['sizes', 'colour'])
                ->limit(2500)
                ->get();

            $sizeCounts = [];
            $colorCounts = [];

            foreach ($sample as $row) {
                if (is_array($row->sizes)) {
                    foreach ($row->sizes as $s) {
                        $rawSize = is_array($s)
                            ? ($s['size'] ?? $s['name'] ?? $s['value'] ?? $s['label'] ?? null)
                            : $s;

                        if (!is_scalar($rawSize)) {
                            continue;
                        }

                        $sizeStr = trim((string) $rawSize);
                        if ($sizeStr !== '' && mb_strlen($sizeStr) <= 12) {
                            $sizeCounts[$sizeStr] = ($sizeCounts[$sizeStr] ?? 0) + 1;
                        }
                    }
                }

                if (is_string($row->colour) && $row->colour !== '') {
                    $mainColor = trim(explode('/', $row->colour)[0]);
                    if ($mainColor !== '' && mb_strlen($mainColor) <= 25) {
                        $colorCounts[$mainColor] = ($colorCounts[$mainColor] ?? 0) + 1;
                    }
                }
            }

            arsort($sizeCounts);
            arsort($colorCounts);

            return [
                'brands' => $brands,
                'genders' => $genders,
                'sizes' => array_slice(array_keys($sizeCounts), 0, 18),
                'colors' => array_slice($colorCounts, 0, 12, true),
            ];
        });
    }

    public function applyPublicFilter(Builder $query, array $where): void
    {
        $this->applyFilter($query, $where);
    }

    private function build(): array
    {
        $tiles = [];

        foreach ((array) config('home_categories.tiles', []) as $tile) {
            $count = $this->productsQuery($tile)->count();
            if ($count === 0) {
                continue;
            }

            $tiles[] = [
                'slug' => (string) $tile['slug'],
                'label' => (string) $tile['label'],
                'img' => (string) ($tile['img'] ?? ($tile['slug'] . '.png')),
                'count' => $count,
            ];
        }

        return $tiles;
    }

    private function applyFilter(Builder $query, array $where): void
    {
        foreach ($where as $key => $value) {
            match ($key) {
                'category_top', 'gender' => $query->where($key, $value),
                'category_path' => $this->wherePathLike($query, (array) $value),
                'discount' => $value ? $query->where('discount_percent', '>', 0) : null,
                default => throw new InvalidArgumentException("config/home_categories.php: неизвестный ключ where «{$key}»"),
            };
        }
    }

    private function wherePathLike(Builder $query, array $needles): void
    {
        $query->where(function (Builder $q) use ($needles) {
            foreach ($needles as $needle) {
                $q->orWhere('category_path', 'like', '%' . $needle . '%');
            }
        });
    }
}