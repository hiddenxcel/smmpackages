<?php
// Run every 15-30 min via Windows Task Scheduler / cron:
//   php c:\xampp\htdocs\smmpackages\cron\cleanup_conversations.php
// Drops expired bot conversations so stale flows reset.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    die();
}

require_once __DIR__ . '/../app/lib/DB.php';
require_once __DIR__ . '/../app/models/BotConversation.php';

$config = require __DIR__ . '/../config/config.php';
DB::connect($config['db']);

$count = BotConversation::cleanupExpired();
echo date('c') . " cleanup_conversations: {$count} expired conversation(s) removed.\n";
