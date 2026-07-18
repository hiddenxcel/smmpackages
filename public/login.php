<?php
require_once __DIR__ . '/includes/bootstrap.php';

TenantAuth::redirectIfAuthed('index.php');

$errors = [];
$notice = isset($_GET['out']) ? Lang::t('logout_done') : null;
$oldEmail = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!RateLimit::hit($ip, 'login', 5, 900)) {
        $errors[] = Lang::t('err_rate_limited');
    } else {
        $oldEmail = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($oldEmail === '' || $password === '') {
            $errors[] = Lang::t('err_required');
        } else {
            $tenant = Tenant::findByEmail($oldEmail);

            if ($tenant === null
                || $tenant['status'] === 'suspended'
                || !Tenant::verifyPassword($tenant, $password)) {
                $errors[] = Lang::t('err_login_failed');
            } else {
                RateLimit::clear($ip, 'login');
                TenantAuth::login((int) $tenant['id']);
                header('Location: index.php');
                exit;
            }
        }
    }
}

$pageTitle = Lang::t('login_title');
require __DIR__ . '/includes/layout_header.php';
?>

<div class="auth-wrap">
  <div class="auth-card">
    <div class="card">
      <h1><?php e('login_title'); ?></h1>
      <p class="auth-sub"><?php e('brand'); ?></p>

      <?php if ($notice !== null): ?>
      <div class="alert alert-success"><i class="fa-solid fa-circle-check" style="margin-top:3px"></i> <?= htmlspecialchars($notice) ?></div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation" style="margin-top:3px"></i>
        <div><?php foreach ($errors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?></div>
      </div>
      <?php endif; ?>

      <form method="post" action="login.php">
        <?= Csrf::field() ?>
        <div class="form-group">
          <label for="email"><?php e('email'); ?></label>
          <input class="form-control" type="email" id="email" name="email" value="<?= htmlspecialchars($oldEmail) ?>" required>
        </div>
        <div class="form-group">
          <label for="password"><?php e('password'); ?></label>
          <input class="form-control" type="password" id="password" name="password" required>
        </div>
        <button class="btn btn-primary btn-lg btn-block" type="submit"><?php e('login_submit'); ?></button>
      </form>

      <p style="text-align:center;margin-top:20px;font-size:.9rem;color:var(--text-muted)">
        <?php e('no_account'); ?> <a href="register.php"><?php e('nav_start_free'); ?></a>
      </p>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
