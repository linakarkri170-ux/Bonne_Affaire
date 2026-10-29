<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/vendor/autoload.php';
if (class_exists(Dotenv\Dotenv::class)) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
}
require_once dirname(__DIR__) . '/config/db.php';

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
final class DatabaseSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }
    public function read(string $id): string|false
    {
        $stmt = database()->prepare('SELECT session_data FROM user_sessions WHERE session_id=? AND expires_at > CURRENT_TIMESTAMP');
        $stmt->execute([$id]);
        return $stmt->fetchColumn() ?: '';
    }
    public function write(string $id, string $data): bool
    {
        $lifetime = max(1, min(86400, (int) ini_get('session.gc_maxlifetime')));
        $stmt = database()->prepare("INSERT INTO user_sessions (session_id, session_data, expires_at) VALUES (?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL {$lifetime} SECOND)) ON DUPLICATE KEY UPDATE session_data=VALUES(session_data), expires_at=VALUES(expires_at)");
        return $stmt->execute([$id, $data]);
    }
    public function destroy(string $id): bool
    {
        $stmt = database()->prepare('DELETE FROM user_sessions WHERE session_id=?');
        return $stmt->execute([$id]);
    }
    public function gc(int $max_lifetime): int|false
    {
        $stmt = database()->prepare('DELETE FROM user_sessions WHERE expires_at <= CURRENT_TIMESTAMP');
        $stmt->execute();
        return $stmt->rowCount();
    }
}
session_set_save_handler(new DatabaseSessionHandler(), true);
if (session_status() !== PHP_SESSION_ACTIVE) {
    try {
        session_start();
    } catch (Throwable $error) {
        error_log($error->getMessage());
        jsonResponse(['success' => false, 'message' => 'Database session storage is unavailable. Check the database setup.'], 503);
    }
}

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requestData(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) jsonResponse(['success' => false, 'message' => 'Invalid request body.'], 400);
    return $data;
}

function requireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
}

function requireCsrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        jsonResponse(['success' => false, 'message' => 'Session expired. Refresh the page and try again.'], 419);
    }
}

function currentUser(): ?array
{
    $id = (int) ($_SESSION['user']['id'] ?? 0);
    if (!$id) return null;
    $stmt = database()->prepare('SELECT id, name, email, role FROM users WHERE id=?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) { unset($_SESSION['user']); return null; }
    $user['id'] = (int) $user['id'];
    $_SESSION['user'] = $user;
    return $user;
}

function requireUser(): array
{
    $user = currentUser();
    if (!$user) jsonResponse(['success' => false, 'message' => 'Sign in to continue.'], 401);
    return $user;
}

function requireAdmin(): array
{
    $user = requireUser();
    if ($user['role'] !== 'admin') jsonResponse(['success' => false, 'message' => 'Administrator access required.'], 403);
    return $user;
}

function database(): PDO
{
    static $pdo = null;
    if (!$pdo) $pdo = getDatabase();
    return $pdo;
}

function failServer(Throwable $error): never
{
    error_log($error->getMessage());
    jsonResponse(['success' => false, 'message' => 'Server error. Check the database configuration and try again.'], 500);
}
