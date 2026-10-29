<?php
require __DIR__ . '/_bootstrap.php';
$user = requireUser();
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        $listingId = filter_var($_GET['listing_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$listingId) {
            $stmt = database()->prepare("SELECT DISTINCT l.id AS listing_id, l.title,
                    CASE WHEN m.sender_id=? THEN m.receiver_id ELSE m.sender_id END AS with_id,
                    other.name AS partner
                FROM messages m JOIN listings l ON l.id=m.listing_id
                JOIN users other ON other.id=CASE WHEN m.sender_id=? THEN m.receiver_id ELSE m.sender_id END
                WHERE m.sender_id=? OR m.receiver_id=? ORDER BY l.id DESC");
            $stmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
            jsonResponse(['success' => true, 'conversations' => $stmt->fetchAll()]);
        }

        $stmt = database()->prepare('SELECT user_id, title FROM listings WHERE id=?');
        $stmt->execute([$listingId]);
        $listing = $stmt->fetch();
        if (!$listing) jsonResponse(['success' => false, 'message' => 'Listing not found.'], 404);
        $ownerId = (int) $listing['user_id'];
        $withId = filter_var($_GET['with_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$withId && $ownerId !== (int) $user['id']) $withId = $ownerId;
        if (!$withId && $ownerId === (int) $user['id']) {
            $latest = database()->prepare('SELECT CASE WHEN sender_id=? THEN receiver_id ELSE sender_id END FROM messages WHERE listing_id=? AND (sender_id=? OR receiver_id=?) ORDER BY created_at DESC, id DESC LIMIT 1');
            $latest->execute([$user['id'], $listingId, $user['id'], $user['id']]);
            $withId = (int) $latest->fetchColumn();
        }
        if (!$withId || ($ownerId !== (int) $user['id'] && $withId !== $ownerId)) {
            jsonResponse(['success' => false, 'message' => 'This conversation is not available.'], 403);
        }
        $partner = database()->prepare('SELECT name FROM users WHERE id=?'); $partner->execute([$withId]);
        $partnerName = $partner->fetchColumn();
        if (!$partnerName) jsonResponse(['success' => false, 'message' => 'Conversation member not found.'], 404);
        $stmt = database()->prepare('SELECT id, sender_id, receiver_id, message, DATE_FORMAT(created_at, \'%Y-%m-%d %H:%i\') AS date FROM messages WHERE listing_id=? AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) ORDER BY created_at, id');
        $stmt->execute([$listingId, $user['id'], $withId, $withId, $user['id']]);
        jsonResponse(['success' => true, 'title' => $listing['title'], 'with_id' => $withId, 'partner' => $partnerName, 'messages' => $stmt->fetchAll()]);
    }

    requireMethod('POST'); requireCsrf();
    $data = requestData();
    $listingId = filter_var($data['listing_id'] ?? null, FILTER_VALIDATE_INT);
    $withId = filter_var($data['with_id'] ?? null, FILTER_VALIDATE_INT);
    $message = trim((string) ($data['message'] ?? ''));
    if (!$listingId || $message === '' || strlen($message) > 4000) jsonResponse(['success' => false, 'message' => 'Enter a message of up to 4,000 characters.'], 422);
    $stmt = database()->prepare("SELECT user_id FROM listings WHERE id=? AND status='approved'"); $stmt->execute([$listingId]);
    $ownerId = (int) $stmt->fetchColumn();
    if (!$ownerId) jsonResponse(['success' => false, 'message' => 'Listing not found.'], 404);
    if ($ownerId === (int) $user['id']) {
        if (!$withId || $withId === $ownerId) jsonResponse(['success' => false, 'message' => 'Choose a conversation to reply to.'], 422);
        $exists = database()->prepare('SELECT 1 FROM messages WHERE listing_id=? AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) LIMIT 1');
        $exists->execute([$listingId, $user['id'], $withId, $withId, $user['id']]);
        if (!$exists->fetchColumn()) jsonResponse(['success' => false, 'message' => 'That conversation does not exist.'], 403);
        $recipientId = $withId;
    } else {
        if ((int) $user['id'] === $ownerId) jsonResponse(['success' => false, 'message' => 'You cannot message yourself.'], 422);
        $recipientId = $ownerId;
    }
    $stmt = database()->prepare('INSERT INTO messages (listing_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)');
    $stmt->execute([$listingId, $user['id'], $recipientId, $message]);
    jsonResponse(['success' => true], 201);
} catch (Throwable $error) { failServer($error); }
