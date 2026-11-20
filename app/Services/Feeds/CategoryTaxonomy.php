<?php

namespace App\Services\Feeds;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

class CategoryTaxonomy
{
    /**
     * Полное 3-уровневое дерево каталога (сохраняет все 13 существующих slug в БД).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function tree(): array
    {
        return [
            // =========================================================================
            // 1. CLOTHING (ОДЕЖДА)
            // =========================================================================
            [
                'slug' => 'clothing', 'sort' => 10,
                'en' => 'Clothing', 'ru' => 'Одежда', 'ka' => 'ტანსაცმელი', 'el' => 'Ρούχα',
                'children' => [
                    [
                        'slug' => 'jackets', 'sort' => 10,
                        'en' => 'Jackets & Coats', 'ru' => 'Верхняя одежда', 'ka' => 'ქურთუკები და პალტოები', 'el' => 'Μπουφάν & Παλτά',
                        'children' => [
                            ['slug' => 'puffer-jackets', 'sort' => 10, 'en' => 'Puffer & Down Jackets', 'ru' => 'Пуховики', 'ka' => 'დუტის ქურთუკები', 'el' => 'Πουπουλένια Μπουφάν'],
                            ['slug' => 'coats', 'sort' => 20, 'en' => 'Coats & Trench Coats', 'ru' => 'Пальто и тренчи', 'ka' => 'პალტოები და ტრენჩები', 'el' => 'Παλτά & Καμπαρντίνες'],
                            ['slug' => 'vests', 'sort' => 30, 'en' => 'Vests & Gilets', 'ru' => 'Жилеты', 'ka' => 'ჟილეტები', 'el' => 'Αμάνικα Μπουφάν & Γιλέκα'],
                            ['slug' => 'blazers', 'sort' => 40, 'en' => 'Blazers & Suits', 'ru' => 'Пиджаки и костюмы', 'ka' => 'პიჯაკები და კოსტიუმები', 'el' => 'Σακάκια & Κοστούμια'],
                        ],
                    ],
                    [
                        'slug' => 'hoodies', 'sort' => 20,
                        'en' => 'Hoodies & Sweatshirts', 'ru' => 'Худи и свитшоты', 'ka' => 'ჰუდები და სვიტშოტები', 'el' => 'Φούτερ & Ζακέτες',
                    ],
                    [
                        'slug' => 'sweaters', 'sort' => 30,
                        'en' => 'Sweaters & Cardigans', 'ru' => 'Свитеры и кардиганы', 'ka' => 'სვიტერები და კარდიგანები', 'el' => 'Πουλόβερ & Πλεκτά',
                    ],
                    [
                        'slug' => 't-shirts', 'sort' => 40,
                        'en' => 'T-Shirts & Polos', 'ru' => 'Футболки и поло', 'ka' => 'მაისურები და პოლო', 'el' => 'T-Shirts & Polo',
                        'children' => [
                            ['slug' => 'polo-shirts', 'sort' => 10, 'en' => 'Polo Shirts', 'ru' => 'Поло', 'ka' => 'პოლო მაისურები', 'el' => 'Μπλούζες Polo'],
                            ['slug' => 'tops-blouses', 'sort' => 20, 'en' => 'Tops & Tank Tops', 'ru' => 'Топы и майки', 'ka' => 'ტოპები და მაისურები', 'el' => 'Τοπ & Αμάνικα'],
                        ],
                    ],
                    [
                        'slug' => 'shirts', 'sort' => 50,
                        'en' => 'Shirts & Blouses', 'ru' => 'Рубашки и блузы', 'ka' => 'პერანგები და ბლუზები', 'el' => 'Πουκάμισα & Μπλούζες',
                    ],
                    [
                        'slug' => 'dresses', 'sort' => 60,
                        'en' => 'Dresses & Jumpsuits', 'ru' => 'Платья и комбинезоны', 'ka' => 'კაბები და კომბინეზონები', 'el' => 'Φορέματα & Ολόσωμες Φόρμες',
                    ],
                    [
                        'slug' => 'skirts', 'sort' => 70,
                        'en' => 'Skirts', 'ru' => 'Юбки', 'ka' => 'ქვედაბოლოები', 'el' => 'Φούστες',
                    ],
                    [
                        'slug' => 'jeans', 'sort' => 80,
                        'en' => 'Jeans', 'ru' => 'Джинсы', 'ka' => 'ჯინსები', 'el' => 'Τζιν',
                    ],
                    [
                        'slug' => 'pants', 'sort' => 90,
                        'en' => 'Pants & Trousers', 'ru' => 'Брюки', 'ka' => 'შარვლები', 'el' => 'Παντελόνια',
                        'children' => [
                            ['slug' => 'chinos', 'sort' => 10, 'en' => 'Chinos & Fabric Pants', 'ru' => 'Чиносы и классические', 'ka' => 'ჩინოსი და კლასიკური', 'el' => 'Παντελόνια Chinos & Υφασμάτινα'],
                            ['slug' => 'leggings', 'sort' => 20, 'en' => 'Leggings', 'ru' => 'Леггинсы', 'ka' => 'ლეგინსები', 'el' => 'Κολάν'],
                        ],
                    ],
                    [
                        'slug' => 'shorts', 'sort' => 100,
                        'en' => 'Shorts', 'ru' => 'Шорты', 'ka' => 'შორტები', 'el' => 'Σορτς & Βερμούδες',
                    ],
                    [
                        'slug' => 'sportswear', 'sort' => 110,
                        'en' => 'Sportswear', 'ru' => 'Спортивная одежда', 'ka' => 'სპორტული ტანსაცმელი', 'el' => 'Αθλητικά Ρούχα',
                        'children' => [
                            ['slug' => 'tracksuits', 'sort' => 10, 'en' => 'Tracksuits', 'ru' => 'Спортивные костюмы', 'ka' => 'სპორტული კოსტიუმები', 'el' => 'Αθλητικές Φόρμες'],
                            ['slug' => 'track-pants', 'sort' => 20, 'en' => 'Track Pants & Joggers', 'ru' => 'Спортивные брюки', 'ka' => 'სპორტული შარვლები', 'el' => 'Παντελόνια Φόρμας'],
                            ['slug' => 'jerseys', 'sort' => 30, 'en' => 'Jerseys & Kits', 'ru' => 'Джерси и форма', 'ka' => 'ჯერსები და ფორმები', 'el' => 'Φανέλες & Εμφανίσεις'],
                            ['slug' => 'sports-shorts', 'sort' => 40, 'en' => 'Sports Shorts', 'ru' => 'Спортивные шорты', 'ka' => 'სპორტული შორტები', 'el' => 'Αθλητικά Σορτς'],
                        ],
                    ],
                    [
                        'slug' => 'underwear-socks', 'sort' => 120,
                        'en' => 'Underwear, Socks & Sleepwear', 'ru' => 'Бельё, носки и пижамы', 'ka' => 'თეთრეული, წინდები და პიჟამოები', 'el' => 'Εσώρουχα, Κάλτσες & Πυτζάμες',
                        'children' => [
                            ['slug' => 'socks', 'sort' => 10, 'en' => 'Socks & Tights', 'ru' => 'Носки и колготки', 'ka' => 'წინდები და კოლგოტები', 'el' => 'Κάλτσες & Καλσόν'],
                            ['slug' => 'sleepwear', 'sort' => 20, 'en' => 'Pyjamas & Loungewear', 'ru' => 'Пижамы и домашняя одежда', 'ka' => 'პიჟამოები და სახლის ტანსაცმელი', 'el' => 'Πυτζάμες & Homewear'],
                        ],
                    ],
                    [
                        'slug' => 'swimwear', 'sort' => 130,
                        'en' => 'Swimwear & Beachwear', 'ru' => 'Купальники и пляж', 'ka' => 'საცურაო კოსტიუმები', 'el' => 'Μαγιό & Μόδα Παραλίας',
                    ],
                ],
            ],

            // =========================================================================
            // 2. SHOES (ОБУВЬ)
            // =========================================================================
            [
                'slug' => 'shoes', 'sort' => 20,
                'en' => 'Shoes', 'ru' => 'Обувь', 'ka' => 'ფეხსაცმელი', 'el' => 'Παπούτσια',
                'children' => [
                    [
                        'slug' => 'sneakers', 'sort' => 10,
                        'en' => 'Sneakers', 'ru' => 'Кроссовки и кеды', 'ka' => 'ბოტასები და კედები', 'el' => 'Sneakers & Αθλητικά',
                    ],
                    [
                        'slug' => 'sports-shoes', 'sort' => 20,
                        'en' => 'Sports & Performance Shoes', 'ru' => 'Спортивная обувь', 'ka' => 'სპორტული ფეხსაცმელი', 'el' => 'Αθλητικά Παπούτσια',
                        'children' => [
                            ['slug' => 'running-shoes', 'sort' => 10, 'en' => 'Running Shoes', 'ru' => 'Беговая обувь', 'ka' => 'სარბენი ფეხსაცმელი', 'el' => 'Παπούτσια Τρεξίματος'],
                            ['slug' => 'football-boots', 'sort' => 20, 'en' => 'Football Boots', 'ru' => 'Футбольные бутсы', 'ka' => 'საფეხბურთო ბუცები', 'el' => 'Παπούτσια Ποδοσφαίρου'],
                            ['slug' => 'training-shoes', 'sort' => 30, 'en' => 'Gym & Training Shoes', 'ru' => 'Обувь для зала', 'ka' => 'სავარჯიშო ფეხსაცმელი', 'el' => 'Παπούτσια Γυμναστηρίου'],
                            ['slug' => 'outdoor-shoes', 'sort' => 40, 'en' => 'Hiking & Outdoor', 'ru' => 'Треккинговая обувь', 'ka' => 'სალაშქრო ფეხსაცმელი', 'el' => 'Παπούτσια Ορειβασίας & Outdoor'],
                        ],
                    ],
                    [
                        'slug' => 'boots', 'sort' => 30,
                        'en' => 'Boots & Ankle Boots', 'ru' => 'Ботинки и сапоги', 'ka' => 'ბათინკები და ჩექმები', 'el' => 'Μποτάκια & Μπότες',
                        'children' => [
                            ['slug' => 'ankle-boots', 'sort' => 10, 'en' => 'Ankle & Chelsea Boots', 'ru' => 'Ботильоны и челси', 'ka' => 'ბოტილიონები და ჩელსი', 'el' => 'Μποτάκια & Chelsea Boots'],
                            ['slug' => 'snow-boots', 'sort' => 20, 'en' => 'Winter & Snow Boots', 'ru' => 'Зимние и сноубутсы', 'ka' => 'ზამთრის ჩექმები', 'el' => 'Μπότες Χιονιού'],
                        ],
                    ],
                    [
                        'slug' => 'dress-casual-shoes', 'sort' => 40,
                        'en' => 'Heels, Loafers & Flats', 'ru' => 'Туфли, лоферы и балетки', 'ka' => 'ფეხსაცმელი, ლოფერები და ბალეტკები', 'el' => 'Γόβες, Μπαλαρίνες & Κλειστά Παπούτσια',
                        'children' => [
                            ['slug' => 'heels', 'sort' => 10, 'en' => 'Heels & Pumps', 'ru' => 'Туфли на каблуке', 'ka' => 'ქუსლიანი ფეხსაცმელი', 'el' => 'Γόβες & Ψηλοτάκουνα'],
                            ['slug' => 'loafers-oxfords', 'sort' => 20, 'en' => 'Loafers, Oxfords & Casual', 'ru' => 'Лоферы и классика', 'ka' => 'ლოფერები და კლასიკური', 'el' => 'Μοκασίνια, Loafers & Casual'],
                            ['slug' => 'ballet-flats', 'sort' => 30, 'en' => 'Ballet Flats & Espadrilles', 'ru' => 'Балетки и эспадрильи', 'ka' => 'ბალეტკები და ესპადრილები', 'el' => 'Μπαλαρίνες & Εσπαντρίγιες'],
                        ],
                    ],
                    [
                        'slug' => 'sandals', 'sort' => 50,
                        'en' => 'Sandals', 'ru' => 'Сандалии и босоножки', 'ka' => 'სანდლები', 'el' => 'Σανδάλια & Πέδιλα',
                    ],
                    [
                        'slug' => 'slides', 'sort' => 60,
                        'en' => 'Slides & Slippers', 'ru' => 'Шлёпанцы и тапочки', 'ka' => 'ჩუსტები', 'el' => 'Παντόφλες & Σαγιονάρες',
                        'children' => [
                            ['slug' => 'home-slippers', 'sort' => 10, 'en' => 'Home Slippers', 'ru' => 'Домашние тапочки', 'ka' => 'სახლის ჩუსტები', 'el' => 'Παντόφλες Σπιτιού'],
                        ],
                    ],
                ],
            ],

            // =========================================================================
            // 3. BAGS & ACCESSORIES (СУМКИ И АКСЕССУАРЫ)
            // =========================================================================
            [
                'slug' => 'accessories', 'sort' => 30,
                'en' => 'Bags & Accessories', 'ru' => 'Сумки и аксессуары', 'ka' => 'ჩანთები და აქსესუარები', 'el' => 'Τσάντες & Αξεσουάρ',
                'children' => [
                    [
                        'slug' => 'bags', 'sort' => 10,
                        'en' => 'Bags', 'ru' => 'Сумки', 'ka' => 'ჩანთები', 'el' => 'Τσάντες',
                        'children' => [
                            ['slug' => 'tote-shopper-bags', 'sort' => 10, 'en' => 'Totes & Shoppers', 'ru' => 'Шоперы и тоуты', 'ka' => 'შოპერები და ტოუტები', 'el' => 'Τσάντες Ώμου & Shoppers'],
                            ['slug' => 'crossbody-bags', 'sort' => 20, 'en' => 'Crossbody & Belt Bags', 'ru' => 'Кроссбоди и поясные', 'ka' => 'კროსბოდი და წელის ჩანთები', 'el' => 'Τσάντες Χιαστί & Μέσης'],
                            ['slug' => 'clutches-evening', 'sort' => 30, 'en' => 'Clutches & Evening Bags', 'ru' => 'Клатчи и вечерние', 'ka' => 'კლატჩები და საღამოს ჩანთები', 'el' => 'Τσαντάκια & Βραδινές Τσάντες'],
                        ],
                    ],
                    [
                        'slug' => 'backpacks', 'sort' => 20,
                        'en' => 'Backpacks', 'ru' => 'Рюкзаки', 'ka' => 'ზურგჩანთები', 'el' => 'Σακίδια Πλάτης',
                    ],
                    [
                        'slug' => 'luggage-travel', 'sort' => 30,
                        'en' => 'Luggage & Travel Bags', 'ru' => 'Чемоданы и дорожные', 'ka' => 'ჩემოდნები და სამგზავრო', 'el' => 'Βαλίτσες & Τσάντες Ταξιδιού',
                    ],
                    [
                        'slug' => 'wallets-belts', 'sort' => 40,
                        'en' => 'Wallets & Belts', 'ru' => 'Кошельки и ремни', 'ka' => 'საფულეები და ქამრები', 'el' => 'Πορτοφόλια & Ζώνες',
                        'children' => [
                            ['slug' => 'wallets', 'sort' => 10, 'en' => 'Wallets & Cardholders', 'ru' => 'Кошельки и картхолдеры', 'ka' => 'საფულეები', 'el' => 'Πορτοφόλια & Θήκες Καρτών'],
                            ['slug' => 'belts', 'sort' => 20, 'en' => 'Belts', 'ru' => 'Ремни', 'ka' => 'ქამრები', 'el' => 'Ζώνες'],
                        ],
                    ],
                    [
                        'slug' => 'eyewear', 'sort' => 50,
                        'en' => 'Sunglasses & Eyewear', 'ru' => 'Очки и оправы', 'ka' => 'სათვალეები', 'el' => 'Γυαλιά Ηλίου & Οράσεως',
                        'children' => [
                            ['slug' => 'sunglasses', 'sort' => 10, 'en' => 'Sunglasses', 'ru' => 'Солнцезащитные очки', 'ka' => 'მზის სათვალეები', 'el' => 'Γυαλιά Ηλίου'],
                            ['slug' => 'optical-frames', 'sort' => 20, 'en' => 'Optical Frames', 'ru' => 'Оправы для очков', 'ka' => 'სათვალის ჩარჩოები', 'el' => 'Σκελετοί Γυαλιών'],
                        ],
                    ],
                    [
                        'slug' => 'watches', 'sort' => 60,
                        'en' => 'Watches', 'ru' => 'Часы', 'ka' => 'საათები', 'el' => 'Ρολόγια',
                    ],
                    [
                        'slug' => 'jewelry', 'sort' => 70,
                        'en' => 'Jewelry', 'ru' => 'Украшения', 'ka' => 'სამკაულები', 'el' => 'Κοσμήματα',
                    ],
                    [
                        'slug' => 'hats-scarves', 'sort' => 80,
                        'en' => 'Hats, Caps & Scarves', 'ru' => 'Шапки, кепки и шарфы', 'ka' => 'ქუდები და შარფები', 'el' => 'Καπέλα, Κασκόλ & Γάντια',
                    ],
                    [
                        'slug' => 'other-accessories', 'sort' => 90,
                        'en' => 'Other Accessories', 'ru' => 'Прочие аксессуары', 'ka' => 'სხვა აქსესუარები', 'el' => 'Άλλα Αξεσουάρ',
                    ],
                ],
            ],

            // =========================================================================
            // 4. BEAUTY & PHARMACY (КРАСОТА И АПТЕКА)
            // =========================================================================
            [
                'slug' => 'beauty', 'sort' => 40,
                'en' => 'Beauty & Pharmacy', 'ru' => 'Красота и аптека', 'ka' => 'სილამაზე და აფთიაქი', 'el' => 'Ομορφιά & Φαρμακείο',
                'children' => [
                    [
                        'slug' => 'perfume', 'sort' => 10,
                        'en' => 'Perfume & Fragrance', 'ru' => 'Парфюмерия', 'ka' => 'პარფიუმერია', 'el' => 'Αρώματα',
                        'children' => [
                            ['slug' => 'eau-de-parfum', 'sort' => 10, 'en' => 'Eau de Parfum', 'ru' => 'Парфюмерная вода', 'ka' => 'პარფიუმერული წყალი', 'el' => 'Eau de Parfum'],
                            ['slug' => 'eau-de-toilette', 'sort' => 20, 'en' => 'Eau de Toilette', 'ru' => 'Туалетная вода', 'ka' => 'ტუალეტის წყალი', 'el' => 'Eau de Toilette'],
                            ['slug' => 'fragrance-sets', 'sort' => 30, 'en' => 'Mists & Fragrance Sets', 'ru' => 'Мисты и наборы', 'ka' => 'მისტები და ნაკრებები', 'el' => 'Body Mists & Σετ Αρωμάτων'],
                        ],
                    ],
                    [
                        'slug' => 'makeup', 'sort' => 20,
                        'en' => 'Makeup', 'ru' => 'Макияж', 'ka' => 'მაკიაჟი', 'el' => 'Μακιγιάζ',
                        'children' => [
                            ['slug' => 'makeup-face', 'sort' => 10, 'en' => 'Face Makeup', 'ru' => 'Тон и лицо', 'ka' => 'სახის მაკიაჟი', 'el' => 'Μακιγιάζ Προσώπου'],
                            ['slug' => 'makeup-lips', 'sort' => 20, 'en' => 'Lips', 'ru' => 'Губы', 'ka' => 'ტუჩები', 'el' => 'Μακιγιάζ Χειλιών'],
                            ['slug' => 'makeup-eyes', 'sort' => 30, 'en' => 'Eyes & Brows', 'ru' => 'Глаза и брови', 'ka' => 'თვალები და წარბები', 'el' => 'Μακιγιάζ Ματιών & Φρυδιών'],
                            ['slug' => 'makeup-nails', 'sort' => 40, 'en' => 'Nails', 'ru' => 'Ногти', 'ka' => 'ფრჩხილები', 'el' => 'Νύχια'],
                        ],
                    ],
                    [
                        'slug' => 'skincare', 'sort' => 30,
                        'en' => 'Skincare', 'ru' => 'Уход за лицом', 'ka' => 'სახის მოვლა', 'el' => 'Περιποίηση Προσώπου',
                        'children' => [
                            ['slug' => 'face-creams', 'sort' => 10, 'en' => 'Moisturizers & Creams', 'ru' => 'Кремы и увлажнение', 'ka' => 'კრემები და დატენიანება', 'el' => 'Κρέμες Προσώπου'],
                            ['slug' => 'serums-oils', 'sort' => 20, 'en' => 'Serums & Face Oils', 'ru' => 'Сыворотки и масла', 'ka' => 'შრატები და ზეთები', 'el' => 'Οροί & Έλαια Προσώπου'],
                            ['slug' => 'cleansers', 'sort' => 30, 'en' => 'Cleansers & Masks', 'ru' => 'Очищение и маски', 'ka' => 'გაწმენდა და ნიღბები', 'el' => 'Καθαρισμός & Μάσκες'],
                            ['slug' => 'acne-antiage', 'sort' => 40, 'en' => 'Anti-Age & Targeted Care', 'ru' => 'Антивозрастной и от акне', 'ka' => 'ანტიასაკობრივი და აკნეს მოვლა', 'el' => 'Αντιγήρανση & Ακμή'],
                        ],
                    ],
                    [
                        'slug' => 'haircare', 'sort' => 40,
                        'en' => 'Haircare', 'ru' => 'Уход за волосами', 'ka' => 'თმის მოვლა', 'el' => 'Περιποίηση Μαλλιών',
                        'children' => [
                            ['slug' => 'shampoo-masks', 'sort' => 10, 'en' => 'Shampoos & Masks', 'ru' => 'Шампуни и маски', 'ka' => 'შამპუნები და ნიღბები', 'el' => 'Σαμπουάν & Μάσκες'],
                            ['slug' => 'hair-color', 'sort' => 20, 'en' => 'Hair Color', 'ru' => 'Краски для волос', 'ka' => 'თმის საღებავები', 'el' => 'Βαφές Μαλλιών'],
                            ['slug' => 'hair-styling', 'sort' => 30, 'en' => 'Styling & Treatments', 'ru' => 'Стайлинг и лечение', 'ka' => 'სტაილინგი და მკურნალობა', 'el' => 'Styling & Θεραπείες Μαλλιών'],
                        ],
                    ],
                    [
                        'slug' => 'body-care', 'sort' => 50,
                        'en' => 'Body Care & Hygiene', 'ru' => 'Уход за телом и гигиена', 'ka' => 'სხეულის მოვლა და ჰიგიენა', 'el' => 'Περιποίηση Σώματος & Υγιεινή',
                        'children' => [
                            ['slug' => 'shower-bath', 'sort' => 10, 'en' => 'Shower & Bath', 'ru' => 'Гели для душа и ванна', 'ka' => 'შხაპის გელები', 'el' => 'Αφρόλουτρα & Μπάνιο'],
                            ['slug' => 'deodorants', 'sort' => 20, 'en' => 'Deodorants', 'ru' => 'Дезодоранты', 'ka' => 'დეოდორანტები', 'el' => 'Αποσμητικά'],
                            ['slug' => 'body-lotions', 'sort' => 30, 'en' => 'Body Lotions & Creams', 'ru' => 'Кремы и лосьоны для тела', 'ka' => 'სხეულის კრემები', 'el' => 'Ενυδάτωση Σώματος'],
                        ],
                    ],
                    [
                        'slug' => 'suncare', 'sort' => 60,
                        'en' => 'Suncare', 'ru' => 'Солнцезащита', 'ka' => 'მზისგან დაცვა', 'el' => 'Αντηλιακά',
                    ],
                    [
                        'slug' => 'pharmacy-health', 'sort' => 70,
                        'en' => 'Pharmacy & Vitamins', 'ru' => 'Аптека и витамины', 'ka' => 'აფთიაქი და ვიტამინები', 'el' => 'Υγεία & Βιταμίνες',
                    ],
                    [
                        'slug' => 'beauty-sets', 'sort' => 80,
                        'en' => 'Beauty Gift Sets', 'ru' => 'Подарочные наборы', 'ka' => 'სასაჩუქრე ნაკრებები', 'el' => 'Σετ Δώρου Ομορφιάς',
                    ],
                ],
            ],

            // =========================================================================
            // 5. TOYS & HOBBIES (ИГРУШКИ И ХОББИ)
            // =========================================================================
            [
                'slug' => 'toys', 'sort' => 50,
                'en' => 'Toys & Hobbies', 'ru' => 'Игрушки и хобби', 'ka' => 'სათამაშოები', 'el' => 'Παιχνίδια',
                'children' => [
                    [
                        'slug' => 'lego-building', 'sort' => 10,
                        'en' => 'LEGO & Building Sets', 'ru' => 'Конструкторы и LEGO', 'ka' => 'კონსტრუქტორები და LEGO', 'el' => 'LEGO & Κατασκευές',
                    ],
                    [
                        'slug' => 'dolls-plush', 'sort' => 20,
                        'en' => 'Dolls & Plush Toys', 'ru' => 'Куклы и мягкие игрушки', 'ka' => 'თოჯინები და რბილი სათამაშოები', 'el' => 'Κούκλες & Λούτρινα',
                        'children' => [
                            ['slug' => 'dolls', 'sort' => 10, 'en' => 'Dolls & Playsets', 'ru' => 'Куклы и аксессуары', 'ka' => 'თოჯინები', 'el' => 'Κούκλες & Κουκλόσπιτα'],
                            ['slug' => 'plush-toys', 'sort' => 20, 'en' => 'Plush Toys', 'ru' => 'Мягкие игрушки', 'ka' => 'რბილი სათამაშოები', 'el' => 'Λούτρινα'],
                        ],
                    ],
                    [
                        'slug' => 'action-figures', 'sort' => 30,
                        'en' => 'Action Figures', 'ru' => 'Фигурки и герои', 'ka' => 'ფიგურები', 'el' => 'Φιγούρες Δράσης',
                    ],
                    [
                        'slug' => 'vehicles-rc', 'sort' => 40,
                        'en' => 'Vehicles & RC Toys', 'ru' => 'Машинки и радиоуправление', 'ka' => 'მანქანები და ტრანსპორტი', 'el' => 'Οχήματα & Τηλεκατευθυνόμενα',
                    ],
                    [
                        'slug' => 'puzzles-boardgames', 'sort' => 50,
                        'en' => 'Puzzles & Board Games', 'ru' => 'Пазлы и настольные игры', 'ka' => 'პაზლები და სამაგიდო თამაშები', 'el' => 'Παζλ & Επιτραπέζια',
                    ],
                    [
                        'slug' => 'educational-wooden', 'sort' => 60,
                        'en' => 'Educational & Wooden Toys', 'ru' => 'Развивающие и деревянные', 'ka' => 'განმავითარებელი და ხის', 'el' => 'Εκπαιδευτικά & Ξύλινα Παιχνίδια',
                    ],
                    [
                        'slug' => 'roleplay-creative', 'sort' => 70,
                        'en' => 'Roleplay, Crafts & Music', 'ru' => 'Ролевые, творчество и музыка', 'ka' => 'როლური და შემოქმედებითი', 'el' => 'Παιχνίδια Ρόλων & Δημιουργικά',
                    ],
                    [
                        'slug' => 'baby-toys', 'sort' => 80,
                        'en' => 'Baby Toys', 'ru' => 'Игрушки для малышей', 'ka' => 'ჩვილის სათამაშოები', 'el' => 'Βρεφικά Παιχνίδια',
                    ],
                ],
            ],

            // =========================================================================
            // 6. BABY & MOM (МАЛЫШАМ И МАМАМ)
            // =========================================================================
            [
                'slug' => 'baby-mom', 'sort' => 60,
                'en' => 'Baby & Mom', 'ru' => 'Малышам и мамам', 'ka' => 'ჩვილებისთვის და დედებისთვის', 'el' => 'Μητέρα & Βρέφος',
                'children' => [
                    [
                        'slug' => 'baby-care', 'sort' => 10,
                        'en' => 'Baby Care & Feeding', 'ru' => 'Уход и кормление', 'ka' => 'ჩვილის მოვლა და კვება', 'el' => 'Βρεφική Φροντίδα & Διατροφή',
                    ],
                    [
                        'slug' => 'baby-textiles', 'sort' => 20,
                        'en' => 'Baby Textiles & Layette', 'ru' => 'Детский текстиль и приданое', 'ka' => 'ჩვილის ტექსტილი', 'el' => 'Προίκα Μωρού',
                    ],
                ],
            ],

            // =========================================================================
            // 7. TECH & GAMING (ТЕХНИКА И ГЕЙМИНГ)
            // =========================================================================
            [
                'slug' => 'tech', 'sort' => 70,
                'en' => 'Tech & Gaming', 'ru' => 'Техника и гейминг', 'ka' => 'ტექნიკა და გეიმინგი', 'el' => 'Τεχνολογία & Gaming',
                'children' => [
                    [
                        'slug' => 'gaming', 'sort' => 10,
                        'en' => 'Gaming', 'ru' => 'Гейминг', 'ka' => 'გეიმინგი', 'el' => 'Gaming',
                        'children' => [
                            ['slug' => 'video-games', 'sort' => 10, 'en' => 'Video Games', 'ru' => 'Игры PS5, Switch, Xbox', 'ka' => 'ვიდეო თამაშები', 'el' => 'Παιχνίδια Κονσολών'],
                            ['slug' => 'gaming-peripherals', 'sort' => 20, 'en' => 'Gaming Peripherals', 'ru' => 'Гейминг-периферия', 'ka' => 'გეიმინგ პერიფერია', 'el' => 'Gaming Αξεσουάρ & Περιφερειακά'],
                            ['slug' => 'consoles-controllers', 'sort' => 30, 'en' => 'Consoles & Controllers', 'ru' => 'Консоли и геймпады', 'ka' => 'კონსოლები და გეიმპადები', 'el' => 'Κονσόλες & Χειριστήρια'],
                        ],
                    ],
                    [
                        'slug' => 'computers-laptops', 'sort' => 20,
                        'en' => 'Laptops & Computers', 'ru' => 'Ноутбуки и ПК', 'ka' => 'ლეპტოპები და კომპიუტერები', 'el' => 'Υπολογιστές & Laptops',
                        'children' => [
                            ['slug' => 'laptops', 'sort' => 10, 'en' => 'Laptops', 'ru' => 'Ноутбуки', 'ka' => 'ლეპტოპები', 'el' => 'Laptops'],
                            ['slug' => 'desktops-monitors', 'sort' => 20, 'en' => 'Desktops & Monitors', 'ru' => 'ПК и мониторы', 'ka' => 'კომპიუტერები და მონიტორები', 'el' => 'Desktops & Οθόνες'],
                            ['slug' => 'pc-peripherals', 'sort' => 30, 'en' => 'Keyboards, Mice & Gear', 'ru' => 'Клавиатуры, мыши, коврики', 'ka' => 'კლავიატურები და მაუსები', 'el' => 'Πληκτρολόγια, Ποντίκια & Περιφερειακά'],
                            ['slug' => 'laptop-bags', 'sort' => 40, 'en' => 'Laptop Bags & Accessories', 'ru' => 'Сумки и аксессуары для ПК', 'ka' => 'ლეპტოპის ჩანთები და აქსესუარები', 'el' => 'Τσάντες & Αξεσουάρ Laptop'],
                        ],
                    ],
                    [
                        'slug' => 'phones-tablets', 'sort' => 30,
                        'en' => 'Smartphones & Tablets', 'ru' => 'Смартфоны и планшеты', 'ka' => 'სმარტფონები და პლანშეტები', 'el' => 'Κινητά & Tablets',
                        'children' => [
                            ['slug' => 'smartphones', 'sort' => 10, 'en' => 'Smartphones', 'ru' => 'Смартфоны', 'ka' => 'სმარტფონები', 'el' => 'Smartphones'],
                            ['slug' => 'tablets', 'sort' => 20, 'en' => 'Tablets', 'ru' => 'Планшеты', 'ka' => 'პლანშეტები', 'el' => 'Tablets'],
                            ['slug' => 'phone-accessories', 'sort' => 30, 'en' => 'Cases & Accessories', 'ru' => 'Чехлы и аксессуары', 'ka' => 'ქეისები და აქსესუარები', 'el' => 'Θήκες & Αξεσουάρ Κινητών'],
                        ],
                    ],
                    [
                        'slug' => 'wearables', 'sort' => 40,
                        'en' => 'Smartwatches & Wearables', 'ru' => 'Смарт-часы и ремешки', 'ka' => 'სმარტ-საათები', 'el' => 'Smartwatches & Wearables',
                    ],
                    [
                        'slug' => 'audio', 'sort' => 50,
                        'en' => 'Headphones & Audio', 'ru' => 'Наушники и аудио', 'ka' => 'ყურსასმენები და აუდიო', 'el' => 'Ήχος & Ακουστικά',
                        'children' => [
                            ['slug' => 'headphones', 'sort' => 10, 'en' => 'Headphones & Earbuds', 'ru' => 'Наушники', 'ka' => 'ყურსასმენები', 'el' => 'Ακουστικά'],
                            ['slug' => 'speakers', 'sort' => 20, 'en' => 'Speakers', 'ru' => 'Колонки', 'ka' => 'დინამიკები', 'el' => 'Ηχεία'],
                        ],
                    ],
                    [
                        'slug' => 'photo-drones-gadgets', 'sort' => 60,
                        'en' => 'Photo, Drones & Gadgets', 'ru' => 'Фото, дроны и гаджеты', 'ka' => 'ფოტო, დრონები და გაჯეტები', 'el' => 'Φωτογραφία, Drones & Gadgets',
                    ],
                ],
            ],
        ];
    }

    /**
     * Засевает / обновляет дерево категорий по slug, сохраняя существующие ID.
     *
     * @return array<string, int> Карта slug => id
     */
    public function seed(): array
    {
        $now = now();
        $slugToId = [];

        $upsertNode = function (array $node, ?int $parentId) use ($now, &$slugToId, &$upsertNode): void {
            $slug = (string) $node['slug'];
            $existing = DB::table('categories')->where('slug', $slug)->first(['id']);

            $payload = [
                'parent_id' => $parentId,
                'gender' => $node['gender'] ?? null,
                'name_en' => $node['en'],
                'name_ru' => $node['ru'],
                'name_ka' => $node['ka'],
                'name_el' => $node['el'],
                'sort_order' => (int) ($node['sort'] ?? 100),
                'is_active' => 1,
                'updated_at' => $now,
            ];

            if ($existing) {
                DB::table('categories')->where('id', $existing->id)->update($payload);
                $id = (int) $existing->id;
            } else {
                $id = (int) DB::table('categories')->insertGetId(array_merge($payload, [
                    'slug' => $slug,
                    'created_at' => $now,
                ]));
            }

            $slugToId[$slug] = $id;

            foreach ((array) ($node['children'] ?? []) as $child) {
                $upsertNode($child, $id);
            }
        };

        foreach (self::tree() as $root) {
            $upsertNode($root, null);
        }

        Category::flushTreeCache();

        return $slugToId;
    }

