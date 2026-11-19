<?php

namespace Tests\Feature;

use App\Models\FeedProduct;
use App\Services\Feeds\AwinFeedImporter;
use App\Services\Feeds\FeedDeduplicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeedPipelineIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private string $tmpCsv;

    protected function setUp(): void
    {
        parent::setUp();

        // Жёсткий предохранитель: никогда не запускать RefreshDatabase против боевой БД или боевого Meilisearch
        $dbName = (string) DB::connection()->getDatabaseName();
        if (app()->environment('production') || ($dbName !== ':memory:' && !str_contains(strtolower($dbName), 'test'))) {
            $this->markTestSkipped("Безопасная остановка: БД «{$dbName}» не похожа на тестовую (настройте phpunit.xml).");
        }

        config()->set('scout.driver', 'null');
    }

    protected function tearDown(): void
    {
        if (isset($this->tmpCsv) && is_file($this->tmpCsv)) {
            @unlink($this->tmpCsv);
        }
        parent::tearDown();
    }

    public function test_importer_is_idempotent_extracts_ean_brand_gallery_and_deactivates_missing(): void
    {
        $this->tmpCsv = tempnam(sys_get_temp_dir(), 'csv_test_');
        $header = ['lw_product_id', 'product_id', 'sku', 'model_name', 'product_name', 'tracking_url', 'price', 'full_price', 'in_stock', 'category', 'brand_name', 'gtin', 'image_url', 'extra_images'];

        $writeCsv = function (array $rows) use ($header) {
            $fh = fopen($this->tmpCsv, 'w');
            fputcsv($fh, $header);
            foreach ($rows as $row) {
                fputcsv($fh, $row);
            }
            fclose($fh);
        };

        // Как в реальном toysgr: brand_name пуст, бренд в model_name, EAN-13 в имени файла image_url
        $row1 = ['11307-1', '1', '', 'LEGO', 'LEGO Technic 42154', 'https://track.test/1', '99,90', '119,90', 'Y', 'Παιχνίδια > Κατασκευές', '', '', 'https://img.test/5702017424736.jpg', 'https://img.test/5702017424736_1.jpg'];
        $row2 = ['11307-2', '2', '', 'Svoora', 'Svoora Wooden Top', 'https://track.test/2', '12,50', '', 'Y', 'Ξύλινα Παιχνίδια > Κλασικά', '', '', 'https://img.test/0771877904141.jpg', ''];
        $garbage = ['11307-3', '3', '', 'Chicco', 'Μπιμπέρο 260ml', 'https://track.test/3', '9,90', '', 'Y', 'Βρεφικά > Μπιμπέρο', '', '', 'https://img.test/8058664000001.jpg', ''];

        $writeCsv([$row1, $row2, $garbage]);

        $importer = app(AwinFeedImporter::class);

        $stats1 = $importer->import('toysgr', $this->tmpCsv);
        $this->assertSame(2, $stats1['imported']);
        $this->assertSame(1, $stats1['skipped']);
        $this->assertSame(2, FeedProduct::where('feed', 'toysgr')->count());

        $p1 = FeedProduct::where('feed', 'toysgr')->where('sku', '11307-1')->firstOrFail();
        $this->assertSame('LEGO', $p1->brand);
        $this->assertSame('5702017424736', $p1->ean);
        $this->assertCount(2, $p1->image_urls);

        // Повторный прогон не создаёт дублей
        $importer->import('toysgr', $this->tmpCsv);
        $this->assertSame(2, FeedProduct::where('feed', 'toysgr')->count());

        // Пропавший товар деактивируется
        sleep(1);
        $writeCsv([$row1]);
        $stats3 = $importer->import('toysgr', $this->tmpCsv, 0, true);
        $this->assertSame(1, $stats3['deactivated']);
        $this->assertFalse(FeedProduct::where('feed', 'toysgr')->where('sku', '11307-2')->firstOrFail()->is_active);
    }

    public function test_stock_aware_cascade_switches_winner_and_never_touches_other_feeds(): void
    {
        $common = ['price' => 50, 'currency' => 'EUR', 'deep_link' => 'https://track.test/x', 'is_active' => true, 'cascade_hidden' => false, 'priority_score' => 80];

        $toys = FeedProduct::create(array_merge($common, ['feed' => 'toysgr', 'sku' => 'TG-1', 'brand' => 'LEGO', 'name' => 'LEGO Technic 42154', 'ean' => '5702017424736', 'in_stock' => true]));
        $mous = FeedProduct::create(array_merge($common, ['feed' => 'moustakas', 'sku' => 'MS-1', 'brand' => 'LEGO', 'name' => 'LEGO Technic 42154', 'ean' => '5702017424736', 'in_stock' => true]));
        $pub  = FeedProduct::create(array_merge($common, ['feed' => 'public', 'sku' => 'PB-1', 'brand' => 'LEGO', 'name' => 'LEGO Technic 42154', 'ean' => null, 'in_stock' => true]));
        $guess = FeedProduct::create(array_merge($common, ['feed' => 'notos', 'sku' => 'GUESS-1', 'brand' => 'Guess', 'name' => 'Guess Bag', 'ean' => '5702017424736', 'in_stock' => true]));

        $dedup = app(FeedDeduplicator::class);

        // Есть у всех -> активен toysgr; moustakas скрыт по EAN, public скрыт по LEGO:42154; notos НЕ ТРОНУТ
        $dedup->run(false);
        $this->assertTrue($toys->fresh()->is_active);
        $this->assertFalse($mous->fresh()->is_active);
        $this->assertTrue($mous->fresh()->cascade_hidden);
        $this->assertFalse($pub->fresh()->is_active);
        $this->assertTrue($pub->fresh()->cascade_hidden);
        $this->assertTrue($guess->fresh()->is_active);

        // Закончился в toysgr -> автоматически всплывает moustakas, public остаётся скрыт, priority_score не испорчен
        $toys->update(['in_stock' => false]);
        $dedup->run(false);
        $this->assertTrue($mous->fresh()->is_active);
        $this->assertFalse($mous->fresh()->cascade_hidden);
        $this->assertFalse($pub->fresh()->is_active);
        $this->assertSame(80, $mous->fresh()->priority_score);
    }
}