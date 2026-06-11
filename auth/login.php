<?php
session_start();
require_once __DIR__ . '/../config/database.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (!empty($_SESSION['admin'])) {
    header('Location: ../admin/dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM admins WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    $storedPassword  = $admin['mot_de_passe'] ?? '';
    $isPasswordHash  = password_get_info($storedPassword)['algoName'] !== 'unknown';
    $passwordIsValid = $admin && (
        password_verify($password, $storedPassword)
        || (!$isPasswordHash && hash_equals($storedPassword, $password))
    );

    if ($passwordIsValid) {
        if (!$isPasswordHash) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $update  = $pdo->prepare('UPDATE admins SET mot_de_passe = ? WHERE id_admin = ?');
            $update->execute([$newHash, $admin['id_admin']]);
        }

        session_regenerate_id(true);
        $_SESSION['admin']      = $admin['id_admin'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $log = $pdo->prepare('INSERT INTO logs (id_admin, action) VALUES (?, ?)');
        $log->execute([$admin['id_admin'], 'Connexion admin']);

        header('Location: ../admin/dashboard.php');
        exit();
    }

    $error = 'Email ou mot de passe incorrect.';
}
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — ADEPTIO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:         #090b0f;
            --bg-card:    #111418;
            --bg-input:   #0d1017;
            --cyan:       #00e5ff;
            --cyan-dim:   rgba(0, 229, 255, 0.12);
            --cyan-glow:  rgba(0, 229, 255, 0.3);
            --border:     rgba(0, 229, 255, 0.1);
            --border-md:  rgba(0, 229, 255, 0.22);
            --text:       #e2e8f0;
            --muted:      #64748b;
            --white:      #ffffff;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            align-items: center;
            background: var(--bg);
            color: var(--text);
            display: flex;
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 0.9rem;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            -webkit-font-smoothing: antialiased;
            overflow: hidden;
        }

        /* Animated background grid */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(0,229,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,229,255,0.03) 1px, transparent 1px);
            background-size: 48px 48px;
            pointer-events: none;
            z-index: 0;
        }

        /* Glowing orb */
        body::after {
            content: '';
            position: fixed;
            top: -20%;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(0,229,255,0.06) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
            animation: orbPulse 6s ease-in-out infinite;
        }

        @keyframes orbPulse {
            0%, 100% { opacity: 0.6; transform: translateX(-50%) scale(1); }
            50%       { opacity: 1;   transform: translateX(-50%) scale(1.08); }
        }

        .login-wrap {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            padding: 20px;
            animation: cardIn 0.5s cubic-bezier(0.4, 0, 0.2, 1) both;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(24px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0)    scale(1); }
        }

        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 8px 48px rgba(0,0,0,0.6), 0 0 0 1px rgba(0,229,255,0.05);
            padding: 36px 32px;
        }

        /* Logo */
        .login-logo {
            align-items: center;
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 32px;
            text-align: center;
        }

        .login-logo-icon {
            align-items: center;
            background: var(--cyan-dim);
            border: 1px solid var(--border-md);
            border-radius: 12px;
            color: var(--cyan);
            display: flex;
            font-size: 1.4rem;
            height: 52px;
            justify-content: center;
            width: 52px;
            box-shadow: 0 0 20px rgba(0,229,255,0.15);
        }

        .login-logo-name {
            color: var(--white);
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .login-logo-sub {
            color: var(--muted);
            font-size: 0.78rem;
            margin-top: -8px;
        }

        /* Error */
        .login-error {
            align-items: center;
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.25);
            border-radius: 8px;
            color: #fca5a5;
            display: flex;
            font-size: 0.83rem;
            gap: 8px;
            margin-bottom: 20px;
            padding: 10px 14px;
            animation: shakeError 0.4s ease both;
        }

        @keyframes shakeError {
            0%, 100% { transform: translateX(0); }
            20%       { transform: translateX(-6px); }
            40%       { transform: translateX(6px); }
            60%       { transform: translateX(-4px); }
            80%       { transform: translateX(4px); }
        }

        /* Form */
        .form-label {
            color: #94a3b8;
            font-size: 0.78rem;
            font-weight: 500;
            letter-spacing: 0.04em;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap .input-icon {
            color: var(--muted);
            left: 13px;
            pointer-events: none;
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.8rem;
            transition: color 0.2s;
        }

        .form-control {
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-family: inherit;
            font-size: 0.875rem;
            padding: 10px 12px 10px 36px;
            transition: border-color 0.2s, box-shadow 0.2s;
            width: 100%;
        }

        .form-control::placeholder { color: #3d4856; }

        .form-control:focus {
            background: var(--bg-input);
            border-color: var(--cyan);
            box-shadow: 0 0 0 3px rgba(0,229,255,0.12);
            color: var(--white);
            outline: none;
        }

        .form-control:focus + .input-icon,
        .input-wrap:focus-within .input-icon {
            color: var(--cyan);
        }

        .btn-login {
            background: var(--cyan);
            border: none;
            border-radius: 8px;
            color: #000;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.875rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            margin-top: 8px;
            padding: 11px;
            transition: background 0.2s, box-shadow 0.2s, transform 0.1s;
            width: 100%;
        }

        .btn-login:hover {
            background: var(--white);
            box-shadow: 0 0 20px var(--cyan-glow);
        }

        .btn-login:active {
            transform: scale(0.98);
        }

        .btn-login .btn-icon { margin-right: 8px; }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-card">
            <div class="login-logo">
                <div class="login-logo-icon"><i class="fas fa-bolt"></i></div>
                <div class="login-logo-name">Adeptio</div>
                <div class="login-logo-sub">Panneau d'administration</div>
            </div>

            <?php if ($error): ?>
                <div class="login-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="mb-3">
                    <label class="form-label" for="email">Adresse email</label>
                    <div class="input-wrap">
                        <input class="form-control" id="email" type="email" name="email"
                               value="<?= e($_POST['email'] ?? '') ?>" placeholder="admin@adeptio.ma" required autofocus>
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="password">Mot de passe</label>
                    <div class="input-wrap">
                        <input class="form-control" id="password" type="password" name="password"
                               placeholder="••••••••" required>
                        <i class="fas fa-lock input-icon"></i>
                    </div>
                </div>

                <button class="btn-login" type="submit">
                    <i class="fas fa-sign-in-alt btn-icon"></i>Se connecter
                </button>
            </form>
        </div>
    </div>
</body>
</html>
