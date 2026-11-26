<?php

return [
    'meta_title' => 'Help: Shopping in Greece & Delivery Guide | Exereto',
    'meta_description' => 'Step-by-step guide on using the Exereto catalog, translating Greek online stores, filling out Greek checkout forms, and shipping parcels to Georgia.',
    'hero_badge' => 'Buyer Guide',
    'hero_title' => 'Shopping & Delivery Help Center',
    'hero_subtitle' => 'How to search on Exereto, navigate Greek-language online stores, fill in checkout forms, and receive orders in Greece or Georgia.',

    'nav' => [
        'catalog' => 'About Exereto',
        'translate' => 'Browser Translation',
        'stores' => 'Store Checkout Guides',
        'delivery' => 'Delivery to Georgia',
        'faq' => 'FAQ',
    ],

    'about' => [
        'title' => 'How Exereto Works',
        'lead' => 'Exereto is a product discovery catalog that helps you shop from official Greek online stores without selling products itself. We bring together honest official retailer prices, seasonal discounts, and a vast selection from the Greek market — with search and filters in your preferred language.',
        'cards' => [
            [
                'title' => 'Multilingual Search & Filters',
                'text' => 'Search by brand, category, size, color, and price in English, Russian, or Georgian — no Greek language skills required.',
            ],
            [
                'title' => 'Official Store Prices',
                'text' => 'Click through directly to the official retailer or brand website and buy at their own listed prices with all active discounts.',
            ],
            [
                'title' => 'Direct Checkout at the Store',
                'text' => 'Exereto charges no buyer markup and never asks for your payment card details — checkout and payment happen directly on the store\'s website.',
            ],
        ],
    ],

    'translate' => [
        'title' => 'How to Translate Any Greek Online Store',
        'subtitle' => 'Some partner retailers operate exclusively in Greek. Any modern desktop or mobile browser can translate the entire page — including size charts, cart, and checkout — in two clicks.',
        'desktop_title' => 'On Desktop (Chrome, Edge, Safari)',
        'desktop_mock_url' => 'notos.gr / epapoutsia.gr',
        'desktop_mock_menu' => [
            'Back / Reload',
            'Translate to English',
            'Inspect / View Page Source',
        ],
        'desktop_steps' => [
            'Open the retailer page via a product link on Exereto.',
            'Right-click anywhere on an empty area of the webpage.',
            'Select "Translate to English" (or click the translate icon on the right side of the address bar).',
            'As you move to the cart and checkout, the browser will automatically keep translating each step.',
        ],
        'mobile_title' => 'On Mobile (Android & iPhone)',
        'mobile_chrome_title' => 'Google Chrome (Android / iPhone)',
        'mobile_chrome_steps' => [
            'Tap the "⋮" menu icon in the corner of the browser.',
            'Tap "Translate" — a language bar will appear at the top or bottom.',
            'Tap "⋮" on the translate bar and enable "Always translate Greek".',
        ],
        'mobile_safari_title' => 'Safari (iPhone / iPad)',
        'mobile_safari_steps' => [
            'Tap the "aA" (or page menu) icon in the address bar.',
            'Select "Translate to English".',
            'Confirm enabling built-in translation if prompted the first time.',
        ],
        'tip' => 'Tip: Always type your name and shipping address using Latin (English) characters at checkout so both the Greek store and your parcel carrier read your label accurately.',
    ],

    'stores' => [
        'title' => 'Checkout Examples for Major Partner Stores',
        'subtitle' => 'Below is a quick checkout guide for 4 popular stores in our catalog: what to watch out for in the cart and how to fill in Greek address fields.',
        'fields_title' => 'Common Greek Checkout Fields & How to Fill Them',
        'sample_badge' => 'Use Latin letters',
        'items' => [
            'epapoutsia' => [
                'name' => 'epapoutsia.gr',
                'domain' => 'epapoutsia.gr',
                'category' => 'Footwear, bags, and accessories',
                'lang_note' => 'Greek interface (works smoothly with browser translation)',
                'tips' => [
                    'Select EU sizes and double-check insole length in centimeters using the product size chart.',
                    'Look for the promo code field in the cart — seasonal campaigns often provide extra discounts.',
                    'Guest checkout ("Συνέχεια ως επισκέπτης") is available; international Visa/Mastercard payments are accepted.',
                ],
                'fields' => [
                    ['gr' => 'Όνομα / Επώνυμο', 'meaning' => 'First / Last Name', 'example' => 'Giorgi Beridze (+ Carrier ID if required)'],
                    ['gr' => 'Οδός & Αριθμός', 'meaning' => 'Street & Number', 'example' => 'Greek warehouse street address'],
                    ['gr' => 'Τ.Κ. (Ταχ. Κώδικας)', 'meaning' => 'Postal Code (5 digits)', 'example' => '10431 (warehouse ZIP)'],
                    ['gr' => 'Πόλη / Περιοχή', 'meaning' => 'City / Area', 'example' => 'Athens / Attiki'],
                    ['gr' => 'Τηλέφωνο', 'meaning' => 'Greek Phone Number', 'example' => 'Carrier warehouse phone'],
                    ['gr' => 'Σημειώσεις / Όροφος', 'meaning' => 'Notes / Room Code', 'example' => 'Your personal carrier ID (Room #)'],
                ],
            ],
            'modivo' => [
                'name' => 'modivo.gr',
                'domain' => 'modivo.gr',
                'category' => 'Designer fashion, footwear, and premium brands',
                'lang_note' => 'Greek interface (same checkout structure as epapoutsia.gr)',
                'tips' => [
                    'Checkout takes 3 steps: Cart (Καλάθι) → Delivery Address (Διεύθυνση παράδοσης) → Card Payment.',
                    'When shipping to a forwarding warehouse in Greece, select Home/Courier Delivery — do NOT select Box Now parcel lockers.',
                    'Once shipped, copy the tracking number from your confirmation email into your carrier\'s dashboard.',
                ],
                'fields' => [
                    ['gr' => 'Όνομα / Επώνυμο', 'meaning' => 'First / Last Name', 'example' => 'Nino Kapanadze'],
                    ['gr' => 'Διεύθυνση (Οδός, Αριθμός)', 'meaning' => 'Street, Number + ID', 'example' => 'Warehouse street + Room/ID'],
                    ['gr' => 'Ταχυδρομικός Κώδικας', 'meaning' => 'Postal Code (5 digits)', 'example' => 'ZIP from carrier dashboard'],
                    ['gr' => 'Πόλη', 'meaning' => 'City', 'example' => 'Athens / Thessaloniki'],
                    ['gr' => 'Κινητό τηλέφωνο', 'meaning' => 'Mobile Phone', 'example' => 'Warehouse Greek phone'],
                    ['gr' => 'Email', 'meaning' => 'Your Email', 'example' => 'For invoice & tracking number'],
                ],
            ],
            'notos' => [
                'name' => 'notos.gr',
                'domain' => 'notos.gr',
                'category' => 'Department store: fashion, beauty, fragrance, home',
                'lang_note' => 'Major Greek department store with frequent sales',
                'tips' => [
                    'Choose courier delivery to an address (Αποστολή με Courier), not in-store pickup (Παραλαβή από κατάστημα).',
                    'Include your personal carrier room/ID either next to the street line or in the Order Notes (Σχόλια) field.',
                    'If ordering perfumes or aerosols, check your chosen carrier\'s policy on liquids and fragrances beforehand.',
                ],
                'fields' => [
                    ['gr' => 'Όνομα / Επώνυμο', 'meaning' => 'First / Last Name', 'example' => 'Name matching carrier account'],
                    ['gr' => 'Διεύθυνση / Αριθμός', 'meaning' => 'Street & Number', 'example' => 'Greek warehouse address'],
                    ['gr' => 'Τ.Κ. & Πόλη', 'meaning' => 'Postal Code & City', 'example' => '5-digit ZIP + City'],
                    ['gr' => 'Νομός / Περιοχή', 'meaning' => 'Region (dropdown)', 'example' => 'Αττικής (Attiki) / Θεσσαλονίκης'],
                    ['gr' => 'Τηλέφωνο επικοινωνίας', 'meaning' => 'Contact Phone', 'example' => 'Greek warehouse phone'],
                    ['gr' => 'Σχόλια παραγγελίας', 'meaning' => 'Order Comments', 'example' => 'Personal ID / Room number'],
                ],
            ],
            'adidas' => [
                'name' => 'adidas.gr',
                'domain' => 'adidas.gr',
                'category' => 'Official Adidas Greece online store',
                'lang_note' => 'Official stock, exclusive drops, and up to 50% off sales',
                'tips' => [
                    'Adidas provides precise EU/UK/cm sizing charts — check foot length in cm/mm if unsure.',
                    'Choose standard address delivery (Home Delivery / Παράδοση σε διεύθυνση) rather than pickup points.',
                    'Use the "Additional address info / Επιπλέον στοιχεία διεύθυνσης" field for your personal forwarding ID.',
                ],
                'fields' => [
                    ['gr' => 'Όνομα / Επώνυμο', 'meaning' => 'First / Last Name', 'example' => 'Your full name in Latin'],
                    ['gr' => 'Διεύθυνση (Οδός & Αριθμός)', 'meaning' => 'Street & Number', 'example' => 'Greek warehouse street'],
                    ['gr' => 'Επιπλέον στοιχεία διεύθυνσης', 'meaning' => 'Additional Address Line', 'example' => 'Your personal carrier ID'],
                    ['gr' => 'Ταχυδρομικός κώδικας', 'meaning' => 'Postal Code (5 digits)', 'example' => 'Warehouse ZIP code'],
                    ['gr' => 'Πόλη', 'meaning' => 'City', 'example' => 'Athens / Thessaloniki'],
                    ['gr' => 'Τηλέφωνο', 'meaning' => 'Phone', 'example' => 'Warehouse Greek phone'],
                ],
            ],
        ],
        'glossary_title' => 'Quick Greek Checkout Glossary',
        'glossary' => [
            ['gr' => 'Προσθήκη στο καλάθι', 'tr' => 'Add to cart'],
            ['gr' => 'Καλάθι αγορών', 'tr' => 'Shopping cart'],
            ['gr' => 'Ολοκλήρωση παραγγελίας', 'tr' => 'Proceed to checkout'],
            ['gr' => 'Συνέχεια ως επισκέπτης', 'tr' => 'Continue as guest'],
            ['gr' => 'Κωδικός έκπτωσης / Κουπόνι', 'tr' => 'Discount / Promo code'],
            ['gr' => 'Πληρωμή με κάρτα', 'tr' => 'Pay by card'],
            ['gr' => 'Δωρεάν μεταφορικά', 'tr' => 'Free shipping'],
            ['gr' => 'Εξαντλημένο', 'tr' => 'Out of stock'],
            ['gr' => 'Οδηγός μεγεθών', 'tr' => 'Size guide'],
        ],
    ],

    'delivery' => [
        'title' => 'Delivery from Greece (Local & to Georgia)',
        'subtitle' => 'Find products on Exereto, proceed to the Greek store, and ship either directly within Greece or to Georgia via your preferred parcel forwarding company.',
        'greece_title' => 'Shopping from Within Greece',
        'greece_text' => 'Simply follow the product link to the merchant and complete checkout as usual — via home courier or local pickup lockers (Box Now, ACS, Geniki Taxydromiki) under the retailer\'s standard delivery terms.',
        'georgia_title' => 'Ordering from Georgia (Using Parcel Forwarders)',
        'georgia_intro' => 'Most Greek online retailers only ship domestically within Greece. To receive orders in Georgia, shoppers register with an independent parcel forwarding company of their choice and obtain a personal Greek warehouse address.',
        'steps' => [
            [
                'title' => 'Register with a Carrier',
                'text' => 'Sign up with any forwarding provider offering a Greek address to receive their warehouse address and your personal customer ID.',
            ],
            [
                'title' => 'Order at the Greek Store',
                'text' => 'Find your item on Exereto, click through to the store, and enter your personal details along with the carrier\'s Greek warehouse address and your ID.',
            ],
            [
                'title' => 'Declare Your Parcel',
                'text' => 'Once the store emails your tracking number, enter the tracking code and item value in your carrier\'s online dashboard.',
            ],
            [
                'title' => 'Receive in Georgia',
                'text' => 'Your chosen carrier receives the package in Greece, transports it to Georgia, and delivers it to a local branch or your door.',
            ],
        ],
        'carriers_title' => 'Overview of Popular Carriers (Greece → Georgia)',
        'carriers_note' => 'Exereto is not affiliated with the logistics providers listed below. Always verify current Greek warehouse availability, shipping schedules, per-kg tariffs, and restricted-item rules on the carrier\'s official website before placing an order.',
        'table_headers' => [
            'carrier' => 'Carrier',
            'type' => 'Service Type',
            'eta' => 'Indicative Transit',
            'notes' => 'Key Conditions & Notes',
        ],
        'carriers' => [
            [
                'name' => 'Inex Group',
                'url' => 'https://inex.ge',
                'domain' => 'inex.ge',
                'type' => 'Greece warehouse → Georgia',
                'eta' => '≈ 5–10 business days',
                'notes' => 'One of the primary carriers on the Greece → Georgia route with dedicated Greek warehouse addresses, nationwide Georgian branches, and courier delivery. Parcel declaration required.',
            ],
            [
                'name' => 'Camex',
                'url' => 'https://camex.ge',
                'domain' => 'camex.ge',
                'type' => 'Greece warehouse → Georgia',
                'eta' => '≈ 5–10 business days',
                'notes' => 'Provides a Greek warehouse address and personal room ID. Make sure to include your personal ID in the address line and declare the tracking number online.',
            ],
            [
                'name' => 'SpaceCargo',
                'url' => 'https://spacecargo.ge',
                'domain' => 'spacecargo.ge',
                'type' => 'International forwarding',
                'eta' => '≈ 5–12 business days',
                'notes' => 'Online tracking dashboard and branch/courier pickup. Verify active Greek/EU warehouse details and flight schedule inside your account before ordering.',
            ],
            [
                'name' => 'Postalon',
                'url' => 'https://postalon.com',
                'domain' => 'postalon.com',
                'type' => 'International forwarding',
                'eta' => '≈ 6–12 business days',
                'notes' => 'Weight-based parcel delivery to Georgia. Check current EU/Greece warehouse availability and volumetric weight rules on their website.',
            ],
            [
                'name' => 'KiwiPost',
                'url' => 'https://kiwipost.ge',
                'domain' => 'kiwipost.ge',
                'type' => 'International forwarding',
                'eta' => '≈ 6–12 business days',
                'notes' => 'Forwarding service with branch pickup and courier options. Confirm active Greek warehouse availability and dispatch schedule before checkout.',
            ],
            [
                'name' => 'Georgian Post',
                'url' => 'https://gpost.ge',
                'domain' => 'gpost.ge',
                'type' => 'International mail (ELTA → Georgian Post)',
                'eta' => '≈ 10–21 days',
                'notes' => 'Applicable when a merchant supports direct international postal shipping to Georgia via ELTA. Parcels are collected at local Georgian Post branches.',
            ],
        ],
        'customs_title' => 'Georgian Customs Threshold Reminder',
        'customs_text' => 'Georgia applies a duty-free threshold on imported parcels (standard limit: 300 GEL based on the exchange rate upon arrival, subject to quantity/weight rules). Shipments above the threshold incur 18% VAT plus a customs declaration fee. Keep your digital store invoice in case your carrier requests it for customs clearance.',
    ],

    'faq' => [
        'title' => 'Frequently Asked Questions',
        'items' => [
            [
                'q' => 'Are prices on Exereto higher than on the retailer\'s website?',
                'a' => 'No. All products are shown at official store or brand prices. Partner commissions are paid by retailers and never increase the price you pay.',
            ],
            [
                'q' => 'Why might a price or size differ slightly once I open the store?',
                'a' => 'Our catalog syncs automatically with official merchant feeds on a regular schedule. During flash sales, stock and prices can change in real time; the retailer\'s checkout cart always reflects the final price.',
            ],
            [
                'q' => 'Can I pay at Greek online stores with a Georgian bank card?',
                'a' => 'Yes. Most major Greek retailers (epapoutsia.gr, modivo.gr, notos.gr, adidas.gr, etc.) accept international Visa and Mastercard cards issued by Georgian banks (TBC, Bank of Georgia, etc.) with 3D-Secure enabled.',
            ],
            [
                'q' => 'Do I need an account on Exereto to browse or shop?',
                'a' => 'No registration is required on Exereto. You can search, filter, compare products, and go straight to the retailer immediately.',
            ],
            [
                'q' => 'How do returns work if a size does not fit?',
                'a' => 'Returns and exchanges follow the retailer\'s own policy (typically 14–30 days under EU consumer law). If shipping to Georgia via a forwarder, consider return shipping costs and always double-check size charts in centimeters before ordering.',
            ],
        ],
    ],

    'contact' => [
        'title' => 'Still Have a Question About the Catalog?',
        'text' => 'If you spotted a translation issue, broken product link, or want to suggest a Greek store to add, feel free to email us.',
        'button' => 'Email Us',
    ],
];