<?php

namespace App\Services\Feeds;

use App\Jobs\TranslateFeedEntitiesJob;
use App\Models\EntityTranslation;
use App\Models\FeedProduct;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FeedEntityLocalizer
{
    private const CACHE_TTL = 86400;

    private const GREEK_STOP_ONLY = [
        'με', 'για', 'σε', 'απο', 'και', 'τησ', 'του', 'των', 'το', 'τα', 'η', 'ο', 'οι', 'ενα',
    ];

    /**
     * Базовый словарь точных терминов (Одежда, Обувь, Материалы, Характеристики, Косметика, Аптека, Цвета).
     *
     * @var array<string, array{en: string, ru: string, ka: string}>
     */
    private const CORE_LEXICON = [
        // Служебные слова и предлоги
        'με' => ['en' => 'with', 'ru' => 'с', 'ka' => '-ით'],
        'χωρισ' => ['en' => 'without', 'ru' => 'без', 'ka' => 'გარეშე'],
        'για' => ['en' => 'for', 'ru' => 'для', 'ka' => '-თვის'],
        'και' => ['en' => 'and', 'ru' => 'и', 'ka' => 'და'],
        'σε' => ['en' => 'in', 'ru' => 'в', 'ka' => '-ში'],
        'απο' => ['en' => 'from', 'ru' => 'из', 'ka' => '-დან'],
        'κατα' => ['en' => 'against', 'ru' => 'против', 'ka' => 'წინააღმდეგ'],
        'η' => ['en' => 'or', 'ru' => 'или', 'ka' => 'ან'],
        'του' => ['en' => 'of', 'ru' => '', 'ka' => ''],
        'τησ' => ['en' => 'of', 'ru' => '', 'ka' => ''],
        'των' => ['en' => 'of', 'ru' => '', 'ka' => ''],
        'το' => ['en' => '', 'ru' => '', 'ka' => ''],
        'τα' => ['en' => '', 'ru' => '', 'ka' => ''],
        'ο' => ['en' => '', 'ru' => '', 'ka' => ''],
        'οι' => ['en' => '', 'ru' => '', 'ka' => ''],
        'στον' => ['en' => 'in', 'ru' => 'в', 'ka' => '-ში'],
        'στην' => ['en' => 'in', 'ru' => 'в', 'ka' => '-ში'],
        'στο' => ['en' => 'in', 'ru' => 'в', 'ka' => '-ში'],
        'στα' => ['en' => 'in', 'ru' => 'в', 'ka' => '-ში'],

        // Состав, материалы и крой (Specifications)
        'περιβλημα' => ['en' => 'Shell', 'ru' => 'Верх', 'ka' => 'ზედაპირი'],
        'φοδρα' => ['en' => 'Lining', 'ru' => 'Подкладка', 'ka' => 'სარჩული'],
        'τσεπεσ' => ['en' => 'Pockets', 'ru' => 'Карманы', 'ka' => 'ჯიბეები'],
        'τσεπη' => ['en' => 'Pocket', 'ru' => 'Карман', 'ka' => 'ჯიბე'],
        'πολυεστερασ' => ['en' => 'polyester', 'ru' => 'полиэстер', 'ka' => 'პოლიესტერი'],
        'ανακυκλωμενοσ' => ['en' => 'recycled', 'ru' => 'переработанный', 'ka' => 'გადამუშავებული'],
        'ανακυκλωμενο' => ['en' => 'recycled', 'ru' => 'переработанный', 'ka' => 'გადამუშავებული'],
        'ανακυκλωμενη' => ['en' => 'recycled', 'ru' => 'переработанная', 'ka' => 'გადამუშავებული'],
        'βαμβακι' => ['en' => 'cotton', 'ru' => 'хлопок', 'ka' => 'ბამბა'],
        'βαμβακερο' => ['en' => 'cotton', 'ru' => 'хлопковый', 'ka' => 'ბამბის'],
        'ελαστανη' => ['en' => 'elastane', 'ru' => 'эластан', 'ka' => 'ელასტანი'],
        'βισκοζη' => ['en' => 'viscose', 'ru' => 'вискоза', 'ka' => 'ვისკოზა'],
        'ναυλον' => ['en' => 'nylon', 'ru' => 'нейлон', 'ka' => 'ნეილონი'],
        'μαλλι' => ['en' => 'wool', 'ru' => 'шерсть', 'ka' => 'შალი'],
        'λινο' => ['en' => 'linen', 'ru' => 'лён', 'ka' => 'სელი'],
        'δερμα' => ['en' => 'leather', 'ru' => 'кожа', 'ka' => 'ტყავი'],
        'δερματινο' => ['en' => 'leather', 'ru' => 'кожаный', 'ka' => 'ტყავის'],
        'καστορι' => ['en' => 'suede', 'ru' => 'замша', 'ka' => 'ზამში'],
        'συνθετικο' => ['en' => 'synthetic', 'ru' => 'синтетика', 'ka' => 'სინთეტიკური'],
        'υφασμα' => ['en' => 'textile', 'ru' => 'текстиль', 'ka' => 'ტექსტილი'],
        'σολα' => ['en' => 'sole', 'ru' => 'подошва', 'ka' => 'ძირი'],
        'εξωτερικη' => ['en' => 'outer', 'ru' => 'внешняя', 'ka' => 'გარე'],
        'εσωτερικη' => ['en' => 'inner', 'ru' => 'внутренняя', 'ka' => 'შიდა'],
        'ενδιαμεση' => ['en' => 'midsole', 'ru' => 'промежуточная', 'ka' => 'შუა'],
        'καουτσουκ' => ['en' => 'rubber', 'ru' => 'резина', 'ka' => 'რეზინი'],
        'κανονικη' => ['en' => 'regular', 'ru' => 'классический', 'ka' => 'სტანდარტული'],
        'στενη' => ['en' => 'slim', 'ru' => 'приталенный', 'ka' => 'ვიწრო'],
        'φαρδια' => ['en' => 'loose', 'ru' => 'свободный', 'ka' => 'თავისუფალი'],
        'εφαρμογη' => ['en' => 'fit', 'ru' => 'крой', 'ka' => 'ჭრილი'],
        'κλεισιμο' => ['en' => 'closure', 'ru' => 'застёжка', 'ka' => 'შესაკრავი'],
        'φερμουαρ' => ['en' => 'zipper', 'ru' => 'молния', 'ka' => 'ელვა-შესაკრავი'],
        'κορδονια' => ['en' => 'laces', 'ru' => 'шнурки', 'ka' => 'თასმები'],
        'κορδονι' => ['en' => 'drawcord', 'ru' => 'шнурок', 'ka' => 'თასმა'],
        'κουκουλα' => ['en' => 'hood', 'ru' => 'капюшон', 'ka' => 'კაპიუშონი'],
        'γιακασ' => ['en' => 'collar', 'ru' => 'воротник', 'ka' => 'საყელო'],
        'μανικια' => ['en' => 'sleeves', 'ru' => 'рукава', 'ka' => 'სახელოები'],
        'μανικι' => ['en' => 'sleeve', 'ru' => 'рукав', 'ka' => 'სახელო'],
        'μεση' => ['en' => 'waist', 'ru' => 'талия / пояс', 'ka' => 'წელი'],
        'ελαστικη' => ['en' => 'elastic', 'ru' => 'эластичный', 'ka' => 'ელასტიური'],
        'σημα' => ['en' => 'crest', 'ru' => 'эмблема', 'ka' => 'ემბლემა'],
        'λογοτυπο' => ['en' => 'logo', 'ru' => 'логотип', 'ka' => 'ლოგო'],
        'υπογραφη' => ['en' => 'signature', 'ru' => 'автограф / подпись', 'ka' => 'ხელმოწერა'],
        'κεντητο' => ['en' => 'embroidered', 'ru' => 'вышитый', 'ka' => 'ნაქარგი'],
        'τυπωμενο' => ['en' => 'printed', 'ru' => 'принт', 'ka' => 'პრინტი'],
        'στριφωμα' => ['en' => 'hem', 'ru' => 'нижний край', 'ka' => 'კიდე'],
        'μανσετ' => ['en' => 'cuffs', 'ru' => 'манжеты', 'ka' => 'მანჟეტები'],
        'μανσετα' => ['en' => 'cuffs', 'ru' => 'манжеты', 'ka' => 'მანჟეტები'],
        'αστραγαλουσ' => ['en' => 'ankles', 'ru' => 'щиколотки', 'ka' => 'კოჭები'],
        'πλαινεσ' => ['en' => 'side', 'ru' => 'боковые', 'ka' => 'გვერდითი'],
        'μπροστινεσ' => ['en' => 'front', 'ru' => 'передние', 'ka' => 'წინა'],
        'πισω' => ['en' => 'back', 'ru' => 'задние', 'ka' => 'უკანა'],

        // Единицы измерения и упаковка
        'μμ' => ['en' => 'mm', 'ru' => 'мм', 'ka' => 'მმ'],
        'εκ' => ['en' => 'cm', 'ru' => 'см', 'ka' => 'სმ'],
        'τεμαχια' => ['en' => 'pcs', 'ru' => 'шт.', 'ka' => 'ცალი'],
        'τεμαχιο' => ['en' => 'pc', 'ru' => 'шт.', 'ka' => 'ცალი'],
        'τεμ' => ['en' => 'pcs', 'ru' => 'шт.', 'ka' => 'ცალი'],
        'καψουλεσ' => ['en' => 'capsules', 'ru' => 'капсулы', 'ka' => 'კაფსულა'],
        'ταμπλετεσ' => ['en' => 'tablets', 'ru' => 'таблетки', 'ka' => 'ტაბლეტი'],
        'δισκια' => ['en' => 'tablets', 'ru' => 'таблетки', 'ka' => 'ტაბლეტი'],
        'φακελακια' => ['en' => 'sachets', 'ru' => 'пакетики', 'ka' => 'პაკეტი'],
        'σετ' => ['en' => 'set', 'ru' => 'набор', 'ka' => 'ნაკრები'],
        'πακετο' => ['en' => 'pack', 'ru' => 'упаковка', 'ka' => 'შეფუთვა'],
        'διπλο' => ['en' => 'double', 'ru' => 'двойной', 'ka' => 'ორმაგი'],

        // Пол и возраст
        'γυναικεια' => ['en' => "Women's", 'ru' => 'Женские', 'ka' => 'ქალის'],
        'γυναικειο' => ['en' => "Women's", 'ru' => 'Женское', 'ka' => 'ქალის'],
        'γυναικειοσ' => ['en' => "Women's", 'ru' => 'Женский', 'ka' => 'ქალის'],
        'γυναικα' => ['en' => 'Women', 'ru' => 'Женщинам', 'ka' => 'ქალი'],
        'γυναικεσ' => ['en' => 'Women', 'ru' => 'Женщинам', 'ka' => 'ქალები'],
        'ανδρικα' => ['en' => "Men's", 'ru' => 'Мужские', 'ka' => 'მამაკაცის'],
        'ανδρικο' => ['en' => "Men's", 'ru' => 'Мужское', 'ka' => 'მამაკაცის'],
        'ανδρικοσ' => ['en' => "Men's", 'ru' => 'Мужской', 'ka' => 'მამაკაცის'],
        'ανδρασ' => ['en' => 'Men', 'ru' => 'Мужчинам', 'ka' => 'მამაკაცი'],
        'ανδρεσ' => ['en' => 'Men', 'ru' => 'Мужчинам', 'ka' => 'მამაკაცები'],
        'παιδικα' => ['en' => "Kids'", 'ru' => 'Детские', 'ka' => 'საბავშვო'],
        'παιδικο' => ['en' => "Kids'", 'ru' => 'Детское', 'ka' => 'საბავშვო'],
        'παιδικοσ' => ['en' => "Kids'", 'ru' => 'Детский', 'ka' => 'საბავშვო'],
        'παιδια' => ['en' => 'Kids', 'ru' => 'Детям', 'ka' => 'ბავშვები'],
        'κοριτσι' => ['en' => 'Girl', 'ru' => 'Для девочки', 'ka' => 'გოგონა'],
        'κοριτσια' => ['en' => 'Girls', 'ru' => 'Для девочек', 'ka' => 'გოგონები'],
        'αγορι' => ['en' => 'Boy', 'ru' => 'Для мальчика', 'ka' => 'ბიჭი'],
        'αγορια' => ['en' => 'Boys', 'ru' => 'Для мальчиков', 'ka' => 'ბიჭები'],
        'βρεφικα' => ['en' => 'Baby', 'ru' => 'Для малышей', 'ka' => 'ჩვილის'],
        'βρεφικο' => ['en' => 'Baby', 'ru' => 'Для малышей', 'ka' => 'ჩვილის'],

        // Обувь
        'παπουτσια' => ['en' => 'Shoes', 'ru' => 'Обувь', 'ka' => 'ფეხსაცმელი'],
        'υποδηματα' => ['en' => 'Footwear', 'ru' => 'Обувь', 'ka' => 'ფეხსაცმელი'],
        'αθλητικα' => ['en' => 'Sneakers', 'ru' => 'Кроссовки', 'ka' => 'სპორტული ფეხსაცმელი'],
        'κλειστα' => ['en' => 'Closed', 'ru' => 'Закрытые', 'ka' => 'დახურული'],
        'μποτακια' => ['en' => 'Ankle Boots', 'ru' => 'Ботинки', 'ka' => 'შუზები'],
        'μποτινια' => ['en' => 'Ankle Boots', 'ru' => 'Ботильоны', 'ka' => 'შუზები'],
        'μποτεσ' => ['en' => 'Boots', 'ru' => 'Сапоги', 'ka' => 'ჩექმები'],
        'σανδαλια' => ['en' => 'Sandals', 'ru' => 'Сандалии', 'ka' => 'სანდლები'],
        'πεδιλα' => ['en' => 'Heeled Sandals', 'ru' => 'Босоножки', 'ka' => 'ქუსლიანი სანდლები'],
        'παντοφλεσ' => ['en' => 'Slippers', 'ru' => 'Шлёпанцы', 'ka' => 'ჩუსტები'],
        'σαγιοναρεσ' => ['en' => 'Flip-Flops', 'ru' => 'Сланцы', 'ka' => 'შლოპანცები'],
        'μπαλαρινεσ' => ['en' => 'Ballet Flats', 'ru' => 'Балетки', 'ka' => 'ბალეტკები'],
        'γοβεσ' => ['en' => 'Pumps', 'ru' => 'Туфли-лодочки', 'ka' => 'ქუსლიანი ფეხსაცმელი'],
        'μοκασινια' => ['en' => 'Loafers', 'ru' => 'Лоферы', 'ka' => 'ლოფერები'],
        'εσπαντριγιεσ' => ['en' => 'Espadrilles', 'ru' => 'Эспадрильи', 'ka' => 'ესპადრილები'],
        'πεζοποριασ' => ['en' => 'Hiking', 'ru' => 'Треккинговые', 'ka' => 'სალაშქრო'],
        'ορειβατικα' => ['en' => 'Trekking', 'ru' => 'Горные', 'ka' => 'სამთო'],
        'τρεξιματοσ' => ['en' => 'Running', 'ru' => 'Беговые', 'ka' => 'სარბენი'],
        'τρεξιμο' => ['en' => 'Running', 'ru' => 'Бег', 'ka' => 'სირბილი'],
        'ποδοσφαιρου' => ['en' => 'Football', 'ru' => 'Футбольные', 'ka' => 'ფეხბურთის'],
        'μπασκετ' => ['en' => 'Basketball', 'ru' => 'Баскетбольные', 'ka' => 'კალათბურთის'],

        // Одежда и аксессуары
        'ρουχα' => ['en' => 'Clothing', 'ru' => 'Одежда', 'ka' => 'ტანსაცმელი'],
        'φορεμα' => ['en' => 'Dress', 'ru' => 'Платье', 'ka' => 'კაბა'],
        'φορεματα' => ['en' => 'Dresses', 'ru' => 'Платья', 'ka' => 'კაბები'],
        'φουστα' => ['en' => 'Skirt', 'ru' => 'Юбка', 'ka' => 'ქვედაბოლო'],
        'φουστεσ' => ['en' => 'Skirts', 'ru' => 'Юбки', 'ka' => 'ქვედაბოლოები'],
        'παντελονι' => ['en' => 'Pants', 'ru' => 'Брюки', 'ka' => 'შარვალი'],
        'παντελονια' => ['en' => 'Pants', 'ru' => 'Брюки', 'ka' => 'შარვლები'],
        'τζιν' => ['en' => 'Jeans', 'ru' => 'Джинсы', 'ka' => 'ჯინსი'],
        'κολαν' => ['en' => 'Leggings', 'ru' => 'Леггинсы', 'ka' => 'ლეგინსები'],
        'σορτσ' => ['en' => 'Shorts', 'ru' => 'Шорты', 'ka' => 'შორტი'],
        'βερμουδα' => ['en' => 'Bermuda Shorts', 'ru' => 'Бермуды', 'ka' => 'ბერმუდა'],
        'μπλουζα' => ['en' => 'Top / Blouse', 'ru' => 'Блузка / Лонгслив', 'ka' => 'ბლუზა / მაისური'],
        'μπλουζεσ' => ['en' => 'Tops / Blouses', 'ru' => 'Блузки', 'ka' => 'ბლუზები'],
        'μπλουζακι' => ['en' => 'T-Shirt', 'ru' => 'Футболка', 'ka' => 'მაისური'],
        'πουκαμισο' => ['en' => 'Shirt', 'ru' => 'Рубашка', 'ka' => 'პერანგი'],
        'πουκαμισα' => ['en' => 'Shirts', 'ru' => 'Рубашки', 'ka' => 'პერანგები'],
        'πουλοβερ' => ['en' => 'Sweater', 'ru' => 'Свитер', 'ka' => 'სვიტერი'],
        'πλεκτο' => ['en' => 'Knitwear', 'ru' => 'Трикотаж', 'ka' => 'ნაქსოვი'],
        'φουτερ' => ['en' => 'Sweatshirt / Hoodie', 'ru' => 'Худи / Свитшот', 'ka' => 'ჰუდი / სვიტშოტი'],
        'ζακετα' => ['en' => 'Cardigan / Zip Hoodie', 'ru' => 'Кардиган / Толстовка', 'ka' => 'ჟაკეტი'],
        'ζακετεσ' => ['en' => 'Cardigans', 'ru' => 'Кардиганы', 'ka' => 'ჟაკეტები'],
        'μπουφαν' => ['en' => 'Jacket', 'ru' => 'Куртка', 'ka' => 'ქურთუკი'],
        'παλτο' => ['en' => 'Coat', 'ru' => 'Пальто', 'ka' => 'პალტო'],
        'σακακι' => ['en' => 'Blazer', 'ru' => 'Пиджак', 'ka' => 'პიჯაკი'],
        'γιλεκο' => ['en' => 'Vest', 'ru' => 'Жилет', 'ka' => 'ჟილეტი'],
        'φορμα' => ['en' => 'Tracksuit / Jumpsuit', 'ru' => 'Костюм / Комбинезон', 'ka' => 'კომბინეზონი / სპორტული'],
        'ολοσωμη' => ['en' => 'Jumpsuit', 'ru' => 'Комбинезон', 'ka' => 'კომბინეზონი'],
        'μαγιο' => ['en' => 'Swimwear', 'ru' => 'Купальник / Плавки', 'ka' => 'საცურაო კოსტიუმი'],
        'μπικινι' => ['en' => 'Bikini', 'ru' => 'Бикини', 'ka' => 'ბიკინი'],
        'εσωρουχα' => ['en' => 'Underwear', 'ru' => 'Бельё', 'ka' => 'თეთრეული'],
        'σουτιεν' => ['en' => 'Bra', 'ru' => 'Бюстгальтер', 'ka' => 'ბიუსტჰალტერი'],
        'καλτσεσ' => ['en' => 'Socks', 'ru' => 'Носки', 'ka' => 'წინდები'],
        'τσαντα' => ['en' => 'Bag', 'ru' => 'Сумка', 'ka' => 'ჩანთა'],
        'τσαντεσ' => ['en' => 'Bags', 'ru' => 'Сумки', 'ka' => 'ჩანთები'],
        'σακιδιο' => ['en' => 'Backpack', 'ru' => 'Рюкзак', 'ka' => 'ზურგჩანთა'],
        'πορτοφολι' => ['en' => 'Wallet', 'ru' => 'Кошелёк', 'ka' => 'საფულე'],
        'ζωνη' => ['en' => 'Belt', 'ru' => 'Ремень', 'ka' => 'ქამარი'],
        'καπελο' => ['en' => 'Cap / Hat', 'ru' => 'Кепка / Шляпа', 'ka' => 'ქუდი'],
        'σκουφοσ' => ['en' => 'Beanie', 'ru' => 'Шапка', 'ka' => 'ქუდი'],
        'κασκολ' => ['en' => 'Scarf', 'ru' => 'Шарф', 'ka' => 'შარფი'],
        'γαντια' => ['en' => 'Gloves', 'ru' => 'Перчатки', 'ka' => 'ხელთათმანები'],
        'ρολοι' => ['en' => 'Watch', 'ru' => 'Часы', 'ka' => 'საათი'],
        'ρολογια' => ['en' => 'Watches', 'ru' => 'Часы', 'ka' => 'საათები'],
        'γυαλια' => ['en' => 'Glasses', 'ru' => 'Очки', 'ka' => 'სათვალე'],
        'ηλιου' => ['en' => 'Sunglasses', 'ru' => 'Солнцезащитные', 'ka' => 'მზის'],
        'σκελετοσ' => ['en' => 'Frame', 'ru' => 'Оправа', 'ka' => 'ჩარჩო'],
        'σκελετοι' => ['en' => 'Frames', 'ru' => 'Оправы', 'ka' => 'ჩარჩოები'],
        'γυαλιων' => ['en' => 'Eyewear', 'ru' => 'для очков', 'ka' => 'სათვალის'],
        'αξεσουαρ' => ['en' => 'Accessories', 'ru' => 'Аксессуары', 'ka' => 'აქსესუარები'],
        'πετσετα' => ['en' => 'Towel', 'ru' => 'Полотенце', 'ka' => 'პირსახოცი'],
        'θαλασσησ' => ['en' => 'Beach', 'ru' => 'Пляжное', 'ka' => 'პლაჟის'],
        'κοντο' => ['en' => 'Short', 'ru' => 'Короткое', 'ka' => 'მოკლე'],
        'μακρυ' => ['en' => 'Long', 'ru' => 'Длинное', 'ka' => 'გრძელი'],
        'χαμηλα' => ['en' => 'Low', 'ru' => 'Низкие', 'ka' => 'დაბალი'],
        'ψηλα' => ['en' => 'High', 'ru' => 'Высокие', 'ka' => 'მაღალი'],
        'ντεκολτε' => ['en' => 'Neckline', 'ru' => 'с декольте', 'ka' => 'დეკოლტეთი'],
        'στεκα' => ['en' => 'Headband', 'ru' => 'ободок', 'ka' => 'აბადოკი'],
        'κεντημενα' => ['en' => 'Embroidered', 'ru' => 'вышитые', 'ka' => 'ნაქარგი'],
        'πουα' => ['en' => 'Polka Dot', 'ru' => 'в горошек', 'ka' => 'კოპლებიანი'],

        // Косметика, Парфюмерия и Аптека
        'αρωμα' => ['en' => 'Fragrance', 'ru' => 'Аромат', 'ka' => 'სუნამო'],
        'αρωματα' => ['en' => 'Fragrances', 'ru' => 'Парфюмерия', 'ka' => 'სუნამოები'],
        'μακιγιαζ' => ['en' => 'Makeup', 'ru' => 'Макияж', 'ka' => 'მაკიაჟი'],
        'κρεμα' => ['en' => 'Cream', 'ru' => 'Крем', 'ka' => 'კრემი'],
        'οροσ' => ['en' => 'Serum', 'ru' => 'Сыворотка', 'ka' => 'შრატი'],
        'λαδι' => ['en' => 'Oil', 'ru' => 'Масло', 'ka' => 'ზეთი'],
        'ελαιο' => ['en' => 'Oil', 'ru' => 'Масло', 'ka' => 'ზეთი'],
        'μασκα' => ['en' => 'Mask', 'ru' => 'Маска', 'ka' => 'ნიღაბი'],
        'μασκεσ' => ['en' => 'Masks', 'ru' => 'Маски', 'ka' => 'ნიღბები'],
        'σαμπουαν' => ['en' => 'Shampoo', 'ru' => 'Шампунь', 'ka' => 'შამპუნი'],
        'αφρολουτρο' => ['en' => 'Shower Gel', 'ru' => 'Гель для душа', 'ka' => 'შხაპის გელი'],
        'λοσιον' => ['en' => 'Lotion', 'ru' => 'Лосьон', 'ka' => 'ლოსიონი'],
        'γαλακτωμα' => ['en' => 'Emulsion / Milk', 'ru' => 'Молочко / Эмульсия', 'ka' => 'ემულსია'],
        'τζελ' => ['en' => 'Gel', 'ru' => 'Гель', 'ka' => 'გელი'],
        'κραγιον' => ['en' => 'Lipstick', 'ru' => 'Помада', 'ka' => 'პომადა'],
        'ρουζ' => ['en' => 'Blush', 'ru' => 'Румяна', 'ka' => 'რუჟი'],
        'πουδρα' => ['en' => 'Powder', 'ru' => 'Пудра', 'ka' => 'პუდრი'],
        'σκιεσ' => ['en' => 'Eyeshadow', 'ru' => 'Тени для век', 'ka' => 'თვალის ჩრდილი'],
        'παλετα' => ['en' => 'Palette', 'ru' => 'Палетка', 'ka' => 'პალიტრა'],
        'μολυβι' => ['en' => 'Pencil', 'ru' => 'Карандаш', 'ka' => 'ფანქარი'],
        'χειλιων' => ['en' => 'Lip', 'ru' => 'для губ', 'ka' => 'ტუჩის'],
        'χειλη' => ['en' => 'Lips', 'ru' => 'Губы', 'ka' => 'ტუჩები'],
        'ματιων' => ['en' => 'Eye', 'ru' => 'для глаз', 'ka' => 'თვალის'],
        'ματια' => ['en' => 'Eyes', 'ru' => 'Глаза', 'ka' => 'თვალები'],
        'φρυδιων' => ['en' => 'Eyebrow', 'ru' => 'для бровей', 'ka' => 'წარბის'],
        'προσωπου' => ['en' => 'Face', 'ru' => 'для лица', 'ka' => 'სახის'],
        'προσωπο' => ['en' => 'Face', 'ru' => 'Лицо', 'ka' => 'სახე'],
        'σωματοσ' => ['en' => 'Body', 'ru' => 'для тела', 'ka' => 'სხეულის'],
        'σωμα' => ['en' => 'Body', 'ru' => 'Тело', 'ka' => 'სხეული'],
        'μαλλιων' => ['en' => 'Hair', 'ru' => 'для волос', 'ka' => 'თმის'],
        'μαλλια' => ['en' => 'Hair', 'ru' => 'Волосы', 'ka' => 'თმა'],
        'χεριων' => ['en' => 'Hand', 'ru' => 'для рук', 'ka' => 'ხელის'],
        'ημερασ' => ['en' => 'Day', 'ru' => 'дневной', 'ka' => 'დღის'],
        'νυκτοσ' => ['en' => 'Night', 'ru' => 'ночной', 'ka' => 'ღამის'],
        'νυχτασ' => ['en' => 'Night', 'ru' => 'ночной', 'ka' => 'ღამის'],
        'ενυδατωση' => ['en' => 'Hydration', 'ru' => 'увлажнение', 'ka' => 'დატენიანება'],
        'ενυδατωσησ' => ['en' => 'Moisturizing', 'ru' => 'увлажняющий', 'ka' => 'დამატენიანებელი'],
        'ενυδατικο' => ['en' => 'Moisturizing', 'ru' => 'Увлажняющее', 'ka' => 'დამატენიანებელი'],
        'ενυδατικη' => ['en' => 'Moisturizing', 'ru' => 'Увлажняющая', 'ka' => 'დამატენიანებელი'],
        'καθαρισμου' => ['en' => 'Cleansing', 'ru' => 'очищающий', 'ka' => 'გამწმენდი'],
        'καθαρισμοσ' => ['en' => 'Cleansing', 'ru' => 'Очищение', 'ka' => 'წმენდა'],
        'αντιγηρανση' => ['en' => 'Anti-Aging', 'ru' => 'антивозрастной уход', 'ka' => 'ანტიასაკობრივი'],
        'αντιγηραντικη' => ['en' => 'Anti-Aging', 'ru' => 'Антивозрастная', 'ka' => 'ანტიასაკობრივი'],
        'αντηλιακο' => ['en' => 'Sunscreen', 'ru' => 'Солнцезащитное средство', 'ka' => 'მზისგან დამცავი'],
        'αντηλιακη' => ['en' => 'Sunscreen', 'ru' => 'Солнцезащитный', 'ka' => 'მზისგან დამცავი'],
        'ενισχυση' => ['en' => 'Strengthening', 'ru' => 'укрепление', 'ka' => 'გაძლიერება'],
        'προστασια' => ['en' => 'Protection', 'ru' => 'защита', 'ka' => 'დაცვა'],
        'λαμψη' => ['en' => 'Glow', 'ru' => 'сияние', 'ka' => 'ბზინვარება'],
        'λαμψησ' => ['en' => 'Radiance', 'ru' => 'для сияния', 'ka' => 'ბზინვარების'],
        'επιδερμιδασ' => ['en' => 'Skin', 'ru' => 'кожи', 'ka' => 'კანის'],
        'επιδερμικου' => ['en' => 'Skin', 'ru' => 'кожного', 'ka' => 'კანის'],
        'φραγμου' => ['en' => 'Barrier', 'ru' => 'барьера', 'ka' => 'ბარიერის'],
        'περιποιηση' => ['en' => 'Care', 'ru' => 'Уход', 'ka' => 'მოვლა'],
        'περιποιησησ' => ['en' => 'Care', 'ru' => 'для ухода', 'ka' => 'მოვლის'],
        'φροντιδα' => ['en' => 'Care', 'ru' => 'Уход', 'ka' => 'მოვლა'],
        'συμπληρωμα' => ['en' => 'Supplement', 'ru' => 'Добавка', 'ka' => 'დანამატი'],
        'συμπληρωματα' => ['en' => 'Supplements', 'ru' => 'Добавки', 'ka' => 'დანამატები'],
        'διατροφησ' => ['en' => 'Dietary', 'ru' => 'пищевая', 'ka' => 'საკვები'],
        'βιταμινη' => ['en' => 'Vitamin', 'ru' => 'Витамин', 'ka' => 'ვიტამინი'],
        'βιταμινεσ' => ['en' => 'Vitamins', 'ru' => 'Витамины', 'ka' => 'ვიტამინები'],
        'πολυβιταμινεσ' => ['en' => 'Multivitamins', 'ru' => 'Мультивитамины', 'ka' => 'მულტივიტამინები'],
        'προπολη' => ['en' => 'Propolis', 'ru' => 'Прополис', 'ka' => 'პროპოლისი'],
        'καραμελεσ' => ['en' => 'Lozenges / Candies', 'ru' => 'Леденцы', 'ka' => 'კანფეტები'],
        'βουρτσακια' => ['en' => 'Brushes', 'ru' => 'Щёточки', 'ka' => 'ჯაგრისები'],
        'μεσοδοντια' => ['en' => 'Interdental', 'ru' => 'Межзубные', 'ka' => 'კბილთაშორისი'],
        'υγιεινη' => ['en' => 'Hygiene', 'ru' => 'Гигиена', 'ka' => 'ჰიგიენა'],
        'υγεια' => ['en' => 'Health', 'ru' => 'Здоровье', 'ka' => 'ჯანმრთელობა'],
        'οφθαλμων' => ['en' => 'Eye', 'ru' => 'глаз', 'ka' => 'თვალის'],
        'μπαρα' => ['en' => 'Bar', 'ru' => 'Батончик', 'ka' => 'ბატონი'],
        'μπαρεσ' => ['en' => 'Bars', 'ru' => 'Батончики', 'ka' => 'ბატონები'],

        // Цвета
        'μαυρο' => ['en' => 'Black', 'ru' => 'Чёрный', 'ka' => 'შავი'],
        'μαυροσ' => ['en' => 'Black', 'ru' => 'Чёрный', 'ka' => 'შავი'],
        'μαυρη' => ['en' => 'Black', 'ru' => 'Чёрный', 'ka' => 'შავი'],
        'λευκο' => ['en' => 'White', 'ru' => 'Белый', 'ka' => 'თეთრი'],
        'λευκοσ' => ['en' => 'White', 'ru' => 'Белый', 'ka' => 'თეთრი'],
        'λευκη' => ['en' => 'White', 'ru' => 'Белый', 'ka' => 'თეთრი'],
        'ασπρο' => ['en' => 'White', 'ru' => 'Белый', 'ka' => 'თეთრი'],
        'μπεζ' => ['en' => 'Beige', 'ru' => 'Бежевый', 'ka' => 'კრემისფერი'],
        'γκρι' => ['en' => 'Grey', 'ru' => 'Серый', 'ka' => 'ნაცრისფერი'],
        'μπλε' => ['en' => 'Blue', 'ru' => 'Синий', 'ka' => 'ლურჯი'],
        'μαρεν' => ['en' => 'Navy Blue', 'ru' => 'Тёмно-синий', 'ka' => 'მუქი ლურჯი'],
        'κοκκινο' => ['en' => 'Red', 'ru' => 'Красный', 'ka' => 'წითელი'],
        'κοκκινη' => ['en' => 'Red', 'ru' => 'Красный', 'ka' => 'წითელი'],
        'κερασι' => ['en' => 'Cherry', 'ru' => 'Вишнёвый', 'ka' => 'ალუბლისფერი'],
        'κερασσι' => ['en' => 'Cherry', 'ru' => 'Вишнёвый', 'ka' => 'ალუბლისფერი'],
        'μπορντο' => ['en' => 'Burgundy', 'ru' => 'Бордовый', 'ka' => 'შინდისფერი'],
        'ροζ' => ['en' => 'Pink', 'ru' => 'Розовый', 'ka' => 'ვარდისფერი'],
        'φουξια' => ['en' => 'Fuchsia', 'ru' => 'Фуксия', 'ka' => 'ფუქსია'],
        'πρασινο' => ['en' => 'Green', 'ru' => 'Зелёный', 'ka' => 'მწვანე'],
        'πρασινη' => ['en' => 'Green', 'ru' => 'Зелёный', 'ka' => 'მწვანე'],
        'χακι' => ['en' => 'Khaki', 'ru' => 'Хаки', 'ka' => 'ხაკისფერი'],
        'κιτρινο' => ['en' => 'Yellow', 'ru' => 'Жёлтый', 'ka' => 'ყვითელი'],
        'κιτρινη' => ['en' => 'Yellow', 'ru' => 'Жёлтый', 'ka' => 'ყვითელი'],
        'παστελ' => ['en' => 'Pastel', 'ru' => 'Пастельный', 'ka' => 'პასტელური'],
        'βανιλια' => ['en' => 'Vanilla', 'ru' => 'Ванильный', 'ka' => 'ვანილისფერი'],
        'πορτοκαλι' => ['en' => 'Orange', 'ru' => 'Оранжевый', 'ka' => 'ნარინჯისფერი'],
        'μωβ' => ['en' => 'Purple', 'ru' => 'Фиолетовый', 'ka' => 'იასამნისფერი'],
        'λιλα' => ['en' => 'Lilac', 'ru' => 'Сиреневый', 'ka' => 'იასამნისფერი'],
        'καφε' => ['en' => 'Brown', 'ru' => 'Коричневый', 'ka' => 'ყავისფერი'],
        'ταμπα' => ['en' => 'Tan / Camel', 'ru' => 'Рыже-коричневый', 'ka' => 'აქლემისფერი'],
        'χρυσο' => ['en' => 'Gold', 'ru' => 'Золотой', 'ka' => 'ოქროსფერი'],
        'ασημι' => ['en' => 'Silver', 'ru' => 'Серебряный', 'ka' => 'ვერცხლისფერი'],
        'εκρου' => ['en' => 'Ecru', 'ru' => 'Экрю', 'ka' => 'ეკრიუ'],
        'πολυχρωμο' => ['en' => 'Multicolor', 'ru' => 'Разноцветный', 'ka' => 'ფერადი'],
        'ταρταρουγα' => ['en' => 'Tortoiseshell', 'ru' => 'Черепаховый', 'ka' => 'კუსებრი'],
        'διαφανο' => ['en' => 'Transparent', 'ru' => 'Прозрачный', 'ka' => 'გამჭვირვალე'],
    ];

    /**
     * Цвета, которые часто приклеиваются в конец греческого заголовка.
     *
     * @var string[]
     */
    private const COLOR_NORMS = [
        'μαυρο', 'μαυροσ', 'μαυρη', 'λευκο', 'λευκοσ', 'λευκη', 'ασπρο', 'μπεζ', 'γκρι', 'μπλε',
        'μαρεν', 'κοκκινο', 'κοκκινη', 'κερασι', 'κερασσι', 'μπορντο', 'ροζ', 'φουξια', 'πρασινο',
        'πρασινη', 'χακι', 'κιτρινο', 'κιτρινη', 'βανιλια', 'πορτοκαλι', 'μωβ', 'λιλα', 'καφε',
        'ταμπα', 'χρυσο', 'ασημι', 'εκρου', 'πολυχρωμο', 'ταρταρουγα', 'διαφανο',
    ];

    /**
     * Извлекает непрерывные греческие спаны из любой строки (заголовка или характеристики).
     *
     * @return string[]
     */
    public static function extractGreekSpans(string $text): array
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!self::containsGreek($text)) {
            return [];
        }

        preg_match_all('/[\x{0370}-\x{03FF}\x{1F00}-\x{1FFF}]+(?:\s+[\x{0370}-\x{03FF}\x{1F00}-\x{1FFF}]+)*/u', $text, $matches);
        $rawSpans = $matches[0] ?? [];

        $result = [];
        foreach ($rawSpans as $span) {
            foreach (self::splitTrailingColorFromSpan(trim($span)) as $part) {
                $part = trim($part);
                if ($part !== '' && mb_strlen($part) >= 2) {
                    $result[] = $part;
                }
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @return string[]
     */
    private static function splitTrailingColorFromSpan(string $span): array
    {
        $words = preg_split('/\s+/u', trim($span), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) <= 1) {
            return [$span];
        }

        $lastNorm = EntityTranslation::normalizeKey((string) end($words));
        if (in_array($lastNorm, self::COLOR_NORMS, true)) {
            $colorWord = (string) array_pop($words);
            if ($words !== [] && EntityTranslation::normalizeKey((string) end($words)) === 'παστελ') {
                $colorWord = array_pop($words) . ' ' . $colorWord;
            }
            $head = trim(implode(' ', $words));

            return $head !== '' ? [$head, $colorWord] : [$colorWord];
        }

        return [$span];
    }

    /**
     * @return array{prefix: string, model: string}|null
     */
    public static function splitGreekTitle(string $name): ?array
    {
        $spans = self::extractGreekSpans($name);
        if ($spans === []) {
            return null;
        }

        $primarySpan = $spans[0];
        $norm = EntityTranslation::normalizeKey($primarySpan);
        if ($norm === '' || in_array($norm, self::GREEK_STOP_ONLY, true)) {
            return null;
        }

        $model = trim(str_replace($primarySpan, '', $name));
        $model = trim((string) preg_replace('/\s+/u', ' ', $model));

        return [
            'prefix' => mb_substr($primarySpan, 0, 120),
            'model' => $model,
        ];
    }

    /**
     * Переводит любую строку (заголовок, цвет, характеристику) через словарь Gemini (entity_translations) и CORE_LEXICON.
     * Сперва проверяет перевод всей строки целиком, затем переводит греческие спаны на месте.
     *
     * @param array<string, array<string, string|EntityTranslation>> $dict
     */
    public static function translateTextWithDictionary(string $text, string $locale, array $dict = []): string
    {
        if ($locale === 'el' || $text === '') {
            return $text;
        }

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 1. Сначала проверяем, есть ли в entity_translations готовый перевод всей строки целиком
        // (до проверки containsGreek, чтобы переводились английские цвета и specs вроде "Core Black")
        $fullMatch = self::lookupInDict($text, $locale, $dict);
        if ($fullMatch !== null && !self::containsGreek($fullMatch)) {
            return $fullMatch;
        }

        if (!self::containsGreek($text)) {
            return $text;
        }

        // 2. Иначе переводим каждый греческий спан внутри строки
        $translated = (string) preg_replace_callback(
            '/[\x{0370}-\x{03FF}\x{1F00}-\x{1FFF}]+(?:\s+[\x{0370}-\x{03FF}\x{1F00}-\x{1FFF}]+)*/u',
            function (array $m) use ($locale, $dict): string {
                $fullSpan = trim($m[0]);

                // Проверяем спан целиком до отделения цвета
                $direct = self::lookupInDict($fullSpan, $locale, $dict);
                if ($direct !== null && !self::containsGreek($direct)) {
                    return $direct;
                }

                $parts = self::splitTrailingColorFromSpan($fullSpan);
                $outParts = [];
                foreach ($parts as $part) {
                    $outParts[] = self::translateSingleSpan($part, $locale, $dict);
                }

                return trim(implode(' ', array_filter($outParts, fn ($s) => $s !== '')));
            },
            $text
        );

        return trim((string) preg_replace('/\s+/u', ' ', $translated));
    }

    /**
     * Переводит один греческий спан через:
     * 1) Полное совпадение в entity_translations (Gemini LLM)
     * 2) CORE_LEXICON
     * 3) Сборку из подфраз entity_translations и слов CORE_LEXICON (без фальшивой транслитерации!)
     */
    private static function translateSingleSpan(string $span, string $locale, array $dict): string
    {
        $span = trim($span);
        if ($span === '') {
            return '';
        }

        // 1. Точное совпадение в базе Gemini (entity_translations)
        $exact = self::lookupInDict($span, $locale, $dict);
        if ($exact !== null && !self::containsGreek($exact)) {
            return $exact;
        }

        // 2. Точное совпадение в CORE_LEXICON
        $norm = EntityTranslation::normalizeKey($span);
        if (isset(self::CORE_LEXICON[$norm][$locale])) {
            return self::CORE_LEXICON[$norm][$locale];
        }

        // 3. Пофразовая и пословная сборка из уже известных переводов Gemini и CORE_LEXICON
        $words = preg_split('/\s+/u', $span, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $count = count($words);
        $resultWords = [];
        $i = 0;

        while ($i < $count) {
            $matchedPhrase = null;
            $matchedLen = 0;

            $maxWindow = min(6, $count - $i);
            for ($len = $maxWindow; $len >= 2; $len--) {
                $subPhrase = implode(' ', array_slice($words, $i, $len));
                $found = self::lookupInDict($subPhrase, $locale, $dict);
                if ($found !== null && !self::containsGreek($found)) {
                    $matchedPhrase = $found;
                    $matchedLen = $len;
                    break;
                }
            }

            if ($matchedPhrase !== null) {
                $resultWords[] = $matchedPhrase;
                $i += $matchedLen;
                continue;
            }

            $word = $words[$i];
            $wNorm = EntityTranslation::normalizeKey($word);

            $singleDict = self::lookupInDict($word, $locale, $dict);
            if ($singleDict !== null && !self::containsGreek($singleDict)) {
                $resultWords[] = $singleDict;
            } elseif (isset(self::CORE_LEXICON[$wNorm][$locale])) {
                $lexVal = self::CORE_LEXICON[$wNorm][$locale];
                if ($lexVal !== '') {
                    $resultWords[] = $lexVal;
                }
            } else {
                $stemmed = self::lookupStemInLexicon($wNorm, $locale);
                $resultWords[] = $stemmed ?? $word;
            }

            $i++;
        }

        $joined = trim(implode(' ', array_filter($resultWords, fn ($w) => $w !== '')));

        return $joined !== '' ? $joined : $span;
    }

    private static function lookupInDict(string $phrase, string $locale, array $dict): ?string
    {
        $h = EntityTranslation::hash($phrase);

        foreach (['spec', 'title_prefix', 'color', 'category_segment'] as $type) {
            if (!isset($dict[$type][$h])) {
                continue;
            }
            $entry = $dict[$type][$h];
            $val = is_object($entry) && method_exists($entry, 'forLocale')
                ? $entry->forLocale($locale)
                : (is_string($entry) ? $entry : null);

            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
        }

        return null;
    }

    private static function lookupStemInLexicon(string $wNorm, string $locale): ?string
    {
        if (mb_strlen($wNorm) < 4) {
            return null;
        }

        $stemCandidates = [
            preg_replace('/(ων|ουσ|εσ|ασ|οσ|ησ|ου|ων)$/u', '', $wNorm),
            preg_replace('/(ων|ουσ|εσ|ασ|οσ|ησ|ου)$/u', 'ο', $wNorm),
            preg_replace('/(ων|ουσ|εσ|ασ|οσ|ησ|ου)$/u', 'α', $wNorm),
            preg_replace('/(ων|ουσ|εσ|ασ|οσ|ησ|ου)$/u', 'η', $wNorm),
            preg_replace('/(ων|ουσ|εσ|ασ|οσ|ησ|ου)$/u', 'ασ', $wNorm),
            preg_replace('/(ων|ουσ|εσ|ασ|οσ|ησ|ου)$/u', 'οσ', $wNorm),
        ];

        foreach ($stemCandidates as $cand) {
            if ($cand && isset(self::CORE_LEXICON[$cand][$locale]) && self::CORE_LEXICON[$cand][$locale] !== '') {
                return self::CORE_LEXICON[$cand][$locale];
            }
        }

        return null;
    }

    public static function containsGreek(string $text): bool
    {
        return (bool) preg_match('/[\x{0370}-\x{03FF}\x{1F00}-\x{1FFF}]/u', $text);
    }

    /**
     * Локализация полного товара для детальной страницы.
     * Если на странице товара встретился редкий непереведённый греческий спан,
     * мгновенно переводит его через Gemini (TranslateFeedEntitiesJob::dispatchSync) и кэширует в БД.
     *
     * @return array{title: string, colour: ?string, specs: string[], categories: string[]}
     */
    public function localizeProduct(FeedProduct $product, string $locale): array
    {
        $rawCategories = array_values(array_filter(array_map('trim', explode(',', (string) $product->category_path))));
        $rawSpecs = array_values(array_filter(array_map('trim', (array) ($product->specs ?? []))));
        $rawColour = trim((string) ($product->colour ?? ''));
        $rawTitle = (string) $product->name;

        if ($locale === 'el') {
            return [
                'title' => $rawTitle,
                'colour' => $rawColour !== '' ? $rawColour : null,
                'specs' => $rawSpecs,
                'categories' => $rawCategories,
            ];
        }

        $dict = $this->buildProductDictionary($rawTitle, $rawColour, $rawSpecs, $rawCategories, $locale);

        $title = self::translateTextWithDictionary($rawTitle, $locale, $dict);

        $colorParts = $rawColour !== ''
            ? array_values(array_filter(array_map('trim', preg_split('/\s*[\/,]\s*/u', $rawColour) ?: [])))
            : [];
        $colour = $colorParts !== []
            ? implode(' / ', array_map(fn ($c) => self::translateTextWithDictionary($c, $locale, $dict), $colorParts))
            : null;

        $specs = array_map(fn ($s) => self::translateTextWithDictionary($s, $locale, $dict), $rawSpecs);
        $categories = array_map(fn ($c) => self::translateTextWithDictionary($c, $locale, $dict), $rawCategories);

        // Если после словаря остались редкие непереведённые греческие фразы — переводим их через Gemini на лету
        $leftoverSpans = [];
        foreach (array_merge([$title, (string) $colour], $specs, $categories) as $textItem) {
            foreach (self::extractGreekSpans($textItem) as $untranslatedSpan) {
                $leftoverSpans[EntityTranslation::hash($untranslatedSpan)] = $untranslatedSpan;
            }
        }

        // Отправляем неизвестные фразы в фон. Страница загрузится за 50 мс мгновенно, 
        // а воркер спокойно переведет их для следующих посетителей.
if ($leftoverSpans !== []) {
    $toQueue = [];
    foreach ($leftoverSpans as $hash => $span) {
        if (\Illuminate\Support\Facades\Cache::add("ai_translate_queued:{$hash}", 1, now()->addHours(6))) {
            $toQueue[] = $span;
        }
    }

    if ($toQueue !== []) {
        TranslateFeedEntitiesJob::dispatch('title_prefix', $toQueue);
    }
}

        return [
            'title' => $title,
            'colour' => $colour,
            'specs' => $specs,
            'categories' => $categories,
        ];
    }

    /**
     * @param string[] $rawSpecs
     * @param string[] $rawCategories
     * @return array<string, array<string, string>>
     */
    private function buildProductDictionary(string $rawTitle, string $rawColour, array $rawSpecs, array $rawCategories, string $locale): array
    {
        $allHashes = [];

        foreach (self::extractGreekSpans($rawTitle) as $s) {
            $allHashes[] = EntityTranslation::hash($s);
        }

        if ($rawColour !== '') {
            foreach (preg_split('/\s*[\/,]\s*/u', $rawColour) ?: [] as $c) {
                $c = trim($c);
                if ($c !== '') {
                    $allHashes[] = EntityTranslation::hash($c);
                }
            }
        }

        foreach ($rawSpecs as $spec) {
            $allHashes[] = EntityTranslation::hash($spec);
            foreach (self::extractGreekSpans($spec) as $s) {
                $allHashes[] = EntityTranslation::hash($s);
            }
        }

        foreach ($rawCategories as $cat) {
            $allHashes[] = EntityTranslation::hash($cat);
            foreach (self::extractGreekSpans($cat) as $s) {
                $allHashes[] = EntityTranslation::hash($s);
            }
        }

        $uniqueHashes = array_values(array_unique($allHashes));

        return [
            'title_prefix' => $this->getMap('title_prefix', $uniqueHashes, $locale),
            'color' => $this->getMap('color', $uniqueHashes, $locale),
            'spec' => $this->getMap('spec', $uniqueHashes, $locale),
            'category_segment' => $this->getMap('category_segment', $uniqueHashes, $locale),
        ];
    }

    /**
     * Пакетная локализация заголовков и цветов для карточек каталога/поиска.
     */
    public function localizeCardTitles(array $cards, string $locale): array
    {
        if ($cards === [] || $locale === 'el') {
            return $cards;
        }

        $allHashes = [];

        foreach ($cards as $card) {
            $rawTitle = (string) ($card['title'] ?? '');
            if ($rawTitle !== '') {
                $allHashes[] = EntityTranslation::hash($rawTitle);
                foreach (self::extractGreekSpans($rawTitle) as $span) {
                    $allHashes[] = EntityTranslation::hash($span);
                }
            }

            $rawColour = (string) ($card['colour'] ?? '');
            if ($rawColour !== '') {
                foreach (preg_split('/\s*[\/,]\s*/u', $rawColour) ?: [] as $cPart) {
                    $cPart = trim($cPart);
                    if ($cPart !== '') {
                        $allHashes[] = EntityTranslation::hash($cPart);
                    }
                }
            }
        }

        $uniqueHashes = array_values(array_unique($allHashes));

        $dict = [
            'title_prefix' => $this->getMap('title_prefix', $uniqueHashes, $locale),
            'color' => $this->getMap('color', $uniqueHashes, $locale),
            'category_segment' => $this->getMap('category_segment', $uniqueHashes, $locale),
            'spec' => $this->getMap('spec', $uniqueHashes, $locale),
        ];

        foreach ($cards as $i => $card) {
            $rawTitle = (string) ($card['title'] ?? '');
            if ($rawTitle !== '' && self::containsGreek($rawTitle)) {
                $cards[$i]['title'] = self::translateTextWithDictionary($rawTitle, $locale, $dict);
            }

            $rawColour = (string) ($card['colour'] ?? '');
            if ($rawColour !== '' && self::containsGreek($rawColour)) {
                $cards[$i]['colour'] = self::translateTextWithDictionary($rawColour, $locale, $dict);
            }
        }

        return $cards;
    }

    /**
     * Собирает непереведённые греческие фразы и спаны (включая спаны внутри specs) для Gemini LLM.
     *
     * @param array<string, int> $minCounts
     * @return array<string, string[]>
     */
    public function collectUntranslatedEntities(?string $feedKey = null, array $minCounts = []): array
    {
        $this->syncCategoryMappings($feedKey);

        $thresholds = array_merge([
            'category_segment' => 1,
            'color' => 1,
            'title_prefix' => 2,
            'spec' => 2,
        ], $minCounts);

        $query = FeedProduct::query()->where('is_active', true);
        if ($feedKey !== null) {
            $query->where('feed', $feedKey);
        }

        $candidates = [
            'category_segment' => [],
            'color' => [],
            'title_prefix' => [],
            'spec' => [],
        ];

        $counts = [
            'category_segment' => [],
            'color' => [],
            'title_prefix' => [],
            'spec' => [],
        ];

        $query->select(['id', 'name', 'colour', 'category_path', 'specs'])
            ->chunkById(2000, function ($rows) use (&$candidates, &$counts) {
                foreach ($rows as $row) {
                    foreach (explode(',', (string) $row->category_path) as $seg) {
                        $seg = trim($seg);
                        if ($seg !== '' && self::containsGreek($seg) && mb_strlen($seg) >= 2 && mb_strlen($seg) <= 80) {
                            $h = EntityTranslation::hash($seg);
                            $candidates['category_segment'][$h] ??= EntityTranslation::ucfirstMb(mb_strtolower($seg));
                            $counts['category_segment'][$h] = ($counts['category_segment'][$h] ?? 0) + 1;
                        }
                    }

                    foreach (preg_split('/\s*[\/,]\s*/u', (string) $row->colour) ?: [] as $colPart) {
                        $colPart = trim($colPart);
                        if ($colPart !== '' && self::containsGreek($colPart) && mb_strlen($colPart) >= 2 && mb_strlen($colPart) <= 50 && !is_numeric($colPart)) {
                            $h = EntityTranslation::hash($colPart);
                            $candidates['color'][$h] ??= EntityTranslation::ucfirstMb(mb_strtolower($colPart));
                            $counts['color'][$h] = ($counts['color'][$h] ?? 0) + 1;
                        }
                    }

                    foreach (self::extractGreekSpans((string) $row->name) as $span) {
                        $norm = EntityTranslation::normalizeKey($span);
                        if ($norm === '' || in_array($norm, self::GREEK_STOP_ONLY, true) || isset(self::CORE_LEXICON[$norm])) {
                            continue;
                        }

                        if (in_array($norm, self::COLOR_NORMS, true)) {
                            $h = EntityTranslation::hash($span);
                            $candidates['color'][$h] ??= EntityTranslation::ucfirstMb(mb_strtolower($span));
                            $counts['color'][$h] = ($counts['color'][$h] ?? 0) + 1;
                            continue;
                        }

                        if (mb_strlen($span) <= 100 && substr_count($span, ' ') <= 10) {
                            $h = EntityTranslation::hash($span);
                            $cleanSpan = EntityTranslation::ucfirstMb(mb_strtolower($span));
                            if (!isset($candidates['title_prefix'][$h]) || preg_match('/[άέήίόύώ]/u', $span)) {
                                $candidates['title_prefix'][$h] = $cleanSpan;
                            }
                            $counts['title_prefix'][$h] = ($counts['title_prefix'][$h] ?? 0) + 1;
                        }
                    }

                    foreach ((array) ($row->specs ?? []) as $spec) {
                        $spec = trim((string) $spec);
                        if ($spec === '' || !self::containsGreek($spec) || str_starts_with($spec, 'GTIN:')) {
                            continue;
                        }

                        // Извлекаем чистые греческие спаны из характеристики (без процентов, скобок и брендов)
                        foreach (self::extractGreekSpans($spec) as $specSpan) {
                            $norm = EntityTranslation::normalizeKey($specSpan);
                            if ($norm === '' || in_array($norm, self::GREEK_STOP_ONLY, true) || isset(self::CORE_LEXICON[$norm])) {
                                continue;
                            }
                            if (mb_strlen($specSpan) >= 2 && mb_strlen($specSpan) <= 100) {
                                $h = EntityTranslation::hash($specSpan);
                                $candidates['spec'][$h] ??= EntityTranslation::ucfirstMb(mb_strtolower($specSpan));
                                $counts['spec'][$h] = ($counts['spec'][$h] ?? 0) + 1;
                            }
                        }
                    }
                }
            });

        $missing = [];

        foreach ($candidates as $type => $byHash) {
            if ($byHash === []) {
                continue;
            }

            $minFreq = max(1, (int) ($thresholds[$type] ?? 1));

            $filteredCounts = array_filter($counts[$type], function ($c, $h) use ($type, $minFreq, $byHash) {
                $text = (string) ($byHash[$h] ?? '');

                if ($type === 'title_prefix' && substr_count($text, ' ') >= 5) {
                    return $c >= max(4, $minFreq);
                }

                return $c >= $minFreq;
            }, ARRAY_FILTER_USE_BOTH);

            arsort($filteredCounts);

            if ($filteredCounts === []) {
                continue;
            }

            // Проверяем по ВСЕМ типам в entity_translations, чтобы не переводить повторно фразы, уже существующие в другом типе
            $existingHashes = [];
            foreach (array_chunk(array_keys($filteredCounts), 500) as $chunk) {
                $found = EntityTranslation::query()
                    ->whereIn('source_hash', $chunk)
                    ->pluck('source_hash')
                    ->all();
                $existingHashes = array_merge($existingHashes, $found);
            }

            $existingMap = array_fill_keys($existingHashes, true);
            $missing[$type] = [];

            foreach (array_keys($filteredCounts) as $hash) {
                if (!isset($existingMap[$hash])) {
                    $missing[$type][] = $byHash[$hash];
                }
            }
        }

        return array_filter($missing, fn ($list) => $list !== []);
    }

    public function dispatchMissingTranslations(?string $feedKey = null, bool $sync = false): int
    {
        $missing = $this->collectUntranslatedEntities($feedKey);
        $dispatched = 0;

        foreach ($missing as $type => $items) {
            foreach (array_chunk($items, 40) as $batch) {
                if ($sync) {
                    TranslateFeedEntitiesJob::dispatchSync($type, $batch);
                } else {
                    TranslateFeedEntitiesJob::dispatch($type, $batch);
                }
                $dispatched += count($batch);
            }
        }

        return $dispatched;
    }

    public function syncCategoryMappings(?string $feedKey = null): void
    {
        $categoriesBySlug = DB::table('categories')->pluck('id', 'slug')->all();
        if ($categoriesBySlug === []) {
            return;
        }

        $pathsQuery = DB::table('feed_products')
            ->where('is_active', true)
            ->whereNotNull('category_path')
            ->where('category_path', '!=', '');

        if ($feedKey !== null) {
            $pathsQuery->where('feed', $feedKey);
        }

        $distinctPaths = $pathsQuery
            ->select('feed', 'category_path')
            ->distinct()
            ->get();

        $now = now();

        foreach ($distinctPaths as $row) {
            $path = trim((string) $row->category_path);
            $hash = EntityTranslation::hash($path);
            $matchedCategoryId = $this->guessCategoryId($path, $categoriesBySlug);

            DB::table('feed_category_mappings')->upsert([[
                'feed' => $row->feed,
                'path_hash' => $hash,
                'raw_category_path' => $path,
                'category_id' => $matchedCategoryId,
                'updated_at' => $now,
                'created_at' => $now,
            ]], ['feed', 'path_hash'], ['raw_category_path']);

            if ($matchedCategoryId !== null) {
                DB::table('feed_products')
                    ->where('feed', $row->feed)
                    ->where('category_path', $path)
                    ->whereNull('category_id')
                    ->update(['category_id' => $matchedCategoryId]);
            }
        }
    }

    private function guessCategoryId(string $categoryPath, array $categoriesBySlug): ?int
    {
        foreach ((array) config('home_categories.tiles', []) as $tile) {
            $slug = (string) ($tile['slug'] ?? '');
            if (!isset($categoriesBySlug[$slug])) {
                continue;
            }

            $wherePaths = (array) ($tile['where']['category_path'] ?? []);
            foreach ($wherePaths as $needle) {
                if ($needle !== '' && mb_stripos($categoryPath, (string) $needle) !== false) {
                    return (int) $categoriesBySlug[$slug];
                }
            }
        }

        $top = trim(explode(',', $categoryPath)[0] ?? '');
        if ($top === 'Αξεσουάρ' && isset($categoriesBySlug['accessories'])) {
            return (int) $categoriesBySlug['accessories'];
        }

        return null;
    }

    /**
     * @param string[] $hashes
     * @return array<string, string>
     */
    private function getMap(string $entityType, array $hashes, string$locale): array
    {
        if ($hashes === []) {
            return [];
        }

        $hashes = array_values(array_unique($hashes));$keys = [];
        foreach ($hashes as $h) {$keys[$h] = "entity_tr:{$entityType}:{$locale}:{$h}";
        }

        $cachedValues = Cache::many(array_values($keys));

        $out = [];$missing = [];

        foreach ($hashes as$h) {
            $cached =$cachedValues[$keys[$h]] ?? null;
            if ($cached === '__MISS__') {
                continue;
            }
            if (is_string($cached) && $cached !== '') {$out[$h] =$cached;
            } else {
                $missing[] =$h;
            }
        }

        if ($missing !== []) {$rows = EntityTranslation::query()
                ->where('entity_type', $entityType)
                ->whereIn('source_hash', $missing)
                ->get();

            $foundHashes = [];$toPut = [];

            foreach ($rows as $row) {$val = $row->forLocale($locale);
                if (is_string($val) && $val !== '') {$out[$row->source_hash] =$val;
                    $toPut["entity_tr:{$entityType}:{$locale}:{$row->source_hash}"] = $val;
                    $foundHashes[$row->source_hash] = true;
                }
            }

            if ($toPut !== []) {
                Cache::putMany($toPut, self::CACHE_TTL);
            }

            // Кэшируем отсутствие перевода на 15 минут, чтобы не дергать MySQL на каждый клик
            $missesToPut = [];
            foreach ($missing as$h) {
                if (!isset($foundHashes[$h])) {
                    $missesToPut["entity_tr:{$entityType}:{$locale}:{$h}"] = '__MISS__';
                }
            }

            if ($missesToPut !== []) {
                Cache::putMany($missesToPut, now()->addMinutes(15));
            }
        }

        return $out;
    }
}