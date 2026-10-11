<?php
declare(strict_types=1);

namespace QualPacote;

final class UsageSession
{
    public static function visitorKey(): string
    {
        if (!isset($_SESSION['_usage_visitor'])) {
            $cookie = $_COOKIE['qualpacote_visitor'] ?? null;
            $token = is_string($cookie) && preg_match('/\A[a-f0-9]{64}\z/D', $cookie)
                ? $cookie : bin2hex(random_bytes(32));
            $_SESSION['_usage_visitor'] = hash('sha256', $token);
            setcookie('qualpacote_visitor', $token, [
                'expires' => time() + 365 * 86400,
                'path' => '/', 'secure' => Env::bool('APP_HTTPS', false),
                'httponly' => true, 'samesite' => 'Lax',
            ]);
        }
        return (string) $_SESSION['_usage_visitor'];
    }

    public static function formToken(): string
    {
        $tokens = array_filter($_SESSION['_usage_forms'] ?? [], static fn ($at) => $at > time() - 86400);
        $token = bin2hex(random_bytes(32));
        $tokens[$token] = time();
        $_SESSION['_usage_forms'] = array_slice($tokens, -100, null, true);
        return $token;
    }

    public static function requestToken(mixed $submitted, array $criteria): string
    {
        // Only server-issued tokens can start a new consultation. Refreshing the
        // same result URL reuses its consultation, including on legacy GET URLs.
        $signature = hash('sha256', serialize($criteria));
        if (is_string($submitted) && isset($_SESSION['_usage_forms'][$submitted])) {
            $token = hash('sha256', $submitted . $signature);
        } else {
            $token = $_SESSION['_usage_queries'][$signature] ?? bin2hex(random_bytes(32));
        }
        $_SESSION['_usage_queries'][$signature] = $token;
        $_SESSION['_usage_queries'] = array_slice($_SESSION['_usage_queries'], -100, null, true);
        return $token;
    }
}
