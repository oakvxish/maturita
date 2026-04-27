<?php
session_start();
require_once __DIR__ . '/includes/db.php';

if (!empty($_SESSION['logged_in']) && !empty($_SESSION['user_id'])) {
    header('Location: impostazioni.php');
    exit;
}

/**
 * Normalizza il ruolo ottenuto durante il login.
 *
 * @param string|null $ruolo Valore grezzo dal DB.
 * @return string `proprietario` o `dipendente`.
 */
function normalizza_ruolo_login(?string $ruolo): string
{
    if ($ruolo === 'proprietario') {
        return 'proprietario';
    }
    return 'dipendente';
}

$errore = '';
$identificativo = trim($_POST['identificativo'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                header('Location: impostazioni.php');
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
    <title>beautify - accesso</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Victor+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/salone.css">
</head>
<body class="auth-page auth-page-login" data-theme="scuro">
    <video class="theme-video theme-video-light" autoplay muted loop playsinline preload="metadata" aria-hidden="true">
    <source src="assets/beautifier-light-bg.mp4" type="video/mp4">
</video>

<video class="theme-video theme-video-dark" autoplay muted loop playsinline preload="metadata" aria-hidden="true">
    <source src="assets/beautifier-light-bg.mp4" type="video/mp4">
</video>

<div class="theme-video-overlay" aria-hidden="true"></div>
    <div class="wrap auth-shell auth-shell-login">
        <section class="auth-spotlight auth-spotlight-login">
            <div class="brand auth-brand-minimal">
                <img src="assets/logo.svg" class="auth-logo-img" alt="beautify">
            </div>

            <div class="stack-md auth-hero-copy">
                <div class="eyebrow">area riservata</div>
                <h1>Effettua l'accesso</h1>
            </div>
        </section>

        <div class="card auth-card auth-card-login auth-card-compact">
            <div class="stack-md auth-card-head">
                <div class="eyebrow">login</div>
                <h2>Entra in Beautify</h2>
            </div>

            <?php if ($errore): ?>
                <div class="err"><?php echo htmlspecialchars($errore); ?></div>
            <?php endif; ?>

            <form method="post" class="stack-md auth-form auth-form-compact">
                <div class="field">
                    <label for="identificativo">username o email</label>
                    <input id="identificativo" type="text" name="identificativo" value="<?php echo htmlspecialchars($identificativo); ?>" required autofocus autocomplete="username" spellcheck="false">
                </div>

                <div class="field">
                    <div class="auth-field-head">
                        <label for="password">password</label>
                        <a href="password_forgot.php">password dimenticata?</a>
                    </div>
                    <input id="password" type="password" name="password" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn-block">accedi</button>
            </form>

            <div class="footer footer-auth-clean">
                <a href="registrazione.php">crea un nuovo salone</a>
            </div>
        </div>
    </div>
</body>
</html>


