<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$user = requireApiAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = sanitizeText((string) ($_GET['action'] ?? ''), 20);

function serializeComment(array $row, bool $maskAuthor = false): array
{
    $isAnonymous = (int) ($row['is_anonymous'] ?? 0) === 1;
    $author = $maskAuthor || $isAnonymous
        ? 'Anonimo Misterioso'
        : (string) ($row['username'] ?? 'Usuário');

    return [
        'id' => (int) $row['id'],
        'post_id' => (int) $row['post_id'],
        'content' => (string) ($row['content'] ?? ''),
        'is_anonymous' => $isAnonymous,
        'author' => $author,
        'created_at' => (string) ($row['created_at'] ?? ''),
        'time_ago' => timeAgo((string) ($row['created_at'] ?? '')),
    ];
}

if ($method === 'GET') {
    $postId = sanitizeInt($_GET['post_id'] ?? 0);
    if ($postId <= 0) {
        jsonResponse(false, [], 'Post inválido.', 422);
    }

    $postStmt = db()->prepare(
        'SELECT id, is_anonymous
         FROM posts
         WHERE id = :post_id
         LIMIT 1'
    );
    $postStmt->execute(['post_id' => $postId]);
    $post = $postStmt->fetch();
    if ($post === false) {
        jsonResponse(false, [], 'Post não encontrado.', 404);
    }

    $pagination = getPagination(1, 20, 50);
    $stmt = db()->prepare(
        'SELECT c.*, u.username
         FROM comments c
         INNER JOIN users u ON u.id = c.user_id
         WHERE c.post_id = :post_id
         ORDER BY c.created_at DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':post_id', $postId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $maskAuthor = (int) $post['is_anonymous'] === 1;
    $comments = array_map(
        static fn (array $row): array => serializeComment($row, $maskAuthor),
        $rows
    );

    jsonResponse(true, ['comments' => $comments]);
}

if ($action === 'create') {
    requireApiPostMethod();
    validateCsrfFromRequest();

    $postId = sanitizeInt($_POST['post_id'] ?? 0);
    $content = sanitizeText((string) ($_POST['content'] ?? ''), COMMENT_MAX_LENGTH);
    $requestAnonymous = sanitizeInt($_POST['is_anonymous'] ?? 0) === 1;

    if ($postId <= 0) {
        jsonResponse(false, [], 'Post inválido.', 422);
    }

    if ($content === '') {
        jsonResponse(false, [], 'Comentário não pode ser vazio.', 422);
    }

    if (mb_strlen($content) > COMMENT_MAX_LENGTH) {
        jsonResponse(false, [], 'Comentário deve ter até 500 caracteres.', 422);
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

    $isAnonymousComment = $requestAnonymous || ((int) $post['is_anonymous'] === 1);
    $currentUserId = (int) $user['id'];

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $insertStmt = $pdo->prepare(
            'INSERT INTO comments (post_id, user_id, content, is_anonymous)
             VALUES (:post_id, :user_id, :content, :is_anonymous)'
        );
        $insertStmt->execute([
            'post_id' => $postId,
            'user_id' => $currentUserId,
            'content' => $content,
            'is_anonymous' => $isAnonymousComment ? 1 : 0,
        ]);

        $commentId = (int) $pdo->lastInsertId();

        $updateStmt = $pdo->prepare(
            'UPDATE posts
             SET comment_count = comment_count + 1
             WHERE id = :post_id'
        );
        $updateStmt->execute(['post_id' => $postId]);

        $pdo->commit();

        $commentStmt = db()->prepare(
            'SELECT c.*, u.username
             FROM comments c
             INNER JOIN users u ON u.id = c.user_id
             WHERE c.id = :comment_id
             LIMIT 1'
        );
        $commentStmt->execute(['comment_id' => $commentId]);
        $comment = $commentStmt->fetch();
        if ($comment === false) {
            jsonResponse(false, [], 'Comentário criado, mas falhou ao carregar.', 500);
        }

        $postAuthorId = (int) $post['user_id'];
        if ($postAuthorId !== $currentUserId) {
            createNotification(
                $postAuthorId,
                'comment',
                sprintf('%s comentou no seu post.', (string) $user['username']),
                appUrl('/feed')
            );
        }

        jsonResponse(true, [
            'comment' => serializeComment($comment, (int) $post['is_anonymous'] === 1),
        ]);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(false, [], $exception->getMessage(), 500);
    }
}

if ($action === 'delete') {
    if (!in_array($method, ['POST', 'DELETE'], true)) {
        jsonResponse(false, [], 'Método inválido.', 405);
    }

    if ($method === 'POST') {
        validateCsrfFromRequest();
    } else {
        $raw = file_get_contents('php://input');
        parse_str(is_string($raw) ? $raw : '', $parsed);
        requireValidCsrfToken((string) ($parsed['csrf_token'] ?? ''));
    }

    $commentId = sanitizeInt($_GET['id'] ?? 0);
    if ($commentId <= 0) {
        jsonResponse(false, [], 'Comentário inválido.', 422);
    }

    $stmt = db()->prepare(
        'SELECT c.id, c.user_id, c.post_id, p.user_id AS post_owner_id
         FROM comments c
         INNER JOIN posts p ON p.id = c.post_id
         WHERE c.id = :comment_id
         LIMIT 1'
    );
    $stmt->execute(['comment_id' => $commentId]);
    $comment = $stmt->fetch();
    if ($comment === false) {
        jsonResponse(false, [], 'Comentário não encontrado.', 404);
    }

    $viewerId = (int) $user['id'];
    $canDelete = ($user['role'] ?? 'user') === 'admin'
        || (int) $comment['user_id'] === $viewerId
        || (int) $comment['post_owner_id'] === $viewerId;

    if (!$canDelete) {
        jsonResponse(false, [], 'Sem permissão para excluir comentário.', 403);
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $deleteStmt = $pdo->prepare('DELETE FROM comments WHERE id = :comment_id');
        $deleteStmt->execute(['comment_id' => $commentId]);

        $updateStmt = $pdo->prepare(
            'UPDATE posts
             SET comment_count = GREATEST(comment_count - 1, 0)
             WHERE id = :post_id'
        );
        $updateStmt->execute(['post_id' => (int) $comment['post_id']]);

        $pdo->commit();

        jsonResponse(true, ['deleted' => true]);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(false, [], $exception->getMessage(), 500);
    }
}

jsonResponse(false, [], 'Ação inválida.', 400);
