<?php

return [
    'meta_title' => 'Help: Shopping in Greece & Delivery Guide | Exereto',
    'meta_description' => 'Step-by-step guide on using the Exereto catalog, translating Greek online stores, and shipping parcels from Greece to Georgia.',
    'hero_badge' => 'Buyer Guide',
    'hero_title' => 'Shopping & Delivery Help Center',
    'hero_subtitle' => 'How to search on Exereto, navigate Greek-language online stores, and receive orders in Greece or Georgia.',

    'nav' => [
        'catalog' => 'About Exereto',
        'translate' => 'Browser Translation',
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
        'carriers_note' => 'Exereto is not affiliated with the logistics providers listed below. Always verify current Greek warehouse addresses, shipping schedules, per-kg tariffs, and restricted-item rules on the carrier\'s official website before placing an order.',
        'table_headers' => [
            'carrier' => 'Carrier',
            'type' => 'Service Type',
            'eta' => 'Indicative Transit',
            'notes' => 'Key Conditions & Notes',
        ],
        'carriers' => [
            [
                'name' => 'Camex',
                'url' => 'https://camex.ge',
                'domain' => 'camex.ge',
                'type' => 'Greece warehouse → Georgia',
                'eta' => '≈ 5–10 business days',
                'notes' => 'Provides a Greek warehouse address and personal room ID; minimum chargeable weight starts from 100 g. Be sure to include your personal ID in the address line and declare the tracking number online.',
            ],
            [
                'name' => 'Inex Group',
                'url' => 'https://inex.ge',
                'domain' => 'inex.ge',
                'type' => 'Greece warehouse → Georgia',
                'eta' => '≈ 5–10 business days',
                'notes' => 'One of the primary carriers on the Greece → Georgia route with a dedicated Greek warehouse, nationwide branches across Georgia, and courier delivery. Parcel declaration is required.',
            ],
            [
                'name' => 'USA2GEORGIA',
                'url' => 'https://www.usa2georgia.com/#prices',
                'domain' => 'usa2georgia.com',
                'type' => 'Road shipping (Greece → Georgia)',
                'eta' => '7–14 business days',
                'notes' => 'Weekly road dispatches and an extensive branch network. Parcels up to 50 kg volumetric weight are billed by actual scale weight; a value-based operational handling fee applies.',
            ],
            [
                'name' => 'Boxette',
                'url' => 'https://www.boxette.ge/en/services/shipping-greece',
                'domain' => 'boxette.ge',
                'type' => 'Ground shipping (Greece → Georgia)',
                'eta' => '6–12 days',
                'notes' => 'Minimum chargeable weight is 0.1 kg with an online cost calculator on the website. Offers both office pickup and courier delivery across Georgia.',
            ],
            [
                'name' => 'Forsa Express',
                'url' => 'https://forsaexpress.ge/prices',
                'domain' => 'forsaexpress.ge',
                'type' => 'Greece warehouse → Georgia',
                'eta' => 'Per dispatch schedule',
                'notes' => 'International parcel forwarding from Greece with a flat per-kg rate. Check the current Greek warehouse address, dispatch schedule, and weight calculation rules on their website before ordering.',
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