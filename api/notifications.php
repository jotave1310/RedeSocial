<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$user = requireApiAuth();
$action = sanitizeText((string) ($_GET['action'] ?? 'list'), 20);
$userId = (int) $user['id'];

if ($action === 'list') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(false, [], 'Método inválido.', 405);
    }

    $pagination = getPagination(1, 30, 100);

    $listStmt = db()->prepare(
        'SELECT id, type, content, link, is_read, created_at
         FROM notifications
         WHERE user_id = :user_id
         ORDER BY created_at DESC
         LIMIT :limit OFFSET :offset'
    );
    $listStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $listStmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
    $listStmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $listStmt->execute();
    $rows = $listStmt->fetchAll();

    $notifications = array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'type' => (string) $row['type'],
            'content' => (string) $row['content'],
            'link' => $row['link'] ? (string) $row['link'] : null,
            'is_read' => (int) $row['is_read'] === 1,
            'created_at' => (string) $row['created_at'],
            'time_ago' => timeAgo((string) $row['created_at']),
        ];
    }, $rows);

    $countStmt = db()->prepare(
        'SELECT COUNT(*) AS unread
         FROM notifications
         WHERE user_id = :user_id
           AND is_read = 0'
    );
    $countStmt->execute(['user_id' => $userId]);
    $unread = (int) (($countStmt->fetch()['unread'] ?? 0));

    jsonResponse(true, [
        'notifications' => $notifications,
        'unread_count' => $unread,
        'page' => $pagination['page'],
    ]);
}

if ($action === 'mark_read') {
    requireApiPostMethod();
    validateCsrfFromRequest();

    $idRaw = (string) ($_POST['id'] ?? '');
    if ($idRaw === '') {
        jsonResponse(false, [], 'ID da notificação obrigatório.', 422);
    }

    if ($idRaw === 'all') {
        $stmt = db()->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE user_id = :user_id
               AND is_read = 0'
        );
        $stmt->execute(['user_id' => $userId]);
    } else {
        $notificationId = sanitizeInt($idRaw);
        if ($notificationId <= 0) {
            jsonResponse(false, [], 'Notificação inválida.', 422);
        }

        $stmt = db()->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE id = :id
               AND user_id = :user_id'
        );
        $stmt->execute([
            'id' => $notificationId,
            'user_id' => $userId,
        ]);
    }

    $unread = getUnreadNotificationsCount($userId);
    jsonResponse(true, ['unread_count' => $unread]);
}

jsonResponse(false, [], 'Ação inválida.', 400);
