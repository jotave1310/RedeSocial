<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$authUser = $GLOBALS['auth_user'];
$betId = sanitizeInt($_GET['id'] ?? 0);
if ($betId <= 0) {
    redirect('/bet');
}

$canManage = false;
$betOwnerStmt = db()->prepare('SELECT creator_id FROM bets WHERE id = :id LIMIT 1');
$betOwnerStmt->execute(['id' => $betId]);
$betOwner = $betOwnerStmt->fetch();
if ($betOwner !== false) {
    $canManage = (($authUser['role'] ?? 'user') === 'admin') || ((int) $betOwner['creator_id'] === (int) $authUser['id']);
}

$pageTitle = 'Aposta #' . $betId;
$pageDescription = 'Detalhes da aposta na BetHouse CARVASILVA.';
$pageCss = ['/assets/css/bet.css'];
$pageScripts = ['/assets/js/bet.js'];
$notificationsUnread = getUnreadNotificationsCount((int) $authUser['id']);
$csrfToken = generateCsrfToken();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<div class="app-shell app-shell--center">
    <main class="page-main">
        <section
            id="betApp"
            class="bet-layout"
            data-mode="single"
            data-bet-id="<?= $betId ?>"
            data-csrf="<?= e($csrfToken) ?>"
        >
            <article class="card">
                <header class="bet-header">
                    <div>
                        <h1>Detalhes da aposta</h1>
                        <p>Visualize opcoes, entradas e status em tempo real.</p>
                    </div>
                    <a href="<?= e(appUrl('/bet')) ?>" class="btn btn--ghost">Voltar</a>
                </header>
            </article>

            <div id="betError" class="bet-error" hidden></div>
            <div id="betLoading" class="card skeleton" style="height: 90px;" hidden></div>

            <section id="betSingleDetail" class="bet-single-options"></section>

            <section class="card">
                <h2 style="margin:0 0 10px;font-size:1rem;">Entradas</h2>
                <div style="overflow:auto;">
                    <table class="bet-entry-table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Aposta</th>
                                <th>Payout</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="betEntriesBody">
                            <tr><td colspan="4">Carregando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="betResolveBox" class="bet-resolve-box" data-can-manage="<?= $canManage ? '1' : '0' ?>" <?= $canManage ? '' : 'hidden' ?>>
                <h3 style="margin:0 0 8px;font-size:.96rem;">Gestao da aposta</h3>
                <p style="margin:0 0 10px;color:var(--text-secondary);font-size:.85rem;">
                    Resolva a aposta indicando a opcao vencedora ou cancele com reembolso total.
                </p>

                <label for="winning_option_id">Opcao vencedora</label>
                <select id="winning_option_id" class="input" name="winning_option_id"></select>

                <div class="bet-resolve-actions">
                    <?php if (($authUser['role'] ?? 'user') === 'admin'): ?>
                        <button type="button" class="btn btn--danger" data-action="cancel-bet" data-bet-id="<?= $betId ?>">
                            Cancelar aposta
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn--gold" data-action="resolve-bet" data-bet-id="<?= $betId ?>">
                        Resolver aposta
                    </button>
                </div>
            </section>
        </section>
    </main>
</div>

<section id="betModal" class="bet-modal" aria-hidden="true">
    <article class="bet-modal-card">
        <h2 class="bet-modal-title">Apostar em: <span data-role="bet-title">Aposta</span></h2>
        <form id="betEntryForm" class="bet-form">
            <input type="hidden" name="bet_id" value="<?= $betId ?>">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <label for="entryOption">Opcao</label>
            <select id="entryOption" name="option_id" required></select>

            <label for="entryAmount">Valor em creditos</label>
            <input id="entryAmount" class="input" type="number" min="1" name="amount" required>
            <p class="bet-form-note">
                Entrada minima: <span data-role="min-entry"><?= e(formatCredits(10)) ?></span>
            </p>

            <div class="bet-modal-actions">
                <button type="button" class="btn btn--ghost" data-action="close-bet-modal">Cancelar</button>
                <button type="submit" class="btn btn--gold">Confirmar aposta</button>
            </div>
        </form>
    </article>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
