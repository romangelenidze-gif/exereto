<?php

namespace Tests\Feature;

use App\Models\FeedProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PreLaunchReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_blade_templates_compile_without_syntax_errors(): void
    {
        $viewsPath = resource_path('views');
        $files = File::allFiles($viewsPath);

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            if (!str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            try {
                Blade::compileString(File::get($file->getPathname()));
            } catch (\Throwable $e) {
                $this->fail("Blade compilation failed in {$file->getRelativePathname()}: {$e->getMessage()}");
            }
        }
    }

    public function test_public_pages_never_make_synchronous_external_http_or_llm_requests(): void
    {
        Http::preventStrayRequests();

        FeedProduct::query()->create([
            'feed' => 'adidas',
            'sku' => 'GUARD-001',
            'model_code' => 'GUARD-MOD-001',
            'brand' => 'ADIDAS',
            'name' => 'Μπουφάν GuardRunner',
            'category_top' => 'Ανδρικά',
            'category_path' => 'Ανδρικά, Ρούχα, Μπουφάν',
            'colour' => 'Core Black / Cloud White',
            'gender' => 'men',
            'price' => 89.90,
            'currency' => 'EUR',
            'image_url' => 'https://example.com/guard.jpg',
            'deep_link' => 'https://example.com/out/guard',
            'merchant_url' => 'https://adidas.gr/guard',
            'in_stock' => true,
            'is_active' => true,
            'search_text' => 'μπουφαν guardrunner adidas core black cloud white',
        ]);

        // Главная во всех локалях — без единого внешнего HTTP-запроса
        $this->get('/')->assertOk()->assertSee('lang="en"', false);
        $this->get('/ru')->assertOk()->assertSee('lang="ru"', false);
        $this->get('/ka')->assertOk()->assertSee('lang="ka"', false);

        // Карточка товара во всех локалях (даже с непереведённым греческим текстом!)
        $this->get('/product/adidas/GUARD-001')->assertOk();
        $this->get('/ru/product/adidas/GUARD-001')->assertOk();
        $this->get('/ka/product/adidas/GUARD-001')->assertOk();

        // Поиск и автокомплит — без синхронных вызовов LLM
        $this->get('/product-search?q=GuardRunner')->assertOk();
        $this->get('/ru/product-search?q=GuardRunner')->assertOk();
        $this->getJson('/api/autocomplete?q=Guard')->assertOk();
    }

    public function test_static_info_pages_return_200_in_all_locales(): void
    {
        Http::preventStrayRequests();

        foreach (['', '/ru', '/ka'] as $prefix) {
            foreach (['/how-it-works', '/forwarding', '/help', '/terms', '/privacy'] as $path) {
                $this->get($prefix . $path)->assertOk();
            }
        }
    }
}