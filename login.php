<?php
session_start();
require_once __DIR__ . '/db.php';

$errore = '';
$nome_salone = 'Salone di prova';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($username && $password) {
    $stmt = $conn->prepare("
            SELECT u.id, u.password, u.salone_id, s.nome_salone AS nome_salone
            FROM userdata u
            JOIN saloni s ON s.id = u.salone_id
            WHERE u.username = ?
        ");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {
      $_SESSION['logged_in'] = true;
      $_SESSION['user_id'] = $user['id'];
      $_SESSION['username'] = $username;
      $_SESSION['salone_id'] = $user['salone_id'];
      $_SESSION['nome_salone'] = $user['nome_salone'];
      header('Location: index.php');
      exit;
    }
    $errore = 'credenziali non valide';
  } else {
    $errore = 'inserisci tutti i campi';
  }
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>accesso — gestionale salone</title>
  <link
    href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;1,400&family=Jost:wght@300;400;600&display=swap"
    rel="stylesheet">
  <style>
    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0
    }

    :root {
      --bg: #111113;
      --surface: #1c1c1f;
      --border: #2e2e32;
      --text: #e4e4e7;
      --muted: #71717a;
      --accent: #9a79e8;
      --gold: #b5924c;
      --danger: #ef4444
    }

    body {
      font-family: 'Jost', sans-serif;
      background: var(--bg);
      color: var(--text);
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh
    }

    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background: radial-gradient(circle at 85% 20%, rgba(154, 121, 232, 0.08) 0%, transparent 55%);
      pointer-events: none
    }

    .wrap {
      width: 100%;
      max-width: 400px;
      padding: 20px;
      z-index: 10;
      animation: up .5s ease
    }

    @keyframes up {
      from {
        opacity: 0;
        transform: translateY(10px)
      }

      to {
        opacity: 1;
        transform: translateY(0)
      }
    }

    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 40px;
      box-shadow: 0 30px 60px rgba(0, 0, 0, .5);
      text-align: center
    }

    .logo {
      display: inline-flex;
      width: 54px;
      height: 54px;
      background: linear-gradient(135deg, var(--accent), #c084fc);
      border-radius: 14px;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-bottom: 20px;
      box-shadow: 0 10px 25px rgba(154, 121, 232, .2)
    }

    h2 {
      font-size: 1.3rem;
      font-weight: 500;
      margin-bottom: 6px
    }

    .sub {
      font-size: .85rem;
      color: var(--muted);
      margin-bottom: 28px
    }

    .field {
      margin-bottom: 18px;
      text-align: left
    }

    .field label {
      display: block;
      font-size: .7rem;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 7px;
      letter-spacing: .1em
    }

    .field input {
      width: 100%;
      padding: 13px;
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 10px;
      color: var(--text);
      font-family: inherit;
      transition: .2s
    }

    .field input:focus {
      border-color: var(--accent);
      outline: none;
      box-shadow: 0 0 0 4px rgba(154, 121, 232, .1)
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
      margin-top: 8px
    }

    .btn-login:hover {
      filter: brightness(1.1);
      transform: translateY(-2px)
    }

    .err {
      background: rgba(239, 68, 68, .1);
      border: 1px solid var(--danger);
      color: #fca5a5;
      padding: 11px;
      border-radius: 10px;
      font-size: .85rem;
      margin-bottom: 20px;
      text-align: left
    }

    .footer {
      margin-top: 28px;
      border-top: 1px solid var(--border);
      padding-top: 18px;
      font-size: .8rem
    }

    a {
      color: var(--muted);
      text-decoration: none;
      transition: .2s
    }

    a:hover {
      color: var(--gold)
    }
  </style>
</head>

<body>
  <div class="wrap">
    <div class="card">
      <div class="logo">✂</div>
      <h1 style="font-family:'Cormorant Garamond',serif;font-weight:300;margin-bottom:5px">
        <?php echo htmlspecialchars($nome_salone) ?></h1>
      <p class="sub">accedi al tuo gestionale</p>
      <?php if ($errore): ?>
        <div class="err">⚠ <?php echo htmlspecialchars($errore) ?></div><?php endif ?>
      <form method="POST">
        <div class="field">
          <label>username</label>
          <input type="text" name="username" required autofocus>
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