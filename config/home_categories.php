<?php

/**
 * Мультибрендовый каталог витрины Exereto (Греция -> Грузия).
 * Объединяет все активные магазины (Adidas, Notos, Modivo, Epapoutsia, Stradivarius, Sephora, Galerie de Beauté, Pharm24, Cosselie, Energiers).
 */
return [
    // Пустой массив = агрегация по ВСЕМ активным фидам в базе
    'feeds' => [],

    // Исключаемые мусорные ветки
    'excluded_tops' => ['Home'],

    'tiles' => [
        // --- ОБУВЬ ---
        [
            'slug' => 'womens-sneakers',
            'label' => 'Женские кроссовки',
            'img' => 'womens-sneakers.png',
            'where' => [
                'gender' => 'women',
                'category_path' => ['Sneakers', 'Αθλητικά', 'Χαμηλά Αθλητικά', 'Κοντά Αθλητικά', 'Running', 'Τρέξιμο'],
            ],
            'keywords' => ['sneakers', 'кроссовки женские', 'кеды женские', 'ქალის ბოტასები'],
            'subcategories' => [
                ['label' => 'Кроссовки и кеды', 'where' => ['category_path' => ['Sneakers', 'Χαμηλά Αθλητικά', 'Κοντά Αθλητικά']]],
                ['label' => 'Для бега и спорта', 'where' => ['category_path' => ['Τρεξίματος', 'Τρέξιμο', 'Γυμναστηρίου', 'Αθλητικά Παπούτσια']]],
            ],
        ],
        [
            'slug' => 'mens-sports-shoes',
            'label' => 'Мужские кроссовки',
            'img' => 'mens-sports-shoes.png',
            'where' => [
                'gender' => 'men',
                'category_path' => ['Sneakers', 'Χαμηλά Αθλητικά', 'Κοντά Αθλητικά', 'Παπούτσια Τρεξίματος', 'Τρέξιμο', 'Παπούτσια Γυμναστηρίου', 'Παπούτσια Ποδοσφαίρου', 'Μπασκετικά', 'Αθλητικά'],
            ],
            'keywords' => ['mens sneakers', 'мужские кроссовки', 'კაცის ბოტასები'],
            'subcategories' => [
                ['label' => 'Sneakers и кеды', 'where' => ['category_path' => ['Sneakers', 'Χαμηλά Αθλητικά', 'Κοντά Αθλητικά']]],
                ['label' => 'Беговые', 'where' => ['category_path' => ['Τρεξίματος', 'Τρέξιμο']]],
                ['label' => 'Футбол и баскетбол', 'where' => ['category_path' => ['Ποδοσφαίρου', 'Μπάσκετ', 'Μπασκετικά']]],
                ['label' => 'Треккинг и outdoor', 'where' => ['category_path' => ['Ορειβατικά', 'Πεζοπορία']]],
            ],
        ],
        [
            'slug' => 'womens-boots',
            'label' => 'Женские ботинки и сапоги',
            'img' => 'womens-boots.png',
            'where' => [
                'gender' => 'women',
                'category_path' => ['Μποτάκια', 'Μπότες', 'Chelsea', 'Ορειβατικά Παπούτσια', 'Boots'],
            ],
            'keywords' => ['womens boots', 'женские ботинки', 'сапоги', 'ქალის ჩექმა'],
        ],
        [
            'slug' => 'mens-boots-shoes',
            'label' => 'Мужские ботинки и туфли',
            'img' => 'mens-boots.png',
            'where' => [
                'gender' => 'men',
                'category_path' => ['Μποτάκια', 'Μπότες', 'Chelsea Boots', 'Κλειστά Παπούτσια', 'Μοκασίνια', 'Loafers', 'Δερμάτινα', 'Επίσημα', 'Ορειβατικά'],
            ],
            'keywords' => ['mens boots', 'loafers', 'мужские ботинки', 'туфли', 'лоферы'],
        ],
        [
            'slug' => 'womens-heels-loafers',
            'label' => 'Туфли, лоферы и балетки',
            'img' => 'womens-shoes.png',
            'where' => [
                'gender' => 'women',
                'category_path' => ['Γόβες', 'Κλειστά Παπούτσια', 'Μοκασίνια', 'Loafers', 'Μπαλαρίνες', 'Εσπαντρίγιες', 'Shoes'],
            ],
            'keywords' => ['heels', 'loafers', 'туфли', 'лоферы', 'балетки'],
        ],
        [
            'slug' => 'sandals-slides',
            'label' => 'Сандалии и шлёпанцы',
            'img' => 'sandals.png',
            'where' => [
                'category_path' => ['Παντόφλες', 'σαγιονάρες', 'Σαγιονάρες', 'Σανδάλια', 'Πέδιλα', 'Πλατφόρμες'],
            ],
            'keywords' => ['sandals', 'slides', 'сандалии', 'босоножки', 'шлепанцы', 'сланцы'],
        ],

        // --- ВЕРХНЯЯ ОДЕЖДА ---
        [
            'slug' => 'womens-jackets',
            'label' => 'Женские куртки и пальто',
            'img' => 'womens-jackets.png',
            'where' => [
                'gender' => 'women',
                'category_path' => ['Μπουφάν', 'Παλτό', 'Πανωφόρια', 'Jackets', 'Coats', 'Outerwear', 'Vests', 'Σακάκια', 'Γιλέκα'],
            ],
            'keywords' => ['womens jacket', 'coat', 'женская куртка', 'пальто', 'пуховик'],
            'subcategories' => [
                ['label' => 'Куртки и пуховики', 'where' => ['category_path' => ['Μπουφάν', 'Jackets', 'Coats & Jackets', 'Outerwear']]],
                ['label' => 'Пальто и тренчи', 'where' => ['category_path' => ['Παλτό', 'Καμπαρντίνες']]],
                ['label' => 'Пиджаки и жилеты', 'where' => ['category_path' => ['Σακάκια', 'Vests', 'Γιλέκα']]],
            ],
        ],
        [
            'slug' => 'mens-jackets',
            'label' => 'Мужские куртки и пальто',
            'img' => 'mens-jackets.png',
            'where' => [
                'gender' => 'men',
                'category_path' => ['Μπουφάν', 'Παλτό', 'Πανωφόρια', 'Jackets', 'Σακάκια', 'Γιλέκα'],
            ],
            'keywords' => ['mens jacket', 'coat', 'мужская куртка', 'пальто', 'пуховик'],
            'subcategories' => [
                ['label' => 'Куртки', 'where' => ['category_path' => ['Μπουφάν', 'Jackets']]],
                ['label' => 'Пальто', 'where' => ['category_path' => 'Παλτό']],
                ['label' => 'Пиджаки', 'where' => ['category_path' => 'Σακάκια']],
            ],
        ],

        // --- ОДЕЖДА ---
        [
            'slug' => 'hoodies-sweatshirts',
            'label' => 'Худи и свитшоты',
            'img' => 'hoodies.png',
            'where' => [
                'category_path' => ['Φούτερ', 'Ζακέτες', 'Μπλούζες & Fleece', 'Fleece', 'Sweatshirts', 'Hoodies'],
            ],
            'keywords' => ['hoodie', 'sweatshirt', 'худи', 'свитшот', 'толстовка', 'ჰუდი'],
        ],
        [
            'slug' => 'knitwear-sweaters',
            'label' => 'Свитеры и кардиганы',
            'img' => 'sweaters.png',
            'where' => [
                'category_path' => ['Πλεκτά', 'Πουλόβερ', 'Ζακέτες Πλεκτές', 'Knitwear', 'Sweaters'],
            ],
            'keywords' => ['sweater', 'knitwear', 'cardigan', 'свитер', 'джемпер', 'кардиган'],
        ],
        [
            'slug' => 'shirts-blouses',
            'label' => 'Рубашки и блузки',
            'img' => 'shirts.png',
            'where' => [
                'category_path' => ['Πουκάμισα', 'Μπλούζες', 'Shirts & Tops', 'Shirts', 'Blouses'],
            ],
            'keywords' => ['shirt', 'blouse', 'рубашка', 'блузка', 'პერანგი'],
        ],
        [
            'slug' => 'tshirts-polos',
            'label' => 'Футболки и поло',
            'img' => 'tshirts.png',
            'where' => [
                'category_path' => ['T-Shirts', 'T-shirts', 'Polo', 'Πολο', 'Φανέλες', 'Tops', 'Shirts & Tops'],
            ],
            'keywords' => ['t-shirt', 'polo', 'футболка', 'поло', 'майка', 'მაისური'],
        ],
        [
            'slug' => 'dresses',
            'label' => 'Платья и юбки',
            'img' => 'dresses.png',
            'where' => [
                'category_path' => ['Φορέματα', 'Φούστες', 'Ολόσωμες Φόρμες', 'Dresses', 'Skirts'],
            ],
            'keywords' => ['dress', 'skirt', 'платье', 'юбка', 'комбинезон', 'კაბა'],
        ],
        [
            'slug' => 'womens-jeans',
            'label' => 'Женские джинсы и брюки',
            'img' => 'womens-jeans.png',
            'where' => [
                'gender' => 'women',
                'category_path' => ['Τζιν', 'Παντελόνια', 'Κολάν', 'Pants', 'Shorts', 'Jeans'],
            ],
            'keywords' => ['womens jeans', 'trousers', 'женские джинсы', 'брюки', 'леггинсы'],
        ],
        [
            'slug' => 'mens-jeans-trousers',
            'label' => 'Мужские джинсы и брюки',
            'img' => 'mens-jeans.png',
            'where' => [
                'gender' => 'men',
                'category_path' => ['Τζιν', 'Παντελόνια', 'Chinos', 'Pants', 'Jeans'],
            ],
            'keywords' => ['mens jeans', 'chinos', 'мужские джинсы', 'брюки', 'чиносы'],
        ],
        [
            'slug' => 'sportswear-tracksuits',
            'label' => 'Спортивная одежда',
            'img' => 'sportswear.png',
            'where' => [
                'category_path' => ['Αθλητικές Φόρμες', 'Παντελόνια Φόρμας', 'Φόρμες', 'Σορτς', 'Shorts', 'Σπορ'],
            ],
            'keywords' => ['sportswear', 'tracksuit', 'спортивный костюм', 'шорты', 'спортивная одежда'],
        ],
        [
            'slug' => 'underwear-socks',
            'label' => 'Бельё, купальники и носки',
            'img' => 'underwear.png',
            'where' => [
                'category_path' => ['Εσώρουχα', 'Σουτιέν', 'Μαγιό', 'Μόδα Παραλίας', 'Μπικίνι', 'κάλτσες', 'Κάλτσες'],
            ],
            'keywords' => ['underwear', 'socks', 'swimwear', 'белье', 'носки', 'купальник'],
        ],

        // --- СУМКИ И АКСЕССУАРЫ ---
        [
            'slug' => 'bags-backpacks',
            'label' => 'Сумки и рюкзаки',
            'img' => 'bags.png',
            'where' => [
                'category_path' => ['Τσάντες', 'Σακίδια', 'Handbags', 'Wallets & Cases'],
            ],
            'keywords' => ['bag', 'backpack', 'сумка', 'рюкзак', 'ჩანთა'],
            'subcategories' => [
                ['label' => 'Женские сумки', 'where' => ['gender' => 'women', 'category_path' => ['Τσάντες', 'Handbags']]],
                ['label' => 'Мужские сумки и рюкзаки', 'where' => ['gender' => 'men', 'category_path' => ['Τσάντες', 'Σακίδια']]],
            ],
        ],
        [
            'slug' => 'accessories-belts-watches',
            'label' => 'Очки, часы и аксессуары',
            'img' => 'smartwatches.png',
            'where' => [
                'category_path' => ['Γυαλιά', 'Γυαλιά Ηλίου', 'Σκελετοί Γυαλιών', 'Ρολόγια', 'Ζώνες', 'Πορτοφόλια', 'Κοσμήματα', 'Jewelry', 'Scarves', 'Clothing Accessories', 'Αξεσουάρ'],
            ],
            'keywords' => ['accessories', 'sunglasses', 'belt', 'wallet', 'watch', 'ремень', 'кошелек', 'часы', 'очки'],
            'subcategories' => [
                ['label' => 'Солнцезащитные очки и оправы', 'where' => ['category_path' => ['Γυαλιά', 'Γυαλιά Ηλίου', 'Σκελετοί Γυαλιών']]],
                ['label' => 'Часы и украшения', 'where' => ['category_path' => ['Ρολόγια', 'Κοσμήματα', 'Jewelry']]],
                ['label' => 'Ремни, кошельки и шарфы', 'where' => ['category_path' => ['Ζώνες', 'Πορτοφόλια', 'Scarves', 'Wallets']]],
            ],
        ],

        // --- КРАСОТА И ЗДОРОВЬЕ ---
        [
            'slug' => 'perfumes',
            'label' => 'Парфюмерия',
            'img' => 'perfume.png',
            'where' => [
                'category_path' => ['ΑΡΩΜΑΤΑ', 'Αρώματα', 'Parfum', 'Perfume', 'Eau de Parfum', 'Eau de Toilette', 'Body Mist'],
            ],
            'keywords' => ['perfume', 'fragrance', 'парфюм', 'духи', 'туалетная вода', 'სუნამო'],
            'subcategories' => [
                ['label' => 'Женская парфюмерия', 'where' => ['category_path' => ['Γυναικεία', 'ΓΥΝΑΙΚΕΙΑ']]],
                ['label' => 'Мужская парфюмерия', 'where' => ['category_path' => ['Ανδρικά', 'ΑΝΔΡΙΚΑ']]],
            ],
        ],
        [
            'slug' => 'face-body-care',
            'label' => 'Уход за кожей и витамины',
            'img' => 'face-creams.png',
            'where' => [
                'category_path' => [
                    'Πρόσωπο', 'ΠΡΟΣΩΠΟ', 'Περιποίηση Προσώπου', 'Ομορφιά',
                    'Σώμα', 'Περιποίηση Σώματος', 'Περιποίηση σώματος', 'Περιποίησης Σώματος',
                    'Μαλλιά', 'ΜΑΛΛΙΑ', 'Φροντίδα Μαλλιών', 'Περιποίηση Μαλλιών',
                    'Αντηλιακά', 'Συμπληρώματα Διατροφής', 'Βιταμίνες',
                ],
            ],
            'keywords' => ['face cream', 'skincare', 'vitamins', 'крем для лица', 'уход', 'сыворотка', 'витамины'],
            'subcategories' => [
                ['label' => 'Уход за лицом', 'where' => ['category_path' => ['Πρόσωπο', 'ΠΡΟΣΩΠΟ', 'Ομορφιά', 'Περιποίηση Προσώπου']]],
                ['label' => 'Уход за телом и SPF', 'where' => ['category_path' => ['Σώμα', 'Περιποίηση Σώματος', 'Περιποίησης Σώματος', 'Αντηλιακά']]],
                ['label' => 'Уход за волосами', 'where' => ['category_path' => ['Μαλλιά', 'ΜΑΛΛΙΑ', 'Φροντίδα Μαλλιών']]],
                ['label' => 'Витамины и добавки', 'where' => ['category_path' => ['Συμπληρώματα Διατροφής', 'Βιταμίνες']]],
            ],
        ],
        [
            'slug' => 'makeup-beauty',
            'label' => 'Макияж и косметика',
            'img' => 'makeup.png',
            'where' => [
                'category_path' => ['Μακιγιάζ', 'ΜΑΚΙΓΙΑΖ', 'Προϊόντα Μακιγιάζ'],
            ],
            'keywords' => ['makeup', 'lipstick', 'foundation', 'макияж', 'помада', 'тональный крем'],
        ],

        // --- ДЕТИ, АВТОЗАПЧАСТИ И СКИДКИ ---
        [
            'slug' => 'childrens-fashion',
            'label' => 'Детская мода',
            'img' => 'childrens-fashion.png',
            'where' => ['gender' => 'kids'],
            'keywords' => ['kids', 'children', 'детская одежда', 'детская обувь', 'საბავშვო'],
            'subcategories' => [
                ['label' => 'Для девочек', 'where' => ['category_path' => ['Κορίτσι', 'ΚΟΡΙΤΣΙ', 'Κορίτσια']]],
                ['label' => 'Для мальчиков', 'where' => ['category_path' => ['Αγόρι', 'ΑΓΟΡΙ', 'Αγόρια']]],
                ['label' => 'Детская обувь', 'where' => ['category_path' => ['Παπούτσια', 'Υποδήματα', 'Αθλητικά', 'Σανδάλια']]],
            ],
        ],
        [
            'slug' => 'auto-parts',
            'label' => 'Автозапчасти и автотовары',
            'img' => 'auto-parts.png',
            'where' => ['category_path' => ['Ανταλλακτικά', 'Αυτοκίνητο', 'Auto Parts', 'Car Parts']],
            'keywords' => ['auto parts', 'car parts', 'автозапчасти', 'запчасти', 'фильтр', 'тормозные'],
        ],
        [
            'slug' => 'sales-offers',
            'label' => 'Акции и скидки',
            'img' => 'sales-offers.png',
            'where' => ['discount' => true],
            'keywords' => ['sale', 'discount', 'скидки', 'распродажа', 'ფასდაკლება'],
        ],
    ],

    'concepts' => [
        'genders' => [
            'men'   => ['men', 'mens', 'man', 'male', 'мужск*', 'мужчин*', 'парн*', 'კაც*', 'მამაკაც*', 'ανδρικ*', 'ανδρων', 'ανδρες'],
            'women' => ['women', 'womens', 'woman', 'ladies', 'lady', 'female', 'женск*', 'женщин*', 'девушк*', 'ქალ*', 'გოგო*', 'გოგონ*', 'γυναικ*'],
            'kids'  => ['kids', 'kid', 'children', 'child', 'junior', 'juniors', 'boys', 'girls', 'youth', 'детск*', 'дет*', 'ребен*', 'мальчик*', 'девочк*', 'ბავშვ*', 'საბავშვო*', 'παιδικ*', 'παιδια', 'αγορι*', 'κοριτσι*', 'βρεφικ*'],
        ],
        'types' => [
            'shoes'       => ['top' => 'Παπούτσια', 'words' => ['shoes', 'shoe', 'footwear', 'обув*', 'ფეხსაცმ*', 'παπουτσ*', 'υποδημα*']],
            'clothing'    => ['top' => 'Ένδυση',    'words' => ['clothing', 'clothes', 'apparel', 'wear', 'одежд*', 'ტანსაცმ*', 'ρουχ*', 'ενδυσ*']],
            'accessories' => ['top' => 'Αξεσουάρ',  'words' => ['accessories', 'accessory', 'аксессуар*', 'აქსესუარ*', 'αξεσουαρ*']],
        ],
        'fillers' => ['sport', 'sports', 'sporty', 'athletic', 'спортивн*', 'სპორტულ*', 'αθλητικ*'],
    ],
];