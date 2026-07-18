<?php

require_once __DIR__ . '/BaseModel.php';

class Superadmin extends BaseModel
{
    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM superadmins WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function findByUsername(string $username): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM superadmins WHERE username = ?');
        $stmt->execute([$username]);

        return $stmt->fetch() ?: null;
    }

    public static function create(string $username, string $password): int
    {
        $stmt = self::db()->prepare(
            'INSERT INTO superadmins (username, password_hash) VALUES (?, ?)'
        );
        $stmt->execute([$username, password_hash($password, PASSWORD_BCRYPT)]);

        return (int) self::db()->lastInsertId();
    }

    public static function verifyPassword(array $admin, string $password): bool
    {
        return password_verify($password, $admin['password_hash']);
    }
}
