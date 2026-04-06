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
        $displayName = sanitizeText((string) ($_POST['display_name'] ?? ''), 100);
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
        $roomId = sanitizeInt($_POST['room_id'] ?? null);

        if ($displayName === '' || $username === '' || $password === '' || $passwordConfirm === '' || $roomId <= 0) {
            jsonResponse(false, [], 'Preencha todos os campos obrigatórios.', 422);
        }

        if (!validateUsername($username)) {
            jsonResponse(false, [], 'Username deve estar no formato Nome_Sala (ex: Lucas_3B).', 422);
        }

        if (mb_strlen($password) < PASSWORD_MIN_LENGTH) {
            jsonResponse(false, [], 'A senha deve ter no mínimo 8 caracteres.', 422);
        }

        if (!hash_equals($password, $passwordConfirm)) {
            jsonResponse(false, [], 'As senhas não conferem.', 422);
        }

        $room = getRoomById($roomId);
        if ($room === null) {
            jsonResponse(false, [], 'Turma inválida.', 422);
        }

        $usernameRoomCode = getRoomCodeFromUsername($username);
        $selectedRoomCode = strtoupper((string) $room['code']);

        if ($usernameRoomCode === null || $usernameRoomCode !== $selectedRoomCode) {
            jsonResponse(false, [], 'O sufixo do username deve corresponder à turma selecionada.', 422);
        }

        if (getUserByUsername($username) !== null) {
            jsonResponse(false, [], 'Este username já está em uso.', 409);
        }

        $avatarPath = null;
        if (isset($_FILES['avatar'])) {
            $avatarPath = uploadAvatarFile($_FILES['avatar']);
        }

        $pdo = db();
        $pdo->beginTransaction();

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $insertUserStmt = $pdo->prepare(
            'INSERT INTO users (username, display_name, password_hash, room_id, avatar, credits)
             VALUES (:username, :display_name, :password_hash, :room_id, :avatar, :credits)'
        );
        $insertUserStmt->execute([
            'username' => $username,
            'display_name' => $displayName,
            'password_hash' => $passwordHash,
            'room_id' => $roomId,
            'avatar' => $avatarPath,
            'credits' => INITIAL_CREDITS,
        ]);

        $newUserId = (int) $pdo->lastInsertId();

        $insertTransactionStmt = $pdo->prepare(
            'INSERT INTO credit_transactions (user_id, amount, balance_after, type, description)
             VALUES (:user_id, :amount, :balance_after, :type, :description)'
        );
        $insertTransactionStmt->execute([
            'user_id' => $newUserId,
            'amount' => INITIAL_CREDITS,
            'balance_after' => INITIAL_CREDITS,
            'type' => 'signup_bonus',
            'description' => 'Bônus de cadastro',
        ]);

        $pdo->commit();

        $user = getUserById($newUserId);
        if ($user === null) {
            jsonResponse(false, [], 'Não foi possível carregar o usuário cadastrado.', 500);
        }

        loginUser($user, false);

        echo json_encode([
            'success' => true,
            'data' => [
                'redirect' => appUrl('/feed'),
                'credits' => INITIAL_CREDITS,
            ],
            'redirect' => appUrl('/feed'),
            'error' => null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    } catch (Throwable $throwable) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        $message = $throwable->getMessage();
        if ($message === '') {
            $message = 'Erro interno ao cadastrar usuário.';
        }

        jsonResponse(false, [], $message, 500);
    }
}

$rooms = getRooms();
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Cadastro na rede social escolar CARVASILVA.">
    <meta name="theme-color" content="#0D0D0D">
    <title>Cadastro | CARVASILVA</title>
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
            max-width: 460px;
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
        select,
        button {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #121224;
            color: var(--text-primary);
            padding: 12px 14px;
            font-size: 0.95rem;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(75, 158, 255, 0.2);
        }

        .help {
            margin-top: 6px;
            font-size: 0.8rem;
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
    <p class="subtitle">Crie sua conta da rede social escolar.</p>

    <form id="registerForm" action="<?= e(appUrl('/auth/register.php')) ?>" method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

        <label for="display_name">Nome completo</label>
        <input id="display_name" name="display_name" type="text" maxlength="100" required>

        <label for="username">Username (Nome_Sala)</label>
        <input id="username" name="username" type="text" maxlength="50" placeholder="Ex.: Lucas_3B" required>
        <p class="help">Formato obrigatório: Nome_Sala (ex.: Ana_2A).</p>

        <label for="room_id">Turma</label>
        <select id="room_id" name="room_id" required>
            <option value="">Selecione sua turma</option>
            <?php foreach ($rooms as $room): ?>
                <option value="<?= (int) $room['id'] ?>"><?= e((string) $room['name']) ?> (<?= e((string) $room['code']) ?>)</option>
            <?php endforeach; ?>
        </select>

        <label for="password">Senha</label>
        <input id="password" name="password" type="password" minlength="8" required>

        <label for="password_confirm">Confirmar senha</label>
        <input id="password_confirm" name="password_confirm" type="password" minlength="8" required>

        <label for="avatar">Avatar (opcional)</label>
        <input id="avatar" name="avatar" type="file" accept=".jpg,.jpeg,.png,.gif,.webp">

        <button type="submit" id="submitBtn">Criar conta</button>
        <p class="error" id="feedback" aria-live="polite"></p>
    </form>

    <p class="bottom-link">
        Já tem conta? <a href="<?= e(appUrl('/login')) ?>">Entrar</a>
    </p>
</main>

<script>
    const form = document.getElementById('registerForm');
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
                throw new Error(payload.error || 'Não foi possível concluir o cadastro.');
            }

            feedback.textContent = 'Cadastro realizado com sucesso. Redirecionando...';
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
