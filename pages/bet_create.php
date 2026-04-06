<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$authUser = $GLOBALS['auth_user'];
$pageTitle = 'Criar Aposta';
$pageDescription = 'Crie uma nova aposta na BetHouse da CARVASILVA.';
$pageCss = ['/assets/css/bet.css'];
$notificationsUnread = getUnreadNotificationsCount((int) $authUser['id']);
$csrfToken = generateCsrfToken();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<div class="app-shell app-shell--center">
    <main class="page-main">
        <section class="bet-layout">
            <article class="card">
                <header class="bet-header">
                    <div>
                        <h1>Criar aposta</h1>
                        <p>Defina opcoes claras, prazo realista e regras objetivas para a comunidade.</p>
                    </div>
                    <a href="<?= e(appUrl('/bet')) ?>" class="btn btn--ghost">Voltar</a>
                </header>
            </article>

            <article class="card">
                <form id="betCreateForm" class="bet-form">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <div>
                        <label for="title">Titulo</label>
                        <input id="title" class="input" name="title" maxlength="200" required>
                    </div>

                    <div>
                        <label for="description">Descricao</label>
                        <textarea id="description" class="input" name="description" rows="4" maxlength="500"></textarea>
                    </div>

                    <div class="bet-form-grid">
                        <div>
                            <label for="type">Tipo</label>
                            <select id="type" class="input" name="type" required>
                                <option value="custom">Custom</option>
                                <option value="pool">Pool</option>
                                <option value="head2head">Head-to-Head</option>
                                <option value="event">Evento</option>
                            </select>
                        </div>

                        <div>
                            <label for="deadline">Prazo</label>
                            <input id="deadline" class="input" name="deadline" type="datetime-local" required>
                        </div>

                        <div>
                            <label for="min_entry">Entrada minima</label>
                            <input id="min_entry" class="input" name="min_entry" type="number" min="10" value="10" required>
                        </div>

                        <div>
                            <label for="max_entry">Entrada maxima (opcional)</label>
                            <input id="max_entry" class="input" name="max_entry" type="number" min="10" placeholder="Sem limite">
                        </div>
                    </div>

                    <section>
                        <div class="bet-builder-head">
                            <label style="margin:0;">Opcoes da aposta</label>
                            <button type="button" id="addOptionBtn" class="btn btn--ghost">Adicionar opcao</button>
                        </div>

                        <div id="optionsBuilder" class="bet-options-builder">
                            <input class="input" name="options[]" maxlength="150" placeholder="Opcao 1" required>
                            <input class="input" name="options[]" maxlength="150" placeholder="Opcao 2" required>
                        </div>
                    </section>

                    <div class="bet-submit-wrap">
                        <button type="submit" class="btn btn--gold">Criar aposta</button>
                    </div>

                    <div id="createFeedback" class="bet-error" hidden></div>
                </form>
            </article>
        </section>
    </main>
</div>

<script>
    (() => {
        const form = document.getElementById("betCreateForm");
        const addOptionBtn = document.getElementById("addOptionBtn");
        const optionsBuilder = document.getElementById("optionsBuilder");
        const feedback = document.getElementById("createFeedback");
        const csrfToken = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE) ?>;
        const betApiBase = <?= json_encode(appUrl('/api/bet.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        const showError = (message) => {
            feedback.hidden = false;
            feedback.textContent = message;
        };

        const hideError = () => {
            feedback.hidden = true;
            feedback.textContent = "";
        };

        addOptionBtn.addEventListener("click", () => {
            const inputs = optionsBuilder.querySelectorAll("input[name='options[]']");
            if (inputs.length >= 8) return;
            const input = document.createElement("input");
            input.className = "input";
            input.name = "options[]";
            input.maxLength = 150;
            input.placeholder = `Opcao ${inputs.length + 1}`;
            optionsBuilder.appendChild(input);
        });

        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            hideError();

            const submitBtn = form.querySelector("button[type='submit']");
            if (submitBtn) submitBtn.disabled = true;

            try {
                const formData = new FormData(form);
                formData.set("csrf_token", csrfToken);

                const response = await fetch(`${betApiBase}?action=create`, {
                    method: "POST",
                    headers: { Accept: "application/json" },
                    body: formData
                });

                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(payload.error || "Nao foi possivel criar a aposta.");
                }

                const redirectTo = payload.data && payload.data.redirect
                    ? payload.data.redirect
                    : `/bet/${payload.data.bet_id}`;

                window.location.href = redirectTo;
            } catch (error) {
                showError(error.message || "Erro ao criar aposta.");
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
