<!DOCTYPE html>
<?php
require_once __DIR__ . '/db.php';

// Identifica il salone tramite ?s=slug
$slug = $conn->real_escape_string(trim($_GET['s'] ?? ''));
if (!$slug) {
    die('<p style="font-family:sans-serif;padding:40px">Parametro salone mancante. Usa <code>?s=slug-salone</code></p>');
}

$salone = $conn->query("SELECT * FROM saloni WHERE slug='$slug'")->fetch_assoc();
if (!$salone) {
    die('<p style="font-family:sans-serif;padding:40px">Salone non trovato.</p>');
}

$sid         = (int)$salone['id'];
$nome_salone = htmlspecialchars($salone['nome']);

// Recupera servizi del salone
$servizi = $conn->query("SELECT id,nome,categoria,durata_minuti,prezzo FROM servizi WHERE salone_id=$sid ORDER BY categoria,nome")->fetch_all(MYSQLI_ASSOC);

$errore  = '';
$successo = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome     = trim($_POST['_nome']     ?? '');
    $cognome  = trim($_POST['_cognome']  ?? '');
    $telefono = trim($_POST['_telefono'] ?? '');
    $email    = trim($_POST['_email']    ?? '');
    $svid     = (int)($_POST['servizio_id'] ?? 0);
    $data_raw = trim($_POST['_data']     ?? '');
    $ora      = trim($_POST['_ora']      ?? '');
    $note     = trim($_POST['note']      ?? '');

    if (!$nome || !$cognome || !$svid || !$data_raw) {
        $errore = 'compila tutti i campi obbligatori';
    } else {
        // Verifica servizio appartiene al salone
        $srv = $conn->query("SELECT id FROM servizi WHERE id=$svid AND salone_id=$sid")->fetch_assoc();
        if (!$srv) { $errore = 'servizio non valido'; }
        else {
            // Crea o aggiorna cliente
            $n  = $conn->real_escape_string($nome);
            $co = $conn->real_escape_string($cognome);
            $te = $conn->real_escape_string($telefono);
            $em = $conn->real_escape_string($email);
            $no = $conn->real_escape_string($note);

            // Cerca cliente esistente per telefono o email
            $cliente = null;
            if ($telefono) {
                $cliente = $conn->query("SELECT id FROM clienti WHERE salone_id=$sid AND telefono='$te'")->fetch_assoc();
            }
            if (!$cliente && $email) {
                $cliente = $conn->query("SELECT id FROM clienti WHERE salone_id=$sid AND email='$em'")->fetch_assoc();
            }

            if ($cliente) {
                $cid = (int)$cliente['id'];
            } else {
                $conn->query("INSERT INTO clienti (salone_id,nome,cognome,telefono,email,note) VALUES ($sid,'$n','$co','$te','$em','$no')");
                $cid = (int)$conn->insert_id;
            }

            // Crea appuntamento in attesa
            $data_ora = $conn->real_escape_string($data_raw . ($ora ? ' '.$ora.':00' : ' 09:00:00'));
            $conn->query("INSERT INTO appuntamenti (salone_id,cliente_id,servizio_id,data_ora,stato,note)
                          VALUES ($sid,$cid,$svid,'$data_ora','attesa','$no')");

            $successo = true;
            // Passa ai dettagli per la conferma
            $nome_out     = htmlspecialchars($nome);
            $cognome_out  = htmlspecialchars($cognome);
            $telefono_out = htmlspecialchars($telefono);
            $ora_out      = htmlspecialchars($ora);
            $note_out     = htmlspecialchars($note);
            $servizio_out = htmlspecialchars($conn->query("SELECT nome FROM servizi WHERE id=$svid")->fetch_assoc()['nome'] ?? '');

            $ts = strtotime($data_raw);
            $giorni = ['domenica','lunedì','martedì','mercoledì','giovedì','venerdì','sabato'];
            $mesi   = ['','gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'];
            $data_fmt = $giorni[date('w',$ts)] . ' ' . date('j',$ts) . ' ' . $mesi[(int)date('n',$ts)] . ' ' . date('Y',$ts);
        }
    }
}
?>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Prenota — <?php echo $nome_salone ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;1,300;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{--cream:#f9f4ed;--warm:#fffdf9;--charcoal:#1c1917;--muted:#78716c;--gold:#b5924c;--gold-l:#e8d5b0;--border:#e8e0d5}
  body{font-family:'Jost',sans-serif;background:var(--warm);color:var(--charcoal);min-height:100vh}
  nav{height:64px;display:flex;align-items:center;justify-content:space-between;padding:0 clamp(20px,5vw,60px);background:rgba(249,244,237,.97);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100}
  .nav-logo{font-family:'Cormorant Garamond',serif;font-size:1.4rem;font-weight:300;letter-spacing:.1em;color:var(--charcoal);text-decoration:none}
  .nav-logo em{font-style:italic;color:var(--gold)}
  .wrap{max-width:760px;margin:0 auto;padding:clamp(32px,6vw,72px) clamp(20px,4vw,40px)}
  h1{font-family:'Cormorant Garamond',serif;font-size:clamp(2rem,4vw,3rem);font-weight:300;line-height:1.1;margin-bottom:8px}
  h1 em{font-style:italic;color:var(--gold)}
  .sub{color:var(--muted);font-size:.9rem;margin-bottom:36px;font-weight:300}
  .form-card{background:var(--warm);border:1px solid var(--border);padding:clamp(24px,4vw,44px)}
  .sezione{font-size:.65rem;letter-spacing:.3em;text-transform:uppercase;color:var(--gold);margin-bottom:16px;margin-top:28px}
  .sezione:first-child{margin-top:0}
  .riga{display:grid;grid-template-columns:1fr 1fr;gap:18px}
  .campo{margin-bottom:20px}
  .campo label{display:block;font-size:.7rem;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);margin-bottom:8px}
  .campo input,.campo select,.campo textarea{width:100%;padding:13px;border:1px solid var(--border);background:var(--cream);color:var(--charcoal);font-family:inherit;font-size:.9rem;transition:.2s}
  .campo input:focus,.campo select:focus,.campo textarea:focus{border-color:var(--gold);outline:none;box-shadow:0 0 0 3px rgba(181,146,76,.1)}
  .campo small{font-size:.75rem;color:var(--muted);margin-top:5px;display:block}
  .btn-prenota{width:100%;padding:16px;background:var(--charcoal);color:var(--cream);border:none;font-family:inherit;font-size:.75rem;letter-spacing:.2em;text-transform:uppercase;cursor:pointer;transition:.25s;margin-top:8px}
  .btn-prenota:hover{background:var(--gold)}
  .err{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);color:#991b1b;padding:14px;font-size:.88rem;margin-bottom:24px}
  /* Conferma */
  .conferma{background:var(--cream);border:1px solid var(--border);padding:clamp(28px,4vw,48px);text-align:center}
  .check{width:54px;height:54px;background:var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:1.4rem}
  .conferma h2{font-family:'Cormorant Garamond',serif;font-size:clamp(1.6rem,3vw,2.4rem);font-weight:300;margin-bottom:10px}
  .conferma h2 em{font-style:italic;color:var(--gold)}
  .conferma p{color:var(--muted);font-size:.88rem;line-height:1.7;font-weight:300;margin-bottom:24px}
  .dettagli{background:var(--warm);border:1px solid var(--border);padding:22px;text-align:left;margin-bottom:24px}
  .det-row{display:flex;gap:12px;align-items:flex-start;margin-bottom:14px}
  .det-row:last-child{margin-bottom:0}
  .det-label{font-size:.65rem;letter-spacing:.15em;text-transform:uppercase;color:var(--muted)}
  .det-val{font-size:.92rem;color:var(--charcoal)}
  .btn-back{display:inline-block;padding:13px 28px;background:var(--charcoal);color:var(--cream);text-decoration:none;font-size:.7rem;letter-spacing:.18em;text-transform:uppercase;transition:.2s}
  .btn-back:hover{background:var(--gold)}
  footer{margin-top:60px;padding:20px clamp(20px,5vw,60px);border-top:1px solid var(--border);font-size:.75rem;color:var(--muted);text-align:center}
  @media(max-width:600px){.riga{grid-template-columns:1fr}}
