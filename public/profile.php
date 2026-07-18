<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tenant = TenantAuth::require('login.php');
$tenantId = (int) $tenant['id'];

$errors = [];
$success = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();

    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $businessName = trim($_POST['business_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $lang = $_POST['lang'] ?? 'en';

        if ($businessName === '') {
            $errors[] = Lang::t('err_required');
        } elseif (!in_array($lang, Lang::SUPPORTED, true)) {
            $errors[] = Lang::t('err_required');
        } else {
            Tenant::updateProfile($tenantId, $businessName, $phone !== '' ? $phone : null);
            Tenant::setLang($tenantId, $lang);
            $_SESSION['lang'] = $lang;
            header('Location: profile.php?saved=1');
            exit;
        }
    }

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($current === '' || $new === '') {
            $errors[] = Lang::t('err_required');
        } elseif (!Tenant::verifyPassword($tenant, $current)) {
            $errors[] = Lang::t('err_current_password');
        } elseif (strlen($new) < 8) {
            $errors[] = Lang::t('err_password_short');
        } elseif ($new !== $confirm) {
            $errors[] = Lang::t('err_password_match');
        } else {
            Tenant::updatePassword($tenantId, $new);
            header('Location: profile.php?pw=1');
            exit;
        }
    }

    // Re-read after a failed POST so the form shows current values.
    $tenant = Tenant::find($tenantId);
}

if (isset($_GET['saved'])) { $success = Lang::t('profile_saved'); }
if (isset($_GET['pw'])) { $success = Lang::t('password_saved'); }

$pageTitle = Lang::t('profile_title');
$activeSide = 'profile';
require __DIR__ . '/includes/dash_header.php';
?>

<?php if ($success !== null): ?>
<div class="alert alert-success"><i class="fa-solid fa-circle-check" style="margin-top:3px"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
  <i class="fa-solid fa-circle-exclamation" style="margin-top:3px"></i>
  <div><?php foreach ($errors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?></div>
</div>
<?php endif; ?>

<div class="grid grid-2">
  <div class="card">
    <h3 style="margin-top:0"><?php e('profile_title'); ?></h3>
    <form method="post" action="profile.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="profile">
      <div class="form-group">
        <label for="business_name"><?php e('business_name'); ?></label>
        <input class="form-control" type="text" id="business_name" name="business_name" value="<?= htmlspecialchars($tenant['business_name']) ?>" required>
      </div>
      <div class="form-group">
        <label for="phone"><?php e('phone'); ?></label>
        <input class="form-control" type="tel" id="phone" name="phone" value="<?= htmlspecialchars($tenant['phone'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="lang">Language / Langue / Lugha</label>
        <select class="form-control" id="lang" name="lang">
          <option value="en" <?= $tenant['lang'] === 'en' ? 'selected' : '' ?>>English</option>
          <option value="fr" <?= $tenant['lang'] === 'fr' ? 'selected' : '' ?>>Français</option>
          <option value="sw" <?= $tenant['lang'] === 'sw' ? 'selected' : '' ?>>Kiswahili</option>
        </select>
      </div>
      <button class="btn btn-primary" type="submit"><?php e('save'); ?></button>
    </form>
  </div>

  <div class="card">
    <h3 style="margin-top:0"><?php e('password'); ?></h3>
    <form method="post" action="profile.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="password">
      <div class="form-group">
        <label for="current_password"><?php e('current_password'); ?></label>
        <input class="form-control" type="password" id="current_password" name="current_password" required>
      </div>
      <div class="form-group">
        <label for="new_password"><?php e('new_password'); ?></label>
        <input class="form-control" type="password" id="new_password" name="new_password" minlength="8" required>
      </div>
      <div class="form-group">
        <label for="confirm_password"><?php e('confirm_password'); ?></label>
        <input class="form-control" type="password" id="confirm_password" name="confirm_password" minlength="8" required>
      </div>
      <button class="btn btn-primary" type="submit"><?php e('save'); ?></button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/dash_footer.php'; ?>
