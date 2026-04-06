<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$authUser = $GLOBALS['auth_user'];
$pageTitle = 'Feed';
$pageDescription = 'Feed principal da rede social escolar CARVASILVA.';
$pageCss = ['/assets/css/feed.css'];
$pageScripts = ['/assets/js/feed.js'];
$notificationsUnread = getUnreadNotificationsCount((int) $authUser['id']);
$csrfToken = generateCsrfToken();
$initialTab = sanitizeText((string) ($_GET['tab'] ?? 'home'), 20);
$allowedTabs = ['home', 'trending', 'following', 'class'];
if (!in_array($initialTab, $allowedTabs, true)) {
    $initialTab = 'home';
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<div class="app-shell">
    <main class="page-main">
        <section
            id="feedApp"
            class="feed-layout"
            data-initial-tab="<?= e($initialTab) ?>"
            data-csrf="<?= e($csrfToken) ?>"
            data-max-chars="<?= POST_MAX_LENGTH ?>"
            data-anonymous-mode="0"
        >
            <article class="card composer-card">
                <header class="composer-header">
                    <div class="composer-heading">
                        <h1 class="composer-title">Feed social</h1>
                        <p class="composer-subtitle">Publice, comente e acompanhe o que esta acontecendo na sua turma.</p>
                    </div>

                    <div class="composer-badges">
                        <span class="badge badge--gold">
                            <i class="fa-solid fa-coins" aria-hidden="true"></i>
                            Saldo: <?= e(formatCredits((int) $authUser['credits'])) ?>
                        </span>
                        <span class="badge badge--blue">
                            <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                            Bonus post: <?= e(formatCredits((int) CREDIT_RULES['post_bonus'])) ?>
                        </span>
                    </div>
                </header>

                <form id="composerForm" class="composer-grid" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <label for="composerContent">Nova postagem</label>
                    <textarea
                        id="composerContent"
                        name="content"
                        maxlength="<?= POST_MAX_LENGTH ?>"
                        placeholder="Compartilhe algo com a comunidade..."
                    ></textarea>

                    <div class="composer-footer">
                        <div class="inline" id="composerActions">
                            <label for="composerImage" class="btn btn--ghost composer-upload-btn">
                                <i class="fa-regular fa-image" aria-hidden="true"></i>
                                Imagem
                            </label>
                            <input id="composerImage" class="composer-image-input" type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.webp">

                            <button type="button" class="btn btn--ghost" data-composer-action="credit-flex">
                                <i class="fa-solid fa-coins" aria-hidden="true"></i>
                                Credit Flex
                            </button>
                        </div>

                        <div class="inline">
                            <span id="composerCounter" class="composer-counter">0/<?= POST_MAX_LENGTH ?></span>
                            <button type="submit" class="btn btn--gold">Publicar</button>
                        </div>
                    </div>
                </form>
            </article>

            <section class="feed-toolbar card">
                <nav class="top-tabs" aria-label="Abas do feed">
                    <button class="tab-btn <?= $initialTab === 'home' ? 'is-active' : '' ?>" data-feed-tab="home" aria-selected="<?= $initialTab === 'home' ? 'true' : 'false' ?>">Para voce</button>
                    <button class="tab-btn <?= $initialTab === 'trending' ? 'is-active' : '' ?>" data-feed-tab="trending" aria-selected="<?= $initialTab === 'trending' ? 'true' : 'false' ?>">Em alta</button>
                    <button class="tab-btn <?= $initialTab === 'following' ? 'is-active' : '' ?>" data-feed-tab="following" aria-selected="<?= $initialTab === 'following' ? 'true' : 'false' ?>">Seguindo</button>
                    <button class="tab-btn <?= $initialTab === 'class' ? 'is-active' : '' ?>" data-feed-tab="class" aria-selected="<?= $initialTab === 'class' ? 'true' : 'false' ?>">Minha turma</button>
                </nav>

                <a href="<?= e(appUrl('/anonymous')) ?>" class="btn btn--ghost feed-toolbar-anon">
                    <i class="fa-solid fa-user-secret" aria-hidden="true"></i>
                    Zona anonima
                </a>
            </section>

            <div id="feedError" class="feed-error" hidden></div>
            <div id="feedLoading" class="card skeleton" style="height: 84px;" hidden></div>

            <section id="feedList" class="feed-list" aria-live="polite"></section>
            <div id="feedSentinel" class="load-more-anchor" aria-hidden="true"></div>
        </section>
    </main>

    <aside class="desktop-panel feed-side-column">
        <section class="card side-widget">
            <h2 class="side-widget__title">Ritmo da comunidade</h2>
            <ul class="side-widget__list">
                <li>
                    <span><i class="fa-solid fa-pen" aria-hidden="true"></i> Bonus por postagem</span>
                    <strong><?= e(formatCredits((int) CREDIT_RULES['post_bonus'])) ?></strong>
                </li>
                <li>
                    <span><i class="fa-solid fa-heart" aria-hidden="true"></i> Bonus por like recebido</span>
                    <strong><?= e(formatCredits((int) CREDIT_RULES['like_received_bonus'])) ?></strong>
                </li>
                <li>
                    <span><i class="fa-solid fa-clock" aria-hidden="true"></i> Limite bonus diario</span>
                    <strong><?= (int) CREDIT_RULES['post_bonus_daily_limit'] ?> posts</strong>
                </li>
            </ul>
        </section>

        <section class="card side-widget" id="rewardProgram">
            <h2 class="side-widget__title">Programa de bonus</h2>
            <p class="side-widget__description">Misses simples para manter seu saldo ativo todos os dias.</p>
            <ul class="reward-program-list">
                <?php foreach (array_slice(REWARD_PROGRAM, 0, 5) as $reward): ?>
                    <li>
                        <span>
                            <i class="fa-solid <?= e((string) $reward['icon']) ?>" aria-hidden="true"></i>
                            <?= e((string) $reward['label']) ?>
                        </span>
                        <strong><?= e(formatCredits((int) $reward['reward'])) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn btn--ghost" data-open-rewards>
                Ver tabela completa
            </button>
        </section>
    </aside>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
