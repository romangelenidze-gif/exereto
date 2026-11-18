<?php

/**
 * Партнёрские продуктовые фиды (Awin, Linkwise). Каждый фид — отдельный ключ в 'feeds'.
 * Чтобы добавить новый магазин: скопируйте блок 'adidas' или 'notos', поменяйте ключ, URL и алиасы
 * и запустите: php artisan feeds:import <ключ>
 *
 * .env:
 *   AWIN_ADIDAS_FEED_URL="https://productdata.awin.com/datafeed/download/apikey/.../fid/92328/.../compression/gzip/"
 *   LINKWISE_NOTOS_FEED_URL="https://..."
 *   (URL с ключом хранится только в .env и не попадает в логи)
 */
return [

    'feeds' => [

        'adidas' => [
            'enabled' => (bool) env('FEEDS_ADIDAS_ENABLED', true),
            'network' => 'awin',
            'label' => 'Adidas',
            'brand' => 'Adidas',
            'store_name' => 'adidas.gr',
            'url' => env('AWIN_ADIDAS_FEED_URL'),

            // Ваш партнерский баннер Awin
            'banner_image' => 'https://a1.awin1.com/ads/awin/77018/img6370369-cam_eu_pm_display_affiliatebanners_blackfriday_peakholiday_fw25_3000x2000-1763993046910.jpg',

            // Если в запросе (или в разборе ИИ) есть любая из этих подстрок, запрос идёт ТОЛЬКО в фид,
            // а такие же товары вырезаются из выдачи BestPrice.
            'brand_aliases' => ['adidas', 'адидас', 'ადიდას', 'αντιντάς', 'αντίντας'],

            // Модели Adidas, по которым ищут без слова «adidas» (целые слова, регистр не важен).
            'model_keywords' => [
                'samba', 'gazelle', 'superstar', 'stan smith', 'campus', 'spezial', 'ultraboost',
                'adizero', 'predator', 'adilette', 'terrex', 'ozweego', 'handball spezial',
            ],

            // Слова бренда, которые не должны попадать в условия поиска по фиду (весь фид и так Adidas).
            'strip_words' => ['adidas', 'originals', 'αντιντάς', 'адидас'],

            // Английская версия страницы магазина по кнопке «Купить». По умолчанию выключена: фид отдаёт только греческие
            // ссылки, а как выглядит адрес английской версии adidas.gr, нужно проверить в браузере.
            // Если, например, греческая страница https://www.adidas.gr/<slug>/KI5764.html, а английская
            // https://www.adidas.gr/en/<slug>/KI5764.html, пропишите в .env:
            //   FEEDS_ADIDAS_EN_FIND=https://www.adidas.gr/
            //   FEEDS_ADIDAS_EN_REPLACE=https://www.adidas.gr/en/
            'english_url' => [
                'find' => env('FEEDS_ADIDAS_EN_FIND'),
                'replace' => env('FEEDS_ADIDAS_EN_REPLACE'),
            ],
        ],

        'notos' => [
            'enabled' => (bool) env('FEEDS_NOTOS_ENABLED', true),
            'network' => 'linkwise',
            'label' => 'Notos',
            'brand' => null,
            'store_name' => 'notos.gr',
            'url' => env('LINKWISE_NOTOS_FEED_URL'),
            'brand_aliases' => [],
            'model_keywords' => [],
            'strip_words' => [],
            'english_url' => [
                'find' => env('FEEDS_NOTOS_EN_FIND'),
                'replace' => env('FEEDS_NOTOS_EN_REPLACE'),
            ],
        ],

    'modivo' => [
            'enabled' => (bool) env('FEEDS_MODIVO_ENABLED', true),
            'network' => env('FEEDS_MODIVO_NETWORK', 'linkwise'),
            'label' => 'Modivo',
            'brand' => null,
            'store_name' => 'modivo.gr',
            'url' => env('FEED_MODIVO_URL'),
            'brand_aliases' => [],
            'model_keywords' => [],
            'strip_words' => [],
        ],

        'epapoutsia' => [
            'enabled' => (bool) env('FEEDS_EPAPOUTSIA_ENABLED', true),
            'network' => env('FEEDS_EPAPOUTSIA_NETWORK', 'linkwise'),
            'label' => 'Epapoutsia',
            'brand' => null,
            'store_name' => 'epapoutsia.gr',
            'url' => env('FEED_EPAPOUTSIA_URL'),
            'brand_aliases' => [],
            'model_keywords' => [],
            'strip_words' => [],
        ],

        'stradivarius' => [
            'enabled' => (bool) env('FEEDS_STRADIVARIUS_ENABLED', true),
            'network' => env('FEEDS_STRADIVARIUS_NETWORK', 'linkwise'),
            'label' => 'Stradivarius',
            'brand' => 'Stradivarius',
            'store_name' => 'stradivarius.com',
            'url' => env('FEED_STRADIVARIUS_URL'),
            'brand_aliases' => ['stradivarius', 'страдивариус'],
            'model_keywords' => [],
            'strip_words' => ['stradivarius'],
        ],

        'sephora' => [
            'enabled' => (bool) env('FEEDS_SEPHORA_ENABLED', true),
            'network' => env('FEEDS_SEPHORA_NETWORK', 'linkwise'),
            'label' => 'Sephora',
            'brand' => null,
            'store_name' => 'sephora.gr',
            'url' => env('FEED_SEPHORA_URL'),
            'brand_aliases' => ['sephora', 'сефора'],
            'model_keywords' => [],
            'strip_words' => [],
        ],

        'galeriedebeaute' => [
            'enabled' => (bool) env('FEEDS_GALERIEDEBEAUTE_ENABLED', true),
            'network' => env('FEEDS_GALERIEDEBEAUTE_NETWORK', 'linkwise'),
            'label' => 'Galerie de Beauté',
            'brand' => null,
            'store_name' => 'galeriedebeaute.gr',
            'url' => env('FEED_GALERIEDEBEAUTE_URL'),
            'brand_aliases' => [],
            'model_keywords' => [],
            'strip_words' => [],
        ],

        'pharm24' => [
            'enabled' => (bool) env('FEEDS_PHARM24_ENABLED', true),
            'network' => env('FEEDS_PHARM24_NETWORK', 'linkwise'),
            'label' => 'Pharm24',
            'brand' => null,
            'store_name' => 'pharm24.gr',
            'url' => env('FEED_PHARM24_URL'),
            'brand_aliases' => [],
            'model_keywords' => [],
            'strip_words' => [],
        ],

        'cosselie' => [
            'enabled' => (bool) env('FEEDS_COSSELIE_ENABLED', true),
            'network' => env('FEEDS_COSSELIE_NETWORK', 'linkwise'),
            'label' => 'Cosselie',
            'brand' => 'Cosselie',
            'store_name' => 'cosselie.com',
            'url' => env('FEED_COSSELIE_URL'),
            'brand_aliases' => ['cosselie'],
            'model_keywords' => [],
            'strip_words' => ['cosselie'],
        ],

        'energiers' => [
            'enabled' => (bool) env('FEEDS_ENERGIERS_ENABLED', true),
            'network' => env('FEEDS_ENERGIERS_NETWORK', 'linkwise'),
            'label' => 'Energiers',
            'brand' => 'Energiers',
            'store_name' => 'energiers.gr',
            'url' => env('FEED_ENERGIERS_URL'),
            'brand_aliases' => ['energiers'],
            'model_keywords' => [],
            'strip_words' => ['energiers'],
        ],
    
    'toysgr' => [
            'enabled' => (bool) env('FEEDS_TOYSGR_ENABLED', true),
            'network' => env('FEEDS_TOYSGR_NETWORK', 'linkwise'),
            'label' => 'Toys.gr',
            'brand' => null,
            'store_name' => 'toys.gr',
            'url' => env('FEED_TOYSGR_URL'),
            'brand_aliases' => [],
            'model_keywords' => [],
            'strip_words' => [],
            'cascade_priority' => 1, // Наивысший приоритет (оставляем этот дубль)
            'blacklist_keywords' => [
                // Исключаем греческие настолки и говорящие игрушки
                'επιτραπέζια', 'επιτραπέζιο', 'βιβλία', 'κόμικς', 'μιλάει ελληνικά', 'στα ελληνικά', 'ελληνική έκδοση'
            ],
        ],

        'moustakas' => [
            'enabled' => (bool) env('FEEDS_MOUSTAKAS_ENABLED', true),
            'network' => env('FEEDS_MOUSTAKAS_NETWORK', 'linkwise'),
            'label' => 'Moustakas Toys',
            'brand' => null,
            'store_name' => 'moustakastoys.gr',
            'url' => env('FEED_MOUSTAKAS_URL'),
            'brand_aliases' => [],
            'model_keywords' => [],
            'strip_words' => [],
            'cascade_priority' => 2, // Средний приоритет
            'blacklist_keywords' => [
                'επιτραπέζια', 'επιτραπέζιο', 'βιβλία', 'κόμικς', 'μιλάει ελληνικά', 'στα ελληνικά', 'ελληνική έκδοση'
            ],
        ],

        'public' => [
            'enabled' => (bool) env('FEEDS_PUBLIC_ENABLED', true),
            'network' => env('FEEDS_PUBLIC_NETWORK', 'linkwise'),
            'label' => 'Public',
            'brand' => null,
            'store_name' => 'public.gr',
            'url' => env('FEED_PUBLIC_URL'),
            'brand_aliases' => ['public'],
            'model_keywords' => [],
            'strip_words' => ['public'],
            'cascade_priority' => 3, // Низший приоритет для игрушек (отдаем трафик специализированным)
            
            // Жёсткий тематический фильтр (пропускаем только эти ветки)
            'whitelist_categories' => [
                'apple', 'iphone', 'ipad', 'macbook', 'airpods', 'smartwatch', 'smartwatches', // Экосистема и мобильные
                '3d εκτυπωτές', 'νήματα 3d', 'ρητίνες', 'filaments', // 3D-печать
                'laptops', 'desktops', 'σταθεροί υπολογιστές', 'hardware', 'κάρτες γραφικών', 'επεξεργαστές', 'μητρικές', 'μνήμες ram', 'σκληροί δίσκοι', 'ssd', 'usb flash', 'κάρτες μνήμης', 'τροφοδοτικά', // PC Hardware
                'περιφερειακά', 'πληκτρολόγια', 'ποντίκια', 'web κάμερες', 'μικρόφωνα', 'γραφίδες', 'docking stations', 'καλώδια', 'powerbanks', // Периферия
                'gaming', 'κονσόλες', 'playstation', 'xbox', 'nintendo', 'steam deck', 'gamepads', 'vr', 'virtual reality', // Гейминг
                'ακουστικά', 'ηχεία', 'φωτογραφικές μηχανές', 'φακοί', 'action cameras', 'drones', 'gopro', 'dji', // Аудио/Фото/Видео
                'σχολικές τσάντες', 'σακίδια', 'τσάντες τρόλεϊ', 'κασετίνες', // Школьные рюкзаки
                'παιχνίδια', 'lego', 'funko', 'playmobil', 'παζλ' // Игрушки (настолки отсекаются блеклистом)
            ],
            
            // Фильтр от скрытого негабарита и мусора внутри разрешенных категорий
            'blacklist_keywords' => [
                'τηλεοράσεις', 'οικιακές συσκευές', 'κλιματισμός', 'έπιπλα', 'καρέκλες', 'γραφεία', // ТВ, бытовуха, мебель
                'πολυμηχανήματα', 'laser', 'inkjet', 'οθόνες υπολογιστή', 'ups', // Тяжелая периферия
                'χαρτικά', 'γραφική ύλη', 'τετράδια', 'στυλό', 'μολύβια', // Мелкая канцелярия
                'επιτραπέζια', 'επιτραπέζιο', 'βιβλία', 'κόμικς', 'μιλάει ελληνικά', 'στα ελληνικά', 'ελληνική έκδοση' // Греческий язык
            ],
        ],
    
    
    ],

        

    // Сколько моделей (карточек) отдавать в выдачу из фида.
    'search_limit' => (int) env('FEEDS_SEARCH_LIMIT', 20),

    // Защита от битого фида: если из него пришло меньше этой доли от текущих активных товаров,
    // отсутствующие товары НЕ деактивируются.
    'min_import_ratio' => 0.5,

    // Слова, которые ничего не сужают в поиске по каталогу спортивного бренда.
    'stop_words' => ['for', 'the', 'and', 'with', 'of', 'in', 'a', 'an', 'για', 'και', 'με', 'σε', 'το', 'τα', 'η', 'ο', 'του', 'των', 'new'],
    'stop_prefixes' => ['αθλητικ', 'sport'],

    // Фразы, которые заменяются целиком до разбора на слова. «Αθλητικά παπούτσια» в греческих магазинах это кроссовки,
    // а в этом фиде они лежат в категории Sneakers (иначе запрос «кроссовки» вернул бы и гольф, и бутсы).
    'phrases' => [
        'αθλητικά παπούτσια' => 'sneakers',
        'αθλητικό παπούτσι' => 'sneakers',
    ],

    // Синонимы для поиска: слово запроса -> другие слова, которые тоже считаются совпадением (логика ИЛИ).
    // Нужны, потому что в фиде названия смешаны: греческие («Παπούτσια»), английские («Sneakers», «Jackets»),
    // а цвета только английские. Ключи и значения пишутся обычными словами, форма нормализуется кодом.
    // Пополняйте по журналу storage/logs/search-*.log, когда видите запросы без результатов.
    'synonyms' => [
        // обувь
        'shoes' => ['παπούτσια'],
        'trainers' => ['sneakers', 'παπούτσια'],
        'running' => ['τρέξιμο'],
        'football' => ['ποδοσφαίρου'],
        'boots' => ['παπούτσια', 'μπότες'],
        'slides' => ['παντόφλες', 'σαγιονάρες'],
        'sandals' => ['παντόφλες', 'σαγιονάρες'],
        // одежда
        'shirt' => ['μπλούζα', 'φανέλα'],
        'tee' => ['μπλούζα', 'φανέλα'],
        'jersey' => ['φανέλα'],
        'shorts' => ['σορτς'],
        'jacket' => ['μπουφάν', 'ζακέτα'],
        'hoodie' => ['φούτερ'],
        'sweatshirt' => ['φούτερ'],
        'pants' => ['παντελόνι'],
        'trousers' => ['παντελόνι'],
        'tracksuit' => ['φόρμα'],
        'leggings' => ['κολάν'],
        'swimsuit' => ['μαγιό'],
        'dress' => ['φόρεμα', 'φούστα'],
        // аксессуары
        'socks' => ['κάλτσες'],
        'bag' => ['τσάντα', 'σακίδιο'],
        'backpack' => ['σακίδιο', 'τσάντα'],
        'cap' => ['καπέλο', 'τζόκεϊ'],
        'hat' => ['καπέλο'],
        // греческие запросы -> английские слова в названиях и категориях
        'μπουφάν' => ['jacket'],
        'φούτερ' => ['hoodie', 'sweat'],
        // цвета: в фиде они только английские
        'λευκό' => ['white'],
        'μαύρο' => ['black'],
        'κόκκινο' => ['red'],
        'μπλε' => ['blue', 'navy'],
        'πράσινο' => ['green'],
        'γκρι' => ['grey', 'gray'],
        'ροζ' => ['pink'],
        'κίτρινο' => ['yellow'],
        'πορτοκαλί' => ['orange'],
        'μωβ' => ['purple'],
        'καφέ' => ['brown'],
        'μπεζ' => ['beige'],
    ],
];