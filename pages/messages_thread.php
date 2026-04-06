<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$authUser = $GLOBALS['auth_user'];
$partnerId = sanitizeInt($_GET['user_id'] ?? 0);
if ($partnerId <= 0 || $partnerId === (int) $authUser['id']) {
    redirect('/messages');
}

$partner = getUserById($partnerId);
if ($partner === null) {
    redirect('/messages');
}

$pageTitle = 'Conversa com ' . $partner['username'];
$pageDescription = 'Thread de mensagem privada.';
$pageCss = ['/assets/css/ranking.css'];
$notificationsUnread = getUnreadNotificationsCount((int) $authUser['id']);
$csrfToken = generateCsrfToken();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<div class="app-shell app-shell--center">
    <main class="page-main">
        <section id="threadApp" class="ranking-layout" data-partner-id="<?= $partnerId ?>" data-csrf="<?= e($csrfToken) ?>">
            <article class="card">
                <header class="ranking-header">
                    <div class="inline">
                        <img src="<?= e(publicPath((string) ($partner['avatar'] ?? ''), '/assets/img/avatars/default-avatar.svg')) ?>" alt="Avatar de <?= e((string) $partner['username']) ?>" class="avatar-sm">
                        <div>
                            <h1 style="margin:0;"><?= e((string) $partner['username']) ?></h1>
                            <p style="margin:2px 0 0;color:var(--text-secondary);font-size:.84rem;">
                                <?= e((string) $partner['display_name']) ?>
                            </p>
                        </div>
                    </div>

                    <a href="<?= e(appUrl('/messages')) ?>" class="btn btn--ghost">Voltar</a>
                </header>
            </article>

            <div id="threadError" class="ranking-error" hidden></div>

            <section id="threadMessages" class="ranking-list thread-messages"></section>

            <article class="card">
                <form id="threadForm" class="stack">
                    <input type="hidden" name="receiver_id" value="<?= $partnerId ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <label for="threadContent">Mensagem</label>
                    <textarea id="threadContent" class="input" name="content" maxlength="500" rows="3" placeholder="Escreva sua mensagem..." required></textarea>
                    <div style="display:flex;justify-content:flex-end;">
                        <button type="submit" class="btn btn--gold">Enviar</button>
                    </div>
                </form>
            </article>
        </section>
    </main>
</div>

<script>
    (() => {
        const app = document.getElementById("threadApp");
        const partnerId = Number(app.dataset.partnerId || "0");
        const csrfToken = app.dataset.csrf || "";
        const messageApiBase = <?= json_encode(appUrl('/api/message.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const messagesEl = document.getElementById("threadMessages");
        const errorEl = document.getElementById("threadError");
        const form = document.getElementById("threadForm");
        const contentEl = document.getElementById("threadContent");

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

        const renderMessages = (messages) => {
            if (!messages.length) {
                messagesEl.innerHTML = `<div class="ranking-empty card">Nenhuma mensagem ainda.</div>`;
                return;
            }

            messagesEl.innerHTML = messages.map((item) => `
                <article class="profile-entry thread-message ${item.mine ? "thread-message--mine" : ""}">
                    <h3 class="profile-entry-title" style="margin:0 0 5px;font-size:.88rem;">${item.mine ? "Voce" : "Contato"}</h3>
                    <div style="white-space:pre-wrap;word-break:break-word;font-size:.92rem;">${html(item.content)}</div>
                    <div class="profile-entry-meta" style="margin-top:5px;">${html(item.time_ago || "")}</div>
                </article>
            `).join("");

            messagesEl.scrollTop = messagesEl.scrollHeight;
        };

        const loadThread = async () => {
            try {
                const response = await fetch(`${messageApiBase}?action=thread&user_id=${encodeURIComponent(partnerId)}&page=1`, {
                    headers: { Accept: "application/json" }
                });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(payload.error || "Erro ao carregar mensagens.");
                }

                const messages = Array.isArray(payload.data.messages) ? payload.data.messages : [];
                renderMessages(messages);
                setError("");
            } catch (error) {
                setError(error.message || "Falha ao carregar thread.");
            }
        };

        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            setError("");

            const text = contentEl.value.trim();
            if (!text) return;

            const submitBtn = form.querySelector("button[type='submit']");
            if (submitBtn) submitBtn.disabled = true;

            try {
                const formData = new FormData(form);
                formData.set("csrf_token", csrfToken);
                formData.set("receiver_id", String(partnerId));

                const response = await fetch(`${messageApiBase}?action=send`, {
                    method: "POST",
                    headers: { Accept: "application/json" },
                    body: formData
                });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(payload.error || "Nao foi possivel enviar.");
                }

                contentEl.value = "";
                await loadThread();
            } catch (error) {
                setError(error.message || "Erro ao enviar mensagem.");
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });

        loadThread();
        setInterval(loadThread, 10000);
    })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
