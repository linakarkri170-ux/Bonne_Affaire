<?php
require __DIR__ . '/_bootstrap.php';
if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
jsonResponse(['success' => true, 'csrf' => $_SESSION['csrf_token'], 'user' => currentUser()]);
