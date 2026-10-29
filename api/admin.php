<?php
require __DIR__ . '/_bootstrap.php';
requireAdmin();
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        $listings = database()->query("SELECT l.id, l.title, l.price, l.status, c.name AS category, ci.name AS city, u.name AS seller FROM listings l JOIN categories c ON c.id=l.category_id JOIN cities ci ON ci.id=l.city_id JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC")->fetchAll();
        $members = database()->query("SELECT id, name, email, role FROM users WHERE role='member' ORDER BY created_at DESC")->fetchAll();
        $counts = database()->query("SELECT COUNT(*) total, SUM(status='pending') pending, SUM(status='approved') approved FROM listings")->fetch();
        jsonResponse(['success' => true, 'listings' => $listings, 'members' => $members, 'counts' => $counts,
            'categories' => database()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(),
            'cities' => database()->query('SELECT id, name FROM cities ORDER BY name')->fetchAll()]);
    }
    requireMethod('POST'); requireCsrf();
    $data = requestData(); $action = (string) ($data['action'] ?? ''); $pdo = database();
    if ($action === 'moderate') {
        $status = $data['status'] ?? '';
        if (!in_array($status, ['approved', 'rejected'], true)) jsonResponse(['success' => false, 'message' => 'Invalid listing status.'], 422);
        $stmt = $pdo->prepare('UPDATE listings SET status=? WHERE id=?'); $stmt->execute([$status, (int) ($data['id'] ?? 0)]);
    } elseif ($action === 'delete-listing') {
        $stmt = $pdo->prepare('DELETE FROM listings WHERE id=?'); $stmt->execute([(int) ($data['id'] ?? 0)]);
    } elseif ($action === 'delete-member') {
        $id = (int) ($data['id'] ?? 0);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM listings WHERE user_id=? AND EXISTS (SELECT id FROM users WHERE id=? AND role='member')");
        $stmt->execute([$id, $id]);
        $stmt = $pdo->prepare("DELETE FROM users WHERE id=? AND role='member'"); $stmt->execute([$id]);
        $pdo->commit();
    } elseif (in_array($action, ['add-category', 'add-city'], true)) {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || strlen($name) > 100) jsonResponse(['success' => false, 'message' => 'Enter a name of up to 100 characters.'], 422);
        $table = $action === 'add-category' ? 'categories' : 'cities';
        $stmt = $pdo->prepare("INSERT INTO {$table} (name) VALUES (?)"); $stmt->execute([$name]);
    } elseif (in_array($action, ['delete-category', 'delete-city'], true)) {
        $table = $action === 'delete-category' ? 'categories' : 'cities';
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id=?"); $stmt->execute([(int) ($data['id'] ?? 0)]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Unknown admin action.'], 422);
    }
    jsonResponse(['success' => true]);
} catch (PDOException $error) {
    if ($error->getCode() === '23000') jsonResponse(['success' => false, 'message' => 'This item is already in use or already exists.'], 409);
    failServer($error);
} catch (Throwable $error) { failServer($error); }
