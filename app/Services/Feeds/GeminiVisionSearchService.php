<?php

namespace App\Services\Feeds;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiVisionSearchService
{
    private const CACHE_TTL_DAYS = 14;

    public function isAvailable(): bool
    {
        return (bool) config('services.gemini.vision_enabled', true)
            && (filled(config('services.gemini.vision_api_key')) || filled(config('services.groq.api_key')));
    }

    /**
     * Анализирует изображение (Gemini Vision -> автоматический фоллбек на Groq Vision)
     * и возвращает структурированный запрос для каталога.
     *
     * @return array{
     *   status: 'ok'|'not_product'|'quota_exceeded'|'error',
     *   brand: ?string,
     *   category_slug: ?string,
     *   gender: ?string,
     *   color: ?string,
     *   model_query: string,
     *   item_label: string,
     *   provider: ?string,
     *   cached: bool
     * }
     */
    public function analyze(string $imageBytes, string $mimeType, string $locale = 'en'): array
    {
        $hash = hash('sha256', $imageBytes);
        $cacheKey = "vision:photo_search:v2:{$locale}:{$hash}";

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && !empty($cached['status'])) {
            $cached['cached'] = true;

            return $cached;
        }

        if (!$this->reserveDailyBudget()) {
            return $this->emptyResult('quota_exceeded');
        }

