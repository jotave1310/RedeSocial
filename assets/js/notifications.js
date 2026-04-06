(function () {
    "use strict";

    const app = document.getElementById("notificationsApp");
    const csrfToken = app ? (app.dataset.csrf || "") : "";
    const listEl = document.getElementById("notificationsList");
    const errorEl = document.getElementById("notificationsError");
    const unreadMeta = document.getElementById("notificationsUnreadMeta");

    const html = (value) =>
        String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");

    const parseResponse = async (response) => {
        const payload = await response.json();
        if (!response.ok || !payload.success) {
            throw new Error(payload.error || "Erro na requisicao.");
        }
        return payload.data || {};
    };

    const updateGlobalBadges = (count) => {
        document.querySelectorAll("[data-notification-badge]").forEach((badge) => {
            if (count > 0) {
                badge.hidden = false;
                badge.textContent = count > 99 ? "99+" : String(count);
            } else {
                badge.hidden = true;
                badge.textContent = "";
            }
        });

        document.querySelectorAll("[data-notification-dot]").forEach((dot) => {
            dot.hidden = count <= 0;
        });
    };

    const setError = (message = "") => {
        if (!errorEl) return;
        errorEl.hidden = !message;
        errorEl.textContent = message;
    };

    const renderNotifications = (items) => {
        if (!listEl) return;

        if (!items.length) {
            listEl.innerHTML = `<div class="ranking-empty card">Nenhuma notificacao pendente.</div>`;
            return;
        }

        listEl.innerHTML = items.map((item) => `
            <article class="profile-entry ${item.is_read ? "" : "is-unread"}" data-id="${item.id}">
                <h3 class="profile-entry-title">${html(item.content)}</h3>
                <div class="profile-entry-meta">
                    ${html(item.type)} | ${html(item.time_ago)}
                </div>
                <div style="margin-top:8px;display:flex;gap:8px;justify-content:flex-end;">
                    ${item.link ? `<a class="btn btn--ghost" href="${html(item.link)}">Abrir</a>` : ""}
                    ${item.is_read ? "" : `<button class="btn btn--gold" data-action="mark-one" data-id="${item.id}">Marcar como lida</button>`}
                </div>
            </article>
        `).join("");
    };

    const loadNotifications = async () => {
        if (!app) return;
        setError("");
        try {
            const response = await fetch("/api/notifications.php?action=list&page=1", {
                headers: { Accept: "application/json" }
            });
            const data = await parseResponse(response);
            const notifications = Array.isArray(data.notifications) ? data.notifications : [];
            renderNotifications(notifications);

            const unread = Number(data.unread_count || 0);
            updateGlobalBadges(unread);
            if (unreadMeta) {
                unreadMeta.textContent = unread > 0 ? `${unread} nao lida(s)` : "Tudo em dia";
            }
        } catch (error) {
            setError(error.message || "Falha ao carregar notificacoes.");
        }
    };

    const markRead = async (id) => {
        const formData = new FormData();
        formData.set("id", id);
        formData.set("csrf_token", csrfToken);

        const response = await fetch("/api/notifications.php?action=mark_read", {
            method: "POST",
            headers: { Accept: "application/json" },
            body: formData
        });
        const data = await parseResponse(response);
        updateGlobalBadges(Number(data.unread_count || 0));
        return data;
    };

    const pollUnread = async () => {
        try {
            const response = await fetch("/api/notifications.php?action=list&page=1&limit=1", {
                headers: { Accept: "application/json" }
            });
            const data = await parseResponse(response);
            const unread = Number(data.unread_count || 0);
            updateGlobalBadges(unread);
            if (unreadMeta) {
                unreadMeta.textContent = unread > 0 ? `${unread} nao lida(s)` : "Tudo em dia";
            }
        } catch (_) {
            // Polling silencioso.
        }
    };

    if (app) {
        document.getElementById("markAllReadBtn")?.addEventListener("click", async () => {
            try {
                await markRead("all");
                await loadNotifications();
            } catch (error) {
                setError(error.message || "Nao foi possivel marcar todas como lidas.");
            }
        });

        listEl?.addEventListener("click", async (event) => {
            const button = event.target.closest("[data-action='mark-one']");
            if (!button) return;
            const id = Number(button.dataset.id || "0");
            if (!id) return;

            try {
                button.disabled = true;
                await markRead(String(id));
                await loadNotifications();
            } catch (error) {
                setError(error.message || "Falha ao marcar notificacao.");
            } finally {
                button.disabled = false;
            }
        });

        loadNotifications();
    }

    pollUnread();
    setInterval(pollUnread, 30000);
})();
