<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$user = requireApiAuth();
$action = sanitizeText((string) ($_GET['action'] ?? 'feed'), 40);

function postDeletePayload(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    parse_str($raw, $parsed);
    return is_array($parsed) ? $parsed : [];
}

function serializePost(array $row, int $viewerId, bool $isAdmin, bool $forceAnonymousMask = false): array
{
    $isAnonymous = (int) ($row['is_anonymous'] ?? 0) === 1;
    $maskIdentity = $forceAnonymousMask && $isAnonymous;
    $authorRoom = (string) ($row['room_code'] ?? '');

    $user = null;
    if (!$maskIdentity || $isAdmin) {
        $user = [
            'id' => (int) ($row['user_id'] ?? 0),
            'username' => $maskIdentity ? 'Anonimo Misterioso' : (string) ($row['username'] ?? ''),
            'display_name' => $maskIdentity ? 'Anonimo Misterioso' : (string) ($row['display_name'] ?? ''),
            'avatar' => $maskIdentity
                ? publicPath('/assets/img/avatars/default-avatar.svg')
                : publicPath((string) ($row['avatar'] ?? ''), '/assets/img/avatars/default-avatar.svg'),
            'credits' => (int) ($row['credits'] ?? 0),
            'room' => $authorRoom,
        ];
    }

    $payload = [
        'id' => (int) $row['id'],
        'user' => $user,
        'content' => (string) ($row['content'] ?? ''),
        'type' => (string) ($row['type'] ?? 'standard'),
        'image_path' => $row['image_path'] ? publicPath((string) $row['image_path']) : null,
        'is_anonymous' => $isAnonymous,
        'like_count' => (int) ($row['like_count'] ?? 0),
        'comment_count' => (int) ($row['comment_count'] ?? 0),
        'repost_count' => (int) ($row['repost_count'] ?? 0),
        'user_liked' => (int) ($row['viewer_liked'] ?? 0) === 1,
        'created_at' => (string) ($row['created_at'] ?? ''),
        'time_ago' => timeAgo((string) ($row['created_at'] ?? '')),
        'can_delete' => $isAdmin || ((int) ($row['user_id'] ?? 0) === $viewerId),
    ];

    if ($payload['type'] === 'credit_flex') {
        if (preg_match('/Saldo atual:\s*' . preg_quote(CURRENCY_SYMBOL, '/') . '\s*([0-9\.\,]+)/u', $payload['content'], $matches)) {
            $amount = (string) $matches[1];
            $amount = str_replace('.', '', $amount);
            $amount = str_replace(',', '.', $amount);
            $payload['flex_balance'] = (int) floor((float) $amount);
        } else {
            $payload['flex_balance'] = (int) ($row['credits'] ?? 0);
        }
    }

    return $payload;
}

function fetchPostById(int $postId, int $viewerId): ?array
{
    $stmt = db()->prepare(
        'SELECT
            p.*,
            u.username,
            u.display_name,
            u.avatar,
            u.credits,
            r.code AS room_code,
            EXISTS(
                SELECT 1
                FROM likes l
                WHERE l.post_id = p.id
                  AND l.user_id = :viewer_id
            ) AS viewer_liked
         FROM posts p
         INNER JOIN users u ON u.id = p.user_id
         INNER JOIN rooms r ON r.id = u.room_id
         WHERE p.id = :post_id
         LIMIT 1'
    );
    $stmt->execute([
        'post_id' => $postId,
        'viewer_id' => $viewerId,
    ]);

    $row = $stmt->fetch();
    return $row ?: null;
}

