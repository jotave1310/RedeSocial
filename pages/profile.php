<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$authUser = $GLOBALS['auth_user'];
$requestedUsername = sanitizeText((string) ($_GET['u'] ?? $authUser['username']), 50);
$profileUser = getUserByUsername($requestedUsername);

if ($profileUser === null) {
    $pageTitle = 'Perfil nao encontrado';
    $pageDescription = 'Perfil inexistente na CARVASILVA.';
    $pageCss = ['/assets/css/ranking.css'];
    $notificationsUnread = getUnreadNotificationsCount((int) $authUser['id']);
    include __DIR__ . '/../includes/header.php';
    include __DIR__ . '/../includes/navbar.php';
    ?>
    <div class="app-shell app-shell--center">
        <main class="page-main">
            <article class="card">
                <h1 style="margin:0 0 8px;">Perfil nao encontrado</h1>
                <p style="margin:0;color:var(--text-secondary);">O usuario solicitado nao existe.</p>
            </article>
        </main>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <?php
    exit;
}

$profileUserId = (int) $profileUser['id'];
$viewerId = (int) $authUser['id'];
$isOwnProfile = $profileUserId === $viewerId;

$statsStmt = db()->prepare(
    'SELECT
        (SELECT COUNT(*) FROM posts WHERE user_id = :posts_user_id) AS posts_count,
        (SELECT COUNT(*) FROM follows WHERE following_id = :followers_user_id) AS followers_count,
        (SELECT COUNT(*) FROM follows WHERE follower_id = :following_user_id) AS following_count'
);
$statsStmt->execute([
    'posts_user_id' => $profileUserId,
    'followers_user_id' => $profileUserId,
    'following_user_id' => $profileUserId,
]);
$stats = $statsStmt->fetch() ?: ['posts_count' => 0, 'followers_count' => 0, 'following_count' => 0];

$badgesStmt = db()->prepare(
    'SELECT badge_key
     FROM badges
     WHERE user_id = :user_id
     ORDER BY earned_at DESC
     LIMIT 12'
);
$badgesStmt->execute(['user_id' => $profileUserId]);
$badges = $badgesStmt->fetchAll();

$postsStmt = db()->prepare(
    'SELECT id, content, type, is_anonymous, created_at, like_count, comment_count
     FROM posts
     WHERE user_id = :user_id
     ORDER BY created_at DESC
     LIMIT 20'
);
$postsStmt->execute(['user_id' => $profileUserId]);
$posts = $postsStmt->fetchAll();

$betsStmt = db()->prepare(
    'SELECT
        be.amount,
        be.payout,
        be.status,
        be.created_at,
        b.id AS bet_id,
        b.title AS bet_title
     FROM bet_entries be
     INNER JOIN bets b ON b.id = be.bet_id
     WHERE be.user_id = :user_id
     ORDER BY be.created_at DESC
     LIMIT 20'
);
$betsStmt->execute(['user_id' => $profileUserId]);
$betEntries = $betsStmt->fetchAll();

$creditsStmt = db()->prepare(
    'SELECT amount, balance_after, type, description, created_at
     FROM credit_transactions
     WHERE user_id = :user_id
     ORDER BY created_at DESC
     LIMIT 20'
);
$creditsStmt->execute(['user_id' => $profileUserId]);
$creditHistory = $creditsStmt->fetchAll();

$following = false;
if (!$isOwnProfile) {
    $followCheck = db()->prepare(
        'SELECT id FROM follows
         WHERE follower_id = :viewer_id
           AND following_id = :profile_id
         LIMIT 1'
    );
    $followCheck->execute([
        'viewer_id' => $viewerId,
        'profile_id' => $profileUserId,
    ]);
    $following = (bool) $followCheck->fetch();
}

