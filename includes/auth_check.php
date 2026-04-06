<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

ensureSessionStarted();

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

if (!isAuthenticated()) {
    $redirect = urlencode($requestUri);
    redirect('/login?redirect=' . $redirect);
}

$loggedUser = currentUser();

if ($loggedUser === null) {
    logoutUser();
    redirect('/login?error=sessao_expirada');
}

if ((int) ($loggedUser['is_banned'] ?? 0) === 1) {
    logoutUser();
    redirect('/login?error=banido');
}

$GLOBALS['auth_user'] = $loggedUser;