    /**
     * Определяет наиболее точный slug категории по фиду, сырому category_path (и при необходимости name).
     */
    public function classifySlug(string $feed, string $rawPath, string $name = ''): ?string
    {
        $feed = strtolower(trim($feed));
        $path = self::norm($rawPath);
        $text = $path !== '' ? $path : self::norm($name);

        if ($text === '') {
            return null;
        }

        // 1. Вертикальные фиды с жёсткой доменной областью (исключают ложные пересечения)
        if ($feed === 'cosselie') {
            return $this->classifyCosselie($text);
        }
        if (in_array($feed, ['moustakas', 'toysgr'], true)) {
            return $this->classifyToys($text);
        }
        if ($feed === 'public') {
            return $this->classifyPublic($text);
        }
        if (in_array($feed, ['sephora', 'galeriedebeaute'], true)) {
            return $this->classifyBeauty($text, false);
        }
        if ($feed === 'pharm24') {
            return $this->classifyPharm24($text);
        }
        if ($feed === 'epapoutsia') {
            return $this->classifyEpapoutsia($text);
        }
        if ($feed === 'adidas') {
            return $this->classifyAdidas($text);
        }

        // 2. Мультибрендовая мода (modivo, notos, stradivarius, energiers)
        $slug = $this->classifyFashion($text, $feed);
        if ($slug === null && $path !== '' && $name !== '') {
            $slug = $this->classifyFashion(self::norm($name), $feed);
        }
        if ($slug === null && $feed === 'energiers') {
            return 'clothing';
        }

        return $slug;
    }

