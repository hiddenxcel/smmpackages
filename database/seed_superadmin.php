<?php
// CLI-only: php database/seed_superadmin.php <username> <password>
// Creates the platform super-admin (HiddenXcel).

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    die();
}

require_once __DIR__ . '/../app/models/Superadmin.php';

[$script, $username, $password] = array_pad($argv, 3, null);

if ($username === null || $password === null) {
    echo "Usage: php database/seed_superadmin.php <username> <password>\n";
    exit(1);
}

if (Superadmin::findByUsername($username) !== null) {
    echo "Super-admin '{$username}' already exists.\n";
    exit(1);
}

Superadmin::create($username, $password);
echo "Super-admin '{$username}' created.\n";
