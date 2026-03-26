<?php
session_start();
require_once __DIR__ . '/db.php';

$errore = '';
$successo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $nome_salone = trim($_POST['nome_salone'] ?? '');

    if ($username === '' || $password === '' || $nome_salone === '') {
        $errore = 'tutti i campi sono obbligatori';
    } else {
        $slug_base = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $nome_salone));
        $slug_base = trim($slug_base, '-');
        if ($slug_base === '') {
            $slug_base = 'salone';
        }

        $slug = $slug_base;
        $n = 1;
        while ($conn->query("SELECT id FROM saloni WHERE slug='" . $conn->real_escape_string($slug) . "'")->num_rows > 0) {
            $slug = $slug_base . '-' . $n;
            $n++;
        }

        $chk = $conn->prepare('SELECT id FROM userdata WHERE username = ? LIMIT 1');
        $chk->bind_param('s', $username);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $errore = 'username gia in uso';
            $chk->close();
        } else {
            $chk->close();
            $conn->begin_transaction();

            try {
                $stmtSalone = $conn->prepare('INSERT INTO saloni (nome_salone, slug) VALUES (?, ?)');
                $stmtSalone->bind_param('ss', $nome_salone, $slug);
                $stmtSalone->execute();
                $salone_id = (int) $conn->insert_id;
                $stmtSalone->close();

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmtUtente = $conn->prepare('INSERT INTO userdata (username, password, salone_id) VALUES (?, ?, ?)');
                $stmtUtente->bind_param('ssi', $username, $hash, $salone_id);
                $stmtUtente->execute();
                $user_id = (int) $conn->insert_id;
                $stmtUtente->close();

                $stmtPivot = $conn->prepare('INSERT INTO user_saloni (user_id, salone_id, ruolo, attivo) VALUES (?, ?, ?, 1)');
                $ruolo = 'proprietario';
                $stmtPivot->bind_param('iis', $user_id, $salone_id, $ruolo);
                $stmtPivot->execute();
                $stmtPivot->close();

                $impostazioni = [
                    ['nome_salone', $nome_salone],
                    ['iva', '22'],
                    ['valuta', 'EUR'],
                    ['tema', 'chiaro'],
                ];

                $stmtCfg = $conn->prepare('INSERT INTO impostazioni (salone_id, chiave, valore) VALUES (?, ?, ?)');
                foreach ($impostazioni as $impostazione) {
                    $chiave = $impostazione[0];
                    $valore = $impostazione[1];
                    $stmtCfg->bind_param('iss', $salone_id, $chiave, $valore);
                    $stmtCfg->execute();
                }
                $stmtCfg->close();

                $conn->commit();
                $successo = 'salone creato correttamente. ora puoi accedere.';
            } catch (Throwable $e) {
                $conn->rollback();
                $errore = 'errore durante la registrazione';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>beautifier - nuovo salone</title>
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
    <div class="wrap">
        <div class="card">
            <div class="brand">
                <div class="brand-mark" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="8" y="8" width="48" height="48" rx="16" stroke="currentColor" stroke-width="2.4"/>
                        <path d="M24 20H37C43.627 20 49 25.373 49 32C49 38.627 43.627 44 37 44H24V20Z" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"/>
                        <path d="M24 32H36.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                        <path d="M24 44V20" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="eyebrow">beautifier</div>
            </div>
            <h1>Crea il tuo salone</h1>
            <p class="sub">Crea un nuovo salone dentro Beautifier e collega subito il proprietario.</p>
            <?php if ($errore): ?>
                <div class="err"><?php echo htmlspecialchars($errore); ?></div>
            <?php endif; ?>
            <?php if ($successo): ?>
                <div class="msg"><?php echo htmlspecialchars($successo); ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="field">
                    <label>nome salone</label>
                    <input type="text" name="nome_salone" required>
                </div>
                <div class="field">
                    <label>username proprietario</label>
                    <input type="text" name="username" required>
                </div>
                <div class="field">
                    <label>password</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="btn">registra salone</button>
            </form>
            <div class="footer">
                <a href="login.php">Hai gia un account? Accedi</a>
            </div>
        </div>
    </div>
</body>
</html>
