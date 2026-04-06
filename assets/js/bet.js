(function () {
    "use strict";

    const app = document.getElementById("betApp");
    if (!app) return;

    const appUrl = (path) => {
        if (window.Carvasilva && typeof window.Carvasilva.url === "function") {
            return window.Carvasilva.url(path);
        }
        return path;
    };

    const updateBalance = (nextBalance, options = {}) => {
        if (window.Carvasilva && typeof window.Carvasilva.updateBalanceDisplay === "function") {
            return window.Carvasilva.updateBalanceDisplay(nextBalance, options);
        }

        const fallback = Math.floor(Number(nextBalance) || 0);
        document.querySelectorAll("[data-credits]").forEach((pill) => {
            pill.setAttribute("data-credits", String(fallback));
            const node = pill.querySelector(".credits-value") || pill;
            node.textContent = `\u20A2 ${fallback.toLocaleString("pt-BR")}`;
        });
        return fallback;
    };

    const notifySuccess = (message) => {
        if (window.Carvasilva && typeof window.Carvasilva.notifySuccess === "function") {
            window.Carvasilva.notifySuccess(message);
            return;
        }
        window.alert(message);
    };

    const notifyError = (message) => {
        if (window.Carvasilva && typeof window.Carvasilva.notifyError === "function") {
            window.Carvasilva.notifyError(message);
            return;
        }
        window.alert(message);
    };

    const csrfToken = app.dataset.csrf || "";
    const initialStatus = app.dataset.initialStatus || "open";
    const singleBetId = Number(app.dataset.betId || "0");
    const listMode = app.dataset.mode === "list";
    const singleMode = app.dataset.mode === "single";

    const listEl = document.getElementById("betList");
    const errorEl = document.getElementById("betError");
    const loadingEl = document.getElementById("betLoading");
    const modal = document.getElementById("betModal");
    const modalForm = document.getElementById("betEntryForm");

    const state = {
        status: initialStatus,
        page: 1,
        loading: false,
        pollTimer: null,
    };

    const html = (value) =>
        String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");

    const formatCredits = (value) => `\u20A2 ${Number(value || 0).toLocaleString("pt-BR")}`;

    const parseResponse = async (response) => {
        const payload = await response.json();
        if (!response.ok || !payload.success) {
            throw new Error(payload.error || "Erro inesperado.");
        }
        return payload.data || {};
    };

    const setError = (message = "") => {
        if (!errorEl) return;
        errorEl.hidden = !message;
        errorEl.textContent = message;
    };

    const setLoading = (loading) => {
        state.loading = loading;
        if (loadingEl) loadingEl.hidden = !loading;
    };

    const statusClass = (status) => {
        switch (status) {
            case "open": return "bet-status--open";
            case "closed": return "bet-status--closed";
            case "resolved": return "bet-status--resolved";
            case "cancelled": return "bet-status--cancelled";
            default: return "";
        }
    };

    const formatRemaining = (seconds) => {
        const safe = Math.max(0, Number(seconds || 0));
        const h = Math.floor(safe / 3600);
        const m = Math.floor((safe % 3600) / 60);
        const s = safe % 60;
        if (h > 0) return `${h}h ${m}min`;
        if (m > 0) return `${m}min ${s}s`;
        return `${s}s`;
    };

    const renderOptions = (options) =>
        options.map((option) => `
            <div class="bet-option">
                <div class="bet-option-top">
                    <span class="bet-option-label">${html(option.label)}</span>
                    <span class="bet-option-value">${option.percentage.toFixed(1)}% | ${formatCredits(option.total_bet)}</span>
                </div>
                <div class="bet-progress">
                    <span style="width:${Math.max(0, Math.min(100, Number(option.percentage || 0)))}%"></span>
                </div>
            </div>
        `).join("");

    const renderBetCard = (bet) => {
        const myEntry = bet.my_entry
            ? `<span class="badge badge--blue">Sua aposta: ${formatCredits(bet.my_entry.amount)}</span>`
            : "";

        return `
            <article class="bet-card" data-bet-id="${bet.id}">
                <header class="bet-card-header">
                    <div>
                        <h3 class="bet-card-title">${html(bet.title)}</h3>
                        <div class="bet-card-meta">
                            <span>Pool: ${formatCredits(bet.total_pool)}</span>
                            <span>Minimo: ${formatCredits(bet.min_entry)}</span>
                            <span>${html(bet.creator.username)}</span>
                            ${myEntry}
                        </div>
                    </div>
                    <span class="bet-status ${statusClass(bet.status)}">${html(bet.status)}</span>
                </header>

                <div class="bet-options">
                    ${renderOptions(Array.isArray(bet.options) ? bet.options : [])}
                </div>

                <footer class="bet-card-footer">
                    <span class="bet-countdown" data-role="countdown" data-remaining="${Number(bet.remaining_seconds || 0)}">
                        Encerra em ${formatRemaining(Number(bet.remaining_seconds || 0))}
                    </span>

                    <div class="bet-actions">
                        <a href="${appUrl(`/bet/${bet.id}`)}" class="btn btn--ghost">Detalhes</a>
                        ${bet.status === "open" ? `<button class="btn btn--gold" data-action="open-entry" data-bet-id="${bet.id}">Entrar</button>` : ""}
                    </div>
                </footer>
            </article>
        `;
    };

    const updateCountdowns = () => {
        document.querySelectorAll("[data-role='countdown']").forEach((el) => {
            const remaining = Number(el.getAttribute("data-remaining") || "0");
            const next = Math.max(0, remaining - 1);
            el.setAttribute("data-remaining", String(next));
            el.textContent = `Encerra em ${formatRemaining(next)}`;
        });
    };

    const loadBetList = async (reset = false) => {
        if (!listMode || !listEl || state.loading) return;
        if (reset) {
            state.page = 1;
            listEl.innerHTML = "";
        }

        setError("");
        setLoading(true);

        try {
            const response = await fetch(`/api/bet.php?action=list&status=${encodeURIComponent(state.status)}&page=${state.page}`, {
                headers: { Accept: "application/json" },
            });
            const data = await parseResponse(response);
            const bets = Array.isArray(data.bets) ? data.bets : [];

            if (bets.length === 0 && state.page === 1) {
                listEl.innerHTML = `<div class="bet-empty card">Nenhuma aposta nesta categoria no momento.</div>`;
            } else {
                listEl.innerHTML = bets.map(renderBetCard).join("");
            }

            state.page += 1;
        } catch (error) {
            setError(error.message || "Nao foi possivel carregar as apostas.");
        } finally {
            setLoading(false);
        }
    };

    const openEntryModal = async (betId) => {
        if (!modal || !modalForm) return;

        try {
            const response = await fetch(`/api/bet.php?action=single&id=${encodeURIComponent(betId)}`, {
                headers: { Accept: "application/json" },
            });
            const data = await parseResponse(response);
            const bet = data.bet;
            if (!bet) throw new Error("Aposta invalida.");

            const titleEl = modal.querySelector("[data-role='bet-title']");
            if (titleEl) titleEl.textContent = bet.title;

            const optionSelect = modal.querySelector("select[name='option_id']");
            if (optionSelect) {
                optionSelect.innerHTML = (bet.options || [])
                    .map((option) => `<option value="${option.id}">${html(option.label)}</option>`)
                    .join("");
            }

            const minEl = modal.querySelector("[data-role='min-entry']");
            if (minEl) minEl.textContent = formatCredits(bet.min_entry);

            const betIdInput = modalForm.querySelector("input[name='bet_id']");
            if (betIdInput) betIdInput.value = String(bet.id);

            modal.classList.add("is-open");
        } catch (error) {
            setError(error.message || "Nao foi possivel abrir o modal.");
            notifyError(error.message || "Nao foi possivel abrir o modal.");
        }
    };

    const closeEntryModal = () => {
        if (modal) modal.classList.remove("is-open");
    };

    const submitEntry = async (event) => {
        event.preventDefault();
        if (!modalForm) return;

        const formData = new FormData(modalForm);
        formData.set("csrf_token", csrfToken);

        const submitButton = modalForm.querySelector("button[type='submit']");
        if (submitButton) submitButton.disabled = true;

        try {
            const response = await fetch("/api/bet.php?action=enter", {
                method: "POST",
                headers: { Accept: "application/json" },
                body: formData,
            });
            const data = await parseResponse(response);

            closeEntryModal();
            if (data.new_balance !== undefined) {
                updateBalance(Number(data.new_balance), { animate: true });
            }
            notifySuccess("Aposta registrada com sucesso.");

            if (listMode) {
                await loadBetList(true);
            }

            if (singleMode && singleBetId > 0) {
                await loadSingle(singleBetId);
            }
        } catch (error) {
            setError(error.message || "Nao foi possivel concluir a aposta.");
            notifyError(error.message || "Nao foi possivel concluir a aposta.");
        } finally {
            if (submitButton) submitButton.disabled = false;
        }
    };

    const handleResolveOrCancel = async (event) => {
        const btn = event.target.closest("[data-action='resolve-bet'], [data-action='cancel-bet']");
        if (!btn) return;

        event.preventDefault();
        const action = btn.dataset.action === "resolve-bet" ? "resolve" : "cancel";
        const betId = Number(btn.dataset.betId || "0");
        if (!betId) return;

        const formData = new FormData();
        formData.set("csrf_token", csrfToken);
        formData.set("bet_id", String(betId));

        if (action === "resolve") {
            const winnerSelect = document.querySelector("[name='winning_option_id']");
            const winningId = Number(winnerSelect ? winnerSelect.value : "0");
            if (!winningId) {
                setError("Selecione a opcao vencedora.");
                notifyError("Selecione a opcao vencedora.");
                return;
            }
            formData.set("winning_option_id", String(winningId));
        }

        if (!window.confirm(action === "resolve" ? "Confirmar resolucao da aposta?" : "Cancelar aposta e reembolsar todos?")) {
            return;
        }

        btn.disabled = true;
        try {
            const response = await fetch(`/api/bet.php?action=${action}`, {
                method: "POST",
                headers: { Accept: "application/json" },
                body: formData,
            });
            await parseResponse(response);
            await loadSingle(betId);
            notifySuccess(action === "resolve" ? "Aposta resolvida com sucesso." : "Aposta cancelada com sucesso.");
        } catch (error) {
            setError(error.message || "Falha ao processar acao.");
            notifyError(error.message || "Falha ao processar acao.");
        } finally {
            btn.disabled = false;
        }
    };

    const loadSingle = async (betId) => {
        const detail = document.getElementById("betSingleDetail");
        const entriesBody = document.getElementById("betEntriesBody");
        const resolveBox = document.getElementById("betResolveBox");
        if (!detail) return;

        setError("");
        setLoading(true);

        try {
            const response = await fetch(`/api/bet.php?action=single&id=${encodeURIComponent(betId)}`, {
                headers: { Accept: "application/json" },
            });
            const data = await parseResponse(response);
            const bet = data.bet;

            if (!bet) throw new Error("Aposta nao encontrada.");

            detail.innerHTML = `
                <article class="bet-card">
                    <header class="bet-card-header">
                        <div>
                            <h2 class="bet-card-title">${html(bet.title)}</h2>
                            <div class="bet-card-meta">
                                <span>Pool total: ${formatCredits(bet.total_pool)}</span>
                                <span>Taxa: ${bet.fee_percent}%</span>
                                <span>Min: ${formatCredits(bet.min_entry)}</span>
                                <span>Criador: ${html(bet.creator.username)}</span>
                            </div>
                        </div>
                        <span class="bet-status ${statusClass(bet.status)}">${html(bet.status)}</span>
                    </header>

                    <p style="margin:12px 0 0;color:var(--text-secondary);">${html(bet.description || "Sem descricao.")}</p>

                    <div class="bet-options">
                        ${renderOptions(Array.isArray(bet.options) ? bet.options : [])}
                    </div>

                    <footer class="bet-card-footer">
                        <span class="bet-countdown" data-role="countdown" data-remaining="${Number(bet.remaining_seconds || 0)}">
                            Encerra em ${formatRemaining(Number(bet.remaining_seconds || 0))}
                        </span>
                        <div class="bet-actions">
                            ${bet.status === "open" ? `<button class="btn btn--gold" data-action="open-entry" data-bet-id="${bet.id}">Apostar</button>` : ""}
                        </div>
                    </footer>
                </article>
            `;

            if (entriesBody) {
                const entries = Array.isArray(data.entries) ? data.entries : [];
                entriesBody.innerHTML = entries.length ? entries.map((entry) => `
                    <tr>
                        <td>${html(entry.username)}</td>
                        <td>${formatCredits(entry.amount)}</td>
                        <td>${entry.payout ? formatCredits(entry.payout) : '-'}</td>
                        <td>${html(entry.status)}</td>
                    </tr>
                `).join("") : `<tr><td colspan="4">Sem entradas ainda.</td></tr>`;
            }

            if (resolveBox) {
                const optionSelect = resolveBox.querySelector("select[name='winning_option_id']");
                if (optionSelect) {
                    optionSelect.innerHTML = (bet.options || []).map((option) =>
                        `<option value="${option.id}">${html(option.label)}</option>`
                    ).join("");
                }

                resolveBox.hidden = !(resolveBox.dataset.canManage === "1" && ["open", "closed"].includes(bet.status));
                resolveBox.querySelectorAll("[data-bet-id]").forEach((el) => {
                    el.setAttribute("data-bet-id", String(bet.id));
                });
            }
        } catch (error) {
            setError(error.message || "Falha ao carregar aposta.");
        } finally {
            setLoading(false);
        }
    };
