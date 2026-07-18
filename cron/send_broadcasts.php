<?php
// Run every 1-2 min via Windows Task Scheduler / cron:
//   php c:\xampp\htdocs\smmpackages\cron\send_broadcasts.php
// Processes one batch per queued/sending broadcast, so large sends drain over
// several runs at a rate WhatsApp tolerates.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    die();
}

require_once __DIR__ . '/../app/lib/DB.php';
require_once __DIR__ . '/../app/lib/Crypto.php';
require_once __DIR__ . '/../app/services/BroadcastSender.php';

$config = require __DIR__ . '/../config/config.php';
DB::connect($config['db']);
Crypto::init($config['app']['key']);

$pending = Broadcast::pending(20);
$totalSent = 0;
$totalFailed = 0;

foreach ($pending as $b) {
    $res = BroadcastSender::processBroadcast($b);
    $totalSent += $res['sent'];
    $totalFailed += $res['failed'];
}

echo date('c') . " send_broadcasts: processed " . count($pending) . " broadcast(s), sent {$totalSent}, failed {$totalFailed}.\n";