    private function classifyCosselie(string $t): string
    {
        if (self::hasAny($t, ['σκελετ', 'ορασεωσ', 'optical', 'frame'])) {
            return 'optical-frames';
        }
        if (self::hasAny($t, ['γυαλι', 'sunglass', 'ηλιου'])) {
            return 'sunglasses';
        }

        return 'other-accessories';
    }

    private function classifyToys(string $t): string
    {
        if (self::hasAny($t, ['lego', 'τουβλακι', 'κατασκευ', 'playmobil', 'building', 'bricks'])) {
            return 'lego-building';
        }
        if (self::hasAny($t, ['βρεφικ', 'baby', 'μωρα'])) {
            return 'baby-toys';
        }
        if (self::hasAny($t, ['λουτριν', 'plush'])) {
            return 'plush-toys';
        }
        if (self::hasAny($t, ['κουκλ', 'barbie', 'doll', 'μικροκοσμ', 'κουκλοσπιτ'])) {
            return 'dolls';
        }
        if (self::hasAny($t, ['φιγουρ', 'action figure', 'ηρωεσ', 'superhero'])) {
            return 'action-figures';
        }
        if (self::hasAny($t, ['τηλεκατευθυνομ', 'οχηματ', 'αυτοκινητ', 'τρεν', 'μηχανεσ', 'vehicle', 'car', 'rc'])) {
            return 'vehicles-rc';
        }
        if (self::hasAny($t, ['παζλ', 'puzzle', 'επιτραπεζι', 'board game', 'καρτεσ'])) {
            return 'puzzles-boardgames';
        }
        if (self::hasAny($t, ['ξυλιν', 'εκπαιδευτικ', 'προσχολικ', 'stem', 'εξερευνηση', 'διαδραστικ', 'educational', 'wooden'])) {
            return 'educational-wooden';
        }
        if (self::hasAny($t, ['ρολων', 'μιμησησ', 'μαγειρικ', 'μουσικ', 'κουκλοθεατρ', 'ομορφιασ', 'οπλα', 'σπαθια', 'δημιουργικ', 'χειροτεχν', 'ζωγραφικ', 'roleplay', 'craft'])) {
            return 'roleplay-creative';
        }

        return 'educational-wooden';
    }

