<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$authUser = $GLOBALS['auth_user'];
if (($authUser['role'] ?? 'user') !== 'admin') {
    redirect('/feed');
}

$csrfToken = generateCsrfToken();
$flashMessage = '';
$flashType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requireValidCsrfToken((string) ($_POST['csrf_token'] ?? ''));

        $action = sanitizeText((string) ($_POST['action'] ?? ''), 30);
        $targetId = sanitizeInt($_POST['user_id'] ?? 0);

        if ($targetId <= 0) {
            throw new RuntimeException('Usuário alvo inválido.');
        }

        if ($targetId === (int) $authUser['id']) {
            throw new RuntimeException('Você não pode modificar a própria conta por aqui.');
        }

        $target = getUserById($targetId);
        if ($target === null) {
            throw new RuntimeException('Usuário não encontrado.');
        }

        if ($action === 'ban') {
            $reason = sanitizeText((string) ($_POST['ban_reason'] ?? 'Violação das regras da plataforma.'), 255);
            $stmt = db()->prepare(
                'UPDATE users
                 SET is_banned = 1, ban_reason = :reason
                 WHERE id = :id'
            );
            $stmt->execute([
                'reason' => $reason !== '' ? $reason : 'Violação das regras da plataforma.',
                'id' => $targetId,
            ]);
            $flashMessage = 'Usuário banido com sucesso.';
        } elseif ($action === 'unban') {
            $stmt = db()->prepare(
                'UPDATE users
                 SET is_banned = 0, ban_reason = NULL
                 WHERE id = :id'
            );
            $stmt->execute(['id' => $targetId]);
            $flashMessage = 'Banimento removido.';
        } elseif ($action === 'grant_credits') {
            $amount = sanitizeInt($_POST['amount'] ?? 0);
            if ($amount <= 0) {
                throw new RuntimeException('Valor inválido para crédito.');
            }
            addCredits($targetId, $amount, 'admin_grant', 'Crédito concedido pela administração');
            $flashMessage = 'Créditos adicionados com sucesso.';
        } elseif ($action === 'deduct_credits') {
            $amount = sanitizeInt($_POST['amount'] ?? 0);
            if ($amount <= 0) {
                throw new RuntimeException('Valor inválido para débito.');
            }
            $result = deductCredits($targetId, $amount, 'admin_deduct', 'Débito aplicado pela administração');
            if ($result === false) {
                throw new RuntimeException('Saldo insuficiente para aplicar o débito.');
            }
            $flashMessage = 'Créditos removidos com sucesso.';
        } else {
            throw new RuntimeException('Ação inválida.');
        }
    } catch (Throwable $exception) {
        $flashType = 'error';
        $flashMessage = $exception->getMessage();
    }
}

$search = sanitizeText((string) ($_GET['search'] ?? ''), 80);
$roomFilter = sanitizeText((string) ($_GET['room'] ?? ''), 10);
$bannedFilter = sanitizeText((string) ($_GET['banned'] ?? 'all'), 10);

$query = '
    SELECT
        u.id,
        u.username,
        u.display_name,
        u.credits,
        u.role,
        u.is_banned,
        u.ban_reason,
        u.created_at,
        r.code AS room_code
    FROM users u
    INNER JOIN rooms r ON r.id = u.room_id
    WHERE 1 = 1
';
$params = [];

if ($search !== '') {
    $query .= ' AND (u.username LIKE :search OR u.display_name LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if ($roomFilter !== '') {
    $query .= ' AND r.code = :room';
    $params['room'] = $roomFilter;
}

if ($bannedFilter === 'yes') {
    $query .= ' AND u.is_banned = 1';
} elseif ($bannedFilter === 'no') {
    $query .= ' AND u.is_banned = 0';
}

$query .= ' ORDER BY u.created_at DESC LIMIT 200';

$stmt = db()->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();
$rooms = getRooms();

