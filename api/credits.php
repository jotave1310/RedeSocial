<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$user = requireApiAuth();
$action = sanitizeText((string) ($_GET['action'] ?? 'history'), 20);
$userId = (int) $user['id'];

if ($action === 'history') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(false, [], 'Método inválido.', 405);
    }

    $pagination = getPagination(1, 20, 100);

    $stmt = db()->prepare(
        'SELECT id, amount, balance_after, type, description, reference_id, created_at
         FROM credit_transactions
         WHERE user_id = :user_id
         ORDER BY created_at DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $history = array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'amount' => (int) $row['amount'],
            'balance_after' => (int) $row['balance_after'],
            'type' => (string) $row['type'],
            'description' => (string) ($row['description'] ?? ''),
            'reference_id' => $row['reference_id'] !== null ? (int) $row['reference_id'] : null,
            'created_at' => (string) $row['created_at'],
            'time_ago' => timeAgo((string) $row['created_at']),
        ];
    }, $rows);

    jsonResponse(true, ['history' => $history]);
}

if ($action === 'balance') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(false, [], 'Método inválido.', 405);
    }

    $freshUser = getUserById($userId);
    if ($freshUser === null) {
        jsonResponse(false, [], 'Usuário não encontrado.', 404);
    }

    jsonResponse(true, ['credits' => (int) $freshUser['credits']]);
}

if ($action === 'tip') {
    requireApiPostMethod();
    validateCsrfFromRequest();

    $postId = sanitizeInt($_POST['post_id'] ?? 0);
    $amount = sanitizeInt($_POST['amount'] ?? 0);

    if ($postId <= 0) {
        jsonResponse(false, [], 'Post inválido.', 422);
    }

    if ($amount < (int) CREDIT_RULES['minimum_tip']) {
        jsonResponse(false, [], 'Tip mínimo é ₢ 5.', 422);
    }

    $postStmt = db()->prepare(
        'SELECT id, user_id, is_anonymous
         FROM posts
         WHERE id = :post_id
         LIMIT 1'
    );
    $postStmt->execute(['post_id' => $postId]);
    $post = $postStmt->fetch();

    if ($post === false) {
        jsonResponse(false, [], 'Post não encontrado.', 404);
    }

    if ((int) $post['is_anonymous'] === 1) {
        jsonResponse(false, [], 'Não é possível enviar tip para posts anônimos.', 422);
    }

    $receiverId = (int) $post['user_id'];
    if ($receiverId === $userId) {
        jsonResponse(false, [], 'Não é possível enviar tip para seu próprio post.', 422);
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $newBalance = deductCredits(
            $userId,
            $amount,
            'tip_sent',
            'Tip enviado para post #' . $postId,
            $postId
        );

        if ($newBalance === false) {
            throw new RuntimeException('Saldo insuficiente para enviar tip.');
        }

        addCredits(
            $receiverId,
            $amount,
            'tip_received',
            'Tip recebido no post #' . $postId,
            $postId
        );

        $tipStmt = $pdo->prepare(
            'INSERT INTO tips (sender_id, receiver_id, post_id, amount)
             VALUES (:sender_id, :receiver_id, :post_id, :amount)'
        );
        $tipStmt->execute([
            'sender_id' => $userId,
            'receiver_id' => $receiverId,
            'post_id' => $postId,
            'amount' => $amount,
        ]);

        $pdo->commit();
        upsertSessionUserSnapshot($userId);

        createNotification(
            $receiverId,
            'tip_received',
            sprintf('%s te enviou %s em tip.', (string) $user['username'], formatCredits($amount)),
            appUrl('/feed')
        );

        jsonResponse(true, [
            'new_balance' => (int) $newBalance,
        ]);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(false, [], $exception->getMessage(), 500);
    }
}

jsonResponse(false, [], 'Ação inválida.', 400);
