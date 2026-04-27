<?php
session_start();
require_once __DIR__ . '/includes/password_reset_lib.php';

if (!empty($_SESSION['logged_in']) && !empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

pulisci_sessione_reset_password();

$errore = '';
$identificativo = trim($_POST['identificativo'] ?? '');
$recoveryCode = trim($_POST['recovery_code'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($identificativo === '' || $recoveryCode === '') {
        $errore = 'inserisci username o email e un recovery code';
    } else {
        $user = trova_utente_per_accesso($conn, $identificativo);
        if (!$user) {
            $errore = 'account non trovato';
            registra_evento_reset_password($conn, null, 'recovery_lookup', 'failed', 'account non trovato per identificativo');
        } elseif (!utente_ha_codici_recupero_attivi($conn, (int) $user['id'])) {
            $errore = 'questo account non ha recovery code attivi';
            registra_evento_reset_password($conn, (int) $user['id'], 'recovery_lookup', 'failed', 'nessun recovery code attivo');
        } elseif (!verifica_e_consuma_codice_recupero($conn, (int) $user['id'], $recoveryCode)) {
            $errore = 'recovery code non valido';
        } else {
            avvia_sessione_reset_password($user);
            registra_evento_reset_password($conn, (int) $user['id'], 'password_reset_access_granted', 'success', 'accesso a reset_password.php con recovery code');
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
                <div class="eyebrow">recupero</div>
            </div>

            <div class="stack-lg">
                <div>
                    <h1>Recupera lâ€™accesso con un codice.</h1>
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
            </div>

            <div class="footer footer-auth-clean footer-auth-links">
                <a href="login.php">torna al login</a>
                            </div>
        </div>
    </div>
</body>
</html>



