<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$user = requireApiAuth();
$action = sanitizeText((string) ($_GET['action'] ?? 'conversations'), 30);
$viewerId = (int) $user['id'];

if ($action === 'conversations') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(false, [], 'Método inválido.', 405);
    }

    $pagination = getPagination(1, 30, 100);

    $partnersStmt = db()->prepare(
        'SELECT
            CASE WHEN sender_id = :viewer_id THEN receiver_id ELSE sender_id END AS partner_id,
            MAX(created_at) AS last_message_at
         FROM messages
         WHERE sender_id = :viewer_sender
            OR receiver_id = :viewer_receiver
         GROUP BY partner_id
         ORDER BY last_message_at DESC
         LIMIT :limit OFFSET :offset'
    );
    $partnersStmt->bindValue(':viewer_id', $viewerId, PDO::PARAM_INT);
    $partnersStmt->bindValue(':viewer_sender', $viewerId, PDO::PARAM_INT);
    $partnersStmt->bindValue(':viewer_receiver', $viewerId, PDO::PARAM_INT);
    $partnersStmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
    $partnersStmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $partnersStmt->execute();
    $partners = $partnersStmt->fetchAll();

    $conversations = [];
    foreach ($partners as $partnerRow) {
        $partnerId = (int) $partnerRow['partner_id'];

        $partnerUser = getUserById($partnerId);
        if ($partnerUser === null) {
            continue;
        }

        $lastMessageStmt = db()->prepare(
            'SELECT content, created_at, sender_id
             FROM messages
             WHERE (sender_id = :viewer_id AND receiver_id = :partner_id)
                OR (sender_id = :partner_id_swap AND receiver_id = :viewer_id_swap)
             ORDER BY created_at DESC
             LIMIT 1'
        );
        $lastMessageStmt->execute([
            'viewer_id' => $viewerId,
            'partner_id' => $partnerId,
            'partner_id_swap' => $partnerId,
            'viewer_id_swap' => $viewerId,
        ]);
        $lastMessage = $lastMessageStmt->fetch();

        $unreadStmt = db()->prepare(
            'SELECT COUNT(*) AS unread
             FROM messages
             WHERE sender_id = :partner_id
               AND receiver_id = :viewer_id
               AND is_read = 0'
        );
        $unreadStmt->execute([
            'partner_id' => $partnerId,
            'viewer_id' => $viewerId,
        ]);
        $unreadCount = (int) (($unreadStmt->fetch()['unread'] ?? 0));

        $conversations[] = [
            'partner' => [
                'id' => $partnerId,
                'username' => (string) $partnerUser['username'],
                'display_name' => (string) $partnerUser['display_name'],
                'avatar' => publicPath((string) ($partnerUser['avatar'] ?? ''), '/assets/img/avatars/default-avatar.svg'),
                'room' => (string) $partnerUser['room_code'],
            ],
            'last_message' => [
                'content' => (string) ($lastMessage['content'] ?? ''),
                'created_at' => (string) ($lastMessage['created_at'] ?? ''),
                'time_ago' => !empty($lastMessage['created_at']) ? timeAgo((string) $lastMessage['created_at']) : '',
                'sent_by_me' => isset($lastMessage['sender_id']) ? ((int) $lastMessage['sender_id'] === $viewerId) : false,
            ],
            'unread_count' => $unreadCount,
        ];
    }

    jsonResponse(true, ['conversations' => $conversations]);
}

