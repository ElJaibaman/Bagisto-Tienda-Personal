<?php

/**
 * Configuracion de las integraciones del examen (Cafe Catracho).
 * Todos los valores se leen del .env.
 */
return [

    'store_name' => env('EXAMEN_STORE_NAME', 'Café Catracho'),

    /*
    | 1. WhatsApp Business (Cloud API de Meta)
    */
    'whatsapp' => [
        'enabled' => (bool) env('WHATSAPP_ENABLED', false),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

        // Numeros del administrador separados por coma, con codigo de pais. Ej: 50499999999
        'admin_numbers' => array_filter(array_map('trim', explode(',', (string) env('WHATSAPP_ADMIN_NUMBERS', '')))),

        // Tambien avisar al cliente (usa el telefono de su direccion de facturacion).
        'notify_customer' => (bool) env('WHATSAPP_NOTIFY_CUSTOMER', false),
        'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', '504'),

        // "text" = mensaje libre (solo dentro de la ventana de 24h de Meta)
        // "template" = plantilla aprobada (necesario para escribir primero)
        'mode' => env('WHATSAPP_MODE', 'template'),
        'template' => env('WHATSAPP_TEMPLATE', 'hello_world'),
        'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en_US'),
        // Si la plantilla tiene variables {{1}} {{2}} {{3}}: pedido, cliente, total.
        'template_with_params' => (bool) env('WHATSAPP_TEMPLATE_WITH_PARAMS', false),
    ],

    /*
    | 2. Cupones automaticos
    */
    'coupons' => [
        'enabled' => (bool) env('AUTO_COUPON_ENABLED', true),
        // Monto acumulado de compras que dispara un cupon.
        'threshold' => (float) env('AUTO_COUPON_THRESHOLD', 10000),
        // Moneda en la que esta expresado el umbral (se convierte a la moneda base con el tipo de cambio de Bagisto).
        'threshold_currency' => env('AUTO_COUPON_THRESHOLD_CURRENCY', 'HNL'),
        'percent' => (float) env('AUTO_COUPON_PERCENT', 10),
        'valid_days' => (int) env('AUTO_COUPON_VALID_DAYS', 30),
        'prefix' => env('AUTO_COUPON_PREFIX', 'CATRACHO'),
    ],

    /*
    | 3. Chat en vivo Tawk.to
    */
    'tawk' => [
        'enabled' => (bool) env('TAWK_ENABLED', false),
        'property_id' => env('TAWK_PROPERTY_ID'),
        'widget_id' => env('TAWK_WIDGET_ID', 'default'),
    ],

    /*
    | 4. PayPal (Sandbox) - se aplica con: php artisan examen:setup
    */
    'paypal' => [
        'client_id' => env('PAYPAL_SANDBOX_CLIENT_ID'),
        'client_secret' => env('PAYPAL_SANDBOX_CLIENT_SECRET'),
        // PayPal no soporta HNL; se cobra en esta moneda.
        'accepted_currencies' => env('PAYPAL_ACCEPTED_CURRENCIES', 'USD'),
        // 1 USD = X HNL (tipo de cambio para mostrar precios en Lempiras).
        'hnl_rate' => (float) env('HNL_EXCHANGE_RATE', 26.5),
    ],
];
