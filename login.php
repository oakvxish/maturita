<?php
session_start();
require_once __DIR__ . '/db.php';

if (!empty($_SESSION['logged_in']) && !empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

function normalizza_ruolo_login(?string $ruolo): string
{
    if ($ruolo === 'proprietario') {
        return 'proprietario';
    }

    return 'dipendente';
}

$errore = '';
$nome_salone = 'beautify';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificativo = trim($_POST['identificativo'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identificativo === '' || $password === '') {
        $errore = 'inserisci tutti i campi';
    } else {
        $stmt = $conn->prepare(
            "SELECT id, username, email, password FROM userdata WHERE username = ? OR email = ? LIMIT 1"
        );
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
    <title>accesso - beautify</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;1,400&family=Jost:wght@300;400;600&display=swap"
        rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg: #111113;
            --surface: #1c1c1f;
            --border: #2e2e32;
            --text: #e4e4e7;
            --muted: #71717a;
            --accent: #9a79e8;
            --gold: #b5924c;
            --danger: #ef4444;
        }

        body {
            font-family: 'Jost', sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at 85% 20%, rgba(154, 121, 232, 0.08) 0%, transparent 55%);
            pointer-events: none;
        }

        .wrap {
            width: 100%;
            max-width: 400px;
            padding: 20px;
            z-index: 10;
            animation: up .5s ease;
        }

        @keyframes up {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, .5);
            text-align: center;
        }

        .logo {
            display: inline-flex;
            width: 54px;
            height: 54px;
            background: rgba(187, 0, 255, 0.39);
            border-radius: 14px;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 20px;
            box-shadow: 0 10px 25px rgba(154, 121, 232, .2);
            backdrop-filter: blur(10px);
        }

        h1 {
            font-family: 'Cormorant Garamond', serif;
            font-weight: 300;
            margin-bottom: 5px;
        }

        .sub {
            font-size: .85rem;
            color: var(--muted);
            margin-bottom: 28px;
        }

        .field {
            margin-bottom: 18px;
            text-align: left;
        }

        .field label {
            display: block;
            font-size: .7rem;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 7px;
            letter-spacing: .1em;
        }

        .field input {
            width: 100%;
            padding: 13px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            font-family: inherit;
            transition: .2s;
        }

        .field input:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 0 4px rgba(154, 121, 232, .1);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--accent), #a855f7);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: .08em;
            transition: .2s;
            margin-top: 8px;
        }

        .btn-login:hover {
            filter: brightness(1.1);
            transform: translateY(-2px);
        }

        .err {
            background: rgba(239, 68, 68, .1);
            border: 1px solid var(--danger);
            color: #fca5a5;
            padding: 11px;
            border-radius: 10px;
            font-size: .85rem;
            margin-bottom: 20px;
            text-align: left;
        }

        .footer {
            margin-top: 28px;
            border-top: 1px solid var(--border);
            padding-top: 18px;
            font-size: .8rem;
        }

        a {
            color: var(--muted);
            text-decoration: none;
            transition: .2s;
        }

        a:hover {
            color: var(--gold);
        }

        body {
            font-family: 'Jost', sans-serif;
            background: url('https://4kwallpapers.com/images/wallpapers/xiaomi-pad-7-pro-2560x1440-19801.jpg') no-repeat center center fixed;
            background-size: cover;
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1;
        }

        .wrap {
            width: 100%;
            max-width: 400px;
            padding: 20px;
            z-index: 10;
            position: relative;
            animation: up .5s ease;
        }

        .card {
            background: rgba(0, 0, 0, 0.41);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="card">
            <div class="logo">🪮</div>
            <h1><?php echo htmlspecialchars($nome_salone); ?></h1>
            <p class="sub">accedi al tuo gestionale</p>
            <?php if ($errore): ?>
                <div class="err">⚠ <?php echo htmlspecialchars($errore); ?></div>
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
                <button type="submit" class="btn-login">accedi</button>
            </form>
            <div class="footer">
                <a href="registrazione.php">nessun account? registra il tuo salone -></a>
            </div>
        </div>
    </div>
</body>

</html>