</style>
</head>
<body>

<nav>
  <span class="nav-logo"><?php echo $nome_salone ?></span>
</nav>

<div class="wrap">

<?php if ($successo): ?>
  <!-- CONFERMA -->
  <div class="conferma">
    <div class="check">✓</div>
    <h2>Grazie, <em><?php echo $nome_out ?></em>.</h2>
    <p>La tua richiesta è stata ricevuta. Ti contatteremo presto per confermarla.</p>
    <div class="dettagli">
      <div style="font-size:.65rem;letter-spacing:.3em;text-transform:uppercase;color:var(--gold);margin-bottom:16px">riepilogo prenotazione</div>
      <div class="det-row">
        <div style="flex-shrink:0">✦</div>
        <div><div class="det-label">trattamento</div><div class="det-val"><?php echo $servizio_out ?></div></div>
      </div>
      <div class="det-row">
        <div style="flex-shrink:0">📅</div>
        <div><div class="det-label">data</div><div class="det-val"><?php echo $data_fmt ?></div></div>
      </div>
      <?php if ($ora_out): ?>
      <div class="det-row">
        <div style="flex-shrink:0">⏰</div>
        <div><div class="det-label">ora richiesta</div><div class="det-val"><?php echo $ora_out ?></div></div>
      </div>
      <?php endif ?>
      <div class="det-row">
        <div style="flex-shrink:0">👤</div>
        <div><div class="det-label">cliente</div><div class="det-val"><?php echo $nome_out.' '.$cognome_out ?></div></div>
      </div>
      <?php if ($telefono_out): ?>
      <div class="det-row">
        <div style="flex-shrink:0">📞</div>
        <div><div class="det-label">telefono</div><div class="det-val"><?php echo $telefono_out ?></div></div>
      </div>
      <?php endif ?>
    </div>
    <a href="prenota.php?s=<?php echo urlencode($slug) ?>" class="btn-back">nuova prenotazione</a>
  </div>

