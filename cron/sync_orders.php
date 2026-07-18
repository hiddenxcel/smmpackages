<?php
// Run every 5-10 min via Windows Task Scheduler / cron:
//   php c:\xampp\htdocs\smmpackages\cron\sync_orders.php
// Polls each in-flight bot order's status from its tenant's panel and updates
// bot_orders. Panels are grouped so each panel's client + key is built once.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    die();
}

require_once __DIR__ . '/../app/lib/DB.php';
require_once __DIR__ . '/../app/lib/Crypto.php';
require_once __DIR__ . '/../app/models/BotOrder.php';
require_once __DIR__ . '/../app/models/TenantPanel.php';
require_once __DIR__ . '/../app/services/SmmProviderClient.php';

$config = require __DIR__ . '/../config/config.php';
DB::connect($config['db']);
Crypto::init($config['app']['key']);

$orders = BotOrder::needingSync(300);
$clients = [];   // panel_id => SmmProviderClient
$updated = 0;
$checked = 0;

foreach ($orders as $o) {
    $panelId = (int) ($o['panel_id'] ?? 0);
    if ($panelId <= 0) {
        continue;
    }

    if (!isset($clients[$panelId])) {
        $panel = TenantPanel::find($panelId);
        if ($panel === null) {
            $clients[$panelId] = false;
        } else {
            $clients[$panelId] = SmmProviderClient::fromPanel($panel);
        }
    }

    $client = $clients[$panelId];
    if ($client === false) {
        continue;
    }

    $checked++;
    $res = $client->checkStatus((string) $o['provider_order_id']);
    if (!empty($res['success']) && $res['status'] !== null && $res['status'] !== $o['status']) {
        BotOrder::updateStatus((int) $o['id'], $res['status']);
        $updated++;
    }
}

echo date('c') . " sync_orders: checked {$checked}, updated {$updated}.\n";
