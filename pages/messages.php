<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$authUser = $GLOBALS['auth_user'];
$pageTitle = 'Mensagens';
$pageDescription = 'Conversas diretas na CARVASILVA.';
$pageCss = ['/assets/css/ranking.css'];
$notificationsUnread = getUnreadNotificationsCount((int) $authUser['id']);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<div class="app-shell app-shell--center">
    <main class="page-main">
        <section class="ranking-layout" id="messagesApp">
            <article class="card">
                <header class="ranking-header">
                    <div>
                        <h1>Mensagens</h1>
                        <p>Suas conversas privadas com outros alunos.</p>
                    </div>
                </header>
            </article>

            <div id="messagesError" class="ranking-error" hidden></div>
            <section id="conversationList" class="ranking-list"></section>
        </section>
    </main>
</div>

<script>
    (() => {
        const listEl = document.getElementById("conversationList");
        const errorEl = document.getElementById("messagesError");
        const threadBase = <?= json_encode(appUrl('/pages/messages_thread.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const avatarFallback = <?= json_encode(appUrl('/assets/img/avatars/default-avatar.svg'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const messageApiBase = <?= json_encode(appUrl('/api/message.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        const html = (value) => String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");

        const setError = (message = "") => {
            errorEl.hidden = !message;
            errorEl.textContent = message;
        };

        const loadConversations = async () => {
            setError("");
            listEl.innerHTML = `<div class="card skeleton" style="height:76px;"></div>`;
            try {
                const response = await fetch(`${messageApiBase}?action=conversations`, {
                    headers: { Accept: "application/json" }
                });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(payload.error || "Falha ao carregar conversas.");
                }

                const conversations = Array.isArray(payload.data.conversations) ? payload.data.conversations : [];
                if (!conversations.length) {
                    listEl.innerHTML = `<div class="ranking-empty card">Nenhuma conversa ainda.</div>`;
                    return;
                }

                listEl.innerHTML = conversations.map((item) => {
                    const partner = item.partner || {};
                    const last = item.last_message || {};
                    return `
                        <a class="ranking-row conversation-link" href="${threadBase}?user_id=${partner.id}">
                            <div class="ranking-pos">
                                <img src="${html(partner.avatar || avatarFallback)}" alt="Avatar" class="avatar-sm">
                            </div>
                            <div class="ranking-user-meta">
                                <span class="ranking-name">${html(partner.username || "Usuario")}</span>
                                <span class="ranking-room">${last.sent_by_me ? "Voce: " : ""}${html(last.content || "Sem mensagens")} | ${html(last.time_ago || "")}</span>
                            </div>
                            <div class="ranking-value">${item.unread_count > 0 ? item.unread_count : ""}</div>
                        </a>
                    `;
                }).join("");
            } catch (error) {
                listEl.innerHTML = "";
                setError(error.message || "Erro ao carregar conversas.");
            }
        };

        loadConversations();
        setInterval(loadConversations, 15000);
    })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
