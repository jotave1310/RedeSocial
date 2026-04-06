<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

ensureSessionStarted();

$path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$basePath = appBasePath();
if ($basePath !== '' && strncasecmp($path, $basePath, strlen($basePath)) === 0) {
    $path = substr($path, strlen($basePath));
    if ($path === '') {
        $path = '/';
    }
}
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

if (strcasecmp($path, '/index.php') === 0) {
    $path = '/';
}

$render = static function (string $file): never {
    require $file;
    exit;
};

if ($path === '/') {
    if (isAuthenticated()) {
        redirect('/feed');
    }
    redirect('/login');
}

if ($path === '/login') {
    $render(__DIR__ . '/auth/login.php');
}

if ($path === '/register') {
    $render(__DIR__ . '/auth/register.php');
}

if ($path === '/logout') {
    $render(__DIR__ . '/auth/logout.php');
}

if ($path === '/feed' || $path === '/feed/trending' || $path === '/feed/class') {
    if ($path === '/feed/trending') {
        $_GET['tab'] = 'trending';
    } elseif ($path === '/feed/class') {
        $_GET['tab'] = 'class';
    }
    $render(__DIR__ . '/pages/feed.php');
}

if ($path === '/anonymous') {
    $render(__DIR__ . '/pages/anonymous.php');
}

if ($path === '/bet') {
    $render(__DIR__ . '/pages/bet.php');
}

if ($path === '/bet/create') {
    $render(__DIR__ . '/pages/bet_create.php');
}

if (preg_match('#^/bet/(\d+)$#', $path, $matches)) {
    $_GET['id'] = $matches[1];
    $render(__DIR__ . '/pages/bet_single.php');
}

if ($path === '/ranking') {
    $render(__DIR__ . '/pages/ranking.php');
}

if (preg_match('#^/profile/([^/]+)$#', $path, $matches)) {
    $_GET['u'] = urldecode($matches[1]);
    $render(__DIR__ . '/pages/profile.php');
}

if ($path === '/notifications') {
    $render(__DIR__ . '/pages/notifications.php');
}

if ($path === '/messages') {
    $render(__DIR__ . '/pages/messages.php');
}

if (preg_match('#^/messages/([^/]+)$#', $path, $matches)) {
    $username = urldecode($matches[1]);
    $target = getUserByUsername($username);
    if ($target !== null) {
        $_GET['user_id'] = (string) $target['id'];
    }
    $render(__DIR__ . '/pages/messages_thread.php');
}

if ($path === '/admin') {
    $render(__DIR__ . '/pages/admin/index.php');
}

if ($path === '/admin/users') {
    $render(__DIR__ . '/pages/admin/users.php');
}

if ($path === '/admin/bets') {
    $render(__DIR__ . '/pages/admin/bets.php');
}

http_response_code(404);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 | CARVASILVA</title>
    <style>
        body { margin:0; min-height:100vh; display:grid; place-items:center; background:#0D0D0D; color:#E8E8E8; font-family:Inter,Arial,sans-serif; }
        .box { text-align:center; padding:24px; border:1px solid #2A2A3E; background:#1A1A2E; border-radius:12px; }
        a { color:#4B9EFF; text-decoration:none; }
    </style>
</head>
<body>
    <main class="box">
        <h1>404</h1>
        <p>Página não encontrada.</p>
        <a href="<?= e(appUrl('/')) ?>">Voltar para o início</a>
    </main>
</body>
</html>