$pageTitle = 'Perfil ' . $profileUser['username'];
$pageDescription = 'Perfil de usuario na CARVASILVA.';
$pageCss = ['/assets/css/ranking.css'];
$notificationsUnread = getUnreadNotificationsCount((int) $authUser['id']);
$csrfToken = generateCsrfToken();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<div class="app-shell app-shell--center">
    <main class="page-main">
        <section class="profile-layout">
            <article class="profile-hero">
                <div class="profile-cover"></div>
                <div class="profile-main">
                    <div class="profile-main-head">
                        <div class="inline">
                            <img src="<?= e(publicPath((string) ($profileUser['avatar'] ?? ''), '/assets/img/avatars/default-avatar.svg')) ?>" alt="Avatar de <?= e((string) $profileUser['username']) ?>" class="avatar-lg">
                            <div class="profile-id">
                                <h1><?= e((string) $profileUser['username']) ?></h1>
                                <p><?= e((string) $profileUser['display_name']) ?> | Turma <?= e((string) $profileUser['room_code']) ?></p>
                                <p><?= e((string) ($profileUser['bio'] ?: 'Sem bio cadastrada.')) ?></p>
                            </div>
                        </div>

                        <?php if (!$isOwnProfile): ?>
                            <button
                                id="followBtn"
                                class="btn <?= $following ? 'btn--ghost' : 'btn--gold' ?>"
                                data-user-id="<?= $profileUserId ?>"
                                data-following="<?= $following ? '1' : '0' ?>"
                            >
                                <?= $following ? 'Seguindo' : 'Seguir' ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="profile-stats">
                        <div class="profile-stat">
                            <span class="profile-stat-value"><?= e(formatCredits((int) $profileUser['credits'])) ?></span>
                            <span class="profile-stat-label">Creditos</span>
                        </div>
                        <div class="profile-stat">
                            <span class="profile-stat-value"><?= (int) $stats['posts_count'] ?></span>
                            <span class="profile-stat-label">Posts</span>
                        </div>
                        <div class="profile-stat">
                            <span class="profile-stat-value" id="followersCount"><?= (int) $stats['followers_count'] ?></span>
                            <span class="profile-stat-label">Seguidores</span>
                        </div>
                        <div class="profile-stat">
                            <span class="profile-stat-value"><?= (int) $stats['following_count'] ?></span>
                            <span class="profile-stat-label">Seguindo</span>
                        </div>
                    </div>

                    <?php if ($badges !== []): ?>
                        <div class="profile-badges-row">
                            <?php foreach ($badges as $badge): ?>
                                <?php
                                $badgeKey = (string) ($badge['badge_key'] ?? '');
                                $badgeName = BADGE_KEYS[$badgeKey] ?? $badgeKey;
                                ?>
                                <span class="badge badge--gold"><?= e($badgeName) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>

            <section class="card profile-content">
                <nav class="top-tabs" aria-label="Abas do perfil">
                    <button class="tab-btn is-active" data-profile-tab="posts" aria-selected="true">Posts</button>
                    <button class="tab-btn" data-profile-tab="bets" aria-selected="false">Apostas</button>
                    <button class="tab-btn" data-profile-tab="credits" aria-selected="false">Creditos</button>
                </nav>

                <div id="profileTabPosts" class="profile-tab-content is-active">
                    <?php if ($posts === []): ?>
                        <div class="ranking-empty">Nenhuma postagem ainda.</div>
                    <?php else: ?>
                        <?php foreach ($posts as $post): ?>
                            <article class="profile-entry <?= (int) $post['is_anonymous'] === 1 ? 'post-card--anonymous' : '' ?> <?= (string) $post['type'] === 'credit_flex' ? 'post-card--credit-flex' : '' ?>">
                                <h3 class="profile-entry-title"><?= e((string) $post['content']) ?></h3>
                                <div class="profile-entry-meta profile-entry-meta-grid">
                                    <span><i class="fa-regular fa-clock" aria-hidden="true"></i> <?= e(timeAgo((string) $post['created_at'])) ?></span>
                                    <span><i class="fa-regular fa-heart" aria-hidden="true"></i> <?= (int) $post['like_count'] ?></span>
                                    <span><i class="fa-regular fa-comment" aria-hidden="true"></i> <?= (int) $post['comment_count'] ?></span>
                                    <span><i class="fa-solid fa-tag" aria-hidden="true"></i> <?= e((string) $post['type']) ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div id="profileTabBets" class="profile-tab-content">
                    <?php if ($betEntries === []): ?>
                        <div class="ranking-empty">Nenhuma aposta registrada.</div>
                    <?php else: ?>
                        <?php foreach ($betEntries as $entry): ?>
                            <article class="profile-entry">
                                <h3 class="profile-entry-title">
                                    <a href="<?= e(appUrl('/bet/' . (int) $entry['bet_id'])) ?>">
                                        <?= e((string) $entry['bet_title']) ?>
                                    </a>
                                </h3>
                                <div class="profile-entry-meta profile-entry-meta-grid">
                                    <span><i class="fa-solid fa-coins" aria-hidden="true"></i> <?= e(formatCredits((int) $entry['amount'])) ?></span>
                                    <span><i class="fa-solid fa-chart-line" aria-hidden="true"></i> <?= $entry['payout'] !== null ? e(formatCredits((int) $entry['payout'])) : '-' ?></span>
                                    <span><i class="fa-solid fa-flag-checkered" aria-hidden="true"></i> <?= e((string) $entry['status']) ?></span>
                                    <span><i class="fa-regular fa-clock" aria-hidden="true"></i> <?= e(timeAgo((string) $entry['created_at'])) ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div id="profileTabCredits" class="profile-tab-content">
                    <?php if ($creditHistory === []): ?>
                        <div class="ranking-empty">Sem transacoes de creditos.</div>
                    <?php else: ?>
                        <?php foreach ($creditHistory as $item): ?>
                            <article class="profile-entry">
                                <h3 class="profile-entry-title"><?= e((string) ($item['description'] ?: 'Movimento de credito')) ?></h3>
                                <div class="profile-entry-meta profile-entry-meta-grid">
                                    <span><i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i> <?= (int) $item['amount'] >= 0 ? '+' : '' ?><?= e(formatCredits((int) $item['amount'])) ?></span>
                                    <span><i class="fa-solid fa-wallet" aria-hidden="true"></i> <?= e(formatCredits((int) $item['balance_after'])) ?></span>
                                    <span><i class="fa-solid fa-tag" aria-hidden="true"></i> <?= e((string) $item['type']) ?></span>
                                    <span><i class="fa-regular fa-clock" aria-hidden="true"></i> <?= e(timeAgo((string) $item['created_at'])) ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </section>
    </main>
