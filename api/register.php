<?php
require __DIR__ . '/_bootstrap.php';
requireMethod('POST');
requireCsrf();
$data = requestData();
$name = trim((string) ($data['name'] ?? ''));
$email = strtolower(trim((string) ($data['email'] ?? '')));
$password = (string) ($data['password'] ?? '');
if ($name === '' || strlen($name) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    jsonResponse(['success' => false, 'message' => 'Enter a name, valid email, and password of at least 8 characters.'], 422);
}
try {
    $stmt = database()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => (int) database()->lastInsertId(), 'name' => $name, 'email' => $email, 'role' => 'member'];
    jsonResponse(['success' => true, 'user' => currentUser()], 201);
} catch (PDOException $error) {
    if ($error->getCode() === '23000') jsonResponse(['success' => false, 'message' => 'That email address is already registered.'], 409);
    failServer($error);
}
