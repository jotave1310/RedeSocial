# CARVASILVA — Business Logic & Credit System Rules

## CREDIT ECONOMY RULES

### Credit Earning Events
| Event | Amount | Daily Limit | Notes |
|---|---|---|---|
| Account Created | ₢ 1.000 | one-time | Signup bonus |
| Daily Login | ₢ 50 | 1x/day | Based on last_login date |
| Create Post | ₢ 10 | 5x/day | Standard/Image posts only |
| Receive Like | ₢ 2 | unlimited | On your posts |
| Win a Bet | variable | — | Proportional payout |
| Receive Tip | variable | — | Min ₢5 sent |

### Credit Spending Events
| Event | Cost | Notes |
|---|---|---|
| Enter a Bet | user-defined amount | Min = bet.min_entry |
| Send a Tip | ₢5 minimum | Sent to post author |

### Credit Rules (Hard Rules)
1. Credits can NEVER go below ₢0
2. Credits cannot be directly transferred — only through bets or tips
3. Minimum credit balance required to enter a bet = bet.min_entry
4. Minimum tip = ₢5
5. Cannot tip your own posts
6. Cannot tip anonymous posts
7. Platform fee on bets = 5% of total pool (always deducted before payout)

---

## BET PAYOUT CALCULATION

```
Total Pool = sum of all bet entries
Fee = Total Pool × 0.05
Prize Pool = Total Pool - Fee

Winning side total = sum of entries on winning option
Loser side total = Total Pool - Winning side total

For each winner:
  payout = (user_bet / winning_side_total) × prize_pool
  net_gain = payout - user_bet  (can be negative if losers' pool small)
```

**Example:**
- Total Pool: ₢ 10.000
- Fee (5%): ₢ 500
- Prize Pool: ₢ 9.500
- Winning side: ₢ 4.000 (40%)
- User bet: ₢ 500 on winning side

```
payout = (500 / 4000) × 9500 = ₢ 1.187,50
net_gain = 1187.50 - 500 = +₢ 687,50
```

---

## MILESTONE SYSTEM

```php
const MILESTONES = [1000, 5000, 10000, 50000, 100000];

function checkMilestone(int $oldBalance, int $newBalance, int $userId): void {
    foreach (MILESTONES as $milestone) {
        if ($oldBalance < $milestone && $newBalance >= $milestone) {
            createMilestonePost($userId, $milestone);
            createNotification($userId, 'milestone', "Parabéns! Você atingiu ₢ " . number_format($milestone));
        }
    }
}
```

---

## USERNAME VALIDATION RULES

```php
function validateUsername(string $username): bool {
    // Must be: Letters_RoomCode
    // RoomCode = 1-3 digits + 1 letter (e.g., 1A, 2B, 10C)
    return (bool) preg_match('/^[A-Za-zÀ-ú]{2,30}_[1-9][0-9]?[A-Ca-c]$/', $username);
}
```

Examples:
- ✅ `Lucas_3B`
- ✅ `MariaJosé_2A`
- ✅ `Pedro_1C`
- ❌ `lucas` (no room)
- ❌ `3B_Lucas` (room before name)
- ❌ `Lucas3B` (no underscore)

---

## RATE LIMITING

### PHP Rate Limiting Implementation
```php
// Check post rate limit
function checkPostRateLimit(int $userId): bool {
    $count = DB::query(
        "SELECT COUNT(*) FROM posts 
         WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
        [$userId]
    );
    return $count < 10; // max 10 posts per hour
}

// Check anonymous post rate limit (1 per hour)
function checkAnonRateLimit(int $userId): bool {
    $row = DB::query(
        "SELECT last_post FROM anonymous_rate_limit WHERE user_id = ?",
        [$userId]
    );
    if (!$row) return true;
    return strtotime($row['last_post']) < (time() - 3600);
}
```

---

## ANONYMOUS POST RULES

1. User identity stored in `posts.user_id` (hidden from frontend)
2. Frontend receives `user: null` for anonymous posts
3. Frontend renders "👤 Anônimo Misterioso" as the author
4. No username, no avatar, no credits shown
5. Admin can see real author in admin panel
6. Rate limit: 1 anonymous post per hour per user
7. No tipping on anonymous posts
8. No reposts of anonymous posts
9. Comments on anonymous posts are also shown as anonymous (if in anon zone)

---

## NOTIFICATION TRIGGERS

```php
// When a post is liked:
createNotification($postAuthorId, 'like', "{$liker->username} curtiu seu post");

// When a comment is added:
createNotification($postAuthorId, 'comment', "{$commenter->username} comentou no seu post");

// When followed:
createNotification($followedId, 'follow', "{$follower->username} começou a te seguir");

// When bet resolved (winner):
createNotification($userId, 'bet_resolved', "Você ganhou ₢ {$payout} na aposta: {$bet->title}");

// When bet resolved (loser):
createNotification($userId, 'bet_resolved', "Você perdeu na aposta: {$bet->title}. Mais sorte na próxima!");

// When tip received:
createNotification($receiver, 'tip_received', "{$sender->username} te enviou ₢ {$amount}");

// Milestone:
createNotification($userId, 'milestone', "🏆 Meta atingida: ₢ {$milestone}!");
```

