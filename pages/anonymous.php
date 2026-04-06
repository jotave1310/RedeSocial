<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$authUser = $GLOBALS['auth_user'];
$pageTitle = 'Zona Anonima';
$pageDescription = 'Area anonima da CARVASILVA para postagens sem identificacao publica.';
$pageCss = ['/assets/css/feed.css'];
$pageScripts = ['/assets/js/feed.js'];
$notificationsUnread = getUnreadNotificationsCount((int) $authUser['id']);
$csrfToken = generateCsrfToken();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<div class="app-shell app-shell--center">
    <main class="page-main">
        <section
            id="feedApp"
            class="feed-layout"
            data-initial-tab="anonymous"
            data-csrf="<?= e($csrfToken) ?>"
            data-max-chars="<?= POST_MAX_LENGTH ?>"
            data-anonymous-mode="1"
        >
            <article class="card composer-card post-card--anonymous">
                <header class="composer-header">
                    <div class="composer-heading">
                        <h1 class="composer-title">Zona anonima</h1>
                        <p class="composer-subtitle">Sua identidade fica oculta para outros usuarios.</p>
                    </div>
                    <span class="badge post-badge-anon">
                        <i class="fa-regular fa-clock" aria-hidden="true"></i>
                        1 post por hora
                    </span>
                </header>

                <form id="composerForm" class="composer-grid">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <label for="composerContent">Sua mensagem anonima</label>
                    <textarea id="composerContent" name="content" maxlength="<?= POST_MAX_LENGTH ?>" placeholder="Escreva algo em modo anonimo..."></textarea>

                    <div class="composer-footer">
                        <span id="composerCounter" class="composer-counter">0/<?= POST_MAX_LENGTH ?></span>
                        <button type="submit" class="btn btn--gold">Publicar anonimamente</button>
                    </div>
                </form>
            </article>

            <section class="feed-toolbar card">
                <nav class="top-tabs" aria-label="Abas do feed anonimo">
                    <button class="tab-btn is-active" data-feed-tab="anonymous" aria-selected="true">Anonimos</button>
                    <a class="tab-btn" href="<?= e(appUrl('/feed')) ?>">Voltar ao feed</a>
                </nav>
            </section>

            <div id="feedError" class="feed-error" hidden></div>
            <div id="feedLoading" class="card skeleton" style="height: 84px;" hidden></div>

            <section id="feedList" class="feed-list" aria-live="polite"></section>
            <div id="feedSentinel" class="load-more-anchor" aria-hidden="true"></div>
        </section>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
