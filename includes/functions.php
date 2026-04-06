<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';

function ensureSessionStarted(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);

    session_start();
}

function isJsonRequest(): bool
{
    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    $requestedWith = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');

    return str_contains($accept, 'application/json')
        || $requestedWith === 'xmlhttprequest'
        || str_contains($contentType, 'application/json');
}

function sanitizeText(string $value, int $maxLength = 0): string
{
    $clean = trim(strip_tags($value));
    $clean = htmlspecialchars($clean, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    if ($maxLength > 0 && mb_strlen($clean) > $maxLength) {
        $clean = mb_substr($clean, 0, $maxLength);
    }

    return $clean;
}

function sanitizeInt(mixed $value, int $default = 0): int
{
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    return $filtered === false ? $default : (int) $filtered;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function appBasePath(): string
{
    static $basePath = null;

    if (is_string($basePath)) {
        return $basePath;
    }

    $envBase = getenv('CARVASILVA_BASE_PATH');
    if (is_string($envBase) && trim($envBase) !== '') {
        $envBase = trim($envBase);
        $envBase = '/' . trim($envBase, '/');
        $basePath = $envBase === '/' ? '' : $envBase;
        return $basePath;
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($scriptName === '') {
        $basePath = '';
        return $basePath;
    }

    if (str_ends_with($scriptName, '/index.php')) {
        $candidate = substr($scriptName, 0, -strlen('/index.php'));
        $basePath = $candidate === '' ? '' : $candidate;
        return $basePath;
    }

    if (preg_match('#^(.*?)/(auth|api|pages|includes|assets|uploads|sql)(/|$)#', $scriptName, $matches)) {
        $candidate = $matches[1] ?? '';
        $basePath = ($candidate === '' || $candidate === '/') ? '' : $candidate;
        return $basePath;
    }

    $dir = str_replace('\\', '/', dirname($scriptName));
    if ($dir === '/' || $dir === '.' || $dir === '\\') {
        $basePath = '';
        return $basePath;
    }

    $basePath = rtrim($dir, '/');
    return $basePath;
}

function appUrl(string $path = '/'): string
{
    if ($path === '') {
        $path = '/';
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    $base = appBasePath();
    if ($base === '') {
        return $path;
    }

    if ($path === '/') {
        return $base . '/';
    }

    return $base . $path;
}

function publicPath(?string $path, string $fallback = ''): string
{
    $value = trim((string) $path);
    if ($value === '') {
        $value = trim($fallback);
    }

    if ($value === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }

    if (str_starts_with($value, '/')) {
        return appUrl($value);
    }

    return $value;
}

function jsonResponse(bool $success, array $data = [], ?string $error = null, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    $payload = [
        'success' => $success,
        'data' => $data,
        'error' => $error,
    ];

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function redirect(string $path): never
{
    header('Location: ' . appUrl($path));
    exit;
}

function generateCsrfToken(): string
{
    ensureSessionStarted();

    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validateCsrfToken(?string $token): bool
{
    ensureSessionStarted();

    if (!is_string($token) || $token === '') {
        return false;
    }

    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireValidCsrfToken(?string $token): void
{
    if (validateCsrfToken($token)) {
        return;
    }

    if (isJsonRequest()) {
        jsonResponse(false, [], 'Token CSRF inválido.', 403);
    }

    redirect('/login?error=csrf');
}

function formatCredits(int $amount): string
{
    return CURRENCY_SYMBOL . ' ' . number_format($amount, 0, ',', '.');
}

function timeAgo(string $datetime): string
{
    $timestamp = strtotime($datetime);

    if ($timestamp === false) {
        return '';
    }

    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'agora';
    }

    if ($diff < 3600) {
        return floor($diff / 60) . 'min atrás';
    }

    if ($diff < 86400) {
        return floor($diff / 3600) . 'h atrás';
    }

    if ($diff < 604800) {
        return floor($diff / 86400) . 'd atrás';
    }

    return date('d/m/Y', $timestamp);
}

function validateUsername(string $username): bool
{
    return (bool) preg_match(USERNAME_REGEX, $username);
}

function getRoomCodeFromUsername(string $username): ?string
{
    if (!str_contains($username, '_')) {
        return null;
    }

    $parts = explode('_', $username);
    $roomCode = end($parts);

    if (!is_string($roomCode) || $roomCode === '') {
        return null;
    }

    return strtoupper($roomCode);
}

function getRooms(): array
{
    $stmt = db()->query('SELECT id, code, name FROM rooms ORDER BY code ASC');
    return $stmt->fetchAll();
}

function getRoomById(int $roomId): ?array
{
    $stmt = db()->prepare('SELECT id, code, name FROM rooms WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $roomId]);
    $room = $stmt->fetch();

    return $room ?: null;
}

function getUserById(int $userId): ?array
{
    $stmt = db()->prepare(
        'SELECT u.*, r.code AS room_code, r.name AS room_name
         FROM users u
         INNER JOIN rooms r ON r.id = u.room_id
         WHERE u.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function getUserByUsername(string $username): ?array
{
    $stmt = db()->prepare(
        'SELECT u.*, r.code AS room_code, r.name AS room_name
         FROM users u
         INNER JOIN rooms r ON r.id = u.room_id
         WHERE u.username = :username
         LIMIT 1'
    );
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function createNotification(int $userId, string $type, string $content, ?string $link = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO notifications (user_id, type, content, link)
         VALUES (:user_id, :type, :content, :link)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'type' => $type,
        'content' => sanitizeText($content, 255),
        'link' => $link,
    ]);
}

function createMilestonePost(int $userId, int $milestone): void
{
    $user = getUserById($userId);
    if ($user === null) {
        return;
    }

    $content = sprintf(
        'Marco de creditos: %s atingiu %s %s. #Carvasilva #Meta',
        $user['username'],
        CURRENCY_SYMBOL,
        number_format($milestone, 0, ',', '.')
    );

    $checkStmt = db()->prepare(
        'SELECT id
         FROM posts
         WHERE user_id = :user_id
           AND type = :type
           AND content = :content
         LIMIT 1'
    );
    $checkStmt->execute([
        'user_id' => $userId,
        'type' => 'milestone',
        'content' => $content,
    ]);

    if ($checkStmt->fetch()) {
        return;
    }

    $insertStmt = db()->prepare(
        'INSERT INTO posts (user_id, content, type, is_anonymous)
         VALUES (:user_id, :content, :type, 0)'
    );
    $insertStmt->execute([
        'user_id' => $userId,
        'content' => $content,
        'type' => 'milestone',
    ]);
}

function checkMilestones(int $userId, int $oldBalance, int $newBalance): void
{
    foreach (MILESTONES as $milestone) {
        if ($oldBalance < $milestone && $newBalance >= $milestone) {
            createMilestonePost($userId, $milestone);
            createNotification(
                $userId,
                'milestone',
                sprintf('Meta atingida: %s %s.', CURRENCY_SYMBOL, number_format($milestone, 0, ',', '.'))
            );
        }
    }
}

function hasBadge(int $userId, string $badgeKey): bool
{
    $stmt = db()->prepare(
        'SELECT id FROM badges WHERE user_id = :user_id AND badge_key = :badge_key LIMIT 1'
    );
    $stmt->execute([
        'user_id' => $userId,
        'badge_key' => $badgeKey,
    ]);

    return (bool) $stmt->fetch();
}

function awardBadge(int $userId, string $badgeKey): void
{
    $stmt = db()->prepare(
        'INSERT IGNORE INTO badges (user_id, badge_key) VALUES (:user_id, :badge_key)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'badge_key' => $badgeKey,
    ]);
}

function getRankPosition(int $userId): ?int
{
    $stmt = db()->prepare(
        'SELECT credits FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if ($user === false) {
        return null;
    }

    $rankStmt = db()->prepare(
        'SELECT COUNT(*) + 1 AS position
         FROM users
         WHERE credits > :credits'
    );
    $rankStmt->execute(['credits' => (int) $user['credits']]);
    $rank = $rankStmt->fetch();

    return $rank ? (int) $rank['position'] : null;
}

function calculateConsecutivePostingDays(int $userId): int
{
    $stmt = db()->prepare(
        'SELECT DISTINCT DATE(created_at) AS post_day
         FROM posts
         WHERE user_id = :user_id
         ORDER BY post_day DESC
         LIMIT 30'
    );
    $stmt->execute(['user_id' => $userId]);
    $rows = $stmt->fetchAll();

    if ($rows === []) {
        return 0;
    }

    $days = array_map(
        static fn (array $row): string => (string) $row['post_day'],
        $rows
    );

    $timezone = new DateTimeZone(APP_TIMEZONE);
    $today = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
    $streak = 0;
    $cursor = new DateTimeImmutable($today, $timezone);

    foreach ($days as $day) {
        if ($day === $cursor->format('Y-m-d')) {
            $streak++;
            $cursor = $cursor->modify('-1 day');
            continue;
        }

        break;
    }

    return $streak;
}

function checkAndAwardBadges(int $userId): void
{
    try {
        $user = getUserById($userId);

        if ($user === null) {
            return;
        }

        $statsStmt = db()->prepare(
            'SELECT
                (SELECT COUNT(*) FROM bet_entries WHERE user_id = :bet_user_id AND status = "won") AS bet_wins,
                (SELECT COALESCE(SUM(like_count), 0) FROM posts WHERE user_id = :likes_user_id) AS likes_received,
                (SELECT COUNT(*) FROM posts WHERE user_id = :anon_user_id AND is_anonymous = 1) AS anon_posts'
        );
        $statsStmt->execute([
            'bet_user_id' => $userId,
            'likes_user_id' => $userId,
            'anon_user_id' => $userId,
        ]);
        $stats = $statsStmt->fetch();

        $position = getRankPosition($userId);
        $consecutiveDays = calculateConsecutivePostingDays($userId);

        $rules = [
            'estrela_escola' => $position === 1,
            'diamante' => (int) $user['credits'] >= 100000,
            'apostador' => (int) ($stats['bet_wins'] ?? 0) >= 10,
            'influencer' => (int) ($stats['likes_received'] ?? 0) >= 500,
            'em_chamas' => $consecutiveDays >= 7,
            'anonimo_misterioso' => (int) ($stats['anon_posts'] ?? 0) >= 20,
        ];

        foreach ($rules as $key => $passed) {
            if ($passed && !hasBadge($userId, $key)) {
                awardBadge($userId, $key);
            }
        }
    } catch (Throwable) {
        // Badge checks must never break the main business flow.
    }
}

function addCredits(
    int $userId,
    int $amount,
    string $type,
    string $description = '',
    ?int $referenceId = null
): int {
    if ($amount <= 0) {
        throw new InvalidArgumentException('O valor para adicionar créditos precisa ser positivo.');
    }

    $pdo = db();
    $startedTransaction = false;
    $oldBalance = 0;
    $newBalance = 0;

    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedTransaction = true;
    }

    try {
        $lockStmt = $pdo->prepare(
            'SELECT credits FROM users WHERE id = :user_id FOR UPDATE'
        );
        $lockStmt->execute(['user_id' => $userId]);
        $user = $lockStmt->fetch();

        if ($user === false) {
            throw new RuntimeException('Usuário não encontrado para operação de crédito.');
        }

        $oldBalance = (int) $user['credits'];
        $newBalance = $oldBalance + $amount;

        $updateStmt = $pdo->prepare(
            'UPDATE users SET credits = :credits WHERE id = :user_id'
        );
        $updateStmt->execute([
            'credits' => $newBalance,
            'user_id' => $userId,
        ]);

        $transactionStmt = $pdo->prepare(
            'INSERT INTO credit_transactions (user_id, amount, balance_after, type, description, reference_id)
             VALUES (:user_id, :amount, :balance_after, :type, :description, :reference_id)'
        );
        $transactionStmt->execute([
            'user_id' => $userId,
            'amount' => $amount,
            'balance_after' => $newBalance,
            'type' => $type,
            'description' => sanitizeText($description, 255),
            'reference_id' => $referenceId,
        ]);

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $throwable) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $throwable;
    }

    checkMilestones($userId, $oldBalance, $newBalance);
    checkAndAwardBadges($userId);

    return $newBalance;
}

function deductCredits(
    int $userId,
    int $amount,
    string $type,
    string $description = '',
    ?int $referenceId = null
): int|false {
    if ($amount <= 0) {
        throw new InvalidArgumentException('O valor para deduzir créditos precisa ser positivo.');
    }

    $pdo = db();
    $startedTransaction = false;
    $newBalance = 0;

    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedTransaction = true;
    }

    try {
        $lockStmt = $pdo->prepare(
            'SELECT credits FROM users WHERE id = :user_id FOR UPDATE'
        );
        $lockStmt->execute(['user_id' => $userId]);
        $user = $lockStmt->fetch();

        if ($user === false) {
            throw new RuntimeException('Usuário não encontrado para operação de débito.');
        }

        $currentBalance = (int) $user['credits'];
        if ($currentBalance < $amount) {
            if ($startedTransaction) {
                $pdo->rollBack();
            }
            return false;
        }

        $newBalance = $currentBalance - $amount;

        $updateStmt = $pdo->prepare(
            'UPDATE users SET credits = :credits WHERE id = :user_id'
        );
        $updateStmt->execute([
            'credits' => $newBalance,
            'user_id' => $userId,
        ]);

        $transactionStmt = $pdo->prepare(
            'INSERT INTO credit_transactions (user_id, amount, balance_after, type, description, reference_id)
             VALUES (:user_id, :amount, :balance_after, :type, :description, :reference_id)'
        );
        $transactionStmt->execute([
            'user_id' => $userId,
            'amount' => -$amount,
            'balance_after' => $newBalance,
            'type' => $type,
            'description' => sanitizeText($description, 255),
            'reference_id' => $referenceId,
        ]);

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $throwable) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $throwable;
    }

    return $newBalance;
}

function updateLastLogin(int $userId): void
{
    $stmt = db()->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
    $stmt->execute(['id' => $userId]);
}

function handleDailyLoginBonus(int $userId): int
{
    $stmt = db()->prepare('SELECT last_login FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();

    if ($row === false) {
        throw new RuntimeException('Usuário não encontrado para bônus diário.');
    }

    $timezone = new DateTimeZone(APP_TIMEZONE);
    $today = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');

    $lastLoginDate = null;
    if (!empty($row['last_login'])) {
        $lastLoginDate = (new DateTimeImmutable((string) $row['last_login']))->setTimezone($timezone)->format('Y-m-d');
    }

    $bonus = 0;
    if ($lastLoginDate === null || $lastLoginDate < $today) {
        $bonus = (int) CREDIT_RULES['daily_login_bonus'];
        addCredits($userId, $bonus, 'daily_login', 'Bônus de login diário');
    }

    updateLastLogin($userId);

    return $bonus;
}

function uploadAvatarFile(array $file): ?string
{
    if (!isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no upload do avatar.');
    }

    if (!isset($file['size']) || (int) $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Avatar deve ter no máximo 5MB.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Arquivo de avatar inválido.');
    }

    $mime = mime_content_type($tmpName);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    if (!is_string($mime) || !isset($allowed[$mime])) {
        throw new RuntimeException('Formato de avatar inválido. Use JPG, PNG, GIF ou WEBP.');
    }

    $uploadDir = __DIR__ . '/../uploads/avatars';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Não foi possível criar a pasta de avatares.');
    }

    $filename = sprintf('%s_%d.%s', bin2hex(random_bytes(6)), time(), $allowed[$mime]);
    $targetPath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        throw new RuntimeException('Não foi possível salvar o avatar.');
    }

    return '/uploads/avatars/' . $filename;
}

function loginUser(array $user, bool $rememberMe = false): void
{
    ensureSessionStarted();
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = (string) $user['username'];
    $_SESSION['role'] = (string) $user['role'];
    $_SESSION['logged_in_at'] = time();

    if ($rememberMe) {
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires' => time() + (30 * 24 * 3600),
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
}

function logoutUser(): void
{
    ensureSessionStarted();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function isAuthenticated(): bool
{
    ensureSessionStarted();
    return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

function currentUserId(): ?int
{
    if (!isAuthenticated()) {
        return null;
    }

    return (int) $_SESSION['user_id'];
}

function currentUser(): ?array
{
    $userId = currentUserId();
    return $userId === null ? null : getUserById($userId);
}

function isAdmin(): bool
{
    ensureSessionStarted();
    return ($_SESSION['role'] ?? '') === 'admin';
}

function requireApiAuth(): array
{
    header('Content-Type: application/json; charset=utf-8');
    ensureSessionStarted();

    if (!isAuthenticated()) {
        jsonResponse(false, [], 'Não autenticado.', 401);
    }

    $user = currentUser();
    if ($user === null) {
        jsonResponse(false, [], 'Sessão inválida.', 401);
    }

    if ((int) ($user['is_banned'] ?? 0) === 1) {
        jsonResponse(false, [], 'Conta bloqueada.', 403);
    }

    return $user;
}

function requireApiAdmin(array $user): void
{
    if (($user['role'] ?? 'user') !== 'admin') {
        jsonResponse(false, [], 'Acesso restrito para administradores.', 403);
    }
}

function requireApiPostMethod(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, [], 'Método inválido.', 405);
    }
}

function jsonInput(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function requestData(): array
{
    $data = $_POST;
    $json = jsonInput();

    if ($json !== []) {
        foreach ($json as $key => $value) {
            $data[$key] = $value;
        }
    }

    return $data;
}

function getRequestInt(string $key, int $default = 0): int
{
    $data = requestData();
    return sanitizeInt($data[$key] ?? ($_GET[$key] ?? null), $default);
}

function getRequestString(string $key, int $maxLength = 0): string
{
    $data = requestData();
    $value = $data[$key] ?? ($_GET[$key] ?? '');
    return sanitizeText((string) $value, $maxLength);
}

function validateCsrfFromRequest(): void
{
    $token = $_POST['csrf_token'] ?? null;

    if ($token === null) {
        $data = jsonInput();
        $token = $data['csrf_token'] ?? null;
    }

    requireValidCsrfToken(is_string($token) ? $token : null);
}

function getPagination(int $defaultPage = 1, int $defaultLimit = 20, int $maxLimit = 100): array
{
    $page = sanitizeInt($_GET['page'] ?? $defaultPage, $defaultPage);
    if ($page < 1) {
        $page = 1;
    }

    $limit = sanitizeInt($_GET['limit'] ?? $defaultLimit, $defaultLimit);
    if ($limit < 1) {
        $limit = $defaultLimit;
    }
    if ($limit > $maxLimit) {
        $limit = $maxLimit;
    }

    $offset = ($page - 1) * $limit;

    return [
        'page' => $page,
        'limit' => $limit,
        'offset' => $offset,
    ];
}

function canUserPostNow(int $userId): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) AS total
         FROM posts
         WHERE user_id = :user_id
           AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
    );
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch();

    return ((int) ($row['total'] ?? 0)) < (int) RATE_LIMITS['posts_per_hour'];
}

function getTodayPostBonusCount(int $userId): int
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) AS total
         FROM posts
         WHERE user_id = :user_id
           AND DATE(CONVERT_TZ(created_at, "+00:00", "-03:00")) = DATE(CONVERT_TZ(NOW(), "+00:00", "-03:00"))
           AND type IN ("standard", "image")'
    );
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch();

    return (int) ($row['total'] ?? 0);
}

