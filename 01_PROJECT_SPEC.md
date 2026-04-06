# CARVASILVA — Project Specification Document
**Version:** 1.0  
**Stack:** PHP · HTML · CSS · JavaScript  
**Type:** School Social Network + Virtual Betting Platform  
**Classification:** Internal School Use Only (No Real Money)

---

## 1. PROJECT OVERVIEW

**Carvasilva** is a school social platform combining a Twitter-style social network with a virtual betting system using fictional credits. The platform is designed for student interaction, fun, and healthy competition within the school environment.

### Core Pillars
1. **Social Feed** — Public posts, reactions, comments, reposts (Twitter-style)
2. **Anonymous Zone** — A dedicated section for anonymous posts
3. **BetHouse** — Virtual betting with fake credits (no real money)
4. **Credit Economy** — Credits earned, spent, ranked, and celebrated
5. **Rankings** — Public leaderboard of top credit holders

---

## 2. IDENTITY & ONBOARDING

### 2.1 Registration Rules
- Username **MUST** include the student's real name AND classroom code
- Format enforced: `FirstName_RoomCode` (e.g., `Lucas_3B`, `Ana_2A`)
- Room code is selected from a dropdown (predefined list of school rooms)
- Profile photo optional at registration, can be added later
- No email required — username + password only

### 2.2 Login
- Username + Password
- Session persists via PHP sessions
- "Remember me" cookie option

### 2.3 Profile Fields
| Field | Required | Notes |
|---|---|---|
| Username | Yes | Name + Room format enforced |
| Password | Yes | Min 8 chars |
| Room | Yes | Dropdown selection |
| Display Name | Yes | Full name |
| Avatar | No | Upload or choose from default avatars |
| Bio | No | Max 160 characters |

---

## 3. SOCIAL NETWORK MODULE

### 3.1 Post Types
| Type | Description |
|---|---|
| **Standard Post** | Public text post, max 280 chars |
| **Image Post** | Post with attached image |
| **Credit Flex** | Special post type showing current credit balance |
| **Milestone Post** | Auto-generated when user hits credit milestone |
| **Anonymous Post** | Posted without identity (separate tab) |
| **Bet Reaction** | Auto-post when user wins/loses a bet |

### 3.2 Feed Algorithm
- Chronological feed (newest first) as default
- "Trending" tab: posts with most reactions in last 24h
- "Following" tab: posts only from followed users
- "Class Feed" tab: posts only from same classroom

### 3.3 Post Interactions
- **Like** (heart icon) — standard reaction
- **Repost** — repost with optional comment
- **Comment** — threaded replies
- **Credit Tip** — send small amount of credits to post author
- **Share** — copy link (internal)

### 3.4 Credit Flex Posts
When a user creates a "Credit Flex" post, the platform auto-formats:
```
💰 [Username] está flexando seus créditos!
Saldo atual: ₢ [AMOUNT]
#CarvasilvaFlex
```
This post type has a special gold border in the feed.

### 3.5 Milestone Posts
Auto-triggered at thresholds: 1.000 · 5.000 · 10.000 · 50.000 · 100.000 credits
```
🏆 [Username] atingiu ₢ [MILESTONE]!
#Carvasilva #Meta
```

### 3.6 Anonymous Zone
- Completely separate tab/section
- Posts have NO author shown — replaced with "Anônimo 👤"
- Server stores identity for moderation only (admin can see)
- Users can post once per hour anonymously
- Same interaction buttons (like, comment) but commenter is also anonymous if in anon zone
- No Credit Tips in anonymous zone

---

## 4. BET HOUSE MODULE

### 4.1 Bet Types
| Type | Description |
|---|---|
| **Event Bet** | Admin creates an event with outcomes (e.g., "Which class wins the tournament?") |
| **Custom Bet** | Any user creates a bet challenge for others to join |
| **Head-to-Head** | Two users bet against each other directly |
| **Pool Bet** | Multiple users, winner takes most of the pool |

### 4.2 Credit System
- Every new user starts with **₢ 1.000** (1,000 credits)
- Symbol: **₢** (Cruzeiro fictício)
- Daily login bonus: **₢ 50**
- Posting bonus: **₢ 10** per post (max 5 posts/day)
- Receiving likes bonus: **₢ 2** per like received
- Credits cannot go below **₢ 0** (no debt)
- Credits cannot be transferred directly, only through bets or tips

### 4.3 Bet Flow
1. Creator defines: title, description, options, deadline, minimum bet amount
2. Other users join by choosing an option and placing credits
3. Admin (or creator if Custom Bet) resolves the bet
4. Winners receive proportional payout from losers' credits
5. Platform takes **5% fee** (fee goes to a school "prize pool" pot)

### 4.4 Bet States
`OPEN` → `CLOSED` → `RESOLVED` / `CANCELLED`

### 4.5 Bet Resolution
- **Proportional payout:** Winner's share = (user's bet / total winning side) × total pool × 0.95
- If cancelled, all credits returned in full

---

## 5. RANKING MODULE

### 5.1 Credit Ranking
- Public leaderboard, all users
- Shows: position, avatar, username, classroom, total credits
- Updated in real-time (or every 5 minutes)
- Top 3 get special crown icons: 🥇 🥈 🥉
- User can see their own position highlighted

### 5.2 Sub-Rankings
- By Classroom (best classroom average)
- Weekly gains (who earned most this week)
- Biggest bet wins (all time)