        $allowedSlugs = $this->getAllowedCategorySlugs();
        $prompt = $this->buildPrompt($allowedSlugs, $locale);
        $base64 = base64_encode($imageBytes);
        $safeMime = in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)
            ? $mimeType
            : 'image/jpeg';

        $errors = [];
        $hitRateLimit = false;

        // 1. Попытка через Gemini Vision (только быстрые lite-модели с коротким таймаутом)
        $geminiKey = (string) config('services.gemini.vision_api_key', '');
        if ($geminiKey !== '') {
            foreach ($this->candidateGeminiModels() as $model) {
                $attempt = $this->callGemini($geminiKey, $model, $prompt, $safeMime, $base64, $allowedSlugs);
                if ($attempt['parsed'] !== null) {
                    $parsed = $attempt['parsed'];
                    $parsed['provider'] = "gemini:{$model}";
                    Cache::put($cacheKey, $parsed, now()->addDays(self::CACHE_TTL_DAYS));

                    return $parsed;
                }

                if ($attempt['rate_limited']) {
                    $hitRateLimit = true;
                }
                if ($attempt['error'] !== null) {
                    $errors[] = $attempt['error'];
                }
            }
        }

        // 2. Автоматический фоллбек на Groq Vision (qwen/qwen3.8-27b)
        $groqKey = (string) config('services.groq.api_key', '');
        if ($groqKey !== '') {
            $groqModel = (string) config('services.groq.vision_model', 'qwen/qwen3.8-27b');
            $attempt = $this->callGroq($groqKey, $groqModel, $prompt, $safeMime, $base64, $allowedSlugs);
            if ($attempt['parsed'] !== null) {
                $parsed = $attempt['parsed'];
                $parsed['provider'] = "groq:{$groqModel}";
                Cache::put($cacheKey, $parsed, now()->addDays(self::CACHE_TTL_DAYS));

                return $parsed;
            }

            if ($attempt['rate_limited']) {
                $hitRateLimit = true;
            }
            if ($attempt['error'] !== null) {
                $errors[] = $attempt['error'];
            }
        }

        Log::warning('GeminiVisionSearchService failed across all providers', [
            'errors' => $errors,
            'rate_limited' => $hitRateLimit,
        ]);

        return $this->emptyResult($hitRateLimit ? 'quota_exceeded' : 'error');
    }

    /**
     * @param  string[]  $allowedSlugs
     * @return array{parsed: ?array<string, mixed>, rate_limited: bool, error: ?string}
     */
    private function callGemini(
        string $apiKey,
        string $model,
        string $prompt,
        string $safeMime,
        string $base64,
        array $allowedSlugs
    ): array {
        try {
            $url = sprintf(
                'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
                rawurlencode($model),
                rawurlencode($apiKey)
            );

            $response = Http::connectTimeout(4)
                ->timeout(10)
                ->acceptJson()
                ->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                                [
                                    'inline_data' => [
                                        'mime_type' => $safeMime,
                                        'data' => $base64,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'maxOutputTokens' => 512,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if ($response->status() === 429) {
                return ['parsed' => null, 'rate_limited' => true, 'error' => "gemini:{$model} HTTP 429"];
            }

            if (!$response->successful()) {
                return [
                    'parsed' => null,
                    'rate_limited' => false,
                    'error' => "gemini:{$model} HTTP {$response->status()}: " . mb_substr((string) $response->body(), 0, 160),
                ];
            }

            $parts = $response->json('candidates.0.content.parts', []);
            if (!is_array($parts) || $parts === []) {
                return ['parsed' => null, 'rate_limited' => false, 'error' => "gemini:{$model} empty parts"];
            }

            $text = '';
            foreach ($parts as $part) {
                if (!is_array($part) || !empty($part['thought'])) {
                    continue;
                }
                if (isset($part['text']) && is_string($part['text'])) {
                    $text .= $part['text'];
                }
            }

            $parsed = $this->parseJsonText($text, $allowedSlugs);
            if ($parsed === null) {
                return ['parsed' => null, 'rate_limited' => false, 'error' => "gemini:{$model} invalid JSON"];
            }

            return ['parsed' => $parsed, 'rate_limited' => false, 'error' => null];
        } catch (\Throwable $e) {
            return ['parsed' => null, 'rate_limited' => false, 'error' => "gemini:{$model}: {$e->getMessage()}"];
        }
    }

    /**
     * @param  string[]  $allowedSlugs
     * @return array{parsed: ?array<string, mixed>, rate_limited: bool, error: ?string}
     */
    private function callGroq(
        string $apiKey,
        string $model,
        string $prompt,
        string $safeMime,
        string $base64,
        array $allowedSlugs
    ): array {
        try {
            $response = Http::connectTimeout(5)
                ->timeout(15)
                ->acceptJson()
                ->withToken($apiKey)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.1,
                    'max_completion_tokens' => 600,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $prompt],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => "data:{$safeMime};base64,{$base64}",
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

            if ($response->status() === 429) {
                return ['parsed' => null, 'rate_limited' => true, 'error' => "groq:{$model} HTTP 429"];
            }

            if (!$response->successful()) {
                return [
                    'parsed' => null,
                    'rate_limited' => false,
                    'error' => "groq:{$model} HTTP {$response->status()}: " . mb_substr((string) $response->body(), 0, 160),
                ];
            }

            $text = (string) $response->json('choices.0.message.content', '');
            $parsed = $this->parseJsonText($text, $allowedSlugs);
            if ($parsed === null) {
                return ['parsed' => null, 'rate_limited' => false, 'error' => "groq:{$model} invalid JSON"];
            }

            return ['parsed' => $parsed, 'rate_limited' => false, 'error' => null];
        } catch (\Throwable $e) {
            return ['parsed' => null, 'rate_limited' => false, 'error' => "groq:{$model}: {$e->getMessage()}"];
        }
    }

    /**
     * @return string[]
     */
    private function candidateGeminiModels(): array
    {
        $configured = trim((string) config('services.gemini.vision_model', ''));
        $defaults = [
            'gemini-3.1-flash-lite',
            'gemini-2.5-flash-lite',
            'gemini-flash-lite-latest',
            'gemini-3.5-flash-lite',
        ];

        $raw = array_merge(
            $configured !== '' && $configured !== 'gemini-3.5-flash-lite' ? [$configured] : [],
            $defaults
        );

        return array_values(array_unique(array_filter(array_map('trim', $raw))));
    }

    /**
     * @return string[]
     */
    private function getAllowedCategorySlugs(): array
    {
        return Cache::remember('vision:allowed_category_slugs:v1', now()->addHours(24), function (): array {
            return DB::table('categories')
                ->where('is_active', 1)
                ->orderBy('parent_id')
                ->orderBy('sort_order')
                ->pluck('slug')
                ->filter(fn ($s) => is_string($s) && trim($s) !== '')
                ->values()
                ->all();
        });
    }

    /**
     * @param  string[]  $allowedSlugs
     */
    private function buildPrompt(array $allowedSlugs, string $locale): string
    {
        $langName = match ($locale) {
            'ru' => 'Russian',
            'ka' => 'Georgian',
            default => 'English',
        };

        $slugList = implode(', ', $allowedSlugs);

        return <<<PROMPT
You are an e-commerce visual product recognition engine for a fashion, footwear, accessories, beauty, toys, and consumer electronics store.
Identify the main shoppable product in the image and return ONLY a valid JSON object (no markdown, no explanation) with these exact keys:
{
  "is_product": boolean,
  "brand": string or null,
  "category_slug": string or null,
  "gender": "men" | "women" | "kids" | "unisex" | null,
  "color": string or null,
  "model_query": string,
  "item_label": string
}

Rules:
1. "is_product": false ONLY if the image contains no shoppable product (e.g. blank screen, meme, document, landscape, blurry unrecognizable image).
2. "brand": recognizable brand name in official spelling (e.g. "adidas", "Nike", "New Balance", "Guess", "Tommy Hilfiger", "Calvin Klein", "Apple", "Samsung", "LEGO", "Sony", "Puma", "Vans", "BOSS"). Set to null if no brand/logo/signature model is clearly identifiable — never hallucinate a brand.
3. "category_slug": choose the SINGLE most specific matching slug strictly from this list: [{$slugList}]. If none fit, return null.
4. "gender": "men", "women", "kids", "unisex", or null (use null for electronics, toys, or neutral accessories).
5. "color": main dominant color as one standard English word (Black, White, Blue, Grey, Beige, Brown, Red, Green, Pink, Purple, Yellow, Orange, Gold, Silver, Navy, Multicolor) or null.
6. "model_query": concise English catalog search query (2 to 4 words max) with brand + exact model/type if known (e.g. "adidas samba", "apple ipad air", "nike air force 1", "leather crossbody bag", "puffer jacket"). No filler words like "photo", "stylish", "modern", "men's".
7. "item_label": concise human-readable product description in {$langName} (2 to 5 words) for showing to the user.
PROMPT;
    }

    /**
     * @param  string[]  $allowedSlugs
     * @return array<string, mixed>|null
     */
    private function parseJsonText(string $text, array $allowedSlugs): ?array
    {
        // Убираем возможные блоки размышлений <think>...</think> у Qwen
        $text = preg_replace('/<think>.*?<\/think>/siu', '', $text) ?? $text;
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        $text = preg_replace('/^```(?:json)?\s*|\s*```$/iu', '', $text) ?? $text;
        $data = json_decode(trim($text), true);

        if (!is_array($data) && preg_match('/\{.*\}/su', $text, $m)) {
            $data = json_decode($m[0], true);
        }

        if (!is_array($data)) {
            return null;
        }

        if (isset($data['is_product']) && $data['is_product'] === false) {
            return $this->emptyResult('not_product');
        }

        $slugSet = array_fill_keys($allowedSlugs, true);
        $rawSlug = isset($data['category_slug']) && is_string($data['category_slug'])
            ? trim(strtolower($data['category_slug']))
            : null;
        $categorySlug = ($rawSlug !== null && isset($slugSet[$rawSlug])) ? $rawSlug : null;

        $rawBrand = isset($data['brand']) && is_string($data['brand']) ? trim($data['brand']) : null;
        if ($rawBrand !== null && in_array(mb_strtolower($rawBrand), ['', 'null', 'none', 'unknown', 'unbranded', 'generic'], true)) {
            $rawBrand = null;
        }

        $rawGender = isset($data['gender']) && is_string($data['gender']) ? mb_strtolower(trim($data['gender'])) : null;
        $gender = in_array($rawGender, ['men', 'women', 'kids', 'unisex'], true) ? $rawGender : null;

        $rawColor = isset($data['color']) && is_string($data['color']) ? trim($data['color']) : null;
        if ($rawColor !== null && in_array(mb_strtolower($rawColor), ['', 'null', 'none', 'unknown'], true)) {
            $rawColor = null;
        }

        $modelQuery = isset($data['model_query']) && is_string($data['model_query'])
            ? trim(preg_replace('/\s+/u', ' ', $data['model_query']) ?? '')
            : '';
        $modelQuery = mb_substr($modelQuery, 0, 100);

        $itemLabel = isset($data['item_label']) && is_string($data['item_label'])
            ? trim($data['item_label'])
            : '';
        if ($itemLabel === '') {
            $itemLabel = $modelQuery !== '' ? $modelQuery : ($rawBrand ?: '');
        }

        if ($rawBrand === null && $categorySlug === null && $modelQuery === '') {
            return $this->emptyResult('not_product');
        }

        return [
            'status' => 'ok',
            'brand' => $rawBrand,
            'category_slug' => $categorySlug,
            'gender' => $gender,
            'color' => $rawColor,
            'model_query' => $modelQuery,
            'item_label' => mb_substr($itemLabel, 0, 120),
            'provider' => null,
            'cached' => false,
        ];
    }

    private function reserveDailyBudget(): bool
    {
        $limit = (int) config('services.gemini.vision_daily_limit', 250);
        if ($limit <= 0) {
            return true;
        }

        $key = 'vision:daily_calls:' . now()->format('Y-m-d');
        $current = (int) Cache::get($key, 0);
        if ($current >= $limit) {
            return false;
        }

        if (!Cache::has($key)) {
            Cache::put($key, 1, now()->addHours(26));
        } else {
            Cache::increment($key);
        }

        return true;
    }

    /**
     * @param  'not_product'|'quota_exceeded'|'error'  $status
     * @return array<string, mixed>
     */
    private function emptyResult(string $status): array
    {
        return [
            'status' => $status,
            'brand' => null,
            'category_slug' => null,
            'gender' => null,
            'color' => null,
            'model_query' => '',
            'item_label' => '',
            'provider' => null,
            'cached' => false,
        ];
    }
}