<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

ensureSessionStarted();

$user = currentUser();
$currentPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$basePath = appBasePath();
if ($basePath !== '' && strncasecmp($currentPath, $basePath, strlen($basePath)) === 0) {
    $currentPath = substr($currentPath, strlen($basePath));
    if ($currentPath === '') {
        $currentPath = '/';
    }
}

$notificationsUnread = isset($notificationsUnread) ? max(0, (int) $notificationsUnread) : 0;

$menuItems = [
    [
        'label' => 'Feed',
        'icon' => 'fa-solid fa-house',
        'href' => '/feed',
        'paths' => ['/feed', '/feed/trending', '/feed/class', '/pages/feed.php'],
    ],
    [
        'label' => 'BetHouse',
        'icon' => 'fa-solid fa-dice',
        'href' => '/bet',
        'paths' => ['/bet', '/bet/create', '/pages/bet.php', '/pages/bet_create.php', '/pages/bet_single.php'],
    ],
    [
        'label' => 'Anonimo',
        'icon' => 'fa-solid fa-user-secret',
        'href' => '/anonymous',
        'paths' => ['/anonymous', '/pages/anonymous.php'],
    ],
    [
        'label' => 'Ranking',
        'icon' => 'fa-solid fa-trophy',
        'href' => '/ranking',
        'paths' => ['/ranking', '/pages/ranking.php'],
    ],
    [
        'label' => 'Mensagens',
        'icon' => 'fa-solid fa-comment-dots',
        'href' => '/messages',
        'paths' => ['/messages', '/pages/messages.php', '/pages/messages_thread.php'],
    ],
    [
        'label' => 'Notificacoes',
        'icon' => 'fa-solid fa-bell',
        'href' => '/notifications',
        'paths' => ['/notifications', '/pages/notifications.php'],
    ],
];

$profileHref = '/profile/' . urlencode((string) ($user['username'] ?? 'me'));
$profilePaths = ['/profile', '/pages/profile.php'];

$matchPath = static function (string $path, array $allowedPaths): bool {
    foreach ($allowedPaths as $allowedPath) {
        if ($path === $allowedPath || str_starts_with($path, $allowedPath . '/')) {
            return true;
        }
    }

    return false;
};

$avatar = '/assets/img/avatars/default-avatar.svg';
if ($user !== null && !empty($user['avatar'])) {
    $avatar = (string) $user['avatar'];
}

$displayName = $user !== null ? (string) $user['display_name'] : 'Visitante';
$username = $user !== null ? (string) $user['username'] : 'guest';
$room = $user !== null ? (string) ($user['room_code'] ?? '') : '';
$credits = $user !== null ? (int) ($user['credits'] ?? 0) : 0;
?>
<header class="mobile-topbar" role="banner">
    <div class="mobile-topbar__inner">
        <a href="<?= e(appUrl('/feed')) ?>" class="brand" aria-label="Ir para o feed">
            <span class="brand__icon"><i class="fa-solid fa-bolt" aria-hidden="true"></i></span>
            <span>CARVASILVA</span>
        </a>

        <div class="mobile-topbar__meta">
            <?php if ($user !== null): ?>
                <span class="credit-pill" id="mobileCredits" data-credits="<?= $credits ?>">
                    <i class="fa-solid fa-coins" aria-hidden="true"></i>
                    <span class="credits-value"><?= e(formatCredits($credits)) ?></span>
                </span>
            <?php endif; ?>

            <button type="button" class="icon-btn" aria-label="Alternar tema" title="Alternar tema" data-theme-toggle>
                <i class="fa-regular fa-moon" data-theme-icon aria-hidden="true"></i>
            </button>

            <a href="<?= e(appUrl('/notifications')) ?>" class="icon-btn" aria-label="Notificacoes">
                <span style="position: relative; display: inline-grid; place-items: center;">
                    <i class="fa-regular fa-bell" aria-hidden="true"></i>
                    <?php if ($notificationsUnread > 0): ?>
                        <span class="icon-btn__badge" data-notification-badge><?= $notificationsUnread > 99 ? '99+' : $notificationsUnread ?></span>
                    <?php else: ?>
                        <span class="icon-btn__badge" data-notification-badge hidden></span>
                    <?php endif; ?>
                </span>
            </a>
        </div>
    </div>
</header>

