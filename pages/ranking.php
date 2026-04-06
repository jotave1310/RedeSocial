<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$authUser = $GLOBALS['auth_user'];
$pageTitle = 'Ranking';
$pageDescription = 'Leaderboard de creditos da CARVASILVA.';
$pageCss = ['/assets/css/ranking.css'];
$notificationsUnread = getUnreadNotificationsCount((int) $authUser['id']);
$csrfToken = generateCsrfToken();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<div class="app-shell app-shell--center">
    <main class="page-main">
        <section id="rankingApp" class="ranking-layout">
            <article class="card">
                <header class="ranking-header">
                    <div>
                        <h1>Ranking de creditos</h1>
                        <p>Veja quem esta no topo da comunidade CARVASILVA.</p>
                    </div>
                    <span class="badge badge--gold">Seu saldo: <?= e(formatCredits((int) $authUser['credits'])) ?></span>
                </header>
            </article>

            <section class="card">
                <nav class="top-tabs" aria-label="Abas de ranking">
                    <button class="tab-btn is-active" data-rank-tab="credits" aria-selected="true">Geral</button>
                    <button class="tab-btn" data-rank-tab="class" aria-selected="false">Turmas</button>
                    <button class="tab-btn" data-rank-tab="weekly" aria-selected="false">Semanal</button>
                    <button class="tab-btn" data-rank-tab="bets" aria-selected="false">Apostas</button>
                </nav>
            </section>

            <div id="rankingError" class="ranking-error" hidden></div>
            <article id="rankingMeta" class="card" hidden></article>
            <section id="rankingList" class="ranking-list"></section>
        </section>
    </main>
</div>

