<?php
require __DIR__ . '/_bootstrap.php';
$user = requireUser();
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        $stmt = database()->prepare("SELECT l.id, l.user_id AS seller_id, l.title, l.description, l.price, l.image_url AS image,
                       c.name AS category, ci.name AS city, u.name AS seller, u.email,
                       DATE_FORMAT(l.created_at, '%Y-%m-%d') AS date
                FROM favorites f JOIN listings l ON l.id=f.listing_id
                JOIN categories c ON c.id=l.category_id JOIN cities ci ON ci.id=l.city_id
                JOIN users u ON u.id=l.user_id WHERE f.user_id=? AND l.status='approved'
                ORDER BY f.created_at DESC");
        $stmt->execute([$user['id']]);
        jsonResponse(['success' => true, 'listings' => $stmt->fetchAll()]);
    }
    requireMethod('POST'); requireCsrf();
    $data = requestData(); $listingId = filter_var($data['listing_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$listingId) jsonResponse(['success' => false, 'message' => 'Invalid listing.'], 422);
    if (!empty($data['remove'])) {
        $stmt = database()->prepare('DELETE FROM favorites WHERE user_id=? AND listing_id=?');
        $stmt->execute([$user['id'], $listingId]);
    } else {
        $stmt = database()->prepare("INSERT IGNORE INTO favorites (user_id, listing_id) SELECT ?, id FROM listings WHERE id=? AND status='approved'");
        $stmt->execute([$user['id'], $listingId]);
    }
    jsonResponse(['success' => true]);
} catch (Throwable $error) { failServer($error); }