try {
    if ($action === 'feed') {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            jsonResponse(false, [], 'Método inválido.', 405);
        }

        $tab = sanitizeText((string) ($_GET['tab'] ?? 'home'), 20);
        $pagination = getPagination(1, 20, 50);

        $where = [];
        $params = [
            'viewer_id' => (int) $user['id'],
        ];
        $orderBy = 'p.created_at DESC';

        switch ($tab) {
            case 'trending':
                $where[] = 'p.is_anonymous = 0';
                $where[] = 'p.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)';
                $orderBy = '(p.like_count + (p.comment_count * 2) + (p.repost_count * 3)) DESC, p.created_at DESC';
                break;

            case 'following':
                $where[] = 'p.is_anonymous = 0';
                $where[] = 'p.user_id IN (
                    SELECT f.following_id
                    FROM follows f
                    WHERE f.follower_id = :viewer_following
                )';
                $params['viewer_following'] = (int) $user['id'];
                break;

            case 'class':
                $where[] = 'p.is_anonymous = 0';
                $where[] = 'u.room_id = :viewer_room';
                $params['viewer_room'] = (int) $user['room_id'];
                break;

            case 'anonymous':
                $where[] = 'p.is_anonymous = 1';
                break;

            case 'home':
            default:
                $where[] = 'p.is_anonymous = 0';
                break;
        }

        $whereSql = $where === [] ? '' : ('WHERE ' . implode(' AND ', $where));

        $stmt = db()->prepare(
            'SELECT
                p.*,
                u.username,
                u.display_name,
                u.avatar,
                u.credits,
                r.code AS room_code,
                EXISTS(
                    SELECT 1
                    FROM likes l
                    WHERE l.post_id = p.id
                      AND l.user_id = :viewer_id
                ) AS viewer_liked
             FROM posts p
             INNER JOIN users u ON u.id = p.user_id
             INNER JOIN rooms r ON r.id = u.room_id
             ' . $whereSql . '
             ORDER BY ' . $orderBy . '
             LIMIT :limit OFFSET :offset'
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $pagination['limit'] + 1, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $hasMore = count($rows) > $pagination['limit'];
        if ($hasMore) {
            array_pop($rows);
        }

        $forceMask = $tab === 'anonymous';
        $posts = array_map(
            static fn (array $row): array => serializePost($row, (int) $user['id'], ($user['role'] ?? 'user') === 'admin', $forceMask),
            $rows
        );

        jsonResponse(true, [
            'posts' => $posts,
            'has_more' => $hasMore,
            'page' => $pagination['page'],
            'tab' => $tab,
        ]);
    }

    if ($action === 'create') {
        requireApiPostMethod();
        validateCsrfFromRequest();

        $currentUserId = (int) $user['id'];

        if (!canUserPostNow($currentUserId)) {
            header('Retry-After: 3600');
            jsonResponse(false, [], 'Limite de posts por hora atingido.', 429);
        }

        $content = sanitizeText((string) ($_POST['content'] ?? ''), POST_MAX_LENGTH);
        $providedType = sanitizeText((string) ($_POST['type'] ?? 'standard'), 20);
        $imagePath = uploadPostImage($_FILES['image'] ?? [], $currentUserId);

        if ($content === '' && $imagePath === null) {
            jsonResponse(false, [], 'Escreva algo ou envie uma imagem.', 422);
        }

        if (mb_strlen($content) > POST_MAX_LENGTH) {
            jsonResponse(false, [], 'Post deve ter no máximo 280 caracteres.', 422);
        }

        $type = $imagePath !== null ? 'image' : 'standard';
        if ($providedType === 'standard') {
            $type = 'standard';
        }

        $insertStmt = db()->prepare(
            'INSERT INTO posts (user_id, content, type, image_path, is_anonymous)
             VALUES (:user_id, :content, :type, :image_path, 0)'
        );
        $insertStmt->execute([
            'user_id' => $currentUserId,
            'content' => $content,
            'type' => $type,
            'image_path' => $imagePath,
        ]);

        $postId = (int) db()->lastInsertId();
        $creditsEarned = 0;

        if (in_array($type, ['standard', 'image'], true) && canReceivePostBonus($currentUserId)) {
            addCredits(
                $currentUserId,
                (int) CREDIT_RULES['post_bonus'],
                'post_bonus',
                'Bônus por postagem',
                $postId
            );
            $creditsEarned = (int) CREDIT_RULES['post_bonus'];
        }

        $freshPost = fetchPostById($postId, $currentUserId);
        if ($freshPost === null) {
            jsonResponse(false, [], 'Não foi possível carregar o post criado.', 500);
        }

        upsertSessionUserSnapshot($currentUserId);

        jsonResponse(true, [
            'post' => serializePost($freshPost, $currentUserId, ($user['role'] ?? 'user') === 'admin'),
            'credits_earned' => $creditsEarned,
        ]);
    }

    if ($action === 'create_anonymous') {
        requireApiPostMethod();
        validateCsrfFromRequest();

        $currentUserId = (int) $user['id'];

        if (!canUserPostNow($currentUserId)) {
            header('Retry-After: 3600');
            jsonResponse(false, [], 'Limite de posts por hora atingido.', 429);
        }

        if (!canPostAnonymousNow($currentUserId)) {
            header('Retry-After: ' . RATE_LIMITS['anonymous_post_interval_seconds']);
            jsonResponse(false, [], 'Você só pode postar no anônimo 1x por hora.', 429);
        }

        $content = sanitizeText((string) ($_POST['content'] ?? ''), POST_MAX_LENGTH);
        if ($content === '') {
            jsonResponse(false, [], 'Escreva algo para publicar no anônimo.', 422);
        }

        $insertStmt = db()->prepare(
            'INSERT INTO posts (user_id, content, type, image_path, is_anonymous)
             VALUES (:user_id, :content, "anonymous", NULL, 1)'
        );
        $insertStmt->execute([
            'user_id' => $currentUserId,
            'content' => $content,
        ]);

        touchAnonymousPostRateLimit($currentUserId);

        $postId = (int) db()->lastInsertId();
        $freshPost = fetchPostById($postId, $currentUserId);
        if ($freshPost === null) {
            jsonResponse(false, [], 'Não foi possível carregar o post criado.', 500);
        }

        jsonResponse(true, [
            'post' => serializePost($freshPost, $currentUserId, ($user['role'] ?? 'user') === 'admin', true),
        ]);
    }

    if ($action === 'credit_flex') {
        requireApiPostMethod();
        validateCsrfFromRequest();

        $currentUserId = (int) $user['id'];
        if (!canUserPostNow($currentUserId)) {
            header('Retry-After: 3600');
            jsonResponse(false, [], 'Limite de posts por hora atingido.', 429);
        }

        $freshUser = getUserById($currentUserId);
        if ($freshUser === null) {
            jsonResponse(false, [], 'Usuário não encontrado.', 404);
        }

        $content = sprintf(
            '%s esta mostrando os creditos na CARVASILVA.' . PHP_EOL .
            'Saldo atual: %s %s' . PHP_EOL .
            '#CarvasilvaFlex',
            $freshUser['username'],
            CURRENCY_SYMBOL,
            number_format((int) $freshUser['credits'], 0, ',', '.')
        );

        $insertStmt = db()->prepare(
            'INSERT INTO posts (user_id, content, type, image_path, is_anonymous)
             VALUES (:user_id, :content, "credit_flex", NULL, 0)'
        );
        $insertStmt->execute([
            'user_id' => $currentUserId,
            'content' => $content,
        ]);

        $postId = (int) db()->lastInsertId();
        $freshPost = fetchPostById($postId, $currentUserId);
        if ($freshPost === null) {
            jsonResponse(false, [], 'Não foi possível carregar o post criado.', 500);
        }

        jsonResponse(true, [
            'post' => serializePost($freshPost, $currentUserId, ($user['role'] ?? 'user') === 'admin'),
        ]);
    }

    if ($action === 'delete') {
        if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'POST'], true)) {
            jsonResponse(false, [], 'Método inválido.', 405);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $deleteData = postDeletePayload();
            requireValidCsrfToken((string) ($deleteData['csrf_token'] ?? ''));
        } else {
            validateCsrfFromRequest();
        }

        $postId = sanitizeInt($_GET['id'] ?? 0);
        if ($postId <= 0) {
            jsonResponse(false, [], 'Post inválido.', 422);
        }

        $post = fetchPostById($postId, (int) $user['id']);
        if ($post === null) {
            jsonResponse(false, [], 'Post não encontrado.', 404);
        }

        $isOwner = (int) $post['user_id'] === (int) $user['id'];
        $isAdmin = ($user['role'] ?? 'user') === 'admin';
        if (!$isOwner && !$isAdmin) {
            jsonResponse(false, [], 'Sem permissão para excluir.', 403);
        }

        $deleteStmt = db()->prepare('DELETE FROM posts WHERE id = :id');
        $deleteStmt->execute(['id' => $postId]);

        jsonResponse(true, ['deleted' => true]);
    }

    jsonResponse(false, [], 'Ação inválida.', 400);
} catch (Throwable $exception) {
    jsonResponse(false, [], $exception->getMessage(), 500);
}