---

## BADGE AWARD LOGIC

```php
function checkAndAwardBadges(int $userId): void {
    $user = getUser($userId);
    $stats = getUserStats($userId);

    $badgeRules = [
        'estrela_escola'   => fn() => getRankPosition($userId) === 1,
        'diamante'         => fn() => $user['credits'] >= 100000,
        'apostador'        => fn() => $stats['bet_wins'] >= 10,
        'influencer'       => fn() => $stats['total_likes_received'] >= 500,
        'em_chamas'        => fn() => $stats['consecutive_days'] >= 7,
        'anonimo_misterioso' => fn() => $stats['anon_post_count'] >= 20,
    ];

    foreach ($badgeRules as $key => $condition) {
        if ($condition() && !hasBadge($userId, $key)) {
            awardBadge($userId, $key);
        }
    }
}
```

---

## DAILY LOGIN BONUS

```php
function handleDailyLoginBonus(int $userId): int {
    $user = getUser($userId);
    $lastLogin = $user['last_login'];
    $today = date('Y-m-d');
    
    if (!$lastLogin || date('Y-m-d', strtotime($lastLogin)) < $today) {
        addCredits($userId, 50, 'daily_login', 'Bônus de login diário');
        updateLastLogin($userId);
        return 50; // credits earned
    }
    return 0;
}
```

---

## CONTENT MODERATION RULES

1. Post content: strip all HTML tags, encode special chars
2. Max 280 chars for posts, 500 for comments
3. No HTML allowed in any user content (server-side strip)
4. Images: max 5MB, only jpg/png/gif/webp
5. Images auto-renamed to `{userId}_{timestamp}.{ext}`
6. Admin can delete any post, comment
7. Admin can ban users (sets is_banned = 1)
8. Banned users see error on login with ban_reason
9. Rate limit violations return HTTP 429 with retry-after header

---

## BET VALIDATION RULES

```php
function validateBet(array $data): array {
    $errors = [];
    
    if (strlen($data['title']) < 5 || strlen($data['title']) > 200) {
        $errors[] = 'Título deve ter entre 5 e 200 caracteres';
    }
    
    if (count($data['options']) < 2 || count($data['options']) > 8) {
        $errors[] = 'Aposta deve ter entre 2 e 8 opções';
    }
    
    if ($data['min_entry'] < 10) {
        $errors[] = 'Entrada mínima deve ser pelo menos ₢10';
    }
    
    $deadline = strtotime($data['deadline']);
    if ($deadline < time() + 3600) {
        $errors[] = 'Prazo deve ser pelo menos 1 hora no futuro';
    }
    
    if ($deadline > time() + (30 * 24 * 3600)) {
        $errors[] = 'Prazo não pode ser mais de 30 dias no futuro';
    }
    
    return $errors;
}
```

---

## SESSION & SECURITY

```php
// auth_check.php — include at top of every protected page
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// CSRF Token generation
function generateCsrfToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF Token validation
function validateCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Include in all forms:
// <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
```

---

## PHP HELPER FUNCTIONS

```php
// Format credits for display
function formatCredits(int $amount): string {
    return '₢ ' . number_format($amount, 0, ',', '.');
}

// Time ago in Portuguese
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'agora';
    if ($diff < 3600) return floor($diff/60) . 'min atrás';
    if ($diff < 86400) return floor($diff/3600) . 'h atrás';
    if ($diff < 604800) return floor($diff/86400) . 'd atrás';
    return date('d/m/Y', strtotime($datetime));
}

// Add credits (with transaction log)
function addCredits(int $userId, int $amount, string $type, string $desc = ''): int {
    DB::query("UPDATE users SET credits = credits + ? WHERE id = ?", [$amount, $userId]);
    $newBalance = DB::query("SELECT credits FROM users WHERE id = ?", [$userId])['credits'];
    DB::query(
        "INSERT INTO credit_transactions (user_id, amount, balance_after, type, description) VALUES (?,?,?,?,?)",
        [$userId, $amount, $newBalance, $type, $desc]
    );
    checkMilestone($newBalance - $amount, $newBalance, $userId);
    checkAndAwardBadges($userId);
    return $newBalance;
}

// Deduct credits (returns false if insufficient)
function deductCredits(int $userId, int $amount, string $type, string $desc = ''): int|false {
    $affected = DB::query(
        "UPDATE users SET credits = credits - ? WHERE id = ? AND credits >= ?",
        [$amount, $userId, $amount]
    );
    if ($affected === 0) return false; // insufficient credits
    $newBalance = DB::query("SELECT credits FROM users WHERE id = ?", [$userId])['credits'];
    DB::query(
        "INSERT INTO credit_transactions (user_id, amount, balance_after, type, description) VALUES (?,?,?,?,?)",
        [$userId, -$amount, $newBalance, $type, $desc]
    );
    return $newBalance;
}
```
