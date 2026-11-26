<?php

namespace App\Console\Commands;

use App\Models\FeedProduct;
use App\Services\FeedCategoryTiles;
use Illuminate\Console\Command;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate {--limit=10000 : Максимум товаров на локаль}';
    protected $description = 'Generate static public/sitemap.xml for all locales';

    public function handle(FeedCategoryTiles $tiles): int
    {
        $baseUrl = rtrim((string) config('app.url', 'https://exereto.com'), '/');
        $locales = ['', '/ru', '/ka'];
        $today = now()->toDateString();

        $staticPaths = [
            ['path' => '',              'freq' => 'daily',   'prio' => '1.0'],
            ['path' => '/help',         'freq' => 'monthly', 'prio' => '0.5'],
            ['path' => '/terms',        'freq' => 'yearly',  'prio' => '0.3'],
            ['path' => '/privacy',      'freq' => 'yearly',  'prio' => '0.3'],
        ];

        $tmpPath = public_path('sitemap.xml.tmp');
        $finalPath = public_path('sitemap.xml');

        $fh = fopen($tmpPath, 'wb');
        if ($fh === false) {
            $this->error('Cannot open temporary sitemap file for writing.');
            return self::FAILURE;
        }

        fwrite($fh, '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
        fwrite($fh, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n");

        $count = 0;
        $writeUrl = function (string $loc, string $lastmod, string $freq, string $prio) use ($fh, &$count): void {
            fwrite(
                $fh,
                "  <url>\n" .
                "    <loc>" . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n" .
                "    <lastmod>{$lastmod}</lastmod>\n" .
                "    <changefreq>{$freq}</changefreq>\n" .
                "    <priority>{$prio}</priority>\n" .
                "  </url>\n"
            );
            $count++;
        };

        // 1. Статические страницы во всех локалях
        foreach ($locales as $prefix) {
            foreach ($staticPaths as $item) {
                $url = $baseUrl . $prefix . ($item['path'] === '' ? ($prefix === '' ? '/' : '') : $item['path']);
                $writeUrl($url, $today, $item['freq'], $item['prio']);
            }
        }

        // 2. Категории из FeedCategoryTiles
        foreach ($tiles->get() as $tile) {
            $slug = (string) ($tile['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            foreach ($locales as $prefix) {
                $writeUrl("{$baseUrl}{$prefix}/category/{$slug}", $today, 'daily', '0.8');
            }
        }

        // 3. Активные товары в наличии (сортировка по приоритету, потоково без нагрузки на RAM)
        $limit = max(1, (int) $this->option('limit'));
        $products = FeedProduct::query()
            ->select(['feed', 'sku', 'updated_at'])
            ->where('is_active', true)
            ->where('in_stock', true)
            ->orderByDesc('priority_score')
            ->limit($limit)
            ->cursor();

        foreach ($products as $p) {
            $lastmod = $p->updated_at ? $p->updated_at->toDateString() : $today;
            $feed = rawurlencode((string) $p->feed);
            $sku = rawurlencode((string) $p->sku);

            foreach ($locales as $prefix) {
                $writeUrl("{$baseUrl}{$prefix}/product/{$feed}/{$sku}", $lastmod, 'weekly', '0.6');
            }
        }

        fwrite($fh, "</urlset>\n");
        fclose($fh);

        // Атомарная подмена файла — боты никогда не увидят недописанный XML
        rename($tmpPath, $finalPath);

        $this->info("Generated {$finalPath} with {$count} URLs.");

        return self::SUCCESS;
    }
}