<?php
require __DIR__ . '/_bootstrap.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        $sql = "SELECT l.id, l.user_id AS seller_id, l.title, l.description, l.price, l.image_url AS image,
                       c.name AS category, ci.name AS city, u.name AS seller, u.email,
                       DATE_FORMAT(l.created_at, '%Y-%m-%d') AS date
                FROM listings l
                JOIN categories c ON c.id = l.category_id
                JOIN cities ci ON ci.id = l.city_id
                JOIN users u ON u.id = l.user_id
                WHERE l.status = 'approved'";
        $params = [];
        if (!empty($_GET['id'])) { $sql .= ' AND l.id = ?'; $params[] = (int) $_GET['id']; }
        $filters = ['q' => trim((string) ($_GET['q'] ?? '')), 'category' => trim((string) ($_GET['category'] ?? '')), 'city' => trim((string) ($_GET['city'] ?? ''))];
        if ($filters['q'] !== '') {
            $sql .= ' AND (l.title LIKE ? OR l.description LIKE ?)';
            $params[] = '%' . $filters['q'] . '%'; $params[] = '%' . $filters['q'] . '%';
        }
        if ($filters['category'] !== '') { $sql .= ' AND c.name = ?'; $params[] = $filters['category']; }
        if ($filters['city'] !== '') { $sql .= ' AND ci.name = ?'; $params[] = $filters['city']; }
        if (isset($_GET['min']) && $_GET['min'] !== '') { $sql .= ' AND l.price >= ?'; $params[] = max(0, (float) $_GET['min']); }
        if (isset($_GET['max']) && $_GET['max'] !== '') { $sql .= ' AND l.price <= ?'; $params[] = max(0, (float) $_GET['max']); }
        if (!empty($_GET['date'])) { $sql .= ' AND DATE(l.created_at) >= ?'; $params[] = $_GET['date']; }
        $sort = $_GET['sort'] ?? 'recent';
        $sql .= match ($sort) { 'low' => ' ORDER BY l.price ASC', 'high' => ' ORDER BY l.price DESC', default => ' ORDER BY l.created_at DESC' };
        $sql .= isset($_GET['limit']) ? ' LIMIT 8' : '';
        $stmt = database()->prepare($sql);
        $stmt->execute($params);
        $listings = $stmt->fetchAll();
        foreach ($listings as &$listing) { $listing['id'] = (int) $listing['id']; $listing['price'] = (float) $listing['price']; }
        jsonResponse(['success' => true, 'listings' => $listings]);
    }

    requireMethod('POST');
    requireCsrf();
    $user = requireUser();
    $data = requestData();
    $title = trim((string) ($data['title'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $price = filter_var($data['price'] ?? null, FILTER_VALIDATE_FLOAT);
    $image = trim((string) ($data['image'] ?? ''));
    $imageScheme = $image === '' ? '' : strtolower((string) parse_url($image, PHP_URL_SCHEME));
    if ($title === '' || strlen($title) > 200 || $description === '' || strlen($description) > 60000 || $price === false || $price < 0 || ($image !== '' && (!filter_var($image, FILTER_VALIDATE_URL) || !in_array($imageScheme, ['http', 'https'], true)))) {
        jsonResponse(['success' => false, 'message' => 'Complete the listing fields with a valid title, price and image URL.'], 422);
    }
    $pdo = database();
    $category = $pdo->prepare('SELECT id FROM categories WHERE name = ?'); $category->execute([(string) ($data['category'] ?? '')]);
    $city = $pdo->prepare('SELECT id FROM cities WHERE name = ?'); $city->execute([(string) ($data['city'] ?? '')]);
    $categoryId = $category->fetchColumn(); $cityId = $city->fetchColumn();
    if (!$categoryId || !$cityId) jsonResponse(['success' => false, 'message' => 'Choose a valid category and city.'], 422);
    $stmt = $pdo->prepare('INSERT INTO listings (user_id, category_id, city_id, title, description, price, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$user['id'], $categoryId, $cityId, $title, $description, $price, $image ?: null]);
    jsonResponse(['success' => true, 'message' => 'Listing submitted for review.'], 201);
} catch (Throwable $error) { failServer($error); }
