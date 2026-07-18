<?php
// CLI-only: php database/seed_templates.php
// Seeds platform-default response templates (tenant_id NULL) in EN/FR/SW.
// Idempotent via UNIQUE(tenant_id, template_key, lang).

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    die();
}

require_once __DIR__ . '/../app/lib/DB.php';
$config = require __DIR__ . '/../config/config.php';
$db = DB::connect($config['db']);

// key => [en, fr, sw]
$templates = [
    // Order flow
    'WELCOME'        => ['👋 Welcome to {business}! Choose a service to order:', '👋 Bienvenue chez {business} ! Choisissez un service :', '👋 Karibu {business}! Chagua huduma:'],
    'SELECT_SERVICE' => ['📦 Choose a service:', '📦 Choisissez un service :', '📦 Chagua huduma:'],
    'SEND_LINK'      => ['🔗 Send the link for {service}.', '🔗 Envoyez le lien pour {service}.', '🔗 Tuma link ya {service}.'],
    'SELECT_PACKAGE' => ['🔢 How many? Enter a quantity.', '🔢 Combien ? Entrez une quantité.', '🔢 Idadi ngapi? Weka namba.'],
    'CONFIRMED'      => ['✅ Order #{order_id} placed! {service} × {quantity}', '✅ Commande #{order_id} passée ! {service} × {quantity}', '✅ Order #{order_id} imewekwa! {service} × {quantity}'],
    'ORDER_ERROR'    => ['⚠️ Could not place the order: {message}', '⚠️ Impossible de passer la commande : {message}', '⚠️ Imeshindwa kuweka order: {message}'],

    // Status
    'STATUS_SUCCESS' => ['📊 Order #{order_id}: {status}', '📊 Commande #{order_id} : {status}', '📊 Order #{order_id}: {status}'],
    'NOT_FOUND'      => ['❌ Order #{order_id} not found.', '❌ Commande #{order_id} introuvable.', '❌ Order #{order_id} haijapatikana.'],

    // Refill
    'REFILL_SUCCESS'      => ['♻️ Refill for #{order_id} submitted! Guarantee: {guarantee} ✅', '♻️ Recharge pour #{order_id} envoyée ! Garantie : {guarantee} ✅', '♻️ Refill ya #{order_id} imewasilishwa! Guarantee: {guarantee} ✅'],
    'REFILL_NO_GUARANTEE' => ['🚫 Order #{order_id} has no refill guarantee.', '🚫 La commande #{order_id} n\'a pas de garantie.', '🚫 Order #{order_id} haina refill guarantee.'],
    'REFILL_ERROR'        => ['⚠️ Refill for #{order_id} could not be submitted: {message}', '⚠️ Recharge pour #{order_id} impossible : {message}', '⚠️ Refill ya #{order_id} imeshindwa: {message}'],

    // Cancel / speedup
    'CANCEL_SUCCESS'  => ['🗑️ Cancellation for #{order_id} requested.', '🗑️ Annulation pour #{order_id} demandée.', '🗑️ Kufuta #{order_id} kumeombwa.'],
    'CANCEL_INVALID'  => ['❌ Order #{order_id} not found.', '❌ Commande #{order_id} introuvable.', '❌ Order #{order_id} haijapatikana.'],
    'SPEEDUP_SUCCESS' => ['🚀 Speed-up for #{order_id} requested.', '🚀 Accélération pour #{order_id} demandée.', '🚀 Kuharakisha #{order_id} kumeombwa.'],

    // General / system
    'HELP'                  => ['🎧 Send: status / refill / cancel / speedup followed by an order ID.', '🎧 Envoyez : status / refill / cancel / speedup suivi d\'un numéro.', '🎧 Tuma: status / refill / cancel / speedup na namba ya order.'],
    'UNKNOWN'               => ['🤔 I didn\'t understand that. Send *help*.', '🤔 Je n\'ai pas compris. Envoyez *help*.', '🤔 Sikuelewa. Tuma *help*.'],
    'GOODBYE'               => ['👋 Thanks! Send *hi* anytime.', '👋 Merci ! Envoyez *hi* quand vous voulez.', '👋 Asante! Tuma *hi* wakati wowote.'],
    'SUBSCRIPTION_EXPIRED'  => ['⏸️ This service is currently paused. Please try again later.', '⏸️ Ce service est en pause. Réessayez plus tard.', '⏸️ Huduma imesimama kwa sasa. Jaribu tena baadaye.'],
];

// NULL tenant_id is treated as distinct by the UNIQUE key, so ON DUPLICATE
// won't fire — clear existing platform defaults first for true idempotency.
$db->exec('DELETE FROM response_templates WHERE tenant_id IS NULL');

$stmt = $db->prepare(
    "INSERT INTO response_templates (tenant_id, template_key, lang, content, is_default)
     VALUES (NULL, ?, ?, ?, 1)"
);

$langs = ['en' => 0, 'fr' => 1, 'sw' => 2];
$count = 0;
foreach ($templates as $key => $translations) {
    foreach ($langs as $lang => $idx) {
        $stmt->execute([$key, $lang, $translations[$idx]]);
        $count++;
    }
}

echo "Done. Seeded {$count} template rows (" . count($templates) . " keys × 3 langs).\n";
