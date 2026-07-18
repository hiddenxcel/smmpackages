<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

$notice = null;
$noticeType = 'success';
$testResult = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $ruleType = $_POST['rule_type'] === 'guarantee' ? 'guarantee' : 'no_guarantee';
        $keyword = trim($_POST['keyword'] ?? '');
        $days = $ruleType === 'guarantee' ? (int) ($_POST['refill_days'] ?? 0) : null;
        if ($keyword !== '') {
            GuaranteeRule::create($tenantId, $ruleType, $keyword, $days);
            $notice = Lang::t('gr_added');
        }
    }

    if ($action === 'delete') {
        if (GuaranteeRule::delete((int) ($_POST['rule_id'] ?? 0), $tenantId)) {
            $notice = Lang::t('gr_deleted');
        }
    }

    if ($action === 'seed') {
        GuaranteeRule::seedDefaults($tenantId);
        $notice = Lang::t('gr_seeded');
    }

    if ($action === 'test') {
        $name = trim($_POST['service_name'] ?? '');
        $testResult = GuaranteeMatcher::forTenant($tenantId)->evaluate($name);
        $testResult['name'] = $name;
    }
}

$rules = GuaranteeRule::forTenant($tenantId);
$noRules = array_filter($rules, fn ($r) => $r['rule_type'] === 'no_guarantee');
$yesRules = array_filter($rules, fn ($r) => $r['rule_type'] === 'guarantee');

$pageTitle = Lang::t('gr_title');
$activeSide = 'guarantee';
require __DIR__ . '/includes/dash_header.php';

function ruleChip(array $r): string
{
    $label = htmlspecialchars($r['keyword']);
    if ($r['rule_type'] === 'guarantee') {
        $days = (int) $r['refill_days'];
        $label .= ' → ' . ($days === 0 ? '∞' : $days);
    }
    $form = '<form method="post" action="guarantee-rules.php" style="display:inline">'
        . Csrf::field()
        . '<input type="hidden" name="action" value="delete"><input type="hidden" name="rule_id" value="' . (int) $r['id'] . '">'
        . '<button type="submit" style="background:none;border:none;color:inherit;cursor:pointer;margin-left:6px;opacity:.6">&times;</button></form>';
    return '<span class="badge" style="margin:3px;padding:8px 12px">' . $label . $form . '</span>';
}
?>

<p style="color:var(--text-muted);margin-bottom:20px"><?php e('gr_intro'); ?></p>

<?php if ($notice !== null): ?>
<div class="alert alert-<?= $noticeType ?>"><i class="fa-solid fa-circle-check" style="margin-top:3px"></i> <?= htmlspecialchars($notice) ?></div>
<?php endif; ?>

<!-- Test tool -->
<div class="card" style="margin-bottom:22px">
  <h3 style="margin-top:0"><i class="fa-solid fa-flask"></i> <?php e('gr_test'); ?></h3>
  <form method="post" action="guarantee-rules.php" style="display:flex;gap:10px;flex-wrap:wrap">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="test">
    <input class="form-control" style="flex:1;min-width:240px" type="text" name="service_name" value="<?= htmlspecialchars($testResult['name'] ?? '') ?>" placeholder="<?= htmlspecialchars(Lang::t('gr_test_ph')) ?>">
    <button class="btn btn-primary" type="submit"><?php e('gr_test_btn'); ?></button>
  </form>
  <?php if ($testResult !== null): ?>
    <?php
      if ($testResult['allowed']) {
        $daysLabel = $testResult['lifetime'] ? Lang::t('gr_test_lifetime') : ($testResult['days'] . ' days');
        $msg = Lang::t('gr_test_allowed', ['days' => $daysLabel]);
        $cls = 'success';
      } else {
        $msg = Lang::t('gr_test_blocked');
        $cls = 'danger';
      }
    ?>
    <div class="alert alert-<?= $cls ?>" style="margin-top:14px;margin-bottom:0">
      <i class="fa-solid <?= $testResult['allowed'] ? 'fa-recycle' : 'fa-ban' ?>" style="margin-top:3px"></i>
      <div><?= htmlspecialchars($msg) ?>
        <?php if ($testResult['matched']): ?><span style="opacity:.7">· <?= htmlspecialchars(Lang::t('gr_test_matched', ['kw' => $testResult['matched']])) ?></span><?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if (empty($rules)): ?>
<div class="card" style="text-align:center">
  <p style="color:var(--text-muted);margin-bottom:14px"><?php e('gr_none'); ?></p>
  <form method="post" action="guarantee-rules.php">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="seed">
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-wand-magic-sparkles"></i> <?php e('gr_seed'); ?></button>
  </form>
</div>
<?php endif; ?>

<div class="grid grid-2">
  <!-- No-guarantee -->
  <div class="card">
    <h3 style="margin-top:0">🚫 <?php e('gr_no_title'); ?></h3>
    <div style="margin:12px 0"><?php foreach ($noRules as $r) { echo ruleChip($r); } ?></div>
    <form method="post" action="guarantee-rules.php" style="display:flex;gap:8px;margin-top:14px">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="rule_type" value="no_guarantee">
      <input class="form-control" type="text" name="keyword" placeholder="<?php e('gr_keyword'); ?>" required>
      <button class="btn btn-outline" type="submit"><?php e('gr_add'); ?></button>
    </form>
  </div>

  <!-- Guarantee -->
  <div class="card">
    <h3 style="margin-top:0">♻️ <?php e('gr_yes_title'); ?></h3>
    <div style="margin:12px 0"><?php foreach ($yesRules as $r) { echo ruleChip($r); } ?></div>
    <form method="post" action="guarantee-rules.php" style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="rule_type" value="guarantee">
      <input class="form-control" style="flex:1" type="text" name="keyword" placeholder="<?php e('gr_keyword'); ?>" required>
      <input class="form-control" style="width:90px" type="number" name="refill_days" value="30" min="0">
      <button class="btn btn-outline" type="submit"><?php e('gr_add'); ?></button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
