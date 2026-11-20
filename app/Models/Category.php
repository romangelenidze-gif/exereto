<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Category extends Model
{
    protected $fillable = [
        'parent_id',
        'slug',
        'gender',
        'name_en',
        'name_ru',
        'name_ka',
        'name_el',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @var array<int, string[]>|null */
    private static ?array $ancestorSlugsCache = null;

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function localizedName(?string $locale = null): string
    {
        $loc = $locale ?: app()->getLocale();

        return match ($loc) {
            'ru' => (string) ($this->name_ru ?: $this->name_en),
            'ka' => (string) ($this->name_ka ?: $this->name_en),
            'el' => (string) ($this->name_el ?: $this->name_en),
            default => (string) $this->name_en,
        };
    }

    /**
     * Возвращает карту [category_id => ['l1-slug', 'l2-slug', 'l3-slug']] за 1 SQL-запрос.
     *
     * @return array<int, string[]>
     */
    public static function ancestorSlugsMap(bool $fresh = false): array
    {
        if (!$fresh && self::$ancestorSlugsCache !== null) {
            return self::$ancestorSlugsCache;
        }

        $rows = DB::table('categories')
            ->where('is_active', true)
            ->get(['id', 'parent_id', 'slug'])
            ->keyBy('id');

        $map = [];
        foreach ($rows as $id => $row) {
            $chain = [];
            $curr = $row;
            $guard = 0;

            while ($curr !== null && $guard < 6) {
                array_unshift($chain, (string) $curr->slug);
                $curr = $curr->parent_id ? ($rows[$curr->parent_id] ?? null) : null;
                $guard++;
            }

            $map[(int) $id] = $chain;
        }

        return self::$ancestorSlugsCache = $map;
    }

    public static function flushTreeCache(): void
    {
        self::$ancestorSlugsCache = null;
    }
}