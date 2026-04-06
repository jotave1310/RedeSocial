<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$user = requireApiAuth();
$action = sanitizeText((string) ($_GET['action'] ?? 'toggle'), 20);

if ($action !== 'toggle') {
    jsonResponse(false, [], 'Ação inválida.', 400);
}

requireApiPostMethod();
validateCsrfFromRequest();

$targetId = sanitizeInt($_POST['user_id'] ?? 0);
$viewerId = (int) $user['id'];

if ($targetId <= 0) {
    jsonResponse(false, [], 'Usuário inválido.', 422);
}

if ($targetId === $viewerId) {
    jsonResponse(false, [], 'Você não pode seguir a si mesmo.', 422);
}

$target = getUserById($targetId);
if ($target === null) {
    jsonResponse(false, [], 'Usuário não encontrado.', 404);
}

$pdo = db();
$pdo->beginTransaction();

try {
    $checkStmt = $pdo->prepare(
        'SELECT id
         FROM follows
         WHERE follower_id = :follower
           AND following_id = :following
         LIMIT 1'
    );
    $checkStmt->execute([
        'follower' => $viewerId,
        'following' => $targetId,
    ]);
    $existing = $checkStmt->fetch();

    $following = false;
    if ($existing) {
        $deleteStmt = $pdo->prepare(
            'DELETE FROM follows
             WHERE follower_id = :follower
               AND following_id = :following'
        );
        $deleteStmt->execute([
            'follower' => $viewerId,
            'following' => $targetId,
        ]);
    } else {
        $insertStmt = $pdo->prepare(
            'INSERT INTO follows (follower_id, following_id)
             VALUES (:follower, :following)'
        );
        $insertStmt->execute([
            'follower' => $viewerId,
            'following' => $targetId,
        ]);
        $following = true;
    }

    $countStmt = $pdo->prepare(
        'SELECT COUNT(*) AS total
         FROM follows
         WHERE following_id = :following'
    );
    $countStmt->execute(['following' => $targetId]);
    $followerCount = (int) (($countStmt->fetch()['total'] ?? 0));

    $pdo->commit();

    if ($following) {
        createNotification(
            $targetId,
            'follow',
            sprintf('%s começou a te seguir.', (string) $user['username']),
            appUrl('/profile/' . urlencode((string) $user['username']))
        );
    }

    jsonResponse(true, [
        'following' => $following,
        'follower_count' => $followerCount,
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(false, [], $exception->getMessage(), 500);
}
