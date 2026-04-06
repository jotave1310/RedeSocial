<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$authUser = $GLOBALS['auth_user'];
if (($authUser['role'] ?? 'user') !== 'admin') {
    redirect('/feed');
}

$statusFilter = sanitizeText((string) ($_GET['status'] ?? 'all'), 20);
$allowedStatus = ['all', 'open', 'closed', 'resolved', 'cancelled'];
if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = 'all';
}

$query = '
    SELECT
        b.*,
        u.username AS creator_username
    FROM bets b
    INNER JOIN users u ON u.id = b.creator_id
';
$params = [];

if ($statusFilter !== 'all') {
    $query .= ' WHERE b.status = :status';
    $params['status'] = $statusFilter;
}

$query .= ' ORDER BY b.created_at DESC LIMIT 150';
$stmt = db()->prepare($query);
$stmt->execute($params);
$bets = $stmt->fetchAll();

$optionsByBet = [];
if ($bets !== []) {
    $betIds = array_map(static fn (array $item): int => (int) $item['id'], $bets);
    $placeholders = implode(',', array_fill(0, count($betIds), '?'));
    $optStmt = db()->prepare(
        'SELECT id, bet_id, label, total_bet
         FROM bet_options
         WHERE bet_id IN (' . $placeholders . ')
         ORDER BY bet_id ASC, id ASC'
    );
    $optStmt->execute($betIds);
    $rows = $optStmt->fetchAll();
    foreach ($rows as $row) {
        $betId = (int) $row['bet_id'];
        $optionsByBet[$betId] ??= [];
        $optionsByBet[$betId][] = $row;
    }
}

$pageTitle = 'Admin Apostas';
$pageDescription = 'Gestão administrativa de apostas da CARVASILVA.';
$pageCss = ['/assets/css/bet.css', '/assets/css/ranking.css'];
$notificationsUnread = getUnreadNotificationsCount((int) $authUser['id']);
$csrfToken = generateCsrfToken();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<div class="app-shell app-shell--center">
    <main class="page-main page-main--wide">
        <section class="ranking-layout" id="adminBetsApp" data-csrf="<?= e($csrfToken) ?>">
            <article class="card">
                <header class="ranking-header">
                    <div>
                        <h1>Admin • Apostas</h1>
                        <p>Resolver, cancelar e monitorar apostas da plataforma.</p>
                    </div>
                    <a href="<?= e(appUrl('/admin')) ?>" class="btn btn--ghost">Voltar ao dashboard</a>
                </header>
            </article>

            <article class="card">
                <form method="get" class="inline" style="justify-content:space-between;gap:10px;flex-wrap:wrap;">
                    <label for="status" style="margin:0;">Status</label>
                    <select id="status" class="input" name="status" style="max-width:200px;">
                        <?php foreach ($allowedStatus as $status): ?>
                            <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
                                <?= e(ucfirst($status)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn--gold">Aplicar</button>
                </form>
            </article>

            <div id="adminBetsFeedback" class="ranking-error" hidden></div>

            <section class="bet-list">
                <?php if ($bets === []): ?>
                    <article class="bet-empty card">Nenhuma aposta encontrada para esse filtro.</article>
                <?php else: ?>
                    <?php foreach ($bets as $bet): ?>
                        <?php
                        $betId = (int) $bet['id'];
                        $options = $optionsByBet[$betId] ?? [];
                        ?>
                        <article class="bet-card" data-bet-id="<?= $betId ?>">
                            <header class="bet-card-header">
                                <div>
                                    <h2 class="bet-card-title"><?= e((string) $bet['title']) ?></h2>
                                    <div class="bet-card-meta">
                                        <span>Criador: <?= e((string) $bet['creator_username']) ?></span>
                                        <span>Pool: <?= e(formatCredits((int) $bet['total_pool'])) ?></span>
                                        <span>Prazo: <?= e((string) $bet['deadline']) ?></span>
                                    </div>
                                </div>
                                <span class="bet-status bet-status--<?= e((string) $bet['status']) ?>"><?= e((string) $bet['status']) ?></span>
                            </header>

                            <?php if ($options !== []): ?>
                                <div class="bet-options">
                                    <?php foreach ($options as $option): ?>
                                        <div class="bet-option">
                                            <div class="bet-option-top">
                                                <span class="bet-option-label"><?= e((string) $option['label']) ?></span>
                                                <span class="bet-option-value"><?= e(formatCredits((int) $option['total_bet'])) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (in_array((string) $bet['status'], ['open', 'closed'], true)): ?>
                                <footer class="bet-card-footer">
                                    <select class="input" data-role="winner-select" style="max-width:280px;">
                                        <?php foreach ($options as $option): ?>
                                            <option value="<?= (int) $option['id'] ?>"><?= e((string) $option['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="bet-actions">
                                        <button class="btn btn--gold" data-action="resolve" data-bet-id="<?= $betId ?>">Resolver</button>
                                        <button class="btn btn--danger" data-action="cancel" data-bet-id="<?= $betId ?>">Cancelar</button>
                                    </div>
                                </footer>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </section>
    </main>
</div>

<script>
    (() => {
        const app = document.getElementById("adminBetsApp");
        const csrfToken = app.dataset.csrf || "";
        const betApiBase = <?= json_encode(appUrl('/api/bet.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const feedback = document.getElementById("adminBetsFeedback");

        const setFeedback = (message = "", error = true) => {
            feedback.hidden = !message;
            feedback.textContent = message;
            if (!message) return;
            feedback.className = error ? "ranking-error" : "card";
        };

        app.addEventListener("click", async (event) => {
            const button = event.target.closest("[data-action='resolve'], [data-action='cancel']");
            if (!button) return;

            const action = button.dataset.action;
            const betId = Number(button.dataset.betId || "0");
            if (!betId) return;

            const card = button.closest("[data-bet-id]");
            const winnerSelect = card ? card.querySelector("[data-role='winner-select']") : null;

            const formData = new FormData();
            formData.set("csrf_token", csrfToken);
            formData.set("bet_id", String(betId));
            if (action === "resolve") {
                const winnerId = Number(winnerSelect ? winnerSelect.value : "0");
                if (!winnerId) {
                    setFeedback("Selecione a opção vencedora.");
                    return;
                }
                formData.set("winning_option_id", String(winnerId));
            }

            const confirmMessage = action === "resolve"
                ? "Confirmar resolução da aposta?"
                : "Cancelar essa aposta e reembolsar todos os participantes?";

            if (!window.confirm(confirmMessage)) {
                return;
            }

            button.disabled = true;
            setFeedback("");
            try {
                const response = await fetch(`${betApiBase}?action=${action}`, {
                    method: "POST",
                    headers: { Accept: "application/json" },
                    body: formData
                });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(payload.error || "Falha ao processar ação.");
                }

                setFeedback("Ação aplicada com sucesso. Recarregando...", false);
                setTimeout(() => window.location.reload(), 700);
            } catch (error) {
                setFeedback(error.message || "Erro ao executar ação.");
            } finally {
                button.disabled = false;
            }
        });
    })();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
