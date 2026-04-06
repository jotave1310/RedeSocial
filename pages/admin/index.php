<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$authUser = $GLOBALS['auth_user'];
if (($authUser['role'] ?? 'user') !== 'admin') {
    redirect('/feed');
}

$statsStmt = db()->query(
    'SELECT
        (SELECT COUNT(*) FROM users) AS total_users,
        (SELECT COUNT(*) FROM posts) AS total_posts,
        (SELECT COUNT(*) FROM bets WHERE status = "open") AS open_bets,
        (SELECT COALESCE(SUM(credits), 0) FROM users) AS total_credits'
);
$stats = $statsStmt->fetch() ?: [
    'total_users' => 0,
    'total_posts' => 0,
    'open_bets' => 0,
    'total_credits' => 0,
];

$recentAnonStmt = db()->query(
    'SELECT
        p.id,
        p.content,
        p.created_at,
        u.username,
        r.code AS room_code
     FROM posts p
     INNER JOIN users u ON u.id = p.user_id
     INNER JOIN rooms r ON r.id = u.room_id
     WHERE p.is_anonymous = 1
     ORDER BY p.created_at DESC
     LIMIT 10'
);
$recentAnonPosts = $recentAnonStmt->fetchAll();

$pageTitle = 'Admin Dashboard';
$pageDescription = 'Painel administrativo da CARVASILVA.';
$pageCss = ['/assets/css/ranking.css'];
$notificationsUnread = getUnreadNotificationsCount((int) $authUser['id']);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<div class="app-shell">
    <main class="page-main page-main--wide">
        <section class="ranking-layout">
            <article class="card">
                <header class="ranking-header">
                    <div>
                        <h1>Admin • Dashboard</h1>
                        <p>Gerencie usuários, apostas e moderação de conteúdo.</p>
                    </div>
                    <div class="inline">
                        <a href="<?= e(appUrl('/admin/users')) ?>" class="btn btn--ghost">Usuários</a>
                        <a href="<?= e(appUrl('/admin/bets')) ?>" class="btn btn--gold">Apostas</a>
                    </div>
                </header>
            </article>

            <section class="profile-stats">
                <article class="profile-stat">
                    <span class="profile-stat-value"><?= (int) $stats['total_users'] ?></span>
                    <span class="profile-stat-label">Usuários</span>
                </article>
                <article class="profile-stat">
                    <span class="profile-stat-value"><?= (int) $stats['total_posts'] ?></span>
                    <span class="profile-stat-label">Posts</span>
                </article>
                <article class="profile-stat">
                    <span class="profile-stat-value"><?= (int) $stats['open_bets'] ?></span>
                    <span class="profile-stat-label">Apostas abertas</span>
                </article>
            </section>

            <article class="card">
                <h2 style="margin:0 0 10px;font-size:1rem;">Economia da plataforma</h2>
                <p style="margin:0;color:var(--text-secondary);">
                    Créditos totais circulando: <strong class="credits"><?= e(formatCredits((int) $stats['total_credits'])) ?></strong>
                </p>
            </article>

            <article class="card">
                <h2 style="margin:0 0 10px;font-size:1rem;">Últimos posts anônimos (identidade visível para admin)</h2>
                <?php if ($recentAnonPosts === []): ?>
                    <div class="ranking-empty">Sem posts anônimos recentes.</div>
                <?php else: ?>
                    <div class="ranking-list">
                        <?php foreach ($recentAnonPosts as $post): ?>
                            <article class="profile-entry post-card--anonymous">
                                <h3 class="profile-entry-title"><?= e((string) $post['content']) ?></h3>
                                <div class="profile-entry-meta">
                                    Autor real: <?= e((string) $post['username']) ?> • Turma <?= e((string) $post['room_code']) ?> • <?= e(timeAgo((string) $post['created_at'])) ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </section>
    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
