<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

$editLang = in_array($_GET['tl'] ?? '', Lang::SUPPORTED, true) ? $_GET['tl'] : Lang::current();
$notice = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();
    $postLang = in_array($_POST['tl'] ?? '', Lang::SUPPORTED, true) ? $_POST['tl'] : 'en';
    $overrides = $_POST['tmpl'] ?? [];
    if (is_array($overrides)) {
        foreach ($overrides as $key => $content) {
            ResponseTemplate::saveOverride($tenantId, (string) $key, $postLang, (string) $content);
        }
    }
    header('Location: templates.php?tl=' . urlencode($postLang) . '&saved=1');
    exit;
}

if (isset($_GET['saved'])) {
    $notice = Lang::t('tmpl_saved');
}

$templates = ResponseTemplate::forEditor($tenantId, $editLang);

$pageTitle = Lang::t('tmpl_title');
$activeSide = 'templates';
require __DIR__ . '/includes/dash_header.php';
?>

<p style="color:var(--text-muted);margin-bottom:18px"><?php e('tmpl_intro'); ?></p>

<?php if ($notice !== null): ?>
<div class="alert alert-success"><i class="fa-solid fa-circle-check" style="margin-top:3px"></i> <?= htmlspecialchars($notice) ?></div>
<?php endif; ?>

<div style="display:flex;gap:8px;margin-bottom:18px">
  <?php foreach (Lang::SUPPORTED as $lng): ?>
  <a class="btn <?= $editLang === $lng ? 'btn-primary' : 'btn-outline' ?>" href="templates.php?tl=<?= $lng ?>" style="padding:8px 16px"><?= strtoupper($lng) ?></a>
  <?php endforeach; ?>
</div>

<form method="post" action="templates.php">
  <?= Csrf::field() ?>
  <input type="hidden" name="tl" value="<?= htmlspecialchars($editLang) ?>">
  <div class="card">
    <?php foreach ($templates as $key => $t): ?>
    <div style="padding:14px 0;border-bottom:1px solid var(--border)">
      <div style="display:flex;justify-content:space-between;margin-bottom:6px">
        <strong style="font-family:monospace;font-size:.85rem"><?= htmlspecialchars($key) ?></strong>
      </div>
      <div style="font-size:.82rem;color:var(--text-muted);margin-bottom:8px"><?php e('tmpl_default'); ?>: <?= htmlspecialchars($t['default'] ?? '—') ?></div>
      <textarea class="form-control" name="tmpl[<?= htmlspecialchars($key) ?>]" rows="2" placeholder="<?= htmlspecialchars($t['default'] ?? '') ?>"><?= htmlspecialchars($t['override'] ?? '') ?></textarea>
    </div>
    <?php endforeach; ?>
    <button class="btn btn-primary" type="submit" style="margin-top:16px"><?php e('tmpl_save'); ?></button>
  </div>
</form>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