$pageTitle = 'Admin Usuários';
$pageDescription = 'Gestão de usuários da CARVASILVA.';
$pageCss = ['/assets/css/ranking.css'];
$notificationsUnread = getUnreadNotificationsCount((int) $authUser['id']);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>
<div class="app-shell app-shell--center">
    <main class="page-main page-main--wide">
        <section class="ranking-layout">
            <article class="card">
                <header class="ranking-header">
                    <div>
                        <h1>Admin • Usuários</h1>
                        <p>Moderação de contas, banimentos e ajustes de crédito.</p>
                    </div>
                    <a href="<?= e(appUrl('/admin')) ?>" class="btn btn--ghost">Voltar ao dashboard</a>
                </header>
            </article>

            <?php if ($flashMessage !== ''): ?>
                <div class="<?= $flashType === 'error' ? 'ranking-error' : 'card' ?>">
                    <?= e($flashMessage) ?>
                </div>
            <?php endif; ?>

            <article class="card">
                <form method="get" class="bet-form">
                    <div class="bet-form-grid">
                        <div>
                            <label for="search">Buscar</label>
                            <input id="search" class="input" type="text" name="search" value="<?= e($search) ?>" placeholder="username ou nome">
                        </div>
                        <div>
                            <label for="room">Turma</label>
                            <select id="room" class="input" name="room">
                                <option value="">Todas</option>
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?= e((string) $room['code']) ?>" <?= $roomFilter === (string) $room['code'] ? 'selected' : '' ?>>
                                        <?= e((string) $room['code']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="banned">Banido</label>
                            <select id="banned" class="input" name="banned">
                                <option value="all" <?= $bannedFilter === 'all' ? 'selected' : '' ?>>Todos</option>
                                <option value="yes" <?= $bannedFilter === 'yes' ? 'selected' : '' ?>>Sim</option>
                                <option value="no" <?= $bannedFilter === 'no' ? 'selected' : '' ?>>Não</option>
                            </select>
                        </div>
                    </div>
                    <div class="inline" style="justify-content:flex-end;">
                        <button type="submit" class="btn btn--gold">Filtrar</button>
                    </div>
                </form>
            </article>

            <article class="card">
                <div style="overflow:auto;">
                    <table class="bet-entry-table">
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Turma</th>
                                <th>Créditos</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users === []): ?>
                                <tr><td colspan="5">Nenhum usuário encontrado.</td></tr>
                            <?php else: ?>
                                <?php foreach ($users as $item): ?>
                                    <tr>
                                        <td>
                                            <?= e((string) $item['username']) ?><br>
                                            <span style="color:var(--text-secondary);font-size:.76rem;"><?= e((string) $item['display_name']) ?></span>
                                        </td>
                                        <td><?= e((string) $item['room_code']) ?></td>
                                        <td><?= e(formatCredits((int) $item['credits'])) ?></td>
                                        <td><?= (int) $item['is_banned'] === 1 ? 'Banido' : 'Ativo' ?></td>
                                        <td>
                                            <div class="inline" style="flex-wrap:wrap;">
                                                <?php if ((int) $item['is_banned'] === 1): ?>
                                                    <form method="post">
                                                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="unban">
                                                        <input type="hidden" name="user_id" value="<?= (int) $item['id'] ?>">
                                                        <button type="submit" class="btn btn--ghost">Desbanir</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post" class="inline">
                                                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="ban">
                                                        <input type="hidden" name="user_id" value="<?= (int) $item['id'] ?>">
                                                        <input type="text" name="ban_reason" class="input" placeholder="Motivo" style="min-width:120px;max-width:160px;">
                                                        <button type="submit" class="btn btn--danger">Banir</button>
                                                    </form>
                                                <?php endif; ?>

                                                <form method="post" class="inline">
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                    <input type="hidden" name="user_id" value="<?= (int) $item['id'] ?>">
                                                    <input type="number" min="1" name="amount" class="input" placeholder="Valor" style="max-width:90px;">
                                                    <button type="submit" name="action" value="grant_credits" class="btn btn--gold">+ Crédito</button>
                                                    <button type="submit" name="action" value="deduct_credits" class="btn btn--ghost">- Crédito</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
