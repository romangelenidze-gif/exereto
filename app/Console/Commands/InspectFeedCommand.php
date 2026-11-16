<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class InspectFeedCommand extends Command
{
    protected $signature = 'feeds:inspect
        {url : Прямой URL фида или ключ из config/feeds.php}
        {--rows=300 : Сколько строк просканировать для сводки категорий}';

    protected $description = 'Безопасная разведка структуры нового CSV/GZ фида без записи в БД';

    public function handle(): int
    {
        $input = trim((string) $this->argument('url'));
        $maxRows = max(20, (int) $this->option('rows'));

        // Можно передать либо ключ из config/feeds.php, либо сразу URL
        $url = config("feeds.feeds.{$input}.url") ?: $input;

        if (!preg_match('#^https?://#i', $url) && !is_file($url)) {
            $this->error("Укажите валидный URL или ключ фида из config/feeds.php (получено: {$input})");
            return self::FAILURE;
        }

        $this->info("Скачиваем первые данные из: {$url} ...");

        $tmp = tempnam(sys_get_temp_dir(), 'inspect_feed_');
        try {
            if (preg_match('#^https?://#i', $url)) {
                Http::timeout(120)->withOptions(['sink' => $tmp])->get($url)->throw();
            } else {
                copy($url, $tmp);
            }

            // Проверяем, gzip это или обычный файл
            $magic = file_get_contents($tmp, false, null, 0, 2);
            $isGzip = ($magic === "\x1f\x8b");
            $streamPath = $isGzip ? ('compress.zlib://' . $tmp) : $tmp;

            $handle = @fopen($streamPath, 'r');
            if (!$handle) {
                $this->error('Не удалось открыть поток чтения.');
                return self::FAILURE;
            }

            $firstLine = (string) fgets($handle);
            rewind($handle);

            // Автоопределение разделителя (, ; \t |)
            $delimiters = [',' => 0, ';' => 0, "\t" => 0, '|' => 0];
            foreach (array_keys($delimiters) as $d) {
                $delimiters[$d] = substr_count($firstLine, $d);
            }
            arsort($delimiters);
            $delimiter = (string) array_key_first($delimiters);
            $delimLabel = $delimiter === "\t" ? 'TAB (\t)' : $delimiter;

            $header = fgetcsv($handle, 0, $delimiter, '"', '');
            if (!is_array($header) || count($header) < 3) {
                $this->error('Не удалось распарсить заголовок CSV. Первая строка: ' . mb_substr($firstLine, 0, 200));
                fclose($handle);
                return self::FAILURE;
            }

            $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");
            $header = array_map('trim', $header);
            $colCount = count($header);

            $network = in_array('aw_deep_link', $header, true)
                ? 'awin'
                : (in_array('tracking_url', $header, true) ? 'linkwise' : 'unknown');

            $this->newLine();
            $this->info("=== ФОРМАТ ФИДА ===");
            $this->line("Сжатие GZIP : " . ($isGzip ? 'Да' : 'Нет (обычный текст)'));
            $this->line("Разделитель : {$delimLabel}");
            $this->line("Сеть        : {$network}");
            $this->line("Колонок ({$colCount}): " . implode(', ', $header));

            $categories = [];
            $brands = [];
            $samples = [];
            $scanned = 0;

            while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false && $scanned < $maxRows) {
                if ($row === [null]) {
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
                        'name' => mb_substr((string) ($r['product_name'] ?? ''), 0, 60),
                        'category' => $cat,
                        'size' => $r['Fashion:size'] ?? $r['size'] ?? '',
                        'colour' => $r['colour'] ?? $r['color'] ?? '',
                        'price' => $r['search_price'] ?? $r['price'] ?? '',
                        'deep_link' => mb_substr((string) ($r['aw_deep_link'] ?? $r['tracking_url'] ?? ''), 0, 110),
                        'merchant_url' => $r['merchant_deep_link'] ?? $r['site_url'] ?? '',
                    ];
                }
            }
            fclose($handle);

            arsort($categories);
            arsort($brands);

            $this->newLine();
            $this->info("=== ТОП КАТЕГОРИЙ (из первых {$scanned} товаров) ===");
            foreach (array_slice($categories, 0, 25, true) as $catName => $cnt) {
                $this->line("  [{$cnt}] {$catName}");
            }

            $this->newLine();
            $this->info("=== ТОП БРЕНДОВ ===");
            $this->line('  ' . implode(', ', array_slice(array_keys($brands), 0, 20)));

            $this->newLine();
            $this->info("=== 3 ПРИМЕРА СТРОК ===");
            foreach ($samples as $idx => $s) {
                $this->line("--- Товар #" . ($idx + 1) . " ---");
                foreach ($s as $k => $v) {
                    $this->line(str_pad($k, 14) . ": " . $v);
                }
            }

            return self::SUCCESS;
        } finally {
            @unlink($tmp);
        }
    }
}