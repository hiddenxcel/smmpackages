<?php
// Run every 15-30 min via Windows Task Scheduler / cron:
//   php c:\xampp\htdocs\smmpackages\cron\expire_stale_payments.php
// Fails pending payments older than 60 minutes (abandoned checkouts), so they
// don't linger and their pending subscriptions can be cleaned up later.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    die();
}

require_once __DIR__ . '/../app/lib/DB.php';
require_once __DIR__ . '/../app/models/SubscriptionPayment.php';

$config = require __DIR__ . '/../config/config.php';
DB::connect($config['db']);

$minutes = 60;
$count = SubscriptionPayment::expireStale($minutes);
echo date('c') . " expire_stale_payments: {$count} pending payment(s) older than {$minutes}m failed.\n";
