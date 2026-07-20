<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

$notice = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();
    $key = trim($_POST['deepseek_key'] ?? '');
    TenantAi::save($tenantId, $key !== '' ? $key : null);
    header('Location: settings-ai.php?saved=1');
    exit;
}

if (isset($_GET['saved'])) {
    $notice = Lang::t('ai_saved');
}

$ai = TenantAi::forTenant($tenantId);
$configured = $ai !== null && !empty($ai['deepseek_api_key_enc']);

$baseUrl = rtrim($config['app']['url'] ?? '', '/');
$widgetUrl = $baseUrl . '/widget/ticket.php?t=' . $tenantId;
$embed = "<script>\n"
    . "(function(){window.SMMTicket={endpoint:'" . $widgetUrl . "'};\n"
    . "var s=document.createElement('script');s.src='" . $baseUrl . "/assets/js/ticket-widget.js';\n"
    . "s.async=true;document.body.appendChild(s);})();\n"
    . "</script>";

// Structured ticket form (Category -> Subcategory -> Order ID), embedded via iframe.
$formUrl = $baseUrl . '/widget/ticket-form.php?t=' . $tenantId;
$formEmbed = '<iframe src="' . $formUrl . '"'
    . ' style="width:100%;max-width:560px;height:760px;border:0;border-radius:14px"'
    . ' title="Support Tickets"></iframe>';

$pageTitle = Lang::t('ai_title');
$activeSide = 'ai';
require __DIR__ . '/includes/dash_header.php';
?>

<?php if ($notice !== null): ?>
<div class="alert alert-success"><i class="fa-solid fa-circle-check" style="margin-top:3px"></i> <?= htmlspecialchars($notice) ?></div>
<?php endif; ?>

<div class="grid grid-2">
  <div class="card">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
      <h3 style="margin:0;flex:1"><?php e('ai_title'); ?></h3>
      <span class="status-chip <?= $configured ? 'active' : 'locked' ?>" style="position:static">
        <?= $configured ? htmlspecialchars(Lang::t('ai_configured')) : htmlspecialchars(Lang::t('ai_not_configured')) ?>
      </span>
    </div>
    <form method="post" action="settings-ai.php">
      <?= Csrf::field() ?>
      <div class="form-group">
        <label><?php e('ai_key'); ?></label>
        <input class="form-control" type="password" name="deepseek_key" placeholder="<?= $configured ? '••••••• (saved)' : 'sk-...' ?>">
        <div class="form-hint"><?php e('ai_key_hint'); ?></div>
      </div>
      <button class="btn btn-primary" type="submit"><?php e('save'); ?></button>
    </form>
  </div>

  <div class="card">
    <h3 style="margin-top:0"><?php e('ai_widget_title'); ?></h3>
    <p style="color:var(--text-muted);font-size:.9rem"><?php e('ai_widget_hint'); ?></p>
    <pre style="background:var(--bg-soft);padding:14px;border-radius:10px;overflow-x:auto;font-size:.8rem;line-height:1.5"><code><?= htmlspecialchars($embed) ?></code></pre>
  </div>
</div>

<div class="card" style="margin-top:18px">
  <h3 style="margin-top:0"><?php e('ai_form_title'); ?></h3>
  <p style="color:var(--text-muted);font-size:.9rem"><?php e('ai_form_hint'); ?></p>
  <label style="display:block;font-size:.85rem;color:var(--text-muted);margin-bottom:6px"><?php e('ai_form_link'); ?></label>
  <pre style="background:var(--bg-soft);padding:14px;border-radius:10px;overflow-x:auto;font-size:.8rem;margin-bottom:14px"><code><?= htmlspecialchars($formUrl) ?></code></pre>
  <label style="display:block;font-size:.85rem;color:var(--text-muted);margin-bottom:6px"><?php e('ai_form_embed'); ?></label>
  <pre style="background:var(--bg-soft);padding:14px;border-radius:10px;overflow-x:auto;font-size:.8rem;line-height:1.5"><code><?= htmlspecialchars($formEmbed) ?></code></pre>
  <a href="<?= htmlspecialchars($formUrl) ?>" target="_blank" class="btn btn-outline" style="margin-top:6px"><i class="fa-solid fa-up-right-from-square"></i> <?php e('ai_form_preview'); ?></a>
</div>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
