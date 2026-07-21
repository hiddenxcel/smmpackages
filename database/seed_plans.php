<?php
// CLI-only: php database/seed_plans.php
// Seeds the a la carte pricing catalog (one plan per service, USD base).
// Idempotent: re-running updates prices without creating duplicates.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    die();
}

require_once __DIR__ . '/../app/lib/DB.php';
$config = require __DIR__ . '/../config/config.php';
$db = DB::connect($config['db']);

// service_key => [code, name, description, price_monthly, price_yearly, max_panels, max_numbers, sort]
// Yearly = monthly * 12 * 0.8 (20% off), per plan.
$plans = [
    ['order_bot',      'order_bot',      'Order Bot',    'Customers place orders through WhatsApp.',        17.00, 163.20, 5, 1, 1],
    ['support_bot',    'support_bot',    'Support Bot',  'Refill, status, cancel and speed-up, automatically.', 17.00, 163.20, 5, 1, 2],
    ['ai_tickets',     'ai_tickets',     'AI Tickets',   'AI-powered support tickets inside your website.', 11.00, 105.60, 1, 1, 3],
    ['ai_chat',        'ai_chat',        'AI Chat',      'AI Support inside your Order Bot — answers customers automatically on WhatsApp.', 5.00, 48.00, 1, 1, 4],
    ['number_rental',  'number_rental',  'Rent a Number','Rent a Cloud API number when you have no Meta Business account.', 11.00, 105.60, 1, 1, 5],
];

$stmt = $db->prepare(
    "INSERT INTO plans
        (code, name, description, service_key, price_monthly, price_yearly, currency, max_panels, max_numbers, status, sort_order)
     VALUES (?, ?, ?, ?, ?, ?, 'USD', ?, ?, 'active', ?)
     ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        description = VALUES(description),
        service_key = VALUES(service_key),
        price_monthly = VALUES(price_monthly),
        price_yearly = VALUES(price_yearly),
        max_panels = VALUES(max_panels),
        max_numbers = VALUES(max_numbers),
        status = 'active',
        sort_order = VALUES(sort_order)"
);

foreach ($plans as $p) {
    [$serviceKey, $code, $name, $desc, $monthly, $yearly, $maxPanels, $maxNumbers, $sort] = $p;
    $stmt->execute([$code, $name, $desc, $serviceKey, $monthly, $yearly, $maxPanels, $maxNumbers, $sort]);
    echo "Seeded plan: {$name} (\${$monthly}/mo)\n";
}

echo "Done. " . count($plans) . " plans.\n";
