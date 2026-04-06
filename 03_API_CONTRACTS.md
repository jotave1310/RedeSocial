# CARVASILVA — API Contracts
**All endpoints use PHP. Responses are JSON. Authentication via PHP session.**

---

## AUTHENTICATION ENDPOINTS

### POST /auth/register.php
**Body (form-data):**
```
username: string (e.g., "Lucas_3B") — validated against Name_Room pattern
display_name: string
password: string (min 8 chars)
password_confirm: string
room_id: int
avatar: file (optional)
```
**Success:** `{ "success": true, "redirect": "/feed" }`  
**Error:** `{ "success": false, "error": "Username must be in Name_Room format" }`

### POST /auth/login.php
**Body:** `username, password`  
**Success:** `{ "success": true, "redirect": "/feed" }`  
**Error:** `{ "success": false, "error": "Credenciais inválidas" }`

### GET /auth/logout.php
Destroys session, redirects to `/`

---

## POSTS API — /api/post.php

### GET ?action=feed&tab=home&page=1
Returns 20 posts, paginated. Tab options: `home`, `trending`, `following`, `class`, `anonymous`  
**Response:**
```json
{
  "posts": [
    {
      "id": 1,
      "user": {
        "id": 5,
        "username": "Ana_2A",
        "display_name": "Ana Paula",
        "avatar": "/uploads/avatars/5.jpg",
        "credits": 4500,
        "room": "2A"
      },
      "content": "Texto do post aqui",
      "type": "standard",
      "image_path": null,
      "is_anonymous": false,
      "like_count": 12,
      "comment_count": 3,
      "repost_count": 1,
      "user_liked": true,
      "created_at": "2024-03-15T14:30:00Z",
      "time_ago": "2h atrás"
    }
  ],
  "has_more": true
}
```

### POST ?action=create
**Body:** `content, type, image (optional)`  
- Validates 280 char limit  
- Checks rate limit (max 5 bonus posts/day)  
- Awards ₢10 if within daily limit  
- Creates credit_transaction record  
**Response:** `{ "success": true, "post": {...}, "credits_earned": 10 }`

### POST ?action=create_anonymous
- Checks hourly rate limit  
- Sets is_anonymous = 1, stores user_id privately  
**Response:** `{ "success": true, "post": {...} }`

### POST ?action=credit_flex
- Creates a post of type `credit_flex`  
- Embeds current credit balance in formatted content  
**Response:** `{ "success": true, "post": {...} }`

### DELETE ?action=delete&id={post_id}
Only post owner or admin can delete.

---

## LIKES API — /api/like.php

### POST ?action=toggle
**Body:** `post_id`  
Toggles like. Awards ₢2 to post author when liked (not on unlike).  
**Response:** `{ "liked": true, "like_count": 13 }`

---

## COMMENTS API — /api/comment.php

### GET ?post_id={id}&page=1
Returns 20 comments for a post.

### POST ?action=create
**Body:** `post_id, content, is_anonymous (0|1)`  
Max 500 chars.  
**Response:** `{ "success": true, "comment": {...} }`

### DELETE ?action=delete&id={comment_id}

---

## BETS API — /api/bet.php

### GET ?action=list&status=open&page=1
Returns open bets with options and entry counts.

### GET ?action=single&id={bet_id}
Returns full bet detail: title, desc, options with totals, entries, countdown.

### POST ?action=create
**Body:**
```
title, description, type, min_entry, max_entry (opt), deadline
options[]: array of label strings (min 2, max 8)
```
Validates user has enough credits (none deducted at creation).  
**Response:** `{ "success": true, "bet_id": 42 }`

### POST ?action=enter
**Body:** `bet_id, option_id, amount`  
- Validates amount >= min_entry  
- Validates user credits >= amount  
- Deducts credits immediately  
- Creates bet_entry and credit_transaction  
- Updates bet.total_pool and option.total_bet  
**Response:** `{ "success": true, "new_balance": 800 }`

### POST ?action=resolve (admin/creator only)
**Body:** `bet_id, winning_option_id`  
- Calculates payout for each winner  
- Distributes credits  
- Creates credit_transactions for all winners  
- Creates bet_reaction posts for winners optionally  
- Creates notifications for all participants  
**Response:** `{ "success": true, "payouts": [...] }`

### POST ?action=cancel (admin only)
Refunds all entries, sets status to cancelled.

---

## FOLLOW API — /api/follow.php

### POST ?action=toggle
**Body:** `user_id`  
**Response:** `{ "following": true, "follower_count": 42 }`

---

## NOTIFICATIONS API — /api/notifications.php

### GET ?action=list&page=1
Returns 30 notifications, newest first.  
**Response:** `{ "notifications": [...], "unread_count": 5 }`

### POST ?action=mark_read
**Body:** `id (or "all")`

---

## MESSAGES API — /api/message.php

### GET ?action=conversations
Returns list of DM conversations with last message preview.

### GET ?action=thread&user_id={id}&page=1
Returns 50 messages in a conversation. Marks as read.

### POST ?action=send
**Body:** `receiver_id, content`  
Max 500 chars.  
**Response:** `{ "success": true, "message": {...} }`

---

## RANKING API — /api/ranking.php

### GET ?action=credits&page=1
Returns top 50 users by credits.  
**Response:**
```json
{
  "ranking": [
    {
      "position": 1,
      "user": { "id": 5, "username": "Ana_2A", "display_name": "Ana Paula", "avatar": "...", "room": "2A" },
      "credits": 95000,
      "badges": ["diamante", "apostador"]
    }
  ],
  "my_position": 15,
  "my_credits": 4500
}
```

### GET ?action=class
Returns classroom averages ranking.

### GET ?action=weekly
Returns users ranked by credits earned this week.

---

## CREDITS API — /api/credits.php

### GET ?action=history&page=1
Returns credit transaction history for logged-in user.

### POST ?action=tip
**Body:** `post_id, amount`  
Sends credits to post author.  
Min tip: ₢5. Cannot tip anonymous posts. Cannot tip own posts.  
**Response:** `{ "success": true, "new_balance": 750 }`

### GET ?action=balance
Returns current balance.  
**Response:** `{ "credits": 4500 }`

---

## ADMIN API — /admin/*.php

All admin endpoints require `role = admin` in session.

### /admin/users.php
- GET list of all users with filters (banned, room, search)
- POST ban/unban user
- POST adjust credits (grant/deduct)

### /admin/bets.php
- GET all bets
- POST resolve/cancel any bet

### /admin/posts.php
- GET all posts including anonymous with author revealed
- POST delete any post
