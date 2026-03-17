<?php
require 'db.php';
require 'auth.php';

$messaggio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $campi = ['nome_salone', 'iva', 'valuta', 'tema'];
  foreach ($campi as $k) {
    $chiave = $conn->real_escape_string($k);
    $val = $conn->real_escape_string(trim($_POST[$k] ?? ''));
    $conn->query("INSERT INTO impostazioni (salone_id,chiave,valore) VALUES ($sid,'$chiave','$val')
                      ON DUPLICATE KEY UPDATE valore='$val'");
  }
  // Aggiorna nome salone anche in sessione
  $_SESSION['nome_salone'] = trim($_POST['nome_salone'] ?? '');
  $messaggio = 'impostazioni salvate';
}

$cfg = [];
$result = $conn->query("SELECT chiave,valore FROM impostazioni WHERE salone_id=$sid");
while ($r = $result->fetch_assoc()) {
  $cfg[$r['chiave']] = $r['valore'];
}

require 'layout_top.php';
?>

<div class="titolo-pagina">impostazioni</div>

<?php if ($messaggio): ?>
  <div class="avviso avviso-ok"><?php echo $messaggio ?></div><?php endif ?>

<form method="post">
  <div class="griglia-2">
    <div class="card">
      <h3 style="font-size:1rem;color:var(--text);margin-bottom:16px">generali</h3>
      <label>nome salone</label>
      <input type="text" name="nome_salone" value="<?php echo htmlspecialchars($cfg['nome_salone'] ?? '') ?>">
      <div class="riga-form">
        <div>
          <label>aliquota iva (%)</label>
          <input type="number" name="iva" min="0" max="100" step="0.5"
            value="<?php echo htmlspecialchars($cfg['iva'] ?? '22') ?>">
        </div>
        <div>
          <label>valuta</label>
          <select name="valuta">
            <?php foreach (['EUR', 'USD', 'GBP', 'CHF'] as $v): ?>
              <option value="<?php echo $v ?>" <?php echo ($cfg['valuta'] ?? 'EUR') === $v ? 'selected' : '' ?>><?php echo $v ?>
              </option>
            <?php endforeach ?>
          </select>
        </div>
      </div>
      <label>tema interfaccia</label>
      <select name="tema">
        <option value="chiaro" <?php echo ($cfg['tema'] ?? 'chiaro') === 'chiaro' ? 'selected' : '' ?>>☀️ chiaro</option>
        <option value="scuro" <?php echo ($cfg['tema'] ?? '') === 'scuro' ? 'selected' : '' ?>>🌙 scuro</option>
      </select>

      <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <h3 style="font-size:.9rem;color:var(--text);margin-bottom:10px">link prenotazione pubblica</h3>
        <?php
        $slug_salone = $conn->query("SELECT slug FROM saloni WHERE id=$sid")->fetch_assoc()['slug'] ?? '';
        $protocol = 'http';
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
          $protocol = 'https';
        }
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = dirname($_SERVER['SCRIPT_NAME']);
        $link_pub = $protocol . '://' . $host . $path . '/prenota.php?s=' . urlencode($slug_salone);
        ?>
        <div
          style="background:var(--bg);border:1px solid var(--border);border-radius:7px;padding:10px 12px;font-size:.82rem;word-break:break-all;color:var(--text-muted)">
          <?php echo htmlspecialchars($link_pub) ?>
        </div>
        <p style="font-size:.78rem;color:var(--text-muted);margin-top:6px">condividi questo link con i tuoi clienti per
          le prenotazioni online</p>
      </div>
    </div>

  </div>

  <div style="text-align:right">
    <button type="submit" class="btn">💾 salva impostazioni</button>
  </div>
</form>

<?php require 'layout_bottom.php'; ?>