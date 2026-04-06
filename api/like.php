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

$postId = sanitizeInt($_POST['post_id'] ?? 0);
if ($postId <= 0) {
    jsonResponse(false, [], 'Post inválido.', 422);
}

$postStmt = db()->prepare(
    'SELECT p.id, p.user_id, p.like_count
     FROM posts p
     WHERE p.id = :post_id
     LIMIT 1'
);
$postStmt->execute(['post_id' => $postId]);
$post = $postStmt->fetch();

if ($post === false) {
    jsonResponse(false, [], 'Post não encontrado.', 404);
}

$viewerId = (int) $user['id'];
$authorId = (int) $post['user_id'];

$pdo = db();
$pdo->beginTransaction();

try {
    $liked = false;

    $likeCheckStmt = $pdo->prepare(
        'SELECT id
         FROM likes
         WHERE post_id = :post_id
           AND user_id = :user_id
         LIMIT 1'
    );
    $likeCheckStmt->execute([
        'post_id' => $postId,
        'user_id' => $viewerId,
    ]);
    $existingLike = $likeCheckStmt->fetch();

    if ($existingLike) {
        $deleteStmt = $pdo->prepare(
            'DELETE FROM likes
             WHERE post_id = :post_id
               AND user_id = :user_id'
        );
        $deleteStmt->execute([
            'post_id' => $postId,
            'user_id' => $viewerId,
        ]);

        $updateCountStmt = $pdo->prepare(
            'UPDATE posts
             SET like_count = GREATEST(like_count - 1, 0)
             WHERE id = :post_id'
        );
        $updateCountStmt->execute(['post_id' => $postId]);
    } else {
        $insertStmt = $pdo->prepare(
            'INSERT INTO likes (post_id, user_id)
             VALUES (:post_id, :user_id)'
        );
        $insertStmt->execute([
            'post_id' => $postId,
            'user_id' => $viewerId,
        ]);

        $updateCountStmt = $pdo->prepare(
            'UPDATE posts
             SET like_count = like_count + 1
             WHERE id = :post_id'
        );
        $updateCountStmt->execute(['post_id' => $postId]);

        $liked = true;
    }

    $countStmt = $pdo->prepare('SELECT like_count FROM posts WHERE id = :post_id LIMIT 1');
    $countStmt->execute(['post_id' => $postId]);
    $fresh = $countStmt->fetch();
    $likeCount = (int) ($fresh['like_count'] ?? 0);

    $pdo->commit();

    if ($liked && $authorId !== $viewerId) {
        addCredits(
            $authorId,
            (int) CREDIT_RULES['like_received_bonus'],
            'like_received',
            'Bônus por like recebido',
            $postId
        );

        createNotification(
            $authorId,
            'like',
            sprintf('%s curtiu seu post.', (string) $user['username']),
            appUrl('/feed')
        );
    }

    jsonResponse(true, [
        'liked' => $liked,
        'like_count' => $likeCount,
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(false, [], $exception->getMessage(), 500);
}