<script>
    (() => {
        const listEl = document.getElementById("rankingList");
        const errorEl = document.getElementById("rankingError");
        const metaEl = document.getElementById("rankingMeta");
        const myUserId = <?= (int) $authUser['id'] ?>;
        const avatarFallback = <?= json_encode(appUrl('/assets/img/avatars/default-avatar.svg'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const rankingApiBase = <?= json_encode(appUrl('/api/ranking.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        let currentTab = "credits";

        const html = (value) => String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");

        const formatCredits = (value) => `\u20A2 ${Number(value || 0).toLocaleString("pt-BR")}`;
        const topIcon = (position) => {
            if (position === 1) return '<i class="fa-solid fa-crown ranking-medal" style="color:#f5c518;" aria-hidden="true"></i>';
            if (position === 2) return '<i class="fa-solid fa-medal ranking-medal" style="color:#c5cfde;" aria-hidden="true"></i>';
            if (position === 3) return '<i class="fa-solid fa-medal ranking-medal" style="color:#cd9c62;" aria-hidden="true"></i>';
            return String(position);
        };

        const setError = (message = "") => {
            errorEl.hidden = !message;
            errorEl.textContent = message;
        };

        const parse = async (response) => {
            const payload = await response.json();
            if (!response.ok || !payload.success) {
                throw new Error(payload.error || "Erro ao carregar ranking.");
            }
            return payload.data || {};
        };

        const renderCredits = (data) => {
            const rows = Array.isArray(data.ranking) ? data.ranking : [];
            if (!rows.length) {
                listEl.innerHTML = `<div class="ranking-empty card">Sem dados para exibir.</div>`;
                return;
            }

            metaEl.hidden = false;
            metaEl.innerHTML = `
                <strong>Sua posicao:</strong> ${data.my_position || "-"} |
                <strong>Seus creditos:</strong> ${formatCredits(data.my_credits || 0)}
            `;

            listEl.innerHTML = rows.map((row) => {
                const user = row.user || {};
                const me = Number(user.id || 0) === myUserId;
                return `
                    <article class="ranking-row ${me ? "is-me" : ""} ${row.position === 1 ? "is-top-1" : ""} ${row.position === 2 ? "is-top-2" : ""} ${row.position === 3 ? "is-top-3" : ""}">
                        <div class="ranking-pos">${topIcon(row.position)}</div>
                        <div class="ranking-user">
                            <img src="${html(user.avatar || avatarFallback)}" alt="Avatar" class="avatar-sm">
                            <div class="ranking-user-meta">
                                <span class="ranking-name">${html(user.username || "Usuario")}</span>
                                <span class="ranking-room">Turma ${html(user.room || "-")}</span>
                            </div>
                        </div>
                        <div class="ranking-value">${formatCredits(row.credits || 0)}</div>
                    </article>
                `;
            }).join("");
        };

        const renderClass = (data) => {
            const rows = Array.isArray(data.ranking) ? data.ranking : [];
            metaEl.hidden = false;
            metaEl.innerHTML = `
                <strong>Sua turma:</strong> ${html(data.my_room || "-")} |
                <strong>Posicao:</strong> ${data.my_room_position || "-"}
            `;

            if (!rows.length) {
                listEl.innerHTML = `<div class="ranking-empty card">Sem dados por turma.</div>`;
                return;
            }

            listEl.innerHTML = rows.map((row) => `
                <article class="ranking-row ${row.room === data.my_room ? "is-me" : ""}">
                    <div class="ranking-pos">${topIcon(row.position)}</div>
                    <div class="ranking-user-meta">
                        <span class="ranking-name">${html(row.room_name)} (${html(row.room)})</span>
                        <span class="ranking-room">${row.users_count} aluno(s)</span>
                    </div>
                    <div class="ranking-value">${formatCredits(row.average_credits)}</div>
                </article>
            `).join("");
        };

        const renderWeekly = (data) => {
            const rows = Array.isArray(data.ranking) ? data.ranking : [];
            metaEl.hidden = false;
            metaEl.innerHTML = `
                <strong>Sua posicao semanal:</strong> ${data.my_position || "-"} |
                <strong>Seu ganho:</strong> ${formatCredits(data.my_weekly_gain || 0)}
            `;

            listEl.innerHTML = rows.length ? rows.map((row) => `
                <article class="ranking-row ${Number(row.user.id || 0) === myUserId ? "is-me" : ""}">
                    <div class="ranking-pos">${topIcon(row.position)}</div>
                    <div class="ranking-user">
                        <img src="${html(row.user.avatar || avatarFallback)}" alt="Avatar" class="avatar-sm">
                        <div class="ranking-user-meta">
                            <span class="ranking-name">${html(row.user.username)}</span>
                            <span class="ranking-room">Turma ${html(row.user.room)}</span>
                        </div>
                    </div>
                    <div class="ranking-value">+${formatCredits(row.weekly_gain)}</div>
                </article>
            `).join("") : `<div class="ranking-empty card">Sem ganhos registrados nesta semana.</div>`;
        };

        const renderBets = (data) => {
            const rows = Array.isArray(data.ranking) ? data.ranking : [];
            metaEl.hidden = false;
            metaEl.innerHTML = `
                <strong>Sua posicao em apostas:</strong> ${data.my_position || "-"} |
                <strong>Total vencido:</strong> ${formatCredits(data.my_total_wins || 0)}
            `;

            listEl.innerHTML = rows.length ? rows.map((row) => `
                <article class="ranking-row ${Number(row.user.id || 0) === myUserId ? "is-me" : ""}">
                    <div class="ranking-pos">${topIcon(row.position)}</div>
                    <div class="ranking-user">
                        <img src="${html(row.user.avatar || avatarFallback)}" alt="Avatar" class="avatar-sm">
                        <div class="ranking-user-meta">
                            <span class="ranking-name">${html(row.user.username)}</span>
                            <span class="ranking-room">Melhor vitoria: ${formatCredits(row.best_win || 0)}</span>
                        </div>
                    </div>
                    <div class="ranking-value">${formatCredits(row.total_wins || 0)}</div>
                </article>
            `).join("") : `<div class="ranking-empty card">Sem vitorias de apostas registradas.</div>`;
        };

        const loadTab = async (tab) => {
            setError("");
            listEl.innerHTML = `<div class="card skeleton" style="height:72px;"></div>`;
            try {
                const response = await fetch(`${rankingApiBase}?action=${encodeURIComponent(tab)}`, {
                    headers: { Accept: "application/json" }
                });
                const data = await parse(response);

                if (tab === "credits") renderCredits(data);
                if (tab === "class") renderClass(data);
                if (tab === "weekly") renderWeekly(data);
                if (tab === "bets") renderBets(data);
            } catch (error) {
                listEl.innerHTML = "";
                metaEl.hidden = true;
                setError(error.message || "Falha ao carregar ranking.");
            }
        };

        document.querySelectorAll("[data-rank-tab]").forEach((btn) => {
            btn.addEventListener("click", async () => {
                const next = btn.dataset.rankTab || "credits";
                if (next === currentTab) return;
                currentTab = next;

                document.querySelectorAll("[data-rank-tab]").forEach((item) => {
                    item.classList.remove("is-active");
                    item.setAttribute("aria-selected", "false");
                });
                btn.classList.add("is-active");
                btn.setAttribute("aria-selected", "true");

                await loadTab(next);
            });
        });

        loadTab("credits");
    })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
