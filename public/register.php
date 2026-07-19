<?php
require_once __DIR__ . '/includes/bootstrap.php';

TenantAuth::redirectIfAuthed('index.php');

$errors = [];
$old = ['business_name' => '', 'email' => '', 'phone' => ''];

// Referral: a ?ref=CODE (GET or carried through POST) links this signup to a referrer.
$refCode = strtoupper(trim($_REQUEST['ref'] ?? ''));
$referrer = $refCode !== '' ? Tenant::findByReferralCode($refCode) : null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!RateLimit::hit($ip, 'register', 5, 3600)) {
        $errors[] = Lang::t('err_rate_limited');
    } else {
        $old['business_name'] = trim($_POST['business_name'] ?? '');
        $old['email'] = trim($_POST['email'] ?? '');
        $old['phone'] = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($old['business_name'] === '' || $old['email'] === '' || $password === '') {
            $errors[] = Lang::t('err_required');
        }
        if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = Lang::t('err_email_invalid');
        }
        if ($password !== '' && strlen($password) < 8) {
            $errors[] = Lang::t('err_password_short');
        }
        if ($password !== $confirm) {
            $errors[] = Lang::t('err_password_match');
        }
        if (empty($errors) && Tenant::findByEmail($old['email']) !== null) {
            $errors[] = Lang::t('err_email_taken');
        }

        if (empty($errors)) {
            $tenant = Tenant::create(
                $old['business_name'],
                $old['email'],
                $password,
                $old['phone'] !== '' ? $old['phone'] : null,
                Lang::current(),
                $referrer !== null ? (int) $referrer['id'] : null
            );
            // Free sign-up: drop the reseller straight into a full SANDBOX — every
            // service unlocked for setup + self-test, but nothing live until they
            // pay and "Go Live". No card required to explore.
            Subscription::provisionSandbox((int) $tenant['id']);
            RateLimit::clear($ip, 'register');
            TenantAuth::login((int) $tenant['id']);
            header('Location: index.php?welcome=1');
            exit;
        }
    }
}

$pageTitle = Lang::t('register_title');
require __DIR__ . '/includes/layout_header.php';
?>

<div class="auth-wrap">
  <div class="auth-card">
    <div class="card">
      <h1><?php e('register_title'); ?></h1>
      <p class="auth-sub"><?php e('tagline'); ?></p>

      <?php if ($referrer !== null): ?>
      <div class="alert alert-success"><i class="fa-solid fa-gift" style="margin-top:3px"></i> <?= htmlspecialchars(Lang::t('ref_invited_by', ['name' => $referrer['business_name']])) ?></div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation" style="margin-top:3px"></i>
        <div><?php foreach ($errors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?></div>
      </div>
      <?php endif; ?>

      <form method="post" action="register.php">
        <?= Csrf::field() ?>
        <?php if ($refCode !== ''): ?><input type="hidden" name="ref" value="<?= htmlspecialchars($refCode) ?>"><?php endif; ?>
        <div class="form-group">
          <label for="business_name"><?php e('business_name'); ?></label>
          <input class="form-control" type="text" id="business_name" name="business_name" value="<?= htmlspecialchars($old['business_name']) ?>" required>
        </div>
        <div class="form-group">
          <label for="email"><?php e('email'); ?></label>
          <input class="form-control" type="email" id="email" name="email" value="<?= htmlspecialchars($old['email']) ?>" required>
        </div>
        <div class="form-group">
          <label for="phone"><?php e('phone'); ?></label>
          <input class="form-control" type="tel" id="phone" name="phone" value="<?= htmlspecialchars($old['phone']) ?>" placeholder="+255...">
        </div>
        <div class="form-group">
          <label for="password"><?php e('password'); ?></label>
          <input class="form-control" type="password" id="password" name="password" minlength="8" required>
          <div class="form-hint"><?php e('err_password_short'); ?></div>
        </div>
        <div class="form-group">
          <label for="confirm_password"><?php e('confirm_password'); ?></label>
          <input class="form-control" type="password" id="confirm_password" name="confirm_password" minlength="8" required>
        </div>
        <button class="btn btn-primary btn-lg btn-block" type="submit"><?php e('register_submit'); ?></button>
      </form>

      <p style="text-align:center;margin-top:20px;font-size:.9rem;color:var(--text-muted)">
        <?php e('have_account'); ?> <a href="login.php"><?php e('nav_login'); ?></a>
      </p>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
