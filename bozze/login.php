<?php
session_start();
require_once __DIR__ . '/db.php';

if (!empty($_SESSION['logged_in']) && !empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

/*
normalizza il ruolo caricato in fase di login.
qualsiasi valore diverso da proprietario viene trattato come dipendente.
*/
function normalizza_ruolo_login(?string $ruolo): string
{
    if ($ruolo === 'proprietario') {
        return 'proprietario';
    }
    return 'dipendente';
}

$errore = '';
$nome_salone = 'salone';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificativo = trim($_POST['identificativo'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identificativo === '' || $password === '') {
        $errore = 'inserisci tutti i campi';
    } else {
        $stmt = $conn->prepare("SELECT id, username, email, password FROM userdata WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param('ss', $identificativo, $identificativo);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            $userId = (int) $user['id'];
            $saloni = [];
            $sqlSaloni = "
                SELECT
                    us.salone_id,
                    us.ruolo,
                    us.attivo,
                    s.nome_salone
                FROM user_saloni us
                JOIN saloni s ON s.id = us.salone_id
                WHERE us.user_id = $userId
                  AND us.attivo = 1
                ORDER BY (us.ruolo = 'proprietario') DESC, s.nome_salone ASC
            ";
            $resultSaloni = $conn->query($sqlSaloni);

            if ($resultSaloni) {
                while ($row = $resultSaloni->fetch_assoc()) {
                    $row['ruolo'] = normalizza_ruolo_login($row['ruolo'] ?? 'dipendente');
                    $saloni[] = $row;
                }
            }

            if (!$saloni) {
                $errore = 'nessun salone attivo associato a questo account';
            } else {
                $saloneCorrente = $saloni[0];
                session_regenerate_id(true);
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = $user['username'];
                $_SESSION['salone_id'] = (int) $saloneCorrente['salone_id'];
                $_SESSION['nome_salone'] = $saloneCorrente['nome_salone'];
                $_SESSION['salone_ruolo'] = $saloneCorrente['ruolo'];
                $_SESSION['saloni_abilitati'] = $saloni;
                header('Location: index.php');
                exit;
            }
        } else {
            $errore = 'credenziali non valide';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>beautifier - accesso</title>
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
            <h1>Beautifier</h1>
            <p class="sub">Accedi al gestionale Beautifier con le credenziali del tuo account.</p>
            <?php if ($errore): ?>
                <div class="err"><?php echo htmlspecialchars($errore); ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="field">
                    <label>username o email</label>
                    <input type="text" name="identificativo" required autofocus>
                </div>
                <div class="field">
                    <label>password</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="btn">accedi</button>
            </form>
            <div class="footer footer-split">
                <a href="registrazione.php">Crea un nuovo salone</a>
                <a href="password_forgot.php">Password dimenticata?</a>
            </div>
        </div>
    </div>
</body>
</html>
