<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/../app/models/BotCustomer.php';
require_once __DIR__ . '/../app/models/BotSettings.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

$notice = null;
$noticeType = 'success';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'adjust') {
        $id = (int) ($_POST['id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        // Confirm the customer belongs to this tenant before touching their wallet.
        $cust = BotCustomer::find($id);
        if ($cust === null || (int) $cust['tenant_id'] !== $tenantId) {
            $notice = 'Customer not found.';
            $noticeType = 'danger';
        } elseif ($amount === 0.0) {
            $notice = 'Enter a non-zero amount (use a minus sign to deduct).';
            $noticeType = 'danger';
        } elseif (BotCustomer::adjustBalance($id, $amount)) {
            $notice = 'Balance adjusted by ' . number_format($amount, 2) . '.';
        } else {
            $notice = 'Could not adjust — that would make the balance negative.';
            $noticeType = 'danger';
        }
    }
}

$q = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = BotCustomer::search($tenantId, $q, $page, 25);
$currency = BotSettings::get($tenantId, 'order')['shop']['currency'] ?? 'USD';

$pageTitle = 'Customers & Wallets';
$activeSide = 'bot-customers';
require __DIR__ . '/includes/dash_header.php';

$money = static fn ($v) => $currency . ' ' . number_format((float) $v, 2);
?>

<div style="margin-bottom:22px">
  <h1 class="dash-title" style="margin-bottom:4px">Customers &amp; Wallets</h1>
  <div style="color:var(--text-muted)">Your bot customers and their wallet balances. Adjust a balance manually when you receive an offline payment.</div>
</div>

<?php if ($notice !== null): ?>
<div class="alert alert-<?= $noticeType ?>">
  <i class="fa-solid <?= $noticeType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" style="margin-top:3px"></i>
  <?= htmlspecialchars($notice) ?>
</div>
<?php endif; ?>

<div class="wapi-card">
  <form method="get" style="display:flex;gap:10px;margin-bottom:16px;max-width:420px">
    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Search by phone, name, or referral code">
    <button class="btn btn-outline" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
  </form>

  <?php if ($result['rows'] === []): ?>
    <p style="color:var(--text-muted);margin:0"><?= $q !== '' ? 'No customers match that search.' : 'No customers yet. They appear here once someone messages your Order Bot.' ?></p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:.88rem">
        <thead>
          <tr style="text-align:left;color:var(--text-muted)">
            <th style="padding:8px">Customer</th><th style="padding:8px">Balance</th>
            <th style="padding:8px">Spent</th><th style="padding:8px">Orders</th><th style="padding:8px">Adjust</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($result['rows'] as $c): ?>
          <tr style="border-top:1px solid var(--glass-border)">
            <td style="padding:8px">
              <strong><?= htmlspecialchars($c['name'] ?: '—') ?></strong><br>
              <span style="color:var(--text-muted);font-size:.76rem"><?= htmlspecialchars($c['phone']) ?> · <?= htmlspecialchars($c['referral_code'] ?? '') ?></span>
            </td>
            <td style="padding:8px;font-weight:700"><?= $money($c['balance']) ?></td>
            <td style="padding:8px;color:var(--text-muted)"><?= $money($c['total_spent']) ?></td>
            <td style="padding:8px"><?= (int) $c['order_count'] ?></td>
            <td style="padding:8px">
              <form method="post" style="display:flex;gap:6px;align-items:center">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="adjust">
                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <input type="number" step="0.01" name="amount" class="form-control" style="width:110px" placeholder="+/− amount">
                <button class="btn btn-primary" type="submit" style="padding:6px 12px">Apply</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($result['totalPages'] > 1): ?>
      <div style="display:flex;gap:8px;margin-top:16px;align-items:center;color:var(--text-muted);font-size:.85rem">
        <?php if ($result['page'] > 1): ?>
          <a class="btn btn-outline" href="?q=<?= urlencode($q) ?>&page=<?= $result['page'] - 1 ?>" style="padding:6px 12px">Prev</a>
        <?php endif; ?>
        <span>Page <?= $result['page'] ?> of <?= $result['totalPages'] ?> · <?= $result['total'] ?> customers</span>
        <?php if ($result['page'] < $result['totalPages']): ?>
          <a class="btn btn-outline" href="?q=<?= urlencode($q) ?>&page=<?= $result['page'] + 1 ?>" style="padding:6px 12px">Next</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
