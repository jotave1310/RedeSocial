<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$user = requireApiAuth();
$action = sanitizeText((string) ($_GET['action'] ?? 'credits'), 20);
$pagination = getPagination(1, 50, 100);

function splitBadges(?string $badgesRaw): array
{
    if ($badgesRaw === null || trim($badgesRaw) === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $badgesRaw))));
}

if ($action === 'credits') {
    $stmt = db()->prepare(
        'SELECT
            u.id,
            u.username,
            u.display_name,
            u.avatar,
            u.credits,
            r.code AS room,
            bg.badges
         FROM users u
         INNER JOIN rooms r ON r.id = u.room_id
         LEFT JOIN (
             SELECT user_id, GROUP_CONCAT(badge_key ORDER BY earned_at SEPARATOR ",") AS badges
             FROM badges
             GROUP BY user_id
         ) bg ON bg.user_id = u.id
         ORDER BY u.credits DESC, u.id ASC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $positionOffset = $pagination['offset'];
    $ranking = [];
    foreach ($rows as $index => $row) {
        $ranking[] = [
            'position' => $positionOffset + $index + 1,
            'user' => [
                'id' => (int) $row['id'],
                'username' => (string) $row['username'],
                'display_name' => (string) $row['display_name'],
                'avatar' => publicPath((string) ($row['avatar'] ?? ''), '/assets/img/avatars/default-avatar.svg'),
                'room' => (string) $row['room'],
            ],
            'credits' => (int) $row['credits'],
            'badges' => splitBadges($row['badges'] ?? null),
        ];
    }

    $myCreditsStmt = db()->prepare('SELECT credits FROM users WHERE id = :id LIMIT 1');
    $myCreditsStmt->execute(['id' => (int) $user['id']]);
    $myCreditsRow = $myCreditsStmt->fetch();
    $myCredits = (int) ($myCreditsRow['credits'] ?? 0);

    $myPositionStmt = db()->prepare(
        'SELECT COUNT(*) + 1 AS position
         FROM users
         WHERE credits > :credits'
    );
    $myPositionStmt->execute(['credits' => $myCredits]);
    $myPosition = (int) (($myPositionStmt->fetch()['position'] ?? 0));

    jsonResponse(true, [
        'ranking' => $ranking,
        'my_position' => $myPosition,
        'my_credits' => $myCredits,
    ]);
}

if ($action === 'class') {
    $stmt = db()->query(
        'SELECT
            r.id,
            r.code,
            r.name,
            COUNT(u.id) AS users_count,
            COALESCE(AVG(u.credits), 0) AS average_credits,
            COALESCE(SUM(u.credits), 0) AS total_credits
         FROM rooms r
         LEFT JOIN users u ON u.room_id = r.id
         GROUP BY r.id, r.code, r.name
         ORDER BY average_credits DESC, total_credits DESC'
    );
    $rows = $stmt->fetchAll();

    $ranking = [];
    $myRoomPosition = null;
    foreach ($rows as $index => $row) {
        $position = $index + 1;
        if ((string) $row['code'] === (string) $user['room_code']) {
            $myRoomPosition = $position;
        }

        $ranking[] = [
            'position' => $position,
            'room_id' => (int) $row['id'],
            'room' => (string) $row['code'],
            'room_name' => (string) $row['name'],
            'users_count' => (int) $row['users_count'],
            'average_credits' => (int) round((float) $row['average_credits']),
            'total_credits' => (int) $row['total_credits'],
        ];
    }

    jsonResponse(true, [
        'ranking' => $ranking,
        'my_room' => (string) $user['room_code'],
        'my_room_position' => $myRoomPosition,
    ]);
}

if ($action === 'weekly') {
    $stmt = db()->prepare(
        'SELECT
            u.id,
            u.username,
            u.display_name,
            u.avatar,
            r.code AS room,
            COALESCE(SUM(CASE WHEN ct.amount > 0 THEN ct.amount ELSE 0 END), 0) AS weekly_gain
         FROM users u
         INNER JOIN rooms r ON r.id = u.room_id
         LEFT JOIN credit_transactions ct
            ON ct.user_id = u.id
           AND YEARWEEK(CONVERT_TZ(ct.created_at, "+00:00", "-03:00"), 1) =
               YEARWEEK(CONVERT_TZ(NOW(), "+00:00", "-03:00"), 1)
         GROUP BY u.id, u.username, u.display_name, u.avatar, r.code
         ORDER BY weekly_gain DESC, u.id ASC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $ranking = [];
    foreach ($rows as $index => $row) {
        $ranking[] = [
            'position' => $pagination['offset'] + $index + 1,
            'user' => [
                'id' => (int) $row['id'],
                'username' => (string) $row['username'],
                'display_name' => (string) $row['display_name'],
                'avatar' => publicPath((string) ($row['avatar'] ?? ''), '/assets/img/avatars/default-avatar.svg'),
                'room' => (string) $row['room'],
            ],
            'weekly_gain' => (int) $row['weekly_gain'],
        ];
    }

    $myWeeklyStmt = db()->prepare(
        'SELECT COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) AS gain
         FROM credit_transactions
         WHERE user_id = :user_id
           AND YEARWEEK(CONVERT_TZ(created_at, "+00:00", "-03:00"), 1) =
               YEARWEEK(CONVERT_TZ(NOW(), "+00:00", "-03:00"), 1)'
    );
    $myWeeklyStmt->execute(['user_id' => (int) $user['id']]);
    $myGain = (int) (($myWeeklyStmt->fetch()['gain'] ?? 0));

    $myPositionStmt = db()->prepare(
        'SELECT COUNT(*) + 1 AS position
         FROM (
             SELECT user_id, COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) AS gain
             FROM credit_transactions
             WHERE YEARWEEK(CONVERT_TZ(created_at, "+00:00", "-03:00"), 1) =
                   YEARWEEK(CONVERT_TZ(NOW(), "+00:00", "-03:00"), 1)
             GROUP BY user_id
         ) x
         WHERE x.gain > :my_gain'
    );
    $myPositionStmt->execute(['my_gain' => $myGain]);
    $myPosition = (int) (($myPositionStmt->fetch()['position'] ?? 1));

    jsonResponse(true, [
        'ranking' => $ranking,
        'my_position' => $myPosition,
        'my_weekly_gain' => $myGain,
    ]);
}

if ($action === 'bets') {
    $stmt = db()->prepare(
        'SELECT
            u.id,
            u.username,
            u.display_name,
            u.avatar,
            r.code AS room,
            COALESCE(SUM(CASE WHEN be.status = "won" THEN be.payout ELSE 0 END), 0) AS total_wins,
            COALESCE(MAX(CASE WHEN be.status = "won" THEN be.payout ELSE 0 END), 0) AS best_win
         FROM users u
         INNER JOIN rooms r ON r.id = u.room_id
         LEFT JOIN bet_entries be ON be.user_id = u.id
         GROUP BY u.id, u.username, u.display_name, u.avatar, r.code
         ORDER BY total_wins DESC, best_win DESC, u.id ASC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $ranking = [];
    foreach ($rows as $index => $row) {
        $ranking[] = [
            'position' => $pagination['offset'] + $index + 1,
            'user' => [
                'id' => (int) $row['id'],
                'username' => (string) $row['username'],
                'display_name' => (string) $row['display_name'],
                'avatar' => publicPath((string) ($row['avatar'] ?? ''), '/assets/img/avatars/default-avatar.svg'),
                'room' => (string) $row['room'],
            ],
            'total_wins' => (int) $row['total_wins'],
            'best_win' => (int) $row['best_win'],
        ];
    }

    $myScoreStmt = db()->prepare(
        'SELECT COALESCE(SUM(CASE WHEN status = "won" THEN payout ELSE 0 END), 0) AS total_wins
         FROM bet_entries
         WHERE user_id = :user_id'
    );
    $myScoreStmt->execute(['user_id' => (int) $user['id']]);
    $myWins = (int) (($myScoreStmt->fetch()['total_wins'] ?? 0));

    $myPositionStmt = db()->prepare(
        'SELECT COUNT(*) + 1 AS position
         FROM (
             SELECT user_id, COALESCE(SUM(CASE WHEN status = "won" THEN payout ELSE 0 END), 0) AS total_wins
             FROM bet_entries
             GROUP BY user_id
         ) x
         WHERE x.total_wins > :my_wins'
    );
    $myPositionStmt->execute(['my_wins' => $myWins]);
    $myPosition = (int) (($myPositionStmt->fetch()['position'] ?? 1));

    jsonResponse(true, [
        'ranking' => $ranking,
        'my_position' => $myPosition,
        'my_total_wins' => $myWins,
    ]);
}

jsonResponse(false, [], 'Ação inválida.', 400);