function canReceivePostBonus(int $userId): bool
{
    return getTodayPostBonusCount($userId) < (int) CREDIT_RULES['post_bonus_daily_limit'];
}

function canPostAnonymousNow(int $userId): bool
{
    $stmt = db()->prepare(
        'SELECT last_post
         FROM anonymous_rate_limit
         WHERE user_id = :user_id
         LIMIT 1'
    );
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch();

    if ($row === false || empty($row['last_post'])) {
        return true;
    }

    $last = strtotime((string) $row['last_post']);
    if ($last === false) {
        return true;
    }

    return $last <= (time() - (int) RATE_LIMITS['anonymous_post_interval_seconds']);
}

function touchAnonymousPostRateLimit(int $userId): void
{
    $stmt = db()->prepare(
        'INSERT INTO anonymous_rate_limit (user_id, last_post)
         VALUES (:user_id, NOW())
         ON DUPLICATE KEY UPDATE last_post = NOW()'
    );
    $stmt->execute(['user_id' => $userId]);
}

function uploadPostImage(array $file, int $userId): ?string
{
    if (!isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no upload da imagem do post.');
    }

    if (!isset($file['size']) || (int) $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('A imagem deve ter no máximo 5MB.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Arquivo de imagem inválido.');
    }

    $mime = mime_content_type($tmpName);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    if (!is_string($mime) || !isset($allowed[$mime])) {
        throw new RuntimeException('Formato de imagem inválido. Use JPG, PNG, GIF ou WEBP.');
    }

    $uploadDir = __DIR__ . '/../uploads/posts';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Não foi possível criar a pasta de uploads de posts.');
    }

    $filename = sprintf('%d_%d_%s.%s', $userId, time(), bin2hex(random_bytes(4)), $allowed[$mime]);
    $targetPath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        throw new RuntimeException('Não foi possível salvar a imagem do post.');
    }

    return '/uploads/posts/' . $filename;
}

function getUnreadNotificationsCount(int $userId): int
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) AS unread
         FROM notifications
         WHERE user_id = :user_id
           AND is_read = 0'
    );
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch();

    return (int) ($row['unread'] ?? 0);
}

function upsertSessionUserSnapshot(int $userId): void
{
    $user = getUserById($userId);
    if ($user === null) {
        return;
    }

    ensureSessionStarted();
    $_SESSION['username'] = (string) $user['username'];
    $_SESSION['role'] = (string) $user['role'];
}
