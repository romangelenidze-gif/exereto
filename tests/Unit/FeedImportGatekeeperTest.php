<?php

namespace Tests\Unit;

use App\Services\Feeds\AwinFeedImporter;
use App\Services\Feeds\FeedDeduplicator;
use App\Services\Feeds\FeedImportGatekeeper;
use Tests\TestCase;

class FeedImportGatekeeperTest extends TestCase
{
    private FeedImportGatekeeper $gatekeeper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gatekeeper = new FeedImportGatekeeper();
    }

    public function test_toysgr_blocks_home_baby_party_board_games_and_allows_toys(): void
    {
        $this->assertFalse($this->gatekeeper->shouldImport('toysgr', [
            'category_top' => 'Είδη σπιτιού', 'category_path' => 'Είδη σπιτιού, Παγούρια', 'name' => 'Παγούρι 500ml', 'in_stock' => 1,
        ]));
        $this->assertFalse($this->gatekeeper->shouldImport('toysgr', [
            'category_top' => 'Βρεφικά', 'category_path' => 'Βρεφικά, Μπιμπέρο', 'name' => 'Μπιμπέρο 260ml', 'in_stock' => 1,
        ]));
        $this->assertFalse($this->gatekeeper->shouldImport('toysgr', [
            'category_top' => 'Παιχνίδια', 'category_path' => 'Παιχνίδια, Είδη Πάρτυ', 'name' => 'Μπαλόνια Πάρτυ', 'in_stock' => 1,
        ]));
        $this->assertFalse($this->gatekeeper->shouldImport('toysgr', [
            'category_top' => 'Παιχνίδια', 'category_path' => 'Παιχνίδια, Επιτραπέζια', 'name' => 'Επιτραπέζιο Monopoly', 'in_stock' => 1,
        ]));
        $this->assertTrue($this->gatekeeper->shouldImport('toysgr', [
            'category_top' => 'Παιχνίδια', 'category_path' => 'Παιχνίδια, Κατασκευές, LEGO', 'name' => 'LEGO Technic 42154', 'in_stock' => 1,
        ]));
        $this->assertTrue($this->gatekeeper->shouldImport('toysgr', [
            'category_top' => 'S.T.E.M.', 'category_path' => 'S.T.E.M., Ρομποτική', 'name' => 'STEM Robotics Kit', 'in_stock' => 1,
        ]));
    }

    public function test_moustakas_blocks_bebe_bikes_batteries_stationery_and_allows_toys_backpacks(): void
    {
        $this->assertFalse($this->gatekeeper->shouldImport('moustakas', [
            'category_top' => 'Bebe-Βρεφικά Είδη', 'category_path' => 'Bebe-Βρεφικά Είδη, Φαγητό', 'name' => 'Σετ Κουτάλι-Πηρούνι', 'in_stock' => 1,
        ]));
        $this->assertFalse($this->gatekeeper->shouldImport('moustakas', [
            'category_top' => 'Τρίκυκλα-Ποδήλατα-Πατίνια', 'category_path' => 'Τρίκυκλα-Ποδήλατα-Πατίνια', 'name' => 'Ποδήλατο 16"', 'in_stock' => 1,
        ]));
        $this->assertFalse($this->gatekeeper->shouldImport('moustakas', [
            'category_top' => 'Σχολικά', 'category_path' => 'Σχολικά, Γραφική Ύλη, Τετράδια', 'name' => 'Τετράδιο 50φ', 'in_stock' => 1,
        ]));
        $this->assertTrue($this->gatekeeper->shouldImport('moustakas', [
            'category_top' => 'Σχολικά', 'category_path' => 'Σχολικά, Σχολικές Τσάντες', 'name' => 'Σακίδιο Πλάτης Polo', 'in_stock' => 1,
        ]));
        $this->assertTrue($this->gatekeeper->shouldImport('moustakas', [
            'category_top' => 'Τουβλάκια-Κατασκευές-Playset', 'category_path' => 'Τουβλάκια-Κατασκευές-Playset, LEGO', 'name' => 'LEGO Star Wars 75375', 'in_stock' => 1,
        ]));
    }

    public function test_public_filters_stock_mrk_refurbished_and_allows_tablets_drones_smartphones(): void
    {
        // Out of stock -> нет
        $this->assertFalse($this->gatekeeper->shouldImport('public', [
            'sku' => '469-100', 'category_top' => 'Apple', 'category_path' => 'Apple, iPhone', 'name' => 'iPhone 16 Pro', 'in_stock' => 0,
        ]));
        // Marketplace MRK -> нет
        $this->assertFalse($this->gatekeeper->shouldImport('public', [
            'sku' => '469-MRK2067581', 'model_code' => 'MRK2067581', 'category_top' => 'Υπολογιστές & Περιφερειακά', 'category_path' => 'Υπολογιστές & Περιφερειακά, Laptops', 'name' => 'MacBook Stand', 'in_stock' => 1,
        ]));
        // Refurbished -> нет
        $this->assertFalse($this->gatekeeper->shouldImport('public', [
            'sku' => '469-1958497', 'category_top' => 'Certified Refurbished', 'category_path' => 'Certified Refurbished, Refurbished Desktops', 'name' => 'Refurbished PC', 'in_stock' => 1,
        ]));
        // Электросамокат в eMobility -> нет
        $this->assertFalse($this->gatekeeper->shouldImport('public', [
            'sku' => '469-200', 'category_top' => 'eMobility, Photo & Drones', 'category_path' => 'eMobility, Photo & Drones, Emobility', 'name' => 'Xiaomi Scooter', 'in_stock' => 1,
        ]));
        // Стерилизатор в Παιχνίδια & Παιδικά -> нет
        $this->assertFalse($this->gatekeeper->shouldImport('public', [
            'sku' => '469-201', 'category_top' => 'Παιχνίδια & Παιδικά', 'category_path' => 'Παιχνίδια & Παιδικά, Βρεφικά, Πιπίλες', 'name' => 'Αποστειρωτής Μπιμπερό', 'in_stock' => 1,
        ]));
        // Дрон в eMobility, Photo & Drones -> ДА
        $this->assertTrue($this->gatekeeper->shouldImport('public', [
            'sku' => '469-300', 'category_top' => 'eMobility, Photo & Drones', 'category_path' => 'eMobility, Photo & Drones, Drones & Τηλεκατευθυνόμενα', 'name' => 'DJI Mini 4 Pro', 'in_stock' => 1,
        ]));
        // Планшет в Tablets & Αξεσουάρ -> ДА
        $this->assertTrue($this->gatekeeper->shouldImport('public', [
            'sku' => '469-301', 'category_top' => 'Tablets & Αξεσουάρ', 'category_path' => 'Tablets & Αξεσουάρ, Tablets', 'name' => 'Apple iPad Mini 256GB', 'in_stock' => 1,
        ]));
        // Смартфон -> ДА
        $this->assertTrue($this->gatekeeper->shouldImport('public', [
            'sku' => '469-302', 'category_top' => 'Τηλεφωνία, Tablets & Wearables', 'category_path' => 'Τηλεφωνία, Tablets & Wearables, Smartphones', 'name' => 'Samsung Galaxy S25', 'in_stock' => 1,
        ]));
        // Слово "setups" не должно резаться коротким блеклистом "ups"
        $this->assertTrue($this->gatekeeper->shouldImport('public', [
            'sku' => '469-303', 'category_top' => 'Gaming', 'category_path' => 'Gaming, PC Gaming', 'name' => 'RGB Kit for Gaming Setups', 'in_stock' => 1,
        ]));
    }

    public function test_existing_fashion_rules_remain_intact(): void
    {
        $this->assertTrue($this->gatekeeper->shouldImport('modivo', [
            'brand' => 'Guess', 'category_top' => 'Τσάντες', 'category_path' => 'Γυναικείες, Τσάντες', 'name' => 'Τσάντα Guess', 'in_stock' => 1,
        ]));
        $this->assertFalse($this->gatekeeper->shouldImport('modivo', [
            'brand' => 'Adidas', 'category_top' => 'Ρούχα', 'category_path' => 'Ρούχα, Φούτερ', 'name' => 'Adidas Hoodie', 'in_stock' => 1,
        ]));
        $this->assertFalse($this->gatekeeper->shouldImport('modivo', [
            'brand' => 'Guess', 'category_top' => 'Παπούτσια', 'category_path' => 'Παπούτσια, Sneakers', 'name' => 'Guess Sneakers', 'in_stock' => 1,
        ]));
    }

    public function test_ean_checksum_validation_and_toy_set_extraction(): void
    {
        // Валидный EAN-13 (контрольная цифра верна)
        $this->assertSame('5702017424736', AwinFeedImporter::extractEan('', '5702017424736'));
        // Валидный UPC-12 -> дополняется ведущим 0 до EAN-13
        $this->assertSame('0840685105269', AwinFeedImporter::extractEan('840685105269'));
        // Невалидная контрольная цифра -> отклоняется
        $this->assertNull(AwinFeedImporter::extractEan('5702017424730'));
        // Мусорный SKU -> отклоняется
        $this->assertNull(AwinFeedImporter::extractEan('469-MRK123456'));

        $this->assertSame('lego:42154', FeedDeduplicator::extractToySetKey('LEGO', 'LEGO Technic 42154 Ford GT'));
        $this->assertSame('playmobil:71234', FeedDeduplicator::extractToySetKey('Playmobil', 'Playmobil City Action 71234'));
        $this->assertNull(FeedDeduplicator::extractToySetKey('Adidas', 'Adidas Running 42154'));
    }
}