</div>

<script>
    (() => {
        const csrfToken = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE) ?>;
        const followApiBase = <?= json_encode(appUrl('/api/follow.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        document.querySelectorAll('[data-profile-tab]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.profileTab;
                document.querySelectorAll('[data-profile-tab]').forEach((item) => {
                    item.classList.remove('is-active');
                    item.setAttribute('aria-selected', 'false');
                });
                btn.classList.add('is-active');
                btn.setAttribute('aria-selected', 'true');

                document.querySelectorAll('.profile-tab-content').forEach((content) => {
                    content.classList.remove('is-active');
                });

                const target = document.getElementById(`profileTab${tab.charAt(0).toUpperCase()}${tab.slice(1)}`);
                if (target) target.classList.add('is-active');
            });
        });

        const followBtn = document.getElementById('followBtn');
        if (!followBtn) return;

        followBtn.addEventListener('click', async () => {
            const userId = Number(followBtn.dataset.userId || '0');
            if (!userId) return;

            followBtn.disabled = true;
            try {
                const formData = new FormData();
                formData.set('user_id', String(userId));
                formData.set('csrf_token', csrfToken);

                const response = await fetch(`${followApiBase}?action=toggle`, {
                    method: 'POST',
                    headers: { Accept: 'application/json' },
                    body: formData,
                });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(payload.error || 'Nao foi possivel atualizar o follow.');
                }

                const following = !!payload.data.following;
                const count = Number(payload.data.follower_count || 0);
                followBtn.dataset.following = following ? '1' : '0';
                followBtn.textContent = following ? 'Seguindo' : 'Seguir';
                followBtn.classList.toggle('btn--gold', !following);
                followBtn.classList.toggle('btn--ghost', following);

                const followersCount = document.getElementById('followersCount');
                if (followersCount) followersCount.textContent = String(count);
            } catch (error) {
                alert(error.message || 'Erro ao seguir usuario.');
            } finally {
                followBtn.disabled = false;
            }
        });
    })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