<?php else: ?>
  <!-- FORM -->
  <h1>Prenota il tuo <em>appuntamento</em></h1>
  <p class="sub">presso <?php echo $nome_salone ?></p>

  <?php if ($errore): ?><div class="err">⚠ <?php echo htmlspecialchars($errore) ?></div><?php endif ?>

  <?php if (!$servizi): ?>
    <div class="form-card"><p style="color:var(--muted)">Nessun servizio disponibile al momento.</p></div>
  <?php else: ?>
  <div class="form-card">
    <form method="POST" action="prenota.php?s=<?php echo urlencode($slug) ?>">

      <div class="sezione">i tuoi dati</div>
      <div class="riga">
        <div class="campo">
          <label>nome *</label>
          <input type="text" name="_nome" value="<?php echo htmlspecialchars($_POST['_nome'] ?? '') ?>" required>
        </div>
        <div class="campo">
          <label>cognome *</label>
          <input type="text" name="_cognome" value="<?php echo htmlspecialchars($_POST['_cognome'] ?? '') ?>" required>
        </div>
      </div>
      <div class="riga">
        <div class="campo">
          <label>telefono</label>
          <input type="tel" name="_telefono" value="<?php echo htmlspecialchars($_POST['_telefono'] ?? '') ?>">
        </div>
        <div class="campo">
          <label>email</label>
          <input type="email" name="_email" value="<?php echo htmlspecialchars($_POST['_email'] ?? '') ?>">
        </div>
      </div>

      <div class="sezione">il tuo appuntamento</div>
      <div class="campo">
        <label>trattamento *</label>
        <select name="servizio_id" required>
          <option value="">scegli il trattamento</option>
          <?php
          $cat_corrente = '';
          foreach ($servizi as $s):
            if ($s['categoria'] !== $cat_corrente) {
                if ($cat_corrente) echo '</optgroup>';
                echo '<optgroup label="' . htmlspecialchars($s['categoria']) . '">';
                $cat_corrente = $s['categoria'];
            }
          ?>
          <option value="<?php echo $s['id'] ?>" <?php echo ($_POST['servizio_id'] ?? '')==$s['id']?'selected':'' ?>>
            <?php echo htmlspecialchars($s['nome']) ?> — <?php echo $s['durata_minuti'] ?>min — €<?php echo number_format($s['prezzo'],2,',','.') ?>
          </option>
          <?php endforeach; if ($cat_corrente) echo '</optgroup>'; ?>
        </select>
      </div>
      <div class="riga">
        <div class="campo">
          <label>data preferita *</label>
          <input type="date" name="_data" value="<?php echo htmlspecialchars($_POST['_data'] ?? '') ?>" min="<?php echo date('Y-m-d') ?>" required>
        </div>
        <div class="campo">
          <label>ora preferita</label>
          <input type="time" name="_ora" value="<?php echo htmlspecialchars($_POST['_ora'] ?? '') ?>" min="08:00" max="20:00">
          <small>confermeremo la disponibilità esatta</small>
        </div>
      </div>
      <div class="campo">
        <label>note aggiuntive</label>
        <textarea name="note" rows="3" placeholder="allergie, preferenze, richieste particolari…"><?php echo htmlspecialchars($_POST['note'] ?? '') ?></textarea>
      </div>

      <button type="submit" class="btn-prenota">invia richiesta di prenotazione</button>
    </form>
  </div>
  <?php endif ?>

<?php endif ?>
</div>

<footer>© <?php echo date('Y') ?> <?php echo $nome_salone ?></footer>
</body>
</html>
