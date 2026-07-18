<?php
require_once __DIR__ . '/_boot.php';
SuperadminAuth::logout();
header('Location: login.php');
exit;