    private function classifyPublic(string $t): string
    {
        if (self::hasAny($t, ['playstation games', 'nintendo games', 'xbox games', 'pc games', 'ps5', 'ps4', 'nintendo switch', 'games'])) {
            if (self::hasAny($t, ['gaming', 'playstation', 'nintendo', 'xbox', 'switch', 'ps5', 'ps4'])) {
                if (self::hasAny($t, ['headset', 'ακουστικ', 'mouse', 'ποντικ', 'keyboard', 'πληκτρολογ', 'chair', 'καρεκλ', 'αξεσουαρ'])) {
                    return 'gaming-peripherals';
                }
                if (self::hasAny($t, ['κονσολ', 'console', 'χειριστηρ', 'controller', 'gamepad'])) {
                    return 'consoles-controllers';
                }

                return 'video-games';
            }
        }
        if (self::hasAny($t, ['gaming'])) {
            if (self::hasAny($t, ['κονσολ', 'console', 'χειριστηρ', 'controller'])) {
                return 'consoles-controllers';
            }

            return 'gaming-peripherals';
        }
        if (self::hasAny($t, ['wearable', 'smartwatch', 'λουρακι', 'activity tracker', 'fitness tracker'])) {
            return 'wearables';
        }
        if (self::hasAny($t, ['smartphone', 'κινητ'])) {
            if (self::hasAny($t, ['θηκ', 'φορτιστ', 'καλωδ', 'προστασ', 'αξεσουαρ', 'powerbank'])) {
                return 'phone-accessories';
            }

            return 'smartphones';
        }
        if (self::hasAny($t, ['tablet'])) {
            if (self::hasAny($t, ['θηκ', 'αξεσουαρ', 'γραφιδ'])) {
                return 'phone-accessories';
            }

            return 'tablets';
        }
        if (self::hasAny($t, ['αξεσουαρ laptop', 'τσαντεσ laptop', 'βασεισ laptop', 'laptop bag'])) {
            return 'laptop-bags';
        }
        if (self::hasAny($t, ['laptop', 'macbook', 'ultrabook'])) {
            return 'laptops';
        }
        if (self::hasAny($t, ['desktop', 'σταθεροι υπολογιστ', 'οθον', 'monitor', 'all in one'])) {
            return 'desktops-monitors';
        }
        if (self::hasAny($t, ['πληκτρολογ', 'keyboard', 'ποντικ', 'mouse', 'mousepad', 'περιφερειακ', 'webcam', 'εκτυπωτ', 'printer', 'router', 'δισκ', 'ssd', 'usb'])) {
            return 'pc-peripherals';
        }
        if (self::hasAny($t, ['ακουστικ', 'headphone', 'earbud', 'earphone', 'airpod'])) {
            return 'headphones';
        }
        if (self::hasAny($t, ['ηχει', 'speaker', 'soundbar', 'ηχοσ', 'audio'])) {
            return 'speakers';
        }
        if (self::hasAny($t, ['drone', 'καμερ', 'camera', 'φωτογραφ', 'action cam', 'projector', 'προτζεκτ', '3d', 'gadget', 'τηλεορασ', 'tv'])) {
            return 'photo-drones-gadgets';
        }

        return 'photo-drones-gadgets';
    }

