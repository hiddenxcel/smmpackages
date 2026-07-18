<?php
// Run hourly via Windows Task Scheduler / cron:
//   php c:\xampp\htdocs\smmpackages\cron\expire_subscriptions.php
// Marks active subscriptions whose ends_at has passed as 'expired' (locks the service).

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    die();
}

require_once __DIR__ . '/../app/lib/DB.php';
require_once __DIR__ . '/../app/models/Subscription.php';

$config = require __DIR__ . '/../config/config.php';
DB::connect($config['db']);

$count = Subscription::expireOverdue();
echo date('c') . " expire_subscriptions: {$count} subscription(s) expired.\n";
