<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';

$authUser = $GLOBALS['auth_user'];
$pageTitle = 'BetHouse';
$pageDescription = 'Apostas e cassino social da CARVASILVA com creditos ficticios.';
$pageCss = ['/assets/css/bet.css'];
$pageScripts = ['/assets/js/bet.js'];
$notificationsUnread = getUnreadNotificationsCount((int) $authUser['id']);
$csrfToken = generateCsrfToken();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<div class="app-shell">
    <main class="page-main">
        <section
            id="betApp"
            class="bet-layout"
            data-mode="list"
            data-initial-status="open"
            data-csrf="<?= e($csrfToken) ?>"
        >
            <article class="card bet-hero">
                <header class="bet-header">
                    <div>
                        <h1>BetHouse Casino</h1>
                        <p>Ambiente social com apostas e mini jogos ao vivo em creditos ficticios.</p>
                    </div>

                    <div class="bet-header-actions">
                        <span class="bet-balance">Seu saldo: <?= e(formatCredits((int) $authUser['credits'])) ?></span>
                        <a href="<?= e(appUrl('/bet/create')) ?>" class="btn btn--gold">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            Criar aposta
                        </a>
                    </div>
                </header>
            </article>

            <section class="card casino-hub">
                <header class="casino-hub__header">
                    <div>
                        <h2>Cassino social em tempo real</h2>
                        <p>Rotacao continua de jogos para entretenimento da comunidade.</p>
                    </div>
                    <span class="badge badge--blue">
                        <i class="fa-solid fa-signal" aria-hidden="true"></i>
                        Sessao online
                    </span>
                </header>

                <div class="casino-grid" id="casinoGrid">
                    <article class="casino-game" data-casino-game="crash">
                        <div class="casino-game__head">
                            <h3>Crash</h3>
                            <span class="badge badge--blue">Ao vivo</span>
                        </div>
                        <p class="casino-game__desc">Rodadas com fases de aposta, voo e crash em tempo real.</p>

                        <div class="crash-phase-badge" data-crash-phase>Betting</div>

                        <div class="crash-canvas-wrap">
                            <canvas
                                class="crash-canvas"
                                width="620"
                                height="250"
                                data-crash-canvas
                                aria-label="Grafico do jogo Crash"
                            ></canvas>
                            <div class="crash-canvas-overlay">
                                <div class="crash-multiplier" data-crash-multiplier>1.00x</div>
                                <div class="crash-round-status" data-crash-round-status>Aguardando abertura de rodada</div>
                            </div>
                        </div>

                        <div class="crash-controls">
                            <label class="crash-control-label" for="crashBetAmount">Valor da aposta</label>
                            <div class="crash-control-row">
                                <input
                                    id="crashBetAmount"
                                    class="input crash-bet-input"
                                    type="number"
                                    min="10"
                                    step="10"
                                    value="20"
                                    data-crash-bet-amount
                                >

                                <button type="button" class="btn btn--gold crash-main-action" data-crash-place-bet>
                                    Apostar
                                </button>

                                <button type="button" class="btn crash-cashout-btn" data-crash-cashout disabled>
                                    Cashout
                                </button>
                            </div>

                            <div class="crash-quick-bets">
                                <button type="button" class="btn btn--ghost" data-crash-quick-bet="10">+10</button>
                                <button type="button" class="btn btn--ghost" data-crash-quick-bet="25">+25</button>
                                <button type="button" class="btn btn--ghost" data-crash-quick-bet="50">+50</button>
                                <button type="button" class="btn btn--ghost" data-crash-quick-bet="100">+100</button>
                            </div>
                        </div>

                        <div class="crash-user-state" data-crash-user-state>Nenhuma aposta ativa nesta rodada</div>

                        <div class="crash-history-wrap">
                            <div class="crash-history-title">Historico</div>
                            <div class="crash-history-chips" data-crash-history></div>
                        </div>

                        <div class="crash-live-bets">
                            <div class="crash-live-bets__title">Apostas ao vivo</div>
                            <ul class="crash-live-list" data-crash-live-bets></ul>
                        </div>
                    </article>

                    <article class="casino-game" data-casino-game="roulette">
                        <div class="casino-game__head">
                            <h3>Roleta</h3>
                            <span class="badge">Mesa aberta</span>
                        </div>
                        <p class="casino-game__desc">Rodadas com numero sorteado automaticamente.</p>
                        <div class="casino-game__value" data-roulette-number>--</div>
                        <div class="casino-game__meta" data-roulette-state>Girando...</div>
                    </article>

                    <article class="casino-game" data-casino-game="aviator">
                        <div class="casino-game__head">
                            <h3>Aviator</h3>
                            <span class="badge">Loop 24h</span>
                        </div>
                        <p class="casino-game__desc">Voo continuo com cashout simulado.</p>
                        <div class="casino-game__value" data-aviator-multiplier>1.00x</div>
                        <div class="casino-game__meta" data-aviator-state>Pista liberada</div>
                    </article>

                    <article class="casino-game" data-casino-game="slots">
                        <div class="casino-game__head">
                            <h3>Slots</h3>
                            <span class="badge">Auto spin</span>
                        </div>
                        <p class="casino-game__desc">Maquina em rotacao constante com premios ficticios.</p>
                        <div class="casino-game__value" data-slots-value>7 - 7 - 7</div>
                        <div class="casino-game__meta" data-slots-state>Ultimo retorno: 0x</div>
                    </article>

                    <article class="casino-game" data-casino-game="blackjack">
                        <div class="casino-game__head">
                            <h3>Blackjack</h3>
                            <span class="badge">Mesa rapida</span>
                        </div>
                        <p class="casino-game__desc">Mao da casa e dos jogadores atualizada em ciclos curtos.</p>
                        <div class="casino-game__value" data-blackjack-score>Casa 12 | Mesa 16</div>
                        <div class="casino-game__meta" data-blackjack-state>Proxima mao em 6s</div>
                    </article>

                    <article class="casino-game" data-casino-game="cards">
                        <div class="casino-game__head">
                            <h3>Salas de cartas</h3>
                            <span class="badge">Multimesa</span>
                        </div>
                        <p class="casino-game__desc">Poker e truco social com filas dinamicas.</p>
                        <div class="casino-game__value" data-cards-online>34 jogadores</div>
                        <div class="casino-game__meta" data-cards-state>6 mesas em andamento</div>
                    </article>
                </div>

                <p class="casino-footnote">
                    Todas as mecanicas desta area usam apenas creditos ficticios da plataforma.
                </p>
            </section>

            <section class="bet-toolbar card">
                <nav class="top-tabs" aria-label="Filtros das apostas">
                    <button class="tab-btn is-active" data-bet-tab="open" aria-selected="true">Abertas</button>
                    <button class="tab-btn" data-bet-tab="closed" aria-selected="false">Encerrando</button>
                    <button class="tab-btn" data-bet-tab="resolved" aria-selected="false">Resolvidas</button>
                    <button class="tab-btn" data-bet-tab="cancelled" aria-selected="false">Canceladas</button>
                </nav>
            </section>

            <div id="betError" class="bet-error" hidden></div>
            <div id="betLoading" class="card skeleton" style="height: 84px;" hidden></div>
            <section id="betList" class="bet-list" aria-live="polite"></section>
        </section>
    </main>

    <aside class="desktop-panel bet-side-column">
        <section class="card bet-side-widget">
            <h2>Regras da mesa</h2>
            <ul>
                <li><span>Taxa da plataforma</span><strong><?= (int) CREDIT_RULES['bet_platform_fee_percent'] ?>%</strong></li>
                <li><span>Entrada minima</span><strong><?= e(formatCredits(10)) ?></strong></li>
                <li><span>Liquidacao</span><strong>Proporcional ao lado vencedor</strong></li>
            </ul>
        </section>

        <section class="card bet-side-widget">
            <h2>Ritmo recomendado</h2>
            <p>Mantenha apostas sustentaveis: use entre 3% e 8% do saldo por rodada para evolucao consistente.</p>
            <button type="button" class="btn btn--ghost" data-open-rewards>
                Ver bonus disponiveis
            </button>
        </section>
    </aside>
</div>

<section id="betModal" class="bet-modal" aria-hidden="true">
    <article class="bet-modal-card">
        <h2 class="bet-modal-title">Apostar em: <span data-role="bet-title">Aposta</span></h2>
        <form id="betEntryForm" class="bet-form">
            <input type="hidden" name="bet_id" value="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <label for="entryOption">Opcao</label>
            <select id="entryOption" name="option_id" required></select>

            <label for="entryAmount">Valor em creditos</label>
            <input id="entryAmount" class="input" type="number" min="1" name="amount" required>
            <p class="bet-form-note">
                Entrada minima: <span data-role="min-entry"><?= e(formatCredits(10)) ?></span>
            </p>

            <div class="bet-modal-actions">
                <button type="button" class="btn btn--ghost" data-action="close-bet-modal">Cancelar</button>
                <button type="submit" class="btn btn--gold">Confirmar aposta</button>
            </div>
        </form>
    </article>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
