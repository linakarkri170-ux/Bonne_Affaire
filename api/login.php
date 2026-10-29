<?php
require __DIR__ . '/_bootstrap.php';
requireMethod('POST');
requireCsrf();
$data = requestData();
$email = strtolower(trim((string) ($data['email'] ?? '')));
$password = (string) ($data['password'] ?? '');
try {
    $stmt = database()->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(['success' => false, 'message' => 'Email or password is incorrect.'], 401);
    }
    session_regenerate_id(true);
    unset($user['password_hash']);
    $user['id'] = (int) $user['id'];
    $_SESSION['user'] = $user;
    jsonResponse(['success' => true, 'user' => $user]);
} catch (Throwable $error) { failServer($error); }