<aside class="sidebar-nav" aria-label="Navegacao principal">
    <div class="sidebar-nav__inner">
        <a href="<?= e(appUrl('/feed')) ?>" class="brand" aria-label="CARVASILVA">
            <span class="brand__icon"><i class="fa-solid fa-bolt" aria-hidden="true"></i></span>
            <span>CARVASILVA</span>
        </a>

        <nav class="sidebar-nav__menu">
            <?php foreach ($menuItems as $item): ?>
                <?php $isActive = $matchPath($currentPath, $item['paths']); ?>
                <a
                    href="<?= e(appUrl((string) $item['href'])) ?>"
                    class="nav-link<?= $isActive ? ' is-active' : '' ?>"
                    <?= $isActive ? 'aria-current="page"' : '' ?>
                >
                    <i class="<?= e((string) $item['icon']) ?>" aria-hidden="true"></i>
                    <span><?= e((string) $item['label']) ?></span>
                    <?php if ($item['label'] === 'Notificacoes' && $notificationsUnread > 0): ?>
                        <span class="notification-dot" data-notification-dot aria-hidden="true"></span>
                    <?php elseif ($item['label'] === 'Notificacoes'): ?>
                        <span class="notification-dot" data-notification-dot hidden aria-hidden="true"></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>

            <a
                href="<?= e(appUrl($profileHref)) ?>"
                class="nav-link<?= $matchPath($currentPath, $profilePaths) ? ' is-active' : '' ?>"
                <?= $matchPath($currentPath, $profilePaths) ? 'aria-current="page"' : '' ?>
            >
                <i class="fa-regular fa-user" aria-hidden="true"></i>
                <span>Perfil</span>
            </a>

            <a href="#" class="nav-link" data-theme-toggle>
                <i class="fa-regular fa-moon" data-theme-icon aria-hidden="true"></i>
                <span>Tema</span>
            </a>

            <a href="#" class="nav-link" data-open-rewards>
                <i class="fa-solid fa-gift" aria-hidden="true"></i>
                <span>Bonus</span>
            </a>

            <?php if ($user !== null): ?>
                <a href="<?= e(appUrl('/logout')) ?>" class="nav-link nav-link--danger">
                    <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                    <span>Sair</span>
                </a>
            <?php else: ?>
                <a href="<?= e(appUrl('/login')) ?>" class="nav-link">
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                    <span>Entrar</span>
                </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-user">
            <div class="sidebar-user__top">
                <img src="<?= e(publicPath($avatar, '/assets/img/avatars/default-avatar.svg')) ?>" alt="Avatar de <?= e($displayName) ?>" class="avatar-sm">
                <div>
                    <div class="sidebar-user__name"><?= e($username) ?></div>
                    <div class="sidebar-user__room"><?= $room !== '' ? 'Turma ' . e($room) : 'Sem turma' ?></div>
                </div>
            </div>

            <?php if ($user !== null): ?>
                <div class="credit-pill" id="desktopCredits" data-credits="<?= $credits ?>">
                    <i class="fa-solid fa-coins" aria-hidden="true"></i>
                    <span class="credits-value"><?= e(formatCredits($credits)) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</aside>

<nav class="mobile-bottom-nav" aria-label="Navegacao movel">
    <a href="<?= e(appUrl('/feed')) ?>" class="mobile-bottom-nav__link<?= $matchPath($currentPath, ['/feed', '/feed/trending', '/feed/class', '/pages/feed.php']) ? ' is-active' : '' ?>">
        <i class="fa-solid fa-house" aria-hidden="true"></i>
        <span>Feed</span>
    </a>
    <a href="<?= e(appUrl('/bet')) ?>" class="mobile-bottom-nav__link<?= $matchPath($currentPath, ['/bet', '/bet/create', '/pages/bet.php', '/pages/bet_create.php', '/pages/bet_single.php']) ? ' is-active' : '' ?>">
        <i class="fa-solid fa-dice" aria-hidden="true"></i>
        <span>Bet</span>
    </a>
    <a href="<?= e(appUrl('/anonymous')) ?>" class="mobile-bottom-nav__link<?= $matchPath($currentPath, ['/anonymous', '/pages/anonymous.php']) ? ' is-active' : '' ?>">
        <i class="fa-solid fa-user-secret" aria-hidden="true"></i>
        <span>Anon</span>
    </a>
    <a href="<?= e(appUrl('/ranking')) ?>" class="mobile-bottom-nav__link<?= $matchPath($currentPath, ['/ranking', '/pages/ranking.php']) ? ' is-active' : '' ?>">
        <i class="fa-solid fa-trophy" aria-hidden="true"></i>
        <span>Rank</span>
    </a>
    <a href="<?= e(appUrl($profileHref)) ?>" class="mobile-bottom-nav__link<?= $matchPath($currentPath, $profilePaths) ? ' is-active' : '' ?>">
        <i class="fa-regular fa-user" aria-hidden="true"></i>
        <span>Perfil</span>
    </a>
</nav>
