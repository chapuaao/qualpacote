<?php
declare(strict_types=1);

namespace QualPacote;

use PDO;

final class Auth
{
    public static function user(): ?array
    {
        if (empty($_SESSION['admin_id'])) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, email, name FROM admins WHERE id = ? AND active = 1 LIMIT 1'
        );
        $stmt->execute([(int) $_SESSION['admin_id']]);

        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function attempt(string $email, string $password): bool
    {
        self::pruneAttempts();

        $attempts = $_SESSION['_login_attempts'] ?? [];
        if (count($attempts) >= 8) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, email, password_hash, active FROM admins WHERE email = ? LIMIT 1'
        );
        $stmt->execute([mb_strtolower(trim($email))]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        $ok = $admin && (int) $admin['active'] === 1 && password_verify($password, $admin['password_hash']);

        if (!$ok) {
            $_SESSION['_login_attempts'][] = time();
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['_login_attempts'] = [];

        $update = Database::connection()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?');
        $update->execute([(int) $admin['id']]);

        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION['admin_id']);
        session_regenerate_id(true);
    }

    private static function pruneAttempts(): void
    {
        $cutoff = time() - 900;
        $attempts = array_filter(
            $_SESSION['_login_attempts'] ?? [],
            static fn ($timestamp) => (int) $timestamp >= $cutoff
        );

        $_SESSION['_login_attempts'] = array_values($attempts);
    }
}