    private function classifyPharm24(string $t): string
    {
        if (self::hasAny($t, ['μητερα', 'βρεφ', 'πιπιλ', 'μπιμπερο', 'θηλασμ', 'πανεσ', 'εγκυμοσυν', 'παιδικη διατροφ'])) {
            return 'baby-care';
        }
        if (self::hasAny($t, ['βιταμιν', 'συμπληρωμ', 'φαρμακ', 'υγεια', 'ιατρικ', 'θερμομετρ', 'πιεσομετρ', 'οδοντ', 'στοματικ', 'προβιοτικ', 'ανοσοποιητ', 'μαγνησ'])) {
            return 'pharmacy-health';
        }

        return $this->classifyBeauty($t, true);
    }

    private function classifyBeauty(string $t, bool $defaultPharmacy = false): string
    {
        if (self::hasAny($t, ['σετ δωρ', 'gift set', 'σετ ομορφ', 'coffret', 'value set'])) {
            return 'beauty-sets';
        }
        if (self::hasAny($t, ['αντηλιακ', 'sun', 'spf', 'after sun', 'μαυρισμ', 'self tan'])) {
            return 'suncare';
        }
        if (self::hasAny($t, ['αρωματ', 'perfume', 'cologne', 'fragrance', 'eau de'])) {
            if (self::hasAny($t, ['eau de parfum', 'edp', 'parfum'])) {
                return 'eau-de-parfum';
            }
            if (self::hasAny($t, ['eau de toilette', 'edt'])) {
                return 'eau-de-toilette';
            }
            if (self::hasAny($t, ['mist', 'σετ', 'set'])) {
                return 'fragrance-sets';
            }

            return 'perfume';
        }
        if (self::hasAny($t, ['μακιγιαζ', 'makeup', 'make up', 'κραγιον', 'lipstick', 'lipgloss', 'gloss', 'foundation', 'concealer', 'ρουζ', 'blush', 'πουδρ', 'highlighter', 'bronzer', 'primer', 'σκιεσ', 'eyeshadow', 'mascara', 'μασκαρα', 'eyeliner', 'φρυδι', 'brow', 'βερνικ', 'νυχι', 'nail'])) {
            if (self::hasAny($t, ['χειλ', 'κραγιον', 'lip', 'gloss', 'balm χειλ'])) {
                return 'makeup-lips';
            }
            if (self::hasAny($t, ['ματι', 'φρυδ', 'σκιεσ', 'eyeliner', 'mascara', 'μασκαρα', 'brow', 'eye', 'βλεφαριδ'])) {
                return 'makeup-eyes';
            }
            if (self::hasAny($t, ['νυχι', 'βερνικ', 'nail', 'μανικιουρ'])) {
                return 'makeup-nails';
            }

            return 'makeup-face';
        }
        if (self::hasAny($t, ['μαλλι', 'hair', 'σαμπουαν', 'shampoo', 'conditioner', 'βαφεσ μαλλι', 'styling'])) {
            if (self::hasAny($t, ['βαφ', 'color', 'χρωμ'])) {
                return 'hair-color';
            }
            if (self::hasAny($t, ['styling', 'θεραπει', 'λακ', 'αφροσ', 'ελαια μαλλι', 'οροσ μαλλι', 'βουρτσ'])) {
                return 'hair-styling';
            }

            return 'shampoo-masks';
        }
        if (self::hasAny($t, ['προσωπ', 'επιδερμιδ', 'face', 'skincare', 'serum', 'οροσ', 'ντεμακιγιαζ', 'καθαρισμ', 'ακν', 'ρυτιδ', 'αντιγηρανσ', 'ενυδατωση ημερασ', 'κρεμα ημερασ', 'κρεμα νυχτασ', 'ματιων κρεμα'])) {
            if (self::hasAny($t, ['serum', 'οροσ', 'ελαια προσωπ', 'λαδια προσωπ'])) {
                return 'serums-oils';
            }
            if (self::hasAny($t, ['καθαρισμ', 'ντεμακιγιαζ', 'cleanser', 'μασκ', 'peeling', 'scrub', 'απολεπισ', 'micellar', 'toner'])) {
                return 'cleansers';
            }
            if (self::hasAny($t, ['ακν', 'ατελειεσ', 'αντιγηρανσ', 'ρυτιδ', 'συσφιξ', 'κηλιδ', 'anti age'])) {
                return 'acne-antiage';
            }

            return 'face-creams';
        }
        if (self::hasAny($t, ['σωματ', 'υγιειν', 'αφρολουτρ', 'αποσμητικ', 'body', 'shower', 'bath', 'deodorant', 'χεριων', 'ποδιων', 'ξυρισμ', 'αποτριχ'])) {
            if (self::hasAny($t, ['αφρολουτρ', 'shower', 'μπανι', 'σαπουν'])) {
                return 'shower-bath';
            }
            if (self::hasAny($t, ['αποσμητικ', 'deodorant'])) {
                return 'deodorants';
            }

            return 'body-lotions';
        }

        return $defaultPharmacy ? 'pharmacy-health' : 'skincare';
    }

