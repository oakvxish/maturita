<?php
session_start();
require_once __DIR__ . '/db.php';

$errore   = '';
$successo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username    = trim($_POST['username']    ?? '');
    $password    = $_POST['password']          ?? '';
    $nome_salone = trim($_POST['nome_salone'] ?? '');

    if (!$username || !$password || !$nome_salone) {
        $errore = 'tutti i campi sono obbligatori';
    } else {
        // ── 1. Genera slug univoco ───────────────────────────────────────────
        $slug_base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $nome_salone));
        $slug_base = trim($slug_base, '-');
        $slug = $slug_base;
        $n = 1;
        while ($conn->query("SELECT id FROM saloni WHERE slug='" . $conn->real_escape_string($slug) . "'")->num_rows > 0) {
            $slug = $slug_base . '-' . $n++;
        }

        // ── 2. Controlla username già in uso ─────────────────────────────────
        $chk = $conn->prepare('SELECT id FROM userdata WHERE username = ?');
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
                // ── 3. Crea salone ───────────────────────────────────────────
                // Nota: saloni non ha più user_id — il collegamento avviene
                // tramite userdata.salone_id e la pivot user_saloni
                $ns        = $conn->real_escape_string($nome_salone);
                $slug_safe = $conn->real_escape_string($slug);
                $conn->query("INSERT INTO saloni (nome_salone, slug) VALUES ('$ns', '$slug_safe')");
                $salone_id = (int)$conn->insert_id;

                // ── 4. Crea utente con salone_id ─────────────────────────────
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('INSERT INTO userdata (username, password, salone_id) VALUES (?, ?, ?)');
                $stmt->bind_param('ssi', $username, $hash, $salone_id);
                $stmt->execute();
                $user_id = (int)$conn->insert_id;
                $stmt->close();

                // ── 5. Collega utente↔salone nel pivot (ruolo proprietario) ──
                $conn->query("INSERT INTO user_saloni (user_id, salone_id, ruolo)
                              VALUES ($user_id, $salone_id, 'proprietario')");

                // ── 6. Impostazioni di default ───────────────────────────────
                $conn->query("INSERT INTO impostazioni (salone_id, chiave, valore) VALUES
                    ($salone_id, 'nome_salone', '$ns'),
                    ($salone_id, 'iva',         '22'),
                    ($salone_id, 'valuta',      'EUR'),
                    ($salone_id, 'tema',        'chiaro'),
                    ($salone_id, 'reminder_24h','1'),
                    ($salone_id, 'reminder_2h', '1')
                ");

                $conn->commit();
                $successo = "salone \"$nome_salone\" creato! ora puoi accedere.";

            } catch (Exception $e) {
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
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;1,400&family=Jost:wght@300;400;600&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{--bg:#111113;--surface:#1c1c1f;--border:#2e2e32;--text:#e4e4e7;--muted:#71717a;--accent:#9a79e8;--gold:#b5924c;--danger:#ef4444;--success:#22c55e}
  body{font-family:'Jost',sans-serif;background:var(--bg);color:var(--text);display:flex;align-items:center;justify-content:center;min-height:100vh}
  body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 60% 50% at 15% 50%,rgba(154,121,232,.1) 0%,transparent 70%);pointer-events:none}
  .wrap{width:100%;max-width:440px;padding:20px;z-index:10;animation:up .5s ease}
  @keyframes up{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
  .logo{text-align:center;margin-bottom:24px}
  .logo-icon{display:inline-flex;width:52px;height:52px;background:linear-gradient(135deg,var(--accent),#c084fc);border-radius:13px;align-items:center;justify-content:center;font-size:1.4rem;box-shadow:0 8px 20px rgba(154,121,232,.25);margin-bottom:10px}
  .card{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:34px;box-shadow:0 24px 70px rgba(0,0,0,.45)}
  h2{font-size:1.2rem;margin-bottom:6px}
  .sub{font-size:.85rem;color:var(--muted);margin-bottom:24px}
  .field{margin-bottom:18px}
  .field label{display:block;font-size:.7rem;text-transform:uppercase;color:var(--muted);margin-bottom:7px;letter-spacing:.1em}
  .field input{width:100%;padding:12px;background:var(--bg);border:1px solid var(--border);border-radius:9px;color:var(--text);font-family:inherit;transition:.2s}
  .field input:focus{border-color:var(--accent);outline:none;box-shadow:0 0 0 3px rgba(154,121,232,.12)}
  .field small{display:block;font-size:.75rem;color:var(--muted);margin-top:5px}
  .btn-reg{width:100%;padding:14px;background:linear-gradient(135deg,var(--accent),#a855f7);color:#fff;border:none;border-radius:9px;font-weight:600;cursor:pointer;text-transform:uppercase;letter-spacing:.07em;transition:.2s}
  .btn-reg:hover{filter:brightness(1.08);transform:translateY(-1px)}
  .err{background:rgba(239,68,68,.1);border:1px solid var(--danger);color:#fca5a5;padding:11px;border-radius:9px;font-size:.85rem;margin-bottom:18px}
  .ok{background:rgba(34,197,94,.1);border:1px solid var(--success);color:#86efac;padding:11px;border-radius:9px;font-size:.85rem;margin-bottom:18px}
  .footer{margin-top:22px;text-align:center;border-top:1px solid var(--border);padding-top:18px;font-size:.8rem}
  a{color:var(--muted);text-decoration:none;transition:.2s}
  a:hover{color:var(--gold)}
  .divider{height:1px;background:var(--border);margin:20px 0}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">
    <div class="logo-icon">✂</div>
    <div style="font-family:'Cormorant Garamond',serif;font-weight:300;font-size:1.4rem;color:var(--text)">Gestionale Salone</div>
  </div>
  <div class="card">
    <h2>registra il tuo salone</h2>
    <p class="sub">crea il tuo spazio personale in pochi secondi</p>
    <?php if ($errore): ?><div class="err">⚠ <?php echo htmlspecialchars($errore) ?></div><?php endif ?>
    <?php if ($successo): ?><div class="ok">✓ <?php echo htmlspecialchars($successo) ?> <a href="login.php" style="color:#86efac;font-weight:600">accedi ora →</a></div><?php endif ?>
    <?php if (!$successo): ?>
    <form method="POST">
      <div class="field">
        <label>nome del salone *</label>
        <input type="text" name="nome_salone" placeholder="es. Lumière Beauty" required autofocus value="<?php echo htmlspecialchars($_POST['nome_salone'] ?? '') ?>">
        <small>verrà usato anche come indirizzo pubblico prenotazioni</small>
      </div>
      <div class="divider"></div>
      <div class="field">
        <label>username amministratore *</label>
        <input type="text" name="username" placeholder="es. admin_lumiere" required value="<?php echo htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="field">
        <label>password *</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn-reg">crea salone e accedi</button>
    </form>
    <?php endif ?>
    <div class="footer">
      <a href="login.php">hai già un account? accedi →</a>
    </div>
  </div>
</div>
</body>
</html>