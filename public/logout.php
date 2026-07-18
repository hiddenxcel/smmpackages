<?php
require_once __DIR__ . '/includes/bootstrap.php';

TenantAuth::logout();
header('Location: login.php?out=1');
exit;
