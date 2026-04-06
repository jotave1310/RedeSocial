<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$authUser = $GLOBALS['auth_user'];
$pageTitle = 'Notificacoes';
$pageDescription = 'Central de notificacoes da CARVASILVA.';
$pageCss = ['/assets/css/ranking.css'];
$pageScripts = ['/assets/js/notifications.js'];
$notificationsUnread = getUnreadNotificationsCount((int) $authUser['id']);
$csrfToken = generateCsrfToken();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<div class="app-shell app-shell--center">
    <main class="page-main">
        <section id="notificationsApp" class="ranking-layout" data-csrf="<?= e($csrfToken) ?>">
            <article class="card">
                <header class="ranking-header">
                    <div>
                        <h1>Notificacoes</h1>
                        <p id="notificationsUnreadMeta"><?= $notificationsUnread > 0 ? $notificationsUnread . ' nao lida(s)' : 'Tudo em dia' ?></p>
                    </div>
                    <button id="markAllReadBtn" class="btn btn--gold">Marcar todas como lidas</button>
                </header>
            </article>

            <div id="notificationsError" class="ranking-error" hidden></div>
            <section id="notificationsList" class="ranking-list"></section>
        </section>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
