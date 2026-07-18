<?php
require_once __DIR__ . '/_boot.php';

SuperadminAuth::redirectIfAuthed('index.php');

$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (!RateLimit::hit("hx:{$ip}", 'admin_login', 5, 900)) {
        $error = 'Too many attempts. Wait a few minutes.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $admin = Superadmin::findByUsername($username);

        if ($admin === null || !Superadmin::verifyPassword($admin, $password)) {
            $error = 'Invalid credentials.';
            ActivityLog::record('system', null, 'hx_login_failed', ['username' => $username]);
        } else {
            RateLimit::clear("hx:{$ip}", 'admin_login');
            SuperadminAuth::login((int) $admin['id']);
            ActivityLog::record('superadmin', (int) $admin['id'], 'hx_login');
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HX Control</title>
<meta name="robots" content="noindex,nofollow">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<script>document.documentElement.setAttribute('data-theme','dark');</script>
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="card">
      <div style="text-align:center;margin-bottom:20px">
        <span class="logo-mark" style="background:var(--accent);width:52px;height:52px;font-size:1.4rem;margin:0 auto;display:flex;align-items:center;justify-content:center;border-radius:14px"><i class="fa-solid fa-shield-halved"></i></span>
      </div>
      <h1 style="text-align:center">HX Control</h1>
      <p class="auth-sub" style="text-align:center">Platform administration</p>

      <?php if ($error !== null): ?>
      <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation" style="margin-top:3px"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" action="login.php">
        <?= Csrf::field() ?>
        <div class="form-group">
          <label>Username</label>
          <input class="form-control" type="text" name="username" required autofocus>
        </div>
        <div class="form-group">
          <label>Password</label>
          <input class="form-control" type="password" name="password" required>
        </div>
        <button class="btn btn-accent btn-lg btn-block" type="submit">Log in</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
