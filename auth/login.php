<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

ensureSessionStarted();

if (isAuthenticated()) {
    redirect('/feed');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        requireValidCsrfToken($_POST['csrf_token'] ?? null);

        $username = sanitizeText((string) ($_POST['username'] ?? ''), 50);
        $password = (string) ($_POST['password'] ?? '');
        $rememberMe = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';

        if ($username === '' || $password === '') {
            jsonResponse(false, [], 'Informe username e senha.', 422);
        }

        $user = getUserByUsername($username);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            jsonResponse(false, [], 'Credenciais inválidas.', 401);
        }

        if ((int) ($user['is_banned'] ?? 0) === 1) {
            $banReason = sanitizeText((string) ($user['ban_reason'] ?? ''), 120);
            $message = $banReason !== '' ? 'Conta banida: ' . $banReason : 'Conta banida. Procure a administração.';
            jsonResponse(false, [], $message, 403);
        }

        loginUser($user, $rememberMe);

        $dailyBonus = handleDailyLoginBonus((int) $user['id']);
        $freshUser = getUserById((int) $user['id']);

        echo json_encode([
            'success' => true,
            'data' => [
                'redirect' => appUrl('/feed'),
                'daily_bonus' => $dailyBonus,
                'credits' => $freshUser ? (int) $freshUser['credits'] : null,
            ],
            'redirect' => appUrl('/feed'),
            'error' => null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    } catch (Throwable $throwable) {
        $message = $throwable->getMessage();
        if ($message === '') {
            $message = 'Erro interno ao fazer login.';
        }

        jsonResponse(false, [], $message, 500);
    }
}

$csrfToken = generateCsrfToken();
$flashError = '';
$errorFromQuery = (string) ($_GET['error'] ?? '');

if ($errorFromQuery === 'banido') {
    $flashError = 'Conta bloqueada pela administração.';
} elseif ($errorFromQuery === 'sessao_expirada') {
    $flashError = 'Sua sessão expirou. Entre novamente.';
} elseif ($errorFromQuery === 'csrf') {
    $flashError = 'Sessão inválida. Atualize a página e tente novamente.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login da rede social escolar CARVASILVA.">
    <meta name="theme-color" content="#0D0D0D">
    <title>Login | CARVASILVA</title>
    <style>
        :root {
            color-scheme: dark;
            --bg-primary: #0D0D0D;
            --bg-surface: #1A1A2E;
            --border: #2A2A3E;
            --text-primary: #E8E8E8;
            --text-secondary: #A7A7B6;
            --gold: #F5C518;
            --blue: #4B9EFF;
            --danger: #E74C3C;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, Arial, sans-serif;
            background: radial-gradient(circle at top, #16213E, var(--bg-primary) 50%);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .auth-card {
            width: 100%;
            max-width: 430px;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 18px 36px rgba(0, 0, 0, 0.35);
        }

        h1 {
            margin: 0 0 8px;
            font-family: Poppins, Arial, sans-serif;
            font-size: 1.5rem;
        }

        .subtitle {
            margin: 0 0 20px;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        label {
            display: block;
            margin: 12px 0 6px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        input,
        button {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #121224;
            color: var(--text-primary);
            padding: 12px 14px;
            font-size: 0.95rem;
        }

        input:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(75, 158, 255, 0.2);
        }

        .remember {
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .remember input {
            width: auto;
            margin: 0;
        }

        .remember label {
            margin: 0;
            color: var(--text-secondary);
        }

        button {
            margin-top: 18px;
            border: 0;
            background: linear-gradient(135deg, var(--gold), #c89d1a);
            color: #111;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.15s ease, filter 0.15s ease;
        }

        button:hover {
            filter: brightness(1.05);
            transform: translateY(-1px);
        }

        button:disabled {
            cursor: not-allowed;
            opacity: 0.7;
            transform: none;
        }

        .error {
            min-height: 22px;
            margin-top: 10px;
            color: var(--danger);
            font-size: 0.9rem;
        }

        .success {
            color: #2ECC71;
        }

        .bottom-link {
            margin-top: 18px;
            text-align: center;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .bottom-link a {
            color: var(--blue);
            text-decoration: none;
        }

        .bottom-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<main class="auth-card">
    <h1>CARVASILVA</h1>
    <p class="subtitle">Entre e acompanhe seu saldo em créditos fictícios.</p>

    <form id="loginForm" action="<?= e(appUrl('/auth/login.php')) ?>" method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

        <label for="username">Username</label>
        <input id="username" name="username" type="text" maxlength="50" required>

        <label for="password">Senha</label>
        <input id="password" name="password" type="password" minlength="8" required>

        <div class="remember">
            <input id="remember_me" name="remember_me" type="checkbox" value="1">
            <label for="remember_me">Lembrar de mim por 30 dias</label>
        </div>

        <button type="submit" id="submitBtn">Entrar</button>
        <p class="error" id="feedback" aria-live="polite"><?= e($flashError) ?></p>
    </form>

    <p class="bottom-link">
        Ainda não tem conta? <a href="<?= e(appUrl('/register')) ?>">Cadastre-se</a>
    </p>
</main>

<script>
    const form = document.getElementById('loginForm');
    const submitBtn = document.getElementById('submitBtn');
    const feedback = document.getElementById('feedback');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        feedback.textContent = '';
        feedback.classList.remove('success');
        submitBtn.disabled = true;

        try {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            });

            const payload = await response.json();

            if (!response.ok || !payload.success) {
                throw new Error(payload.error || 'Não foi possível entrar.');
            }

            const bonus = payload.data && payload.data.daily_bonus ? Number(payload.data.daily_bonus) : 0;
            feedback.textContent = bonus > 0
                ? `Login realizado. Você ganhou ₢ ${bonus.toLocaleString('pt-BR')} de bônus diário.`
                : 'Login realizado. Redirecionando...';
            feedback.classList.add('success');

            const redirectTo = payload.redirect || (payload.data && payload.data.redirect) || <?= json_encode(appUrl('/feed'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            window.location.href = redirectTo;
        } catch (error) {
            feedback.textContent = error.message || 'Erro de rede. Tente novamente.';
        } finally {
            submitBtn.disabled = false;
        }
    });
</script>
</body>
</html>
