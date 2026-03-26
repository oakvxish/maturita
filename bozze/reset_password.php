<?php
session_start();
require_once __DIR__ . '/password_reset_lib.php';

if (!empty($_SESSION['logged_in']) && !empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$resetSession = pr_get_reset_session();
$errore = '';
$successo = '';

if (!$resetSession) {
    $errore = 'sessione di recupero non valida o scaduta';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resetSession) {
    $password = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password_conferma'] ?? '');

    if ($password === '' || $password2 === '') {
        $errore = 'compila entrambi i campi password';
    } elseif ($password !== $password2) {
        $errore = 'le due password non coincidono';
    } elseif (strlen($password) < 6) {
        $errore = 'la password deve avere almeno 6 caratteri';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE userdata SET password = ? WHERE id = ?');
        if ($stmt) {
            $userId = (int) $resetSession['user_id'];
            $stmt->bind_param('si', $hash, $userId);
            $stmt->execute();
            $stmt->close();
            pr_log_event($conn, $userId, 'password_changed_via_recovery_code', 'success', 'password aggiornata tramite recovery code');
            pr_clear_reset_session();
            $successo = 'password aggiornata correttamente. ora puoi accedere con la nuova password';
            $resetSession = null;
        } else {
            $errore = 'errore durante l’aggiornamento password';
            pr_log_event($conn, (int) $resetSession['user_id'], 'password_changed_via_recovery_code', 'failed', 'update password fallito');
        }
    }
}

$remainingSeconds = $resetSession ? max(0, (int) $resetSession['expires_at'] - time()) : 0;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>nuova password</title>
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
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="10" cy="10" r="6.75" stroke="currentColor" stroke-width="1.25"/>
                        <path d="M10 2.75V8.25" stroke="currentColor" stroke-width="1.25" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="eyebrow">nuova password</div>
            </div>

            <div class="stack-lg">
                <div>
                    <h1>Imposta una nuova password</h1>
                    <p class="sub">Hai già superato il controllo con recovery code. Questa sessione di recupero resta valida per circa <?php echo (int) ceil($remainingSeconds / 60); ?> minuti.</p>
                </div>

                <?php if ($errore): ?>
                    <div class="err"><?php echo htmlspecialchars($errore); ?></div>
                <?php endif; ?>

                <?php if ($successo): ?>
                    <div class="msg"><?php echo htmlspecialchars($successo); ?></div>
                <?php endif; ?>

                <?php if ($resetSession): ?>
                    <div class="summary reset-summary">
                        <div class="summary-grid">
                            <div>
                                <div class="metric-label">account</div>
                                <div class="detail-value"><?php echo htmlspecialchars($resetSession['username']); ?></div>
                            </div>
                            <div>
                                <div class="metric-label">sessione reset</div>
                                <div class="detail-value">attiva</div>
                            </div>
                        </div>
                    </div>

                    <form method="post" class="stack-md">
                        <div class="field">
                            <label>nuova password</label>
                            <input type="password" name="password" required>
                        </div>
                        <div class="field">
                            <label>conferma password</label>
                            <input type="password" name="password_conferma" required>
                        </div>
                        <button type="submit" class="btn">aggiorna password</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="footer footer-split">
                <a href="password_forgot.php">torna al recupero</a>
                <a href="login.php">torna al login</a>
            </div>
        </div>
    </div>
</body>
</html>
