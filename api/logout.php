<?php
require __DIR__ . '/_bootstrap.php';
requireMethod('POST');
requireCsrf();
$_SESSION = [];
session_regenerate_id(true);
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
jsonResponse(['success' => true]);
