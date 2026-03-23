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
            $errore = 'username già in uso, scegline un altro';
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
                $stmtUtente = $conn->prepare(
                    'INSERT INTO userdata (username, password, salone_id) VALUES (?, ?, ?)'
                );
                $stmtUtente->bind_param('ssi', $username, $hash, $salone_id);
                $stmtUtente->execute();
                $user_id = (int) $conn->insert_id;
                $stmtUtente->close();

                $stmtPivot = $conn->prepare(
                    'INSERT INTO user_saloni (user_id, salone_id, ruolo, attivo) VALUES (?, ?, ?, 1)'
                );
                $ruolo = 'proprietario';
                $stmtPivot->bind_param('iis', $user_id, $salone_id, $ruolo);
                $stmtPivot->execute();
                $stmtPivot->close();

                $impostazioni = [
                    ['nome_salone', $nome_salone],
                    ['iva', '22'],
                    ['valuta', 'EUR'],
                    ['tema', 'chiaro'],
                    ['reminder_24h', '1'],
                    ['reminder_2h', '1'],
                ];

                $stmtCfg = $conn->prepare(
                    'INSERT INTO impostazioni (salone_id, chiave, valore) VALUES (?, ?, ?)'
                );
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
    <title>registra salone</title>
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
            --success: #22c55e;
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

        .wrap {
            width: 100%;
            max-width: 440px;
            padding: 20px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, .5);
        }

        h1 {
            font-family: 'Cormorant Garamond', serif;
            font-weight: 300;
            margin-bottom: 6px;
            text-align: center;
        }

        .sub {
            font-size: .85rem;
            color: var(--muted);
            margin-bottom: 28px;
            text-align: center;
        }

        .field {
            margin-bottom: 18px;
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
        }

        .field input:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 0 4px rgba(154, 121, 232, .1);
        }

        .btn {
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

        .btn:hover {
            filter: brightness(1.08);
        }

        .msg,
        .err {
            padding: 11px;
            border-radius: 10px;
            font-size: .85rem;
            margin-bottom: 20px;
        }

        .msg {
            background: rgba(34, 197, 94, .12);
            border: 1px solid var(--success);
            color: #86efac;
        }

        .err {
            background: rgba(239, 68, 68, .1);
            border: 1px solid var(--danger);
            color: #fca5a5;
        }

        .footer {
            margin-top: 24px;
            border-top: 1px solid var(--border);
            padding-top: 18px;
            font-size: .8rem;
            text-align: center;
        }

        a {
            color: var(--muted);
            text-decoration: none;
        }

        a:hover {
            color: var(--gold);
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="card">
            <h1>crea il tuo salone</h1>
            <p class="sub">apri accedi subito al gestionale</p>

            <?php if ($errore): ?>
                <div class="err">⚠ <?php echo htmlspecialchars($errore); ?></div>
            <?php endif; ?>

            <?php if ($successo): ?>
                <div class="msg">✓ <?php echo htmlspecialchars($successo); ?></div>
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
                <a href="login.php">hai già un account? accedi -></a>
            </div>
        </div>
    </div>
</body>

</html>