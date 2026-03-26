<?php
session_start();
require_once __DIR__ . '/password_reset_lib.php';

if (!empty($_SESSION['logged_in']) && !empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

pr_clear_reset_session();

$errore = '';
$identificativo = trim($_POST['identificativo'] ?? '');
$recoveryCode = trim($_POST['recovery_code'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($identificativo === '' || $recoveryCode === '') {
        $errore = 'inserisci username o email e un recovery code';
    } else {
        $user = pr_find_user($conn, $identificativo);
        if (!$user) {
            $errore = 'account non trovato';
            pr_log_event($conn, null, 'recovery_lookup', 'failed', 'account non trovato per identificativo');
        } elseif (!pr_user_has_active_codes($conn, (int) $user['id'])) {
            $errore = 'questo account non ha recovery code attivi';
            pr_log_event($conn, (int) $user['id'], 'recovery_lookup', 'failed', 'nessun recovery code attivo');
        } elseif (!pr_consume_recovery_code($conn, (int) $user['id'], $recoveryCode)) {
            $errore = 'recovery code non valido';
        } else {
            pr_start_reset_session($user);
            pr_log_event($conn, (int) $user['id'], 'password_reset_access_granted', 'success', 'accesso a reset_password.php con recovery code');
            header('Location: reset_password.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>recupero password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Victor+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/salone.css">
</head>
<body class="auth-page" data-theme="chiaro">
    <video class="theme-video theme-video-light" autoplay muted loop playsinline preload="metadata">
        <source src="assets/beautifier-light-bg.mp4" type="video/mp4">
    </video>
    <video class="theme-video theme-video-dark" autoplay muted loop playsinline preload="metadata">
        <source src="assets/beautifier-dark-bg.mp4" type="video/mp4">
    </video>
    <div class="theme-video-overlay" aria-hidden="true"></div>
    <button type="button" class="theme-float" data-theme-toggle><span class="theme-toggle-text">tema scuro</span></button>
    <script src="assets/theme.js"></script>

    <div class="wrap auth-wrap-lg">
        <div class="card auth-card auth-card-spaced">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="8" y="8" width="48" height="48" rx="16" stroke="currentColor" stroke-width="2.4"/>
                        <path d="M24 20H37C43.627 20 49 25.373 49 32C49 38.627 43.627 44 37 44H24V20Z" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"/>
                        <path d="M24 32H36.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                        <path d="M24 44V20" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="eyebrow">recupero accesso</div>
            </div>

            <div class="stack-lg">
                <div>
                    <h1>Usa un recovery code</h1>
                    <p class="sub">Inserisci il recovery code che hai salvato in precedenza per poter impostare una nuova password.</p>
                </div>

                <?php if ($errore): ?>
                    <div class="err"><?php echo htmlspecialchars($errore); ?></div>
                <?php endif; ?>

                <form method="post" class="stack-md">
                    <div class="field">
                        <label>username o email</label>
                        <input type="text" name="identificativo" value="<?php echo htmlspecialchars($identificativo); ?>" required autofocus>
                    </div>
                    <div class="field">
                        <label>recovery code</label>
                        <input type="text" name="recovery_code" value="<?php echo htmlspecialchars($recoveryCode); ?>" placeholder="ABCD-EFGH-JKLM" required>
                    </div>
                    <button type="submit" class="btn">verifica recovery code</button>
                </form>

                <div class="surface-soft stack-md">
                    <div>
                        <div class="metric-label">come funziona</div>
                        <div class="helper-text mt-8">Ogni recovery code è monouso, viene salvato solo in forma hashata nel database e dopo l’uso non è più riutilizzabile.</div>
                    </div>
                    <div>
                        <div class="metric-label">se sei già dentro al gestionale</div>
                        <div class="helper-text mt-8">Apri impostazioni e rigenera un nuovo set di codici da salvare offline.</div>
                    </div>
                </div>
            </div>

            <div class="footer footer-split">
                <a href="login.php">torna al login</a>
                <span class="helper-text">usa un codice salvato in precedenza</span>
            </div>
        </div>
    </div>
</body>
</html>