    private function classifyEpapoutsia(string $t): string
    {
        // У epapoutsia иногда встречаются сумки, аксессуары и уход за обувью
        if (self::hasAny($t, ['σακιδι', 'backpack'])) {
            return 'backpacks';
        }
        if (self::hasAny($t, ['βαλιτσ', 'ταξιδι', 'luggage'])) {
            return 'luggage-travel';
        }
        if (self::hasAny($t, ['τσαντ', 'bag', 'handbag', 'shopper', 'clutch'])) {
            if (self::hasAny($t, ['χιαστ', 'μεσησ', 'crossbody'])) {
                return 'crossbody-bags';
            }
            if (self::hasAny($t, ['shopper', 'ωμου', 'tote'])) {
                return 'tote-shopper-bags';
            }

            return 'bags';
        }
        if (self::hasAny($t, ['πορτοφολ', 'wallet'])) {
            return 'wallets';
        }
        if (self::hasAny($t, ['ζων', 'belt'])) {
            return 'belts';
        }
        if (self::hasAny($t, ['καλτσ', 'sock'])) {
            return 'socks';
        }
        if (self::hasAny($t, ['καπελ', 'σκουφ', 'γαντ', 'κασκολ'])) {
            return 'hats-scarves';
        }
        if (self::hasAny($t, ['πατοι', 'κορδονι', 'φροντιδα παπουτσι', 'βουρτσ', 'σπρει', 'αξεσουαρ'])) {
            return 'other-accessories';
        }

        return $this->classifyShoes($t);
    }

