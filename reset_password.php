<?php
session_start();
require_once __DIR__ . '/includes/password_reset_lib.php';

if (!empty($_SESSION['logged_in']) && !empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$resetSession = leggi_sessione_reset_password();
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
            registra_evento_reset_password($conn, $userId, 'password_changed_via_recovery_code', 'success', 'password aggiornata tramite recovery code');
            pulisci_sessione_reset_password();
            $successo = 'password aggiornata correttamente. ora puoi accedere con la nuova password';
            $resetSession = null;
        } else {
            $errore = 'errore durante lâ€™aggiornamento password';
            registra_evento_reset_password($conn, (int) $resetSession['user_id'], 'password_changed_via_recovery_code', 'failed', 'update password fallito');
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
<body class="auth-page" data-theme="scuro">
    <video class="theme-video theme-video-light" autoplay muted loop playsinline preload="metadata">
        <source src="assets/beautifier-light-bg.mp4" type="video/mp4">
    </video>
    <video class="theme-video theme-video-dark" autoplay muted loop playsinline preload="metadata">
        <source src="assets/beautifier-light-bg.mp4" type="video/mp4">
    </video>
    <div class="theme-video-overlay" aria-hidden="true"></div>
    

    <div class="wrap auth-wrap-lg">
        <div class="card auth-card auth-card-spaced">
            <div class="brand">
                <img src="assets/logo.svg" class="auth-logo-img" alt="beautify">
                <div class="eyebrow">reset password</div>
            </div>

            <div class="stack-lg">
                <div>
                    <h1>Imposta una nuova password.</h1>
                </div>

                <?php if ($errore): ?>
                    <div class="err"><?php echo htmlspecialchars($errore); ?></div>
                <?php endif; ?>

                <?php if ($successo): ?>
                    <div class="msg"><?php echo htmlspecialchars($successo); ?></div>
                <?php endif; ?>

                <?php if ($resetSession): ?>
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

            <div class="footer footer-auth-clean footer-auth-links">
                <a href="password_forgot.php">torna al recupero</a>
                <a href="login.php">torna al login</a>
            </div>
        </div>
    </div>
</body>
</html>