### 5.3 Badges
| Badge | Condition |
|---|---|
| 🌟 Estrela da Escola | Top 1 in ranking |
| 💎 Diamante | Reach ₢ 100.000 |
| 🎰 Apostador | Win 10 bets |
| 📢 Influencer | Receive 500 total likes |
| 🔥 Em Chamas | Post 7 days in a row |
| 🤫 Anônimo Misterioso | Post 20 anonymous posts |

---

## 6. NOTIFICATIONS MODULE

- In-app notification bell (no email)
- Notification types:
  - Someone liked your post
  - Someone commented on your post
  - Bet you joined was resolved (win/loss)
  - Someone tipped you credits
  - You reached a credit milestone
  - New bet created (if subscribed to category)
  - Someone followed you

---

## 7. DIRECT MESSAGES (DM)

- One-on-one private text chat
- No image sending in DMs (text only)
- Messages stored in DB, accessible only to the two participants
- No real-time (page refresh or AJAX polling every 10s)

---

## 8. ADMIN PANEL

### Admin Capabilities
- View all users (including anonymous post authors)
- Ban/suspend users
- Create official Event Bets
- Resolve bets
- Manage credit prizes (add/remove credits from users)
- View all posts including anonymous
- Moderate/delete any post
- Manage school rooms list
- View platform statistics

---

## 9. PAGES & ROUTES

| Route | Description |
|---|---|
| `/` | Landing page / login redirect |
| `/login` | Login page |
| `/register` | Registration page |
| `/feed` | Main social feed |
| `/feed/trending` | Trending posts |
| `/feed/class` | Classroom feed |
| `/anonymous` | Anonymous zone |
| `/bet` | BetHouse home |
| `/bet/create` | Create a bet |
| `/bet/{id}` | Single bet view |
| `/ranking` | Credit rankings |
| `/profile/{username}` | User profile |
| `/notifications` | Notifications list |
| `/messages` | DM list |
| `/messages/{username}` | DM conversation |
| `/admin` | Admin dashboard |
| `/admin/bets` | Manage bets |
| `/admin/users` | Manage users |

---

## 10. VISUAL DESIGN GUIDELINES

### Theme
- Dark mode primary (school night use friendly)
- Accent color: **Gold (#F5C518)** for credits/bets
- Secondary accent: **Electric Blue (#4B9EFF)** for social actions
- Background: **#0D0D0D** (near black)
- Surface cards: **#1A1A2E**
- Text: **#E8E8E8**

### Typography
- Headings: `Poppins` (Google Font)
- Body: `Inter` (Google Font)

### Layout
- Mobile-first responsive design
- Sidebar navigation on desktop (Twitter-style)
- Bottom tab bar on mobile
- Max content width: 600px centered (feed), 900px (admin)

### Credit Display
- Always show with ₢ symbol in gold color
- Animated counter on milestone achievements
- Credit amount in profile header always visible

---

## 11. SECURITY REQUIREMENTS

- All inputs sanitized (PHP `htmlspecialchars`, `PDO prepared statements`)
- CSRF tokens on all forms
- Password hashing: `password_hash()` with `PASSWORD_BCRYPT`
- Session validation on every protected page
- Rate limiting: max 10 posts/hour per user, max 20 actions/minute
- Anonymous posts: IP logged server-side for abuse tracking
- Admin routes protected by role check

---

## 12. DATABASE OVERVIEW

### Tables
- `users` — id, username, display_name, password_hash, room, avatar, bio, credits, role, created_at, last_login
- `posts` — id, user_id, content, type, image_path, is_anonymous, created_at
- `comments` — id, post_id, user_id, content, is_anonymous, created_at
- `likes` — id, post_id, user_id, created_at
- `follows` — id, follower_id, following_id, created_at
- `bets` — id, creator_id, title, description, status, deadline, fee_percent, created_at
- `bet_options` — id, bet_id, label
- `bet_entries` — id, bet_id, user_id, option_id, amount, created_at
- `notifications` — id, user_id, type, content, is_read, created_at
- `messages` — id, sender_id, receiver_id, content, created_at
- `credit_transactions` — id, user_id, amount, type, description, created_at
- `badges` — id, user_id, badge_key, earned_at
- `rooms` — id, code, name

---

## 13. FILE STRUCTURE

```
carvasilva/
├── index.php
├── config/
│   ├── db.php
│   └── constants.php
├── auth/
│   ├── login.php
│   ├── logout.php
│   └── register.php
├── pages/
│   ├── feed.php
│   ├── anonymous.php
│   ├── bet.php
│   ├── bet_create.php
│   ├── bet_single.php
│   ├── ranking.php
│   ├── profile.php
│   ├── notifications.php
│   ├── messages.php
│   └── admin/
│       ├── index.php
│       ├── users.php
│       └── bets.php
├── api/
│   ├── post.php
│   ├── like.php
│   ├── comment.php
│   ├── bet.php
│   ├── follow.php
│   ├── message.php
│   └── credits.php
├── includes/
│   ├── header.php
│   ├── footer.php
│   ├── navbar.php
│   ├── auth_check.php
│   └── functions.php
├── assets/
│   ├── css/
│   │   ├── main.css
│   │   ├── feed.css
│   │   ├── bet.css
│   │   └── ranking.css
│   ├── js/
│   │   ├── app.js
│   │   ├── feed.js
│   │   ├── bet.js
│   │   └── notifications.js
│   └── img/
│       └── avatars/
├── uploads/
│   └── posts/
└── sql/
    └── schema.sql
```
