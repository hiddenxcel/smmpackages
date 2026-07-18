<?php
/**
 * Public ticket widget API (customer -> AI). Called by the embeddable widget
 * on a tenant's own website. Identified by ?t=<tenant_id> (public key).
 *
 * POST JSON: { message, ticket_id?, customer? }
 * Returns  : { ok, ticket_id, ai_reply }
 *
 * CORS is open because the widget lives on the tenant's domain. Rate-limited
 * per tenant+IP. The gate (ai_tickets active) is enforced by TicketService.
 */

require_once __DIR__ . '/../../app/lib/DB.php';
require_once __DIR__ . '/../../app/lib/Crypto.php';
require_once __DIR__ . '/../../app/services/TicketService.php';
require_once __DIR__ . '/../../app/helpers/RateLimit.php';

$config = require __DIR__ . '/../../config/config.php';
DB::connect($config['db']);
if (!empty($config['app']['key'])) {
    Crypto::init($config['app']['key']);
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST only']);
    exit;
}

$tenantId = (int) ($_GET['t'] ?? 0);
if ($tenantId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Missing tenant']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!RateLimit::hit("widget:{$tenantId}:{$ip}", 'ticket', 20, 300)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'message' => 'Too many messages, slow down.']);
    exit;
}

$input = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
$message = trim($input['message'] ?? '');
$ticketId = isset($input['ticket_id']) ? (int) $input['ticket_id'] : null;
$customer = isset($input['customer']) ? mb_substr(trim((string) $input['customer']), 0, 190) : null;

if ($message === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Empty message']);
    exit;
}

$result = TicketService::customerMessage($tenantId, $ticketId, $customer, mb_substr($message, 0, 2000));

echo json_encode($result);
