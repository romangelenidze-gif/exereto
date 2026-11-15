<?php

/**
 * Мультибрендовый каталог витрины Exereto (Греция -> Грузия).
 * Объединяет все активные магазины (Adidas, Notos и будущие фиды).
 * Категории без товаров в наличии автоматически скрываются на главной.
 */
return [
    // Пустой массив или null = агрегация по ВСЕМ активным фидам в базе
    'feeds' => [],

    // Исключаемые мусорные ветки (посуда, канцелярия, бытовой пластик)
    'excluded_tops' => ['Home'],

    'tiles' => [
        // --- ОБУВЬ ---
        [
            'slug' => 'womens-sneakers',
            'label' => 'Женские кроссовки',
            'img' => 'womens-sneakers.png',
            'where' => ['gender' => 'women', 'category_path' => ['Sneakers', 'Αθλητικά']],
            'keywords' => ['sneakers', 'кроссовки женские', 'кеды женские', 'ქალის ბოტასები'],
            'subcategories' => [
                ['label' => 'Кроссовки', 'where' => ['category_path' => 'Sneakers']],
                ['label' => 'Для бега и спорта', 'where' => ['category_path' => ['Τρεξίματος', 'Γυμναστηρίου', 'Αθλητικά']]],
            ],
        ],
        [
            'slug' => 'mens-sports-shoes',
            'label' => 'Мужские кроссовки',
            'img' => 'mens-sports-shoes.png',
            'where' => ['gender' => 'men', 'category_path' => ['Sneakers', 'Παπούτσια Τρεξίματος', 'Παπούτσια Γυμναστηρίου', 'Παπούτσια Ποδοσφαίρου', 'Αθλητικά']],
            'keywords' => ['mens sneakers', 'мужские кроссовки', 'კაცის ბოტასები'],
            'subcategories' => [
                ['label' => 'Sneakers', 'where' => ['category_path' => 'Sneakers']],
                ['label' => 'Беговые', 'where' => ['category_path' => 'Τρεξίματος']],
                ['label' => 'Футбольные бутсы', 'where' => ['category_path' => 'Ποδοσφαίρου']],
                ['label' => 'Для зала', 'where' => ['category_path' => 'Γυμναστηρίου']],
            ],
        ],
        [
            'slug' => 'womens-boots',
            'label' => 'Женские ботинки и сапоги',
            'img' => 'womens-boots.png',
            'where' => ['gender' => 'women', 'category_path' => ['Μποτάκια', 'Μπότες']],
            'keywords' => ['womens boots', 'женские ботинки', 'сапоги', 'ქალის ჩექმა'],
        ],
        [
            'slug' => 'mens-boots-shoes',
            'label' => 'Мужские ботинки и туфли',
            'img' => 'mens-boots.png',
            'where' => ['gender' => 'men', 'category_path' => ['Μποτάκια', 'Μπότες', 'Μοκασίνια', 'Loafers', 'Δερμάτινα', 'Επίσημα']],
            'keywords' => ['mens boots', 'loafers', 'мужские ботинки', 'туфли', 'лоферы'],
        ],
        [
            'slug' => 'womens-heels-loafers',
            'label' => 'Туфли, лоферы и балетки',
            'img' => 'womens-shoes.png',
            'where' => ['gender' => 'women', 'category_path' => ['Γόβες', 'Μοκασίνια', 'Loafers', 'Μπαλαρίνες', 'Εσπαντρίγιες']],
            'keywords' => ['heels', 'loafers', 'туфли', 'лоферы', 'балетки'],
        ],
        [
            'slug' => 'sandals-slides',
            'label' => 'Сандалии и шлёпанцы',
            'img' => 'sandals.png',
            'where' => ['category_path' => ['Παντόφλες', 'σαγιονάρες', 'Σανδάλια', 'Πέδιλα']],
            'keywords' => ['sandals', 'slides', 'сандалии', 'босоножки', 'шлепанцы', 'сланцы'],
        ],

        // --- ВЕРХНЯЯ ОДЕЖДА ---
        [
            'slug' => 'womens-jackets',
            'label' => 'Женские куртки и пальто',
            'img' => 'womens-jackets.png',
            'where' => ['gender' => 'women', 'category_path' => ['Μπουφάν', 'Παλτό', 'Πανωφόρια', 'Jackets', 'Σακάκια']],
            'keywords' => ['womens jacket', 'coat', 'женская куртка', 'пальто', 'пуховик'],
            'subcategories' => [
                ['label' => 'Куртки и пуховики', 'where' => ['category_path' => ['Μπουφάν', 'Jackets']]],
                ['label' => 'Пальто и тренчи', 'where' => ['category_path' => ['Παλτό', 'Καμπαρντίνες']]],
                ['label' => 'Пиджаки и жакеты', 'where' => ['category_path' => 'Σακάκια']],
            ],
        ],
        [
            'slug' => 'mens-jackets',
            'label' => 'Мужские куртки и пальто',
            'img' => 'mens-jackets.png',
            'where' => ['gender' => 'men', 'category_path' => ['Μπουφάν', 'Παλτό', 'Πανωφόρια', 'Jackets', 'Σακάκια']],
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
            'where' => ['category_path' => ['Φούτερ', 'Ζακέτες']],
            'keywords' => ['hoodie', 'sweatshirt', 'худи', 'свитшот', 'толстовка', 'ჰუდი'],
        ],
        [
            'slug' => 'knitwear-sweaters',
            'label' => 'Свитеры и кардиганы',
            'img' => 'sweaters.png',
            'where' => ['category_path' => ['Πλεκτά', 'Πουλόβερ', 'Ζακέτες Πλεκτές']],
            'keywords' => ['sweater', 'knitwear', 'cardigan', 'свитер', 'джемпер', 'кардиган'],
        ],
        [
            'slug' => 'shirts-blouses',
            'label' => 'Рубашки и блузки',
            'img' => 'shirts.png',
            'where' => ['category_path' => ['Πουκάμισα', 'Μπλούζες Casual']],
            'keywords' => ['shirt', 'blouse', 'рубашка', 'блузка', 'პერანგი'],
        ],
        [
            'slug' => 'tshirts-polos',
            'label' => 'Футболки и поло',
            'img' => 'tshirts.png',
            'where' => ['category_path' => ['T-Shirts', 'Πολο', 'Φανέλες', 'Tops']],
            'keywords' => ['t-shirt', 'polo', 'футболка', 'поло', 'майка', 'მაისური'],
        ],
        [
            'slug' => 'dresses',
            'label' => 'Платья и юбки',
            'img' => 'dresses.png',
            'where' => ['category_path' => ['Φορέματα', 'Φούστες', 'Ολόσωμες Φόρμες']],
            'keywords' => ['dress', 'skirt', 'платье', 'юбка', 'комбинезон', 'კაბა'],
        ],
        [
            'slug' => 'womens-jeans',
            'label' => 'Женские джинсы и брюки',
            'img' => 'womens-jeans.png',
            'where' => ['gender' => 'women', 'category_path' => ['Τζιν', 'Παντελόνια', 'Κολάν']],
            'keywords' => ['womens jeans', 'trousers', 'женские джинсы', 'брюки', 'леггинсы'],
        ],
        [
            'slug' => 'mens-jeans-trousers',
            'label' => 'Мужские джинсы и брюки',
            'img' => 'mens-jeans.png',
            'where' => ['gender' => 'men', 'category_path' => ['Τζιν', 'Παντελόνια', 'Chinos']],
            'keywords' => ['mens jeans', 'chinos', 'мужские джинсы', 'брюки', 'чиносы'],
        ],
        [
            'slug' => 'sportswear-tracksuits',
            'label' => 'Спортивная одежда',
            'img' => 'sportswear.png',
            'where' => ['category_path' => ['Αθλητικές Φόρμες', 'Φόρμες', 'Σορτς', 'Αθλητικά']],
            'keywords' => ['sportswear', 'tracksuit', 'спортивный костюм', 'шорты', 'спортивная одежда'],
        ],
        [
            'slug' => 'underwear-socks',
            'label' => 'Бельё, купальники и носки',
            'img' => 'underwear.png',
            'where' => ['category_path' => ['Εσώρουχα', 'Σουτιέν', 'Μαγιό', 'κάλτσες', 'Κάλτσες']],
            'keywords' => ['underwear', 'socks', 'swimwear', 'белье', 'носки', 'купальник'],
        ],

        // --- СУМКИ И АКСЕССУАРЫ ---
        [
            'slug' => 'bags-backpacks',
            'label' => 'Сумки и рюкзаки',
            'img' => 'bags.png',
            'where' => ['category_path' => ['Τσάντες', 'Σακίδια']],
            'keywords' => ['bag', 'backpack', 'сумка', 'рюкзак', 'ჩანთა'],
            'subcategories' => [
                ['label' => 'Женские сумки', 'where' => ['gender' => 'women', 'category_path' => 'Τσάντες']],
                ['label' => 'Мужские сумки и рюкзаки', 'where' => ['gender' => 'men', 'category_path' => ['Τσάντες', 'Σακίδια']]],
            ],
        ],
        [
            'slug' => 'accessories-belts-watches',
            'label' => 'Аксессуары, ремни и часы',
            'img' => 'smartwatches.png',
            'where' => ['category_path' => ['Αξεσουάρ', 'Ζώνες', 'Πορτοφόλια', 'Ρολόγια', 'Γυαλιά']],
            'keywords' => ['accessories', 'belt', 'wallet', 'watch', 'ремень', 'кошелек', 'часы', 'очки'],
        ],

        // --- КРАСОТА И ЗДОРОВЬЕ ---
        [
            'slug' => 'perfumes',
            'label' => 'Парфюмерия',
            'img' => 'perfume.png',
            'where' => ['category_path' => ['ΑΡΩΜΑΤΑ', 'Αρώματα', 'Parfum']],
            'keywords' => ['perfume', 'fragrance', 'парфюм', 'духи', 'туалетная вода', 'სუნამო'],
            'subcategories' => [
                ['label' => 'Женская парфюмерия', 'where' => ['category_path' => 'Γυναικεία Αρώματα']],
                ['label' => 'Мужская парфюмерия', 'where' => ['category_path' => 'Ανδρικά Αρώματα']],
            ],
        ],
        [
            'slug' => 'face-body-care',
            'label' => 'Кремы и уход за кожей',
            'img' => 'face-creams.png',
            'where' => ['category_path' => ['Περιποίηση Προσώπου', 'Περιποίηση Σώματος', 'Αντηλιακά', 'Περιποίηση Μαλλιών', 'Ανδρικής Περιποίησης']],
            'keywords' => ['face cream', 'skincare', 'крем для лица', 'уход', 'сыворотка', 'солнцезащитный'],
            'subcategories' => [
                ['label' => 'Уход за лицом', 'where' => ['category_path' => 'Περιποίηση Προσώπου']],
                ['label' => 'Уход за телом', 'where' => ['category_path' => 'Περιποίηση Σώματος']],
                ['label' => 'Солнцезащитные', 'where' => ['category_path' => 'Αντηλιακά']],
                ['label' => 'Уход за волосами', 'where' => ['category_path' => 'Περιποίηση Μαλλιών']],
            ],
        ],
        [
            'slug' => 'makeup-beauty',
            'label' => 'Макияж и косметика',
            'img' => 'makeup.png',
            'where' => ['category_path' => 'Μακιγιάζ'],
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
                ['label' => 'Для девочек', 'where' => ['category_path' => 'Κορίτσια']],
                ['label' => 'Для мальчиков', 'where' => ['category_path' => 'Αγόρια']],
                ['label' => 'Детская обувь', 'where' => ['category_path' => 'Παπούτσια']],
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
            'men'   => ['men', 'mens', 'man', 'male', 'мужск*', 'мужчин*', 'парн*', 'კაც*', 'მამაკაც*', 'ανδρικ*', 'ανδρων'],
            'women' => ['women', 'womens', 'woman', 'ladies', 'lady', 'female', 'женск*', 'женщин*', 'девушк*', 'ქალ*', 'გოგო*', 'გოგონ*', 'γυναικ*'],
            'kids'  => ['kids', 'kid', 'children', 'child', 'junior', 'juniors', 'boys', 'girls', 'youth', 'детск*', 'дет*', 'ребен*', 'мальчик*', 'девочк*', 'ბავშვ*', 'საბავშვო*', 'παιδικ*'],
        ],
        'types' => [
            'shoes'       => ['top' => 'Παπούτσια', 'words' => ['shoes', 'shoe', 'footwear', 'обув*', 'ფეხსაცმ*', 'παπουτσ*']],
            'clothing'    => ['top' => 'Ένδυση',    'words' => ['clothing', 'clothes', 'apparel', 'wear', 'одежд*', 'ტანსაცმ*', 'ρουχ*', 'ενδυσ*']],
            'accessories' => ['top' => 'Αξεσουάρ',  'words' => ['accessories', 'accessory', 'аксессуар*', 'აქსესუარ*', 'αξεσουαρ*']],
        ],
        'fillers' => ['sport', 'sports', 'sporty', 'athletic', 'спортивн*', 'სპორტულ*', 'αθλητικ*'],
    ],
];