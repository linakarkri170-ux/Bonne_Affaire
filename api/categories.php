<?php
require __DIR__ . '/_bootstrap.php';
try {
    $categories = database()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
    $cities = database()->query('SELECT id, name FROM cities ORDER BY name')->fetchAll();
    jsonResponse(['success' => true, 'categories' => $categories, 'cities' => $cities]);
} catch (Throwable $error) { failServer($error); }
