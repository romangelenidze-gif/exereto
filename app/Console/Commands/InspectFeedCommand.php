<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InspectFeedCommand extends Command
{
    protected $signature = 'feeds:inspect
        {url : Прямой URL фида или ключ из config/feeds.php}
        {--rows=300 : Сколько товаров просканировать для сводки категорий}
        {--kb=2048 : Сколько килобайт скачать с начала файла (по умолчанию 2 МБ)}';

    protected $description = 'Быстрая потоковая разведка структуры XML/CSV/GZ фида без скачивания всего файла';

    public function handle(): int
    {
        $input = trim((string) $this->argument('url'));
        $maxRows = max(10, (int) $this->option('rows'));
        $maxBytes = max(256, (int) $this->option('kb')) * 1024;

        $url = config("feeds.feeds.{$input}.url") ?: $input;

        if (!preg_match('#^https?://#i', $url) && !is_file($url)) {
            $this->error("Укажите валидный URL или ключ фида из config/feeds.php (получено: {$input})");
            return self::FAILURE;
        }

        $this->info("Читаем первые " . round($maxBytes / 1024) . " КБ из фида «{$input}»...");

        $tmp = tempnam(sys_get_temp_dir(), 'inspect_head_');
        try {
            $downloaded = $this->downloadHead($url, $tmp, $maxBytes);
            if ($downloaded === 0) {
                $this->error('Не удалось получить данные с сервера.');
                return self::FAILURE;
            }

            // Проверяем GZIP по сигнатуре \x1f\x8b и распаковываем доступный кусок
            $magic = (string) @file_get_contents($tmp, false, null, 0, 2);
            $isGzip = ($magic === "\x1f\x8b");

            $rawText = '';
            if ($isGzip) {
                $gz = @gzopen($tmp, 'rb');
                if ($gz) {
                    while (!gzeof($gz) && strlen($rawText) < $maxBytes * 4) {
                        $chunk = @gzread($gz, 65536);
                        if ($chunk === false || $chunk === '') {
                            break;
                        }
                        $rawText .= $chunk;
                    }
                    @gzclose($gz);
                }
            } else {
                $rawText = (string) @file_get_contents($tmp);
            }

            $trimmedStart = ltrim($rawText, "\xEF\xBB\xBF \t\r\n");
            if ($trimmedStart === '') {
                $this->error('Получен пустой ответ от сервера.');
                return self::FAILURE;
            }

            $isXml = str_starts_with($trimmedStart, '<');

            $this->newLine();
            $this->info('=== ФОРМАТ ФИДА ===');
            $this->line('Скачано для теста : ' . round($downloaded / 1024) . ' КБ');
            $this->line('Сжатие GZIP       : ' . ($isGzip ? 'Да' : 'Нет'));
            $this->line('Тип документа     : ' . ($isXml ? 'XML' : 'CSV / Текст'));

            if ($isXml) {
                return $this->inspectXml($trimmedStart, $maxRows);
            }

            return $this->inspectCsv($trimmedStart, $maxRows);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Скачивает только первые $maxBytes байт по HTTP и сразу обрывает соединение.
     */
    private function downloadHead(string $source, string $destPath, int $maxBytes): int
    {
        if (!preg_match('#^https?://#i', $source)) {
            $data = (string) @file_get_contents($source, false, null, 0, $maxBytes);
            file_put_contents($destPath, $data);
            return strlen($data);
        }

        $fp = fopen($destPath, 'wb');
        $bytesWritten = 0;

        $ch = curl_init($source);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ExeretoFeedInspector/1.0)',
            CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use ($fp, &$bytesWritten, $maxBytes): int {
                $remaining = $maxBytes - $bytesWritten;
                if ($remaining <= 0) {
                    return 0; // Прерываем скачивание (CURLE_WRITE_ERROR), так как лимит набран
                }
                $slice = strlen($chunk) > $remaining ? substr($chunk, 0, $remaining) : $chunk;
                fwrite($fp, $slice);
                $bytesWritten += strlen($slice);

                return strlen($slice);
            },
        ]);

        curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($httpCode >= 400) {
            $this->error("Сервер вернул HTTP {$httpCode}");
            return 0;
        }

        return $bytesWritten;
    }

    private function inspectXml(string $xmlChunk, int $maxRows): int
    {
        // Находим повторяющийся тег карточки товара (<product>, <item>, <entry>, <offer>)
        $itemTag = null;
        foreach (['product', 'item', 'offer', 'entry'] as $candidate) {
            if (preg_match("/<{$candidate}(?:\s[^>]*)?>/i", $xmlChunk)) {
                $itemTag = $candidate;
                break;
            }
        }

        if ($itemTag === null) {
            $this->warn('Не удалось определить тег товара. Первые 400 символов XML:');
            $this->line(mb_substr($xmlChunk, 0, 400));
            return self::FAILURE;
        }

        // Вырезаем все полные закрытые блоки <product>...</product> из скачанного фрагмента
        preg_match_all("/<{$itemTag}(?:\s[^>]*)?>.*?<\/{$itemTag}>/is", $xmlChunk, $matches);
        $blocks = $matches[0] ?? [];

        if ($blocks === []) {
            $this->warn("Найден тег <{$itemTag}>, но один товар весит больше скачанного фрагмента. Запустите с --kb=8192");
            return self::FAILURE;
        }

        $categories = [];
        $brands = [];
        $samples = [];
        $fields = [];
        $scanned = 0;

        libxml_use_internal_errors(true);
        foreach ($blocks as $block) {
            if ($scanned >= $maxRows) {
                break;
            }

            // Убираем пространства имён вроде g:price -> g_price для простого доступа
            $cleanBlock = preg_replace('/<(\/?)([a-z0-9_]+):([a-z0-9_]+)/i', '<$1$2_$3', $block);
            $node = @simplexml_load_string((string) $cleanBlock, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($node === false) {
                continue;
            }

            $r = [];
            foreach ($node->children() as $child) {
                $key = $child->getName();
                $val = trim((string) $child);
                if ($val === '' && $child->count() > 0) {
                    $subVals = [];
                    foreach ($child->children() as $sub) {
                        $subVals[] = trim((string) $sub);
                    }
                    $val = implode(', ', array_filter($subVals));
                }
                $r[$key] = $val;
                $fields[$key] = true;
            }

            $scanned++;
            $cat = $r['category'] ?? $r['merchant_category'] ?? $r['g_product_type'] ?? $r['product_type'] ?? '';
            if ($cat !== '') {
                $categories[$cat] = ($categories[$cat] ?? 0) + 1;
            }

            $brand = $r['brand_name'] ?? $r['brand'] ?? $r['g_brand'] ?? $r['manufacturer'] ?? '';
            if ($brand !== '') {
                $brands[$brand] = ($brands[$brand] ?? 0) + 1;
            }

            if (count($samples) < 3) {
                $samples[] = [
                    'sku' => $r['sku'] ?? $r['product_id'] ?? $r['lw_product_id'] ?? $r['merchant_product_id'] ?? $r['g_id'] ?? '',
                    'model' => $r['model_name'] ?? $r['mpn'] ?? $r['g_mpn'] ?? '',
                    'brand' => $brand,
                    'name' => mb_substr((string) ($r['product_name'] ?? $r['title'] ?? $r['g_title'] ?? ''), 0, 65),
                    'category' => $cat,
                    'size' => $r['size'] ?? $r['g_size'] ?? '',
                    'colour' => $r['colour'] ?? $r['color'] ?? $r['g_color'] ?? '',
                    'price' => $r['price'] ?? $r['search_price'] ?? $r['g_price'] ?? '',
                    'deep_link' => mb_substr((string) ($r['tracking_url'] ?? $r['aw_deep_link'] ?? $r['link'] ?? ''), 0, 110),
                    'merchant_url' => $r['site_url'] ?? $r['merchant_deep_link'] ?? '',
                ];
            }
        }
        libxml_clear_errors();

        $this->line("Тег товара        : <{$itemTag}> (прочитано полных карточек: {$scanned})");
        $this->line('Поля (' . count($fields) . ')         : ' . implode(', ', array_keys($fields)));

        $this->printSummary($categories, $brands, $samples, $scanned);
        return self::SUCCESS;
    }

    private function inspectCsv(string $csvChunk, int $maxRows): int
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csvChunk);
        rewind($stream);

        $firstLine = (string) fgets($stream);
        rewind($stream);

        $delimiters = [',' => 0, ';' => 0, "\t" => 0, '|' => 0];
        foreach (array_keys($delimiters) as $d) {
            $delimiters[$d] = substr_count($firstLine, $d);
        }
        arsort($delimiters);
        $delimiter = (string) array_key_first($delimiters);

        $header = fgetcsv($stream, 0, $delimiter, '"', '');
        if (!is_array($header) || count($header) < 3) {
            $this->error('Не удалось распарсить заголовок CSV.');
            fclose($stream);
            return self::FAILURE;
        }

        $header = array_map('trim', $header);
        $colCount = count($header);
        $this->line('Разделитель       : ' . ($delimiter === "\t" ? 'TAB' : $delimiter));
        $this->line("Колонок ({$colCount})      : " . implode(', ', $header));

        $categories = [];
        $brands = [];
        $samples = [];
        $scanned = 0;

        while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false && $scanned < $maxRows) {
            if ($row === [null] || count($row) < 3) {
                continue;
            }
            $scanned++;
            $row = array_pad(array_slice($row, 0, $colCount), $colCount, '');
            $r = array_combine($header, $row);

            $cat = trim((string) ($r['merchant_category'] ?? $r['category'] ?? $r['product_type'] ?? ''));
            if ($cat !== '') {
                $categories[$cat] = ($categories[$cat] ?? 0) + 1;
            }

            $brand = trim((string) ($r['brand_name'] ?? $r['brand'] ?? ''));
            if ($brand !== '') {
                $brands[$brand] = ($brands[$brand] ?? 0) + 1;
            }

            if (count($samples) < 3) {
                $samples[] = [
                    'sku' => $r['merchant_product_id'] ?? $r['sku'] ?? $r['product_id'] ?? '',
                    'model' => $r['mpn'] ?? $r['model_name'] ?? '',
                    'brand' => $brand,
                    'name' => mb_substr((string) ($r['product_name'] ?? ''), 0, 65),
                    'category' => $cat,
                    'size' => $r['Fashion:size'] ?? $r['size'] ?? '',
                    'colour' => $r['colour'] ?? $r['color'] ?? '',
                    'price' => $r['search_price'] ?? $r['price'] ?? '',
                    'deep_link' => mb_substr((string) ($r['aw_deep_link'] ?? $r['tracking_url'] ?? ''), 0, 110),
                    'merchant_url' => $r['merchant_deep_link'] ?? $r['site_url'] ?? '',
                ];
            }
        }
        fclose($stream);

        $this->printSummary($categories, $brands, $samples, $scanned);
        return self::SUCCESS;
    }

    private function printSummary(array $categories, array $brands, array $samples, int $scanned): void
    {
        arsort($categories);
        arsort($brands);

        $this->newLine();
        $this->info("=== ТОП КАТЕГОРИЙ (из первых {$scanned} товаров) ===");
        foreach (array_slice($categories, 0, 25, true) as $catName => $cnt) {
            $this->line("  [{$cnt}] {$catName}");
        }

        $this->newLine();
        $this->info('=== ТОП БРЕНДОВ ===');
        $this->line('  ' . implode(', ', array_slice(array_keys($brands), 0, 20)));

        $this->newLine();
        $this->info('=== 3 ПРИМЕРА СТРОК ===');
        foreach ($samples as $idx => $s) {
            $this->line('--- Товар #' . ($idx + 1) . ' ---');
            foreach ($s as $k => $v) {
                $this->line(str_pad($k, 14) . ': ' . $v);
            }
        }
    }
}