    private function classifyAdidas(string $t): string
    {
        if (self::hasAny($t, ['παπουτσι', 'sneaker', 'μποτ', 'σανδαλ', 'παντοφλ', 'σαγιοναρ', 'shoe', 'boot', 'slide'])) {
            return $this->classifyShoes($t);
        }
        if (self::hasAny($t, ['καλτσ', 'sock'])) {
            return 'socks';
        }
        if (self::hasAny($t, ['σακιδι', 'backpack'])) {
            return 'backpacks';
        }
        if (self::hasAny($t, ['τσαντ', 'bag'])) {
            return 'bags';
        }
        if (self::hasAny($t, ['κεφαλησ', 'καπελ', 'σκουφ', 'γαντ', 'κασκολ', 'cap', 'hat', 'beanie'])) {
            return 'hats-scarves';
        }
        if (self::hasAny($t, ['αξεσουαρ', 'μπαλ', 'παγουρ', 'επικαλαμιδ', 'accessories'])) {
            return 'other-accessories';
        }

        // Одежда Adidas (спортивная специфика)
        if (self::hasAny($t, ['μαγιο', 'κολυμβ', 'swim'])) {
            return 'swimwear';
        }
        if (self::hasAny($t, ['jacket', 'μπουφαν', 'παλτ', 'αντιανεμ', 'γιλεκ', 'coat', 'vest'])) {
            if (self::hasAny($t, ['γιλεκ', 'vest', 'αμανικ'])) {
                return 'vests';
            }

            return 'jackets';
        }
        if (self::hasAny($t, ['φουτερ', 'ζακετ', 'hoodie', 'sweatshirt'])) {
            return 'hoodies';
        }
        if (self::hasAny($t, ['αθλητικεσ φορμεσ', 'φορμεσ πανω', 'tracksuit'])) {
            if (self::hasAny($t, ['παντελον'])) {
                return 'track-pants';
            }

            return 'tracksuits';
        }
        if (self::hasAny($t, ['παντελονια φορμ', 'φορμασ', 'jogger'])) {
            return 'track-pants';
        }
        if (self::hasAny($t, ['κολαν', 'legging', 'tight'])) {
            return 'leggings';
        }
        if (self::hasAny($t, ['παντελον', 'pant'])) {
            return 'track-pants';
        }
        if (self::hasAny($t, ['σορτσ', 'short'])) {
            return 'sports-shorts';
        }
        if (self::hasAny($t, ['φανελ', 'εμφανισ', 'jersey'])) {
            return 'jerseys';
        }
        if (self::hasAny($t, ['φορεμ', 'dress'])) {
            return 'dresses';
        }
        if (self::hasAny($t, ['φουστ', 'skirt'])) {
            return 'skirts';
        }
        if (self::hasAny($t, ['εσώρουχ', 'εσωρουχ', 'μπουστακ', 'bra', 'underwear'])) {
            return 'underwear-socks';
        }
        if (self::hasAny($t, ['polo'])) {
            return 'polo-shirts';
        }
        if (self::hasAny($t, ['μπλουζ', 't shirt', 'tshirt', 'top', 'shirt'])) {
            return 't-shirts';
        }

        return 'sportswear';
    }

