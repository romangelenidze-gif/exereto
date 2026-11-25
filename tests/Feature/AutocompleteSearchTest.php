<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class AutocompleteSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            Redis::del('autocomplete_hits_counter');
        } catch (\Throwable $e) {
            // Игнорируем, если Redis локально не запущен
        }
    }

    public function test_autocomplete_endpoint_returns_suggestions(): void
    {
        DB::table('learned_autocomplete')->insert([
            'query' => 'кроссовки',
            'suggestions_json' => json_encode(['Кроссовки Adidas', 'Кроссовки Nike']),
            'hits' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson(route('api.autocomplete', ['q' => 'крос']));

        $response->assertStatus(200)
                 ->assertJsonStructure();
    }

    public function test_autocomplete_is_protected_by_rate_limiting(): void
    {
        // В routes/localized.php стоит throttle:60,1
        for ($i = 0; $i < 60; $i++) {
            $this->getJson(route('api.autocomplete', ['q' => 'тест']));
        }

        $response = $this->getJson(route('api.autocomplete', ['q' => 'тест']));

        $response->assertStatus(429);
    }

    public function test_sync_autocomplete_hits_artisan_command_works(): void
    {
        DB::table('learned_autocomplete')->insert([
            'query' => 'сумка',
            'suggestions_json' => json_encode(['Сумка кожаная']),
            'hits' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            Redis::hset('autocomplete_hits_counter', 'сумка', 5);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis недоступен локально: ' . $e->getMessage());
        }

        $this->artisan('autocomplete:sync-hits')
             ->assertExitCode(0);

        $this->assertDatabaseHas('learned_autocomplete', [
            'query' => 'сумка',
            'hits' => 15,
        ]);
    }
}