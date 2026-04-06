<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$user = requireApiAuth();
$action = sanitizeText((string) ($_GET['action'] ?? 'list'), 30);

function normalizeBetStatus(): void
{
    $stmt = db()->prepare(
        'UPDATE bets
         SET status = "closed"
         WHERE status = "open"
           AND deadline < NOW()'
    );
    $stmt->execute();
}

function fetchBetById(int $betId): ?array
{
    $stmt = db()->prepare(
        'SELECT
            b.*,
            u.username AS creator_username,
            u.display_name AS creator_display_name,
            u.avatar AS creator_avatar,
            r.code AS creator_room
         FROM bets b
         INNER JOIN users u ON u.id = b.creator_id
         INNER JOIN rooms r ON r.id = u.room_id
         WHERE b.id = :bet_id
         LIMIT 1'
    );
    $stmt->execute(['bet_id' => $betId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function fetchBetOptions(int $betId): array
{
    $stmt = db()->prepare(
        'SELECT id, label, total_bet
         FROM bet_options
         WHERE bet_id = :bet_id
         ORDER BY id ASC'
    );
    $stmt->execute(['bet_id' => $betId]);
    return $stmt->fetchAll();
}

function serializeBet(array $bet, array $options, ?array $myEntry = null): array
{
    $total = (int) ($bet['total_pool'] ?? 0);
    $deadlineTs = strtotime((string) $bet['deadline']);
    $remaining = $deadlineTs ? max(0, $deadlineTs - time()) : 0;

    $serializedOptions = [];
    foreach ($options as $option) {
        $totalBet = (int) ($option['total_bet'] ?? 0);
        $percent = $total > 0 ? round(($totalBet / $total) * 100, 1) : 0;
        $serializedOptions[] = [
            'id' => (int) $option['id'],
            'label' => (string) $option['label'],
            'total_bet' => $totalBet,
            'percentage' => $percent,
        ];
    }

    return [
        'id' => (int) $bet['id'],
        'creator_id' => (int) $bet['creator_id'],
        'creator' => [
            'username' => (string) $bet['creator_username'],
            'display_name' => (string) $bet['creator_display_name'],
            'avatar' => publicPath((string) ($bet['creator_avatar'] ?? ''), '/assets/img/avatars/default-avatar.svg'),
            'room' => (string) $bet['creator_room'],
        ],
        'title' => (string) $bet['title'],
        'description' => (string) ($bet['description'] ?? ''),
        'type' => (string) $bet['type'],
        'status' => (string) $bet['status'],
        'min_entry' => (int) $bet['min_entry'],
        'max_entry' => $bet['max_entry'] !== null ? (int) $bet['max_entry'] : null,
        'fee_percent' => (int) $bet['fee_percent'],
        'total_pool' => $total,
        'deadline' => (string) $bet['deadline'],
        'resolved_option_id' => $bet['resolved_option_id'] !== null ? (int) $bet['resolved_option_id'] : null,
        'created_at' => (string) $bet['created_at'],
        'remaining_seconds' => $remaining,
        'time_ago' => timeAgo((string) $bet['created_at']),
        'options' => $serializedOptions,
        'my_entry' => $myEntry,
    ];
}

function validateBetData(array $payload): array
{
    $errors = [];
    $title = sanitizeText((string) ($payload['title'] ?? ''), 200);
    $description = sanitizeText((string) ($payload['description'] ?? ''), 500);
    $type = sanitizeText((string) ($payload['type'] ?? 'custom'), 20);
    $minEntry = sanitizeInt($payload['min_entry'] ?? 0);
    $maxEntry = sanitizeInt($payload['max_entry'] ?? 0);
    $deadlineRaw = sanitizeText((string) ($payload['deadline'] ?? ''), 30);
    $optionsRaw = $payload['options'] ?? [];

    $allowedTypes = ['event', 'custom', 'head2head', 'pool'];
    if (!in_array($type, $allowedTypes, true)) {
        $errors[] = 'Tipo de aposta inválido.';
    }

    if (mb_strlen($title) < 5 || mb_strlen($title) > 200) {
        $errors[] = 'Título deve ter entre 5 e 200 caracteres.';
    }

    if ($minEntry < 10) {
        $errors[] = 'Entrada mínima deve ser pelo menos ₢ 10.';
    }

    if ($maxEntry > 0 && $maxEntry < $minEntry) {
        $errors[] = 'Entrada máxima não pode ser menor que a mínima.';
    }

    $deadlineTs = strtotime($deadlineRaw);
    if ($deadlineTs === false) {
        $errors[] = 'Prazo da aposta inválido.';
    } else {
        if ($deadlineTs < time() + 3600) {
            $errors[] = 'Prazo deve ser pelo menos 1 hora no futuro.';
        }
        if ($deadlineTs > time() + (30 * 24 * 3600)) {
            $errors[] = 'Prazo não pode ser maior que 30 dias no futuro.';
        }
    }

    $options = [];
    if (is_array($optionsRaw)) {
        foreach ($optionsRaw as $option) {
            $label = sanitizeText((string) $option, 150);
            if ($label !== '') {
                $options[] = $label;
            }
        }
    }

    $options = array_values(array_unique($options));
    if (count($options) < 2 || count($options) > 8) {
        $errors[] = 'Aposta deve ter entre 2 e 8 opções.';
    }

    return [
        'errors' => $errors,
        'clean' => [
            'title' => $title,
            'description' => $description,
            'type' => $type,
            'min_entry' => $minEntry,
            'max_entry' => $maxEntry > 0 ? $maxEntry : null,
            'deadline' => $deadlineTs !== false ? date('Y-m-d H:i:s', $deadlineTs) : null,
            'options' => $options,
        ],
    ];
}

try {
    normalizeBetStatus();

    if ($action === 'list') {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            jsonResponse(false, [], 'Método inválido.', 405);
        }

        $status = sanitizeText((string) ($_GET['status'] ?? 'open'), 20);
        $allowedStatuses = ['open', 'closed', 'resolved', 'cancelled', 'all'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'open';
        }

        $pagination = getPagination(1, 20, 50);
        $params = [];
        $where = '';

        if ($status !== 'all') {
            $where = 'WHERE b.status = :status';
            $params['status'] = $status;
        }

        $stmt = db()->prepare(
            'SELECT
                b.*,
                u.username AS creator_username,
                u.display_name AS creator_display_name,
                u.avatar AS creator_avatar,
                r.code AS creator_room
             FROM bets b
             INNER JOIN users u ON u.id = b.creator_id
             INNER JOIN rooms r ON r.id = u.room_id
             ' . $where . '
             ORDER BY b.created_at DESC
             LIMIT :limit OFFSET :offset'
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();
        $bets = $stmt->fetchAll();

        $serialized = [];
        foreach ($bets as $bet) {
            $betId = (int) $bet['id'];
            $options = fetchBetOptions($betId);

            $entryStmt = db()->prepare(
                'SELECT option_id, amount, payout, status
                 FROM bet_entries
                 WHERE bet_id = :bet_id
                   AND user_id = :user_id
                 LIMIT 1'
            );
            $entryStmt->execute([
                'bet_id' => $betId,
                'user_id' => (int) $user['id'],
            ]);
            $myEntry = $entryStmt->fetch();
            $serialized[] = serializeBet($bet, $options, $myEntry ?: null);
        }

        jsonResponse(true, [
            'bets' => $serialized,
            'status' => $status,
            'page' => $pagination['page'],
        ]);
    }

    if ($action === 'single') {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            jsonResponse(false, [], 'Método inválido.', 405);
        }

        $betId = sanitizeInt($_GET['id'] ?? 0);
        if ($betId <= 0) {
            jsonResponse(false, [], 'Aposta inválida.', 422);
        }

        $bet = fetchBetById($betId);
        if ($bet === null) {
            jsonResponse(false, [], 'Aposta não encontrada.', 404);
        }

        $options = fetchBetOptions($betId);

        $myEntryStmt = db()->prepare(
            'SELECT option_id, amount, payout, status, created_at
             FROM bet_entries
             WHERE bet_id = :bet_id
               AND user_id = :user_id
             LIMIT 1'
        );
        $myEntryStmt->execute([
            'bet_id' => $betId,
            'user_id' => (int) $user['id'],
        ]);
        $myEntry = $myEntryStmt->fetch();

        $entriesStmt = db()->prepare(
            'SELECT
                be.id,
                be.user_id,
                be.option_id,
                be.amount,
                be.payout,
                be.status,
                be.created_at,
                u.username
             FROM bet_entries be
             INNER JOIN users u ON u.id = be.user_id
             WHERE be.bet_id = :bet_id
             ORDER BY be.amount DESC'
        );
        $entriesStmt->execute(['bet_id' => $betId]);
        $entries = $entriesStmt->fetchAll();

        jsonResponse(true, [
            'bet' => serializeBet($bet, $options, $myEntry ?: null),
            'entries' => $entries,
        ]);
    }

    if ($action === 'create') {
        requireApiPostMethod();
        validateCsrfFromRequest();

        $payload = requestData();
        $validated = validateBetData($payload);

        if ($validated['errors'] !== []) {
            jsonResponse(false, [], implode(' ', $validated['errors']), 422);
        }

        $clean = $validated['clean'];

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $insertBetStmt = $pdo->prepare(
                'INSERT INTO bets
                    (creator_id, title, description, type, status, min_entry, max_entry, fee_percent, total_pool, deadline)
                 VALUES
                    (:creator_id, :title, :description, :type, "open", :min_entry, :max_entry, :fee_percent, 0, :deadline)'
            );
            $insertBetStmt->execute([
                'creator_id' => (int) $user['id'],
                'title' => $clean['title'],
                'description' => $clean['description'],
                'type' => $clean['type'],
                'min_entry' => (int) $clean['min_entry'],
                'max_entry' => $clean['max_entry'],
                'fee_percent' => (int) CREDIT_RULES['bet_platform_fee_percent'],
                'deadline' => (string) $clean['deadline'],
            ]);

            $betId = (int) $pdo->lastInsertId();

            $insertOptionStmt = $pdo->prepare(
                'INSERT INTO bet_options (bet_id, label, total_bet)
                 VALUES (:bet_id, :label, 0)'
            );

            foreach ($clean['options'] as $optionLabel) {
                $insertOptionStmt->execute([
                    'bet_id' => $betId,
                    'label' => $optionLabel,
                ]);
            }

            $pdo->commit();

            jsonResponse(true, [
                'bet_id' => $betId,
                'redirect' => appUrl('/bet/' . $betId),
            ]);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            jsonResponse(false, [], $exception->getMessage(), 500);
        }
    }

    if ($action === 'enter') {
        requireApiPostMethod();
        validateCsrfFromRequest();

        $betId = sanitizeInt($_POST['bet_id'] ?? 0);
        $optionId = sanitizeInt($_POST['option_id'] ?? 0);
        $amount = sanitizeInt($_POST['amount'] ?? 0);

        if ($betId <= 0 || $optionId <= 0 || $amount <= 0) {
            jsonResponse(false, [], 'Dados de aposta inválidos.', 422);
        }

        $bet = fetchBetById($betId);
        if ($bet === null) {
            jsonResponse(false, [], 'Aposta não encontrada.', 404);
        }

        if ((string) $bet['status'] !== 'open') {
            jsonResponse(false, [], 'Aposta não está aberta para entrada.', 422);
        }

        if (strtotime((string) $bet['deadline']) < time()) {
            jsonResponse(false, [], 'Aposta encerrada pelo prazo.', 422);
        }

        if ($amount < (int) $bet['min_entry']) {
            jsonResponse(false, [], 'Valor abaixo da entrada mínima.', 422);
        }

        if ($bet['max_entry'] !== null && $amount > (int) $bet['max_entry']) {
            jsonResponse(false, [], 'Valor acima da entrada máxima permitida.', 422);
        }

        $optionStmt = db()->prepare(
            'SELECT id
             FROM bet_options
             WHERE id = :option_id
               AND bet_id = :bet_id
             LIMIT 1'
        );
        $optionStmt->execute([
            'option_id' => $optionId,
            'bet_id' => $betId,
        ]);
        if ($optionStmt->fetch() === false) {
            jsonResponse(false, [], 'Opção de aposta inválida.', 422);
        }

        $entryCheckStmt = db()->prepare(
            'SELECT id
             FROM bet_entries
             WHERE bet_id = :bet_id
               AND user_id = :user_id
             LIMIT 1'
        );
        $entryCheckStmt->execute([
            'bet_id' => $betId,
            'user_id' => (int) $user['id'],
        ]);
        if ($entryCheckStmt->fetch()) {
            jsonResponse(false, [], 'Você já apostou nessa disputa.', 409);
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $newBalance = deductCredits(
                (int) $user['id'],
                $amount,
                'bet_entry',
                'Entrada em aposta #' . $betId,
                $betId
            );

            if ($newBalance === false) {
                throw new RuntimeException('Saldo insuficiente para entrar na aposta.');
            }

            $entryInsertStmt = $pdo->prepare(
                'INSERT INTO bet_entries (bet_id, user_id, option_id, amount, status)
                 VALUES (:bet_id, :user_id, :option_id, :amount, "pending")'
            );
            $entryInsertStmt->execute([
                'bet_id' => $betId,
                'user_id' => (int) $user['id'],
                'option_id' => $optionId,
                'amount' => $amount,
            ]);

            $updateOptionStmt = $pdo->prepare(
                'UPDATE bet_options
                 SET total_bet = total_bet + :amount
                 WHERE id = :option_id'
            );
            $updateOptionStmt->execute([
                'amount' => $amount,
                'option_id' => $optionId,
            ]);

            $updateBetStmt = $pdo->prepare(
                'UPDATE bets
                 SET total_pool = total_pool + :amount
                 WHERE id = :bet_id'
            );
            $updateBetStmt->execute([
                'amount' => $amount,
                'bet_id' => $betId,
            ]);

            $pdo->commit();
            upsertSessionUserSnapshot((int) $user['id']);

            if ((int) $bet['creator_id'] !== (int) $user['id']) {
                createNotification(
                    (int) $bet['creator_id'],
                    'bet_created',
                    sprintf('%s entrou na sua aposta "%s".', (string) $user['username'], (string) $bet['title']),
                    appUrl('/bet/' . $betId)
                );
            }

            jsonResponse(true, [
                'new_balance' => (int) $newBalance,
                'bet_id' => $betId,
            ]);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            jsonResponse(false, [], $exception->getMessage(), 500);
        }
    }

    if ($action === 'resolve') {
        requireApiPostMethod();
        validateCsrfFromRequest();

        $betId = sanitizeInt($_POST['bet_id'] ?? 0);
        $winningOptionId = sanitizeInt($_POST['winning_option_id'] ?? 0);

        if ($betId <= 0 || $winningOptionId <= 0) {
            jsonResponse(false, [], 'Dados de resolução inválidos.', 422);
        }

        $bet = fetchBetById($betId);
        if ($bet === null) {
            jsonResponse(false, [], 'Aposta não encontrada.', 404);
        }

        if (!in_array((string) $bet['status'], ['open', 'closed'], true)) {
            jsonResponse(false, [], 'Aposta já foi finalizada.', 422);
        }

        $isAdmin = ($user['role'] ?? 'user') === 'admin';
        $isCreator = (int) $bet['creator_id'] === (int) $user['id'];
        if (!$isAdmin && !$isCreator) {
            jsonResponse(false, [], 'Sem permissão para resolver essa aposta.', 403);
        }

        $validOptionStmt = db()->prepare(
            'SELECT id
             FROM bet_options
             WHERE id = :option_id
               AND bet_id = :bet_id
             LIMIT 1'
        );
        $validOptionStmt->execute([
            'option_id' => $winningOptionId,
            'bet_id' => $betId,
        ]);
        if ($validOptionStmt->fetch() === false) {
            jsonResponse(false, [], 'Opção vencedora inválida.', 422);
        }

        $entriesStmt = db()->prepare(
            'SELECT id, user_id, option_id, amount
             FROM bet_entries
             WHERE bet_id = :bet_id
             ORDER BY id ASC'
        );
        $entriesStmt->execute(['bet_id' => $betId]);
        $entries = $entriesStmt->fetchAll();

        $totalPool = 0;
        $winningTotal = 0;
        foreach ($entries as $entry) {
            $amount = (int) $entry['amount'];
            $totalPool += $amount;
            if ((int) $entry['option_id'] === $winningOptionId) {
                $winningTotal += $amount;
            }
        }

        $feePercent = (int) ($bet['fee_percent'] ?? CREDIT_RULES['bet_platform_fee_percent']);
        $feeAmount = (int) floor($totalPool * ($feePercent / 100));
        $prizePool = max(0, $totalPool - $feeAmount);

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $payouts = [];
            foreach ($entries as $entry) {
                $entryId = (int) $entry['id'];
                $entryUserId = (int) $entry['user_id'];
                $entryAmount = (int) $entry['amount'];
                $isWinner = (int) $entry['option_id'] === $winningOptionId;

                $status = 'lost';
                $payout = 0;

                if ($isWinner && $winningTotal > 0) {
                    $status = 'won';
                    $payout = (int) floor(($entryAmount / $winningTotal) * $prizePool);
                }

                $updateEntryStmt = $pdo->prepare(
                    'UPDATE bet_entries
                     SET status = :status, payout = :payout
                     WHERE id = :entry_id'
                );
                $updateEntryStmt->execute([
                    'status' => $status,
                    'payout' => $payout > 0 ? $payout : null,
                    'entry_id' => $entryId,
                ]);

                if ($payout > 0) {
                    addCredits(
                        $entryUserId,
                        $payout,
                        'bet_win',
                        'Vitória na aposta #' . $betId,
                        $betId
                    );
                }

                $entryUser = getUserById($entryUserId);
                if ($entryUser !== null) {
                    if ($status === 'won') {
                        createNotification(
                            $entryUserId,
                            'bet_resolved',
                            sprintf('Você ganhou %s na aposta: %s', formatCredits($payout), (string) $bet['title']),
                            appUrl('/bet/' . $betId)
                        );

                        $reactionContent = sprintf(
                            '%s ganhou %s na aposta "%s".',
                            (string) $entryUser['username'],
                            formatCredits($payout),
                            (string) $bet['title']
                        );
                        $postStmt = $pdo->prepare(
                            'INSERT INTO posts (user_id, content, type, is_anonymous)
                             VALUES (:user_id, :content, "bet_reaction", 0)'
                        );
                        $postStmt->execute([
                            'user_id' => $entryUserId,
                            'content' => $reactionContent,
                        ]);
                    } else {
                        createNotification(
                            $entryUserId,
                            'bet_resolved',
                            sprintf('Você perdeu na aposta: %s. Mais sorte na próxima!', (string) $bet['title']),
                            appUrl('/bet/' . $betId)
                        );
                    }
                }

                $payouts[] = [
                    'entry_id' => $entryId,
                    'user_id' => $entryUserId,
                    'status' => $status,
                    'amount' => $entryAmount,
                    'payout' => $payout,
                ];
            }

            $updateBetStmt = $pdo->prepare(
                'UPDATE bets
                 SET status = "resolved",
                     total_pool = :total_pool,
                     resolved_option_id = :resolved_option_id,
                     resolved_at = NOW()
                 WHERE id = :bet_id'
            );
            $updateBetStmt->execute([
                'total_pool' => $totalPool,
                'resolved_option_id' => $winningOptionId,
                'bet_id' => $betId,
            ]);

            $pdo->commit();

            jsonResponse(true, [
                'bet_id' => $betId,
                'winning_option_id' => $winningOptionId,
                'total_pool' => $totalPool,
                'fee_amount' => $feeAmount,
                'prize_pool' => $prizePool,
                'payouts' => $payouts,
            ]);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            jsonResponse(false, [], $exception->getMessage(), 500);
        }
    }

    if ($action === 'cancel') {
        requireApiPostMethod();
        validateCsrfFromRequest();
        requireApiAdmin($user);

        $betId = sanitizeInt($_POST['bet_id'] ?? 0);
        if ($betId <= 0) {
            jsonResponse(false, [], 'Aposta inválida.', 422);
        }

        $bet = fetchBetById($betId);
        if ($bet === null) {
            jsonResponse(false, [], 'Aposta não encontrada.', 404);
        }

        if (!in_array((string) $bet['status'], ['open', 'closed'], true)) {
            jsonResponse(false, [], 'Aposta não pode ser cancelada.', 422);
        }

        $entriesStmt = db()->prepare(
            'SELECT id, user_id, amount
             FROM bet_entries
             WHERE bet_id = :bet_id'
        );
        $entriesStmt->execute(['bet_id' => $betId]);
        $entries = $entriesStmt->fetchAll();

        $pdo = db();
        $pdo->beginTransaction();

        try {
            foreach ($entries as $entry) {
                $entryId = (int) $entry['id'];
                $entryUserId = (int) $entry['user_id'];
                $amount = (int) $entry['amount'];

                addCredits(
                    $entryUserId,
                    $amount,
                    'bet_refund',
                    'Reembolso da aposta cancelada #' . $betId,
                    $betId
                );

                $updateEntryStmt = $pdo->prepare(
                    'UPDATE bet_entries
                     SET status = "refunded", payout = :payout
                     WHERE id = :entry_id'
                );
                $updateEntryStmt->execute([
                    'payout' => $amount,
                    'entry_id' => $entryId,
                ]);

                createNotification(
                    $entryUserId,
                    'bet_resolved',
                    sprintf('A aposta "%s" foi cancelada. Valor devolvido.', (string) $bet['title']),
                    appUrl('/bet/' . $betId)
                );
            }

            $updateBetStmt = $pdo->prepare(
                'UPDATE bets
                 SET status = "cancelled",
                     resolved_at = NOW()
                 WHERE id = :bet_id'
            );
            $updateBetStmt->execute(['bet_id' => $betId]);

            $pdo->commit();

            jsonResponse(true, [
                'cancelled' => true,
                'bet_id' => $betId,
                'refunded_entries' => count($entries),
            ]);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            jsonResponse(false, [], $exception->getMessage(), 500);
        }
    }

    jsonResponse(false, [], 'Ação inválida.', 400);
} catch (Throwable $exception) {
    jsonResponse(false, [], $exception->getMessage(), 500);
}