    private function classifyFashion(string $t, string $feed): ?string
    {
        // Детское приданое (energiers)
        if (self::hasAny($t, ['προικα μωρ', 'κουβερτ', 'υπνοσακ', 'σεντον', 'πετσετα μωρ'])) {
            return 'baby-textiles';
        }

        // Косметика/парфюмерия внутри мультибрендовых магазинов (notos, stradivarius, modivo)
        if (self::hasAny($t, ['ομορφιασ', 'καλλυντικ', 'αρωματ', 'perfume', 'cologne', 'cosmetics', 'μακιγιαζ', 'health & beauty', 'προσωπου', 'σωματοσ'])) {
            return $this->classifyBeauty($t, false);
        }

        // Игрушки внутри notos (если попались)
        if ($feed === 'notos' && self::hasAny($t, ['παιχνιδ']) && !self::hasAny($t, ['ρουχ', 'μπλουζ', 'παντελον', 'παπουτσ', 'φορεμ', 'μπουφαν'])) {
            return $this->classifyToys($t);
        }

        // Обувь
        if (self::hasAny($t, [
            'παπουτσ', 'υποδηματ', 'shoes', 'footwear', 'sneaker', 'μποτακ', 'μποτεσ', 'boots', 'σανδαλ', 'πεδιλ', 'sandal',
            'παντοφλ', 'σαγιοναρ', 'slipper', 'slide', 'γοβεσ', 'heels', 'μπαλαριν', 'μοκασιν', 'loafer', 'εσπαντριγ', 'oxford', 'derby', 'αθλητικα παπουτσ',
        ])) {
            return $this->classifyShoes($t);
        }

        // Сумки и аксессуары
        if (self::hasAny($t, ['βαλιτσ', 'ταξιδι', 'luggage', 'suitcase'])) {
            return 'luggage-travel';
        }
        if (self::hasAny($t, ['σακιδι', 'backpack'])) {
            return 'backpacks';
        }
        if (self::hasAny($t, ['τσαντ', 'handbag', 'bags', 'bag', 'shopper', 'tote', 'clutch', 'crossbody'])) {
            if (self::hasAny($t, ['χιαστ', 'μεσησ', 'crossbody', 'waist', 'belt bag'])) {
                return 'crossbody-bags';
            }
            if (self::hasAny($t, ['shopper', 'tote', 'ωμου', 'χερουλ'])) {
                return 'tote-shopper-bags';
            }
            if (self::hasAny($t, ['βραδιν', 'clutch', 'τσαντακι'])) {
                return 'clutches-evening';
            }

            return 'bags';
        }
        if (self::hasAny($t, ['πορτοφολ', 'wallet', 'καρτοθηκ', 'cardholder'])) {
            return 'wallets';
        }
        if (self::hasAny($t, ['ζωνη', 'ζωνεσ', 'belt'])) {
            return 'belts';
        }
        if (self::hasAny($t, ['γυαλι', 'sunglass', 'eyewear'])) {
            if (self::hasAny($t, ['ορασεωσ', 'σκελετ', 'optical'])) {
                return 'optical-frames';
            }

            return 'sunglasses';
        }
        if (self::hasAny($t, ['ρολογ', 'watch'])) {
            return 'watches';
        }
        if (self::hasAny($t, ['κοσμημ', 'jewelry', 'jewellery', 'κολιε', 'necklace', 'σκουλαρικ', 'earring', 'βραχιολ', 'bracelet', 'δαχτυλιδ', 'ring'])) {
            return 'jewelry';
        }
        if (self::hasAny($t, ['καπελ', 'σκουφ', 'κασκολ', 'μαντηλ', 'γαντ', 'scarf', 'scarves', 'shawl', 'headwear', 'hat', 'cap', 'beanie', 'gloves', 'χειμερινα αξεσουαρ'])) {
            return 'hats-scarves';
        }
        if (self::hasAny($t, ['μπρελοκ', 'keychain', 'ομπρελ', 'γραβατ', 'παπιγιον', 'αξεσουαρ παραλ'])) {
            return 'other-accessories';
        }

        // Одежда
        if (self::hasAny($t, ['μαγιο', 'μπικινι', 'μοδα παραλιασ', 'swimwear', 'bikini', 'beachwear'])) {
            return 'swimwear';
        }
        if (self::hasAny($t, ['καλτσ', 'καλσον', 'sock', 'tight', 'hosiery'])) {
            return 'socks';
        }
        if (self::hasAny($t, ['πυτζαμ', 'πιτζαμ', 'νυχτικ', 'ρομπ', 'homewear', 'pyjama', 'pajama', 'sleepwear', 'loungewear'])) {
            return 'sleepwear';
        }
        if (self::hasAny($t, ['εσωρουχ', 'σλιπ', 'μποξερ', 'σουτιεν', 'lingerie', 'underwear', 'boxer', 'brief', 'bra', 'tanga'])) {
            return 'underwear-socks';
        }
        if (self::hasAny($t, ['αθλητικη φορμ', 'αθλητικεσ φορμεσ', 'tracksuit', 'φορμεσ', 'φορμα']) && !self::hasAny($t, ['ολοσωμ'])) {
            if (self::hasAny($t, ['παντελον'])) {
                return 'track-pants';
            }

            return 'tracksuits';
        }
        if (self::hasAny($t, ['σακακ', 'κοστουμ', 'blazer', 'suit'])) {
            return 'blazers';
        }
        if (self::hasAny($t, ['γιλεκ', 'αμανικο μπουφαν', 'vest', 'gilet'])) {
            return 'vests';
        }
        if (self::hasAny($t, ['παλτ', 'καμπαρντιν', 'coat', 'trench', 'overcoat']) && !self::hasAny($t, ['μπουφαν', 'jacket'])) {
            return 'coats';
        }
        if (self::hasAny($t, ['πουπουλεν', 'puffer', 'down jacket', 'παρκα', 'parka'])) {
            return 'puffer-jackets';
        }
        if (self::hasAny($t, ['μπουφαν', 'jacket', 'outerwear', 'coat', 'πανωφορ', 'αντιανεμ', 'δερματιν'])) {
            return 'jackets';
        }
        if (self::hasAny($t, ['φουτερ', 'hoodie', 'sweatshirt', 'ζακετ φουτερ'])) {
            return 'hoodies';
        }
        if (self::hasAny($t, ['πουλοβερ', 'πλεκτ', 'καρντιγκαν', 'ζακετ', 'sweater', 'cardigan', 'knitwear', 'pullover', 'jumper'])) {
            return 'sweaters';
        }
        if (self::hasAny($t, ['φορεμ', 'ολοσωμ', 'dress', 'jumpsuit', 'romper'])) {
            return 'dresses';
        }
        if (self::hasAny($t, ['φουστ', 'skirt'])) {
            return 'skirts';
        }
        if (self::hasAny($t, ['τζιν', 'jean', 'denim'])) {
            return 'jeans';
        }
        if (self::hasAny($t, ['κολαν', 'legging'])) {
            return 'leggings';
        }
        if (self::hasAny($t, ['chinos', 'υφασματινα παντελον', 'chino'])) {
            return 'chinos';
        }
        if (self::hasAny($t, ['παντελον', 'pant', 'trouser', 'jogger', 'cargo'])) {
            return 'pants';
        }
        if (self::hasAny($t, ['σορτσ', 'βερμουδ', 'short'])) {
            return 'shorts';
        }
        if (self::hasAny($t, ['πουκαμισ', 'shirt']) && !self::hasAny($t, ['t shirt', 't-shirt', 'tshirt', 'sweatshirt', 'polo'])) {
            return 'shirts';
        }
        if (self::hasAny($t, ['polo', 'πολο'])) {
            return 'polo-shirts';
        }
        if (self::hasAny($t, ['t shirt', 't-shirt', 'tshirt'])) {
            return 't-shirts';
        }
        if (self::hasAny($t, ['τοπ', 'top', 'μπλουζακ', 'αμανικ', 'tank', 'camisole', 'body'])) {
            return 'tops-blouses';
        }
        if (self::hasAny($t, ['μπλουζ', 'blouse', 'tee'])) {
            return 't-shirts';
        }
        if (self::hasAny($t, ['αξεσουαρ', 'accessories'])) {
            return 'other-accessories';
        }
        if (self::hasAny($t, ['ρουχ', 'clothing', 'apparel', 'σετ', 'αγορι', 'κοριτσι'])) {
            return 'clothing';
        }

        return null;
    }

    private function classifyShoes(string $t): string
    {
        if (self::hasAny($t, ['ποδοσφαιρ', 'football', 'soccer', 'cleat'])) {
            return 'football-boots';
        }
        if (self::hasAny($t, ['τρεξιμ', 'running', 'jogging'])) {
            return 'running-shoes';
        }
        if (self::hasAny($t, ['γυμναστηρ', 'training', 'fitness', 'crossfit'])) {
            return 'training-shoes';
        }
        if (self::hasAny($t, ['ορειβασ', 'trekking', 'hiking', 'outdoor', 'trail'])) {
            return 'outdoor-shoes';
        }
        if (self::hasAny($t, ['παπουτσια αλλων αθληματ', 'μπασκετ', 'τενισ', 'basketball', 'tennis'])) {
            return 'sports-shoes';
        }
        if (self::hasAny($t, ['παντοφλεσ σπιτιου', 'home slipper', 'σπιτιου'])) {
            return 'home-slippers';
        }
        if (self::hasAny($t, ['παντοφλ', 'σαγιοναρ', 'slide', 'flip flop', 'slipper', 'clog', 'σαμπο', 'crocs'])) {
            return 'slides';
        }
        if (self::hasAny($t, ['σανδαλ', 'πεδιλ', 'sandal'])) {
            return 'sandals';
        }
        if (self::hasAny($t, ['μποτεσ χιονιου', 'snow boot', 'χιονιου', 'γαλλοτσ', 'γαλοτσ', 'rain boot'])) {
            return 'snow-boots';
        }
        if (self::hasAny($t, ['μποτακ', 'chelsea', 'ankle boot'])) {
            return 'ankle-boots';
        }
        if (self::hasAny($t, ['μποτ', 'boot', 'αρβυλ'])) {
            return 'boots';
        }
        if (self::hasAny($t, ['γοβεσ', 'ψηλοτακουν', 'heel', 'pump', 'stiletto'])) {
            return 'heels';
        }
        if (self::hasAny($t, ['μπαλαριν', 'εσπαντριγ', 'ballet', 'espadrille'])) {
            return 'ballet-flats';
        }
        if (self::hasAny($t, ['μοκασιν', 'loafer', 'oxford', 'derby', 'κλειστα παπουτσ', 'brogue', 'casual'])) {
            return 'loafers-oxfords';
        }
        if (self::hasAny($t, ['sneaker', 'αθλητικ', 'χαμηλα αθλητικ', 'trainer', 'running', 'canvas'])) {
            return 'sneakers';
        }

        return 'shoes';
    }

    /**
     * @param  string[]  $needles
     */
    private static function hasAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function norm(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, [
            'ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ό' => 'ο', 'ύ' => 'υ', 'ώ' => 'ω',
            'ϊ' => 'ι', 'ΐ' => 'ι', 'ϋ' => 'υ', 'ΰ' => 'υ', 'ς' => 'σ',
        ]);
        $text = (string) preg_replace('/[^\p{L}\p{N}&]+/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}