<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$pageScripts = isset($pageScripts) && is_array($pageScripts) ? $pageScripts : [];
$globalScripts = ['/assets/js/notifications.js'];
$allScripts = array_values(array_unique(array_merge($globalScripts, $pageScripts)));
$baseUrl = appUrl('/');
$rewardProgram = defined('REWARD_PROGRAM') ? REWARD_PROGRAM : [];
?>
<script>
    (() => {
        const baseUrl = <?= json_encode(rtrim($baseUrl, '/'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const rewards = <?= json_encode($rewardProgram, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const themeKey = 'carvasilva_theme';
        const root = document.documentElement;

        function formatCredits(value) {
            return `\u20A2 ${Number(value).toLocaleString('pt-BR')}`;
        }

        function buildUrl(path = '/') {
            if (!path) {
                return `${baseUrl}/`;
            }

            if (/^https?:\/\//i.test(path)) {
                return path;
            }

            const normalized = path.startsWith('/') ? path : `/${path}`;
            return `${baseUrl}${normalized}`;
        }

        function applyTheme(theme, persist = true) {
            const safeTheme = theme === 'light' ? 'light' : 'dark';
            root.setAttribute('data-theme', safeTheme);

            const themeMeta = document.querySelector('meta[name="theme-color"]');
            if (themeMeta) {
                themeMeta.setAttribute('content', safeTheme === 'light' ? '#f5f8ff' : '#0d0d0d');
            }

            document.querySelectorAll('[data-theme-icon]').forEach((icon) => {
                icon.classList.toggle('fa-sun', safeTheme === 'light');
                icon.classList.toggle('fa-moon', safeTheme !== 'light');
            });

            if (persist) {
                try {
                    localStorage.setItem(themeKey, safeTheme);
                } catch (_) {
                    // Ignore storage errors.
                }
            }

            window.Carvasilva.theme = safeTheme;
        }

        function initTheme() {
            let preferred = 'dark';
            try {
                preferred = localStorage.getItem(themeKey) === 'light' ? 'light' : 'dark';
            } catch (_) {
                preferred = root.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
            }

            applyTheme(preferred, false);

            document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    if (button.tagName === 'A') {
                        event.preventDefault();
                    }

                    const next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
                    applyTheme(next, true);
                });
            });
        }

        function animateCredits(element, from, to, duration = 950) {
            if (!element) {
                return;
            }

            const valueNode = element.querySelector('.credits-value') || element;

            if (reduceMotion || Number(from) === Number(to)) {
                valueNode.textContent = formatCredits(to);
                return;
            }

            const startValue = Number(from);
            const endValue = Number(to);
            const start = performance.now();

            valueNode.classList.add('is-updating');

            const step = (now) => {
                const elapsed = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - elapsed, 3);
                const current = Math.round(startValue + (endValue - startValue) * eased);
                valueNode.textContent = formatCredits(current);

                if (elapsed < 1) {
                    requestAnimationFrame(step);
                    return;
                }

                valueNode.classList.remove('is-updating');
            };

            requestAnimationFrame(step);
        }

        function syncCreditPills() {
            const pills = document.querySelectorAll('[data-credits]');
            pills.forEach((pill) => {
                const rawValue = Number(pill.getAttribute('data-credits') || '0');
                const currentText = pill.querySelector('.credits-value')?.textContent || '\u20A2 0';
                const parsedCurrent = Number(
                    currentText
                        .replace(/[^\d,-]/g, '')
                        .replace(/\./g, '')
                        .replace(',', '.')
                );

                animateCredits(pill, Number.isFinite(parsedCurrent) ? parsedCurrent : rawValue, rawValue);
            });
        }

        function updateBalanceDisplay(nextBalance, options = {}) {
            const targetBalance = Number(nextBalance);
            if (!Number.isFinite(targetBalance)) {
                return null;
            }

            const shouldAnimate = options.animate !== false;
            const pills = document.querySelectorAll('[data-credits]');
            pills.forEach((pill) => {
                const current = Number(pill.getAttribute('data-credits') || '0');
                pill.setAttribute('data-credits', String(Math.floor(targetBalance)));

                if (shouldAnimate) {
                    animateCredits(pill, current, targetBalance);
                    return;
                }

                const valueNode = pill.querySelector('.credits-value') || pill;
                valueNode.textContent = formatCredits(targetBalance);
            });

            return Math.floor(targetBalance);
        }

        function ensureToastContainer() {
            let container = document.getElementById('toastContainer');
            if (container) {
                return container;
            }

            container = document.createElement('section');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'false');
            document.body.appendChild(container);
            return container;
        }

        function showToast(message, type = 'info', duration = 3200) {
            const safeMessage = String(message || '').trim();
            if (safeMessage === '') {
                return;
            }

            const container = ensureToastContainer();
            const toast = document.createElement('article');
            const safeType = ['success', 'error', 'info'].includes(type) ? type : 'info';

            let iconClass = 'fa-circle-info';
            if (safeType === 'success') iconClass = 'fa-circle-check';
            if (safeType === 'error') iconClass = 'fa-circle-exclamation';

            toast.className = `toast toast--${safeType}`;
            toast.innerHTML = `
                <div class="toast__icon">
                    <i class="fa-solid ${iconClass}" aria-hidden="true"></i>
                </div>
                <div class="toast__message">${safeMessage}</div>
                <button type="button" class="toast__close" aria-label="Fechar aviso">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            `;

            const closeToast = () => {
                toast.classList.remove('is-visible');
                window.setTimeout(() => {
                    toast.remove();
                }, 180);
            };

            toast.querySelector('.toast__close')?.addEventListener('click', closeToast);
            container.appendChild(toast);

            window.requestAnimationFrame(() => {
                toast.classList.add('is-visible');
            });

            window.setTimeout(closeToast, Math.max(1200, Number(duration) || 3200));
        }

        function buildRewardsPanel() {
            if (!Array.isArray(rewards) || rewards.length === 0) {
                return;
            }

            if (document.getElementById('rewardsFab') && document.getElementById('rewardsPanel')) {
                return;
            }

            const fab = document.createElement('button');
            fab.type = 'button';
            fab.id = 'rewardsFab';
            fab.className = 'rewards-fab';
            fab.setAttribute('aria-label', 'Abrir programa de bonus');
            fab.innerHTML = '<i class="fa-solid fa-gift" aria-hidden="true"></i>';

            const panel = document.createElement('aside');
            panel.id = 'rewardsPanel';
            panel.className = 'rewards-panel';
            panel.setAttribute('aria-hidden', 'true');

            const itemsHtml = rewards.map((item) => {
                const icon = item.icon ? String(item.icon) : 'fa-star';
                const label = item.label ? String(item.label) : 'Missao';
                const reward = Number(item.reward || 0);
                const frequency = item.frequency ? String(item.frequency) : '';

                return `
                    <li class="rewards-item">
                        <i class="fa-solid ${icon}" aria-hidden="true"></i>
                        <div>
                            <div class="rewards-item__name">${label}</div>
                            <small style="color:var(--text-muted);font-size:0.72rem;">${frequency}</small>
                        </div>
                        <div class="rewards-item__value">${formatCredits(reward)}</div>
                    </li>
                `;
            }).join('');

            panel.innerHTML = `
                <header class="rewards-panel__header">
                    <div class="rewards-panel__title">Programa de Bonus</div>
                    <button type="button" class="icon-btn" data-close-rewards aria-label="Fechar bonus">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </header>
                <ul class="rewards-list">${itemsHtml}</ul>
                <div style="margin-top:10px;color:var(--text-muted);font-size:0.77rem;">
                    Recompensas sao em creditos ficticios da plataforma.
                </div>
            `;

            const closePanel = () => {
                panel.classList.remove('is-open');
                panel.setAttribute('aria-hidden', 'true');
            };

            const openPanel = () => {
                panel.classList.add('is-open');
                panel.setAttribute('aria-hidden', 'false');
            };

            const togglePanel = () => {
                if (panel.classList.contains('is-open')) {
                    closePanel();
                    return;
                }
                openPanel();
            };

            fab.addEventListener('click', togglePanel);
            panel.querySelector('[data-close-rewards]')?.addEventListener('click', closePanel);

            document.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof Element)) {
                    return;
                }

                const openTrigger = target.closest('[data-open-rewards]');
                if (openTrigger) {
                    event.preventDefault();
                    openPanel();
                    return;
                }

                if (target.closest('#rewardsPanel') || target.closest('#rewardsFab')) {
                    return;
                }

                closePanel();
            });

            document.body.appendChild(fab);
            document.body.appendChild(panel);
        }

        window.Carvasilva = window.Carvasilva || {};
        window.Carvasilva.baseUrl = baseUrl;
        window.Carvasilva.url = buildUrl;
        window.Carvasilva.animateCredits = animateCredits;
        window.Carvasilva.syncCreditPills = syncCreditPills;
        window.Carvasilva.updateBalanceDisplay = updateBalanceDisplay;
        window.Carvasilva.showToast = showToast;
        window.Carvasilva.notifySuccess = (message, duration) => showToast(message, 'success', duration);
        window.Carvasilva.notifyError = (message, duration) => showToast(message, 'error', duration);
        window.Carvasilva.rewards = rewards;
        window.Carvasilva.applyTheme = applyTheme;

        const nativeFetch = window.fetch.bind(window);
        window.fetch = (input, init) => {
            if (typeof input === 'string' && input.startsWith('/')) {
                return nativeFetch(buildUrl(input), init);
            }

            if (input instanceof Request && input.url.startsWith('/')) {
                return nativeFetch(new Request(buildUrl(input.url), input), init);
            }

            return nativeFetch(input, init);
        };

        document.addEventListener('DOMContentLoaded', () => {
            initTheme();
            syncCreditPills();
            buildRewardsPanel();
        });
    })();
</script>

<?php foreach ($allScripts as $script): ?>
    <?php if (is_string($script) && $script !== ''): ?>
        <?php $scriptSrc = str_starts_with($script, '/') ? appUrl($script) : $script; ?>
        <script src="<?= e($scriptSrc) ?>" defer></script>
    <?php endif; ?>
<?php endforeach; ?>
</body>
</html>