if ($action === 'thread') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(false, [], 'Método inválido.', 405);
    }

    $partnerId = sanitizeInt($_GET['user_id'] ?? 0);
    if ($partnerId <= 0 || $partnerId === $viewerId) {
        jsonResponse(false, [], 'Usuário de conversa inválido.', 422);
    }

    $partnerUser = getUserById($partnerId);
    if ($partnerUser === null) {
        jsonResponse(false, [], 'Usuário não encontrado.', 404);
    }

    $pagination = getPagination(1, 50, 100);
    $messagesStmt = db()->prepare(
        'SELECT id, sender_id, receiver_id, content, is_read, created_at
         FROM messages
         WHERE (sender_id = :viewer_id AND receiver_id = :partner_id)
            OR (sender_id = :partner_id_swap AND receiver_id = :viewer_id_swap)
         ORDER BY created_at DESC
         LIMIT :limit OFFSET :offset'
    );
    $messagesStmt->bindValue(':viewer_id', $viewerId, PDO::PARAM_INT);
    $messagesStmt->bindValue(':partner_id', $partnerId, PDO::PARAM_INT);
    $messagesStmt->bindValue(':partner_id_swap', $partnerId, PDO::PARAM_INT);
    $messagesStmt->bindValue(':viewer_id_swap', $viewerId, PDO::PARAM_INT);
    $messagesStmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
    $messagesStmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $messagesStmt->execute();
    $rows = $messagesStmt->fetchAll();

    $markReadStmt = db()->prepare(
        'UPDATE messages
         SET is_read = 1
         WHERE sender_id = :partner_id
           AND receiver_id = :viewer_id
           AND is_read = 0'
    );
    $markReadStmt->execute([
        'partner_id' => $partnerId,
        'viewer_id' => $viewerId,
    ]);

    $messages = array_reverse(array_map(static function (array $row) use ($viewerId): array {
        return [
            'id' => (int) $row['id'],
            'sender_id' => (int) $row['sender_id'],
            'receiver_id' => (int) $row['receiver_id'],
            'content' => (string) $row['content'],
            'is_read' => (int) $row['is_read'] === 1,
            'created_at' => (string) $row['created_at'],
            'time_ago' => timeAgo((string) $row['created_at']),
            'mine' => (int) $row['sender_id'] === $viewerId,
        ];
    }, $rows));

    jsonResponse(true, [
        'partner' => [
            'id' => $partnerId,
            'username' => (string) $partnerUser['username'],
            'display_name' => (string) $partnerUser['display_name'],
            'avatar' => publicPath((string) ($partnerUser['avatar'] ?? ''), '/assets/img/avatars/default-avatar.svg'),
        ],
        'messages' => $messages,
    ]);
}

if ($action === 'send') {
    requireApiPostMethod();
    validateCsrfFromRequest();

    $receiverId = sanitizeInt($_POST['receiver_id'] ?? 0);
    $content = sanitizeText((string) ($_POST['content'] ?? ''), 500);

    if ($receiverId <= 0 || $receiverId === $viewerId) {
        jsonResponse(false, [], 'Destinatário inválido.', 422);
    }

    if ($content === '') {
        jsonResponse(false, [], 'Mensagem vazia.', 422);
    }

    if (mb_strlen($content) > 500) {
        jsonResponse(false, [], 'Mensagem deve ter até 500 caracteres.', 422);
    }

    $receiver = getUserById($receiverId);
    if ($receiver === null) {
        jsonResponse(false, [], 'Destinatário não encontrado.', 404);
    }

    $insertStmt = db()->prepare(
        'INSERT INTO messages (sender_id, receiver_id, content, is_read)
         VALUES (:sender_id, :receiver_id, :content, 0)'
    );
    $insertStmt->execute([
        'sender_id' => $viewerId,
        'receiver_id' => $receiverId,
        'content' => $content,
    ]);

    $messageId = (int) db()->lastInsertId();
    $messageStmt = db()->prepare(
        'SELECT id, sender_id, receiver_id, content, is_read, created_at
         FROM messages
         WHERE id = :id
         LIMIT 1'
    );
    $messageStmt->execute(['id' => $messageId]);
    $message = $messageStmt->fetch();

    if ($message === false) {
        jsonResponse(false, [], 'Mensagem enviada, mas falhou ao carregar.', 500);
    }

    jsonResponse(true, [
        'message' => [
            'id' => (int) $message['id'],
            'sender_id' => (int) $message['sender_id'],
            'receiver_id' => (int) $message['receiver_id'],
            'content' => (string) $message['content'],
            'is_read' => (int) $message['is_read'] === 1,
            'created_at' => (string) $message['created_at'],
            'time_ago' => timeAgo((string) $message['created_at']),
            'mine' => true,
        ],
    ]);
}

jsonResponse(false, [], 'Ação inválida.', 400);
