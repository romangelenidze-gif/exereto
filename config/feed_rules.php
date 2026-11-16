<?php

return [
    'stores' => [

        // 1. Adidas GR (прямой контракт — приоритет №1 по бренду Adidas)
        'adidas' => [
            'enabled' => true,
            'store_name' => 'Adidas GR',
            'base_priority' => 120,
            'brand_priorities' => [
                'adidas' => 250,
                'adidas originals' => 250,
                'adidas performance' => 250,
                'adidas terrex' => 250,
                'y-3' => 250,
            ],
            'allow_categories' => [],
            'exclude_categories' => [],
            'exclude_brands' => [],
            'url_modifier' => null,
        ],

        // Действующий универмаг Notos
        'notos' => [
            'enabled' => true,
            'store_name' => 'Notos',
            'base_priority' => 55,
            'brand_priorities' => [],
            'allow_categories' => [],
            'exclude_categories' => ['Home', 'Σπίτι'],
            'exclude_brands' => [],
            'url_modifier' => null,
        ],

        // 2. Modivo.gr — одежда, сумки, часы, аксессуары (жёстко исключаем любую обувь)
        'modivo' => [
            'enabled' => true,
            'store_name' => 'Modivo',
            'base_priority' => 70,
            'brand_priorities' => [
                'boss' => 95,
                'hugo' => 95,
                'calvin klein' => 95,
                'tommy hilfiger' => 95,
                'tommy jeans' => 95,
                'guess' => 90,
                'guess jeans' => 90,
                'lauren ralph lauren' => 95,
                'polo ralph lauren' => 95,
                'karl lagerfeld' => 95,
            ],
            'allow_categories' => [
                'Ρούχα', 'Τσάντες', 'Ρολόγια', 'Αξεσουάρ', 'Γυαλιά',
            ],
            'exclude_categories' => [
                'Υποδήματα', 'Παπούτσια', 'Αθλητικά', 'Μποτάκια', 'Μπότες',
                'Σανδάλια', 'Πέδιλα', 'Μπαλαρίνες', 'Παντόφλες', 'Σαγιονάρες',
                'Γόβες', 'Sneakers', 'Boots', 'Shoes',
            ],
            'exclude_brands' => ['adidas', 'adidas originals', 'adidas performance'],
            'url_modifier' => null,
        ],

        // 3. Epapoutsia.gr — главный обувной хаб (отсекаем одежду/сумки/аксессуары, оставляем всю обувь)
        'epapoutsia' => [
            'enabled' => true,
            'store_name' => 'Epapoutsia',
            'base_priority' => 85,
            'brand_priorities' => [
                'new balance' => 180,
                'asics' => 180,
                'puma' => 180,
                'fila' => 180,
                'reebok' => 170,
                'vans' => 170,
                'converse' => 170,
                'skechers' => 170,
                'geox' => 165,
                'clarks' => 165,
                'tamaris' => 165,
                'dr. martens' => 170,
                'ugg' => 170,
                'rieker' => 165,
                'pikolinos' => 165,
            ],
            'allow_categories' => [],
            'exclude_categories' => [
                'Ρούχα', 'Τσάντες', 'Αξεσουάρ', 'Καπέλα', 'Κάλτσες', 'Ζώνες', 'Πορτοφόλια', 'Σακίδια',
            ],
            'exclude_brands' => [],
            'url_modifier' => null,
        ],

        // 4. Stradivarius — женский масс-маркет
        'stradivarius' => [
            'enabled' => true,
            'store_name' => 'Stradivarius',
            'base_priority' => 85,
            'brand_priorities' => [
                'stradivarius' => 200,
            ],
            'allow_categories' => [],
            'exclude_categories' => [],
            'exclude_brands' => [],
            'url_modifier' => null,
        ],

        // 5. Sephora — люксовая парфюмерия и эксклюзивный макияж
        'sephora' => [
            'enabled' => true,
            'store_name' => 'Sephora',
            'base_priority' => 90,
            'brand_priorities' => [
                'sephora collection' => 220,
                'rare beauty' => 220,
                'fenty beauty' => 220,
                'huda beauty' => 220,
                'charlotte tilbury' => 220,
                'drunk elephant' => 220,
                'sol de janeiro' => 220,
                'gisou' => 220,
                'tarte' => 220,
                'laneige' => 220,
                'glow recipe' => 220,
                'westman atelier' => 220,
                'makeup by mario' => 220,
                'refy' => 220,
                'hourglass' => 220,
                'onesize' => 220,
                'dior' => 160,
                'chanel' => 160,
                'tom ford' => 160,
                'yves saint laurent' => 160,
                'gucci' => 160,
                'guerlain' => 160,
            ],
            'allow_categories' => [],
            'exclude_categories' => [],
            'exclude_brands' => [],
            'url_modifier' => null,
        ],

        // 6. Galerie de Beauté — парфюмерия и косметика со скидками (выигрывает у Sephora на распродажах)
        'galeriedebeaute' => [
            'enabled' => true,
            'store_name' => 'Galerie de Beauté',
            'base_priority' => 80,
            'discount_bonus' => true,
            'brand_priorities' => [],
            'allow_categories' => [],
            'exclude_categories' => [],
            'exclude_brands' => [],
            'url_modifier' => null,
        ],

        // 7. Pharm24 — уходовая косметика, кремы, SPF, витамины и детская аптека
        'pharm24' => [
            'enabled' => true,
            'store_name' => 'Pharm24',
            'base_priority' => 85,
            'brand_priorities' => [
                'la roche-posay' => 190,
                'la roche posay' => 190,
                'vichy' => 190,
                'apivita' => 190,
                'korres' => 190,
                'cerave' => 190,
                'avene' => 190,
                'bioderma' => 190,
                'frezyderm' => 190,
                'uriage' => 185,
                'nuxe' => 185,
                'caudalie' => 185,
                'institut esthederm' => 185,
                'mustela' => 190,
                'weleda' => 185,
                'hipp' => 185,
                'nutricia' => 185,
            ],
            'allow_categories' => [
                'Ομορφιά', 'Συμπληρώματα Διατροφής', 'Μητέρα-Παιδί', 'Εποχιακά',
                'Περιποίησης Σώματος', 'Φροντίδα Μαλλιών', 'Αντηλιακά',
            ],
            'exclude_categories' => [
                'Ακράτεια', 'Σεξουαλική Ευεξία', 'Σερβιέτες', 'Ορθοπεδικά', 'Ιατρικές Συσκευές',
            ],
            'exclude_brands' => [],
            'url_modifier' => null,
        ],

        // 8. Cosselie — солнцезащитные очки и оправы
        'cosselie' => [
            'enabled' => true,
            'store_name' => 'Cosselie',
            'base_priority' => 110,
            'brand_priorities' => [
                'cosselie' => 220,
            ],
            'allow_categories' => [],
            'exclude_categories' => [],
            'exclude_brands' => [],
            'url_modifier' => null,
        ],

        // 9. Energiers — детская одежда (весь фид, принудительно gender = kids)
        'energiers' => [
            'enabled' => true,
            'store_name' => 'Energiers',
            'base_priority' => 95,
            'force_gender' => 'kids',
            'brand_priorities' => [
                'energiers' => 210,
            ],
            'allow_categories' => [],
            'exclude_categories' => ['ΔΩΡΕΑΝ ΔΙΑΘΕΣΗ'],
            'exclude_brands' => [],
            'url_modifier' => null,
        ],

        // В РЕЗЕРВЕ
        'attica' => [
            'enabled' => false,
            'store_name' => 'Attica',
            'base_priority' => 65,
            'brand_priorities' => [],
            'allow_categories' => [],
            'exclude_categories' => [],
            'exclude_brands' => ['pull&bear', 'bershka', 'stradivarius', 'ovs', 'vero moda', 'only'],
            'url_modifier' => null,
        ],

        'cosmossport' => [
            'enabled' => false,
            'store_name' => 'Cosmos Sport',
            'base_priority' => 50,
            'brand_priorities' => [
                'nike' => 190,
                'jordan' => 190,
                'air jordan' => 190,
            ],
            'allow_categories' => [],
            'exclude_categories' => [],
            'exclude_brands' => [],
            'url_modifier' => null,
        ],
    ],
];