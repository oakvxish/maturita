<?php
require 'db.php';
require 'auth.php';

$messaggio = '';
$errore    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'crea') {
        $cid   = (int)$_POST['cliente_id'];
        $svid  = (int)$_POST['servizio_id'];
        $data  = $conn->real_escape_string($_POST['data_ora']);
        $stato = $conn->real_escape_string($_POST['stato']);
        $note  = $conn->real_escape_string($_POST['note'] ?? '');

        $ok_c = (int)$conn->query("SELECT COUNT(*) AS n FROM clienti WHERE id=$cid AND salone_id=$sid")->fetch_assoc()['n'];
        $ok_s = (int)$conn->query("SELECT COUNT(*) AS n FROM servizi WHERE id=$svid AND salone_id=$sid")->fetch_assoc()['n'];

        if ($cid && $svid && $data && $ok_c && $ok_s) {
            $conn->query("INSERT INTO appuntamenti (salone_id,cliente_id,servizio_id,data_ora,stato,note)
                          VALUES ($sid,$cid,$svid,'$data','$stato','$note')");
            $messaggio = 'appuntamento creato';
        } else {
            $errore = 'compila tutti i campi obbligatori';
        }
    }

    if ($azione === 'aggiorna_stato') {
        $id    = (int)$_POST['id'];
        $stato = $conn->real_escape_string($_POST['stato']);
        $conn->query("UPDATE appuntamenti SET stato='$stato' WHERE id=$id AND salone_id=$sid");
        $messaggio = 'stato aggiornato';
    }

    if ($azione === 'elimina') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM appuntamenti WHERE id=$id AND salone_id=$sid");
        $messaggio = 'appuntamento eliminato';
    }
}

$filtro_stato = $conn->real_escape_string($_GET['stato'] ?? '');
$filtro_data  = $conn->real_escape_string($_GET['data']  ?? '');

$where = "a.salone_id=$sid";
if ($filtro_stato) $where .= " AND a.stato='$filtro_stato'";
if ($filtro_data)  $where .= " AND DATE(a.data_ora)='$filtro_data'";

$appuntamenti = $conn->query("
    SELECT a.*, c.nome, c.cognome, c.telefono, s.nome AS servizio, s.durata_minuti, s.prezzo
    FROM appuntamenti a
    JOIN clienti c ON a.cliente_id=c.id
    JOIN servizi s ON a.servizio_id=s.id
    WHERE $where
    ORDER BY a.data_ora DESC
")->fetch_all(MYSQLI_ASSOC);

$clienti = $conn->query("SELECT id,nome,cognome FROM clienti WHERE salone_id=$sid ORDER BY cognome,nome")->fetch_all(MYSQLI_ASSOC);
$servizi = $conn->query("SELECT id,nome,prezzo,durata_minuti FROM servizi WHERE salone_id=$sid ORDER BY nome")->fetch_all(MYSQLI_ASSOC);

require 'layout_top.php';
?>

<div class="titolo-pagina">appuntamenti</div>

<?php if ($messaggio): ?><div class="avviso avviso-ok"><?php echo $messaggio ?></div><?php endif ?>
<?php if ($errore): ?><div class="avviso avviso-err"><?php echo $errore ?></div><?php endif ?>

<div class="griglia-2">
  <div class="card">
    <h3 style="font-size:1rem;color:var(--text);margin-bottom:16px">nuovo appuntamento</h3>
    <form method="post">
      <input type="hidden" name="azione" value="crea">
      <label>cliente</label>
      <select name="cliente_id" required>
        <option value="">scegli cliente</option>
        <?php foreach ($clienti as $c): ?>
        <option value="<?php echo $c['id'] ?>"><?php echo htmlspecialchars($c['cognome'].' '.$c['nome']) ?></option>
        <?php endforeach ?>
      </select>
      <label>servizio</label>
      <select name="servizio_id" required>
        <option value="">scegli servizio</option>
        <?php foreach ($servizi as $s): ?>
        <option value="<?php echo $s['id'] ?>"><?php echo htmlspecialchars($s['nome']) ?> — €<?php echo $s['prezzo'] ?> (<?php echo $s['durata_minuti'] ?>min)</option>
        <?php endforeach ?>
      </select>
      <label>data e ora</label>
      <input type="datetime-local" name="data_ora" required>
      <label>stato</label>
      <select name="stato">
        <option value="attesa">in attesa</option>
        <option value="confermato">confermato</option>
      </select>
      <label>note</label>
      <textarea name="note" rows="2" placeholder="note opzionali"></textarea>
      <button type="submit" class="btn">➕ aggiungi</button>
    </form>
  </div>

  <div class="card">
    <h3 style="font-size:1rem;color:var(--text);margin-bottom:16px">filtra</h3>
    <form method="get">
      <label>stato</label>
      <select name="stato">
        <option value="">tutti</option>
        <?php foreach (['attesa','confermato','completato','annullato'] as $st): ?>
        <option value="<?php echo $st ?>" <?php echo $filtro_stato===$st?'selected':'' ?>><?php echo $st ?></option>
        <?php endforeach ?>
      </select>
      <label>data</label>
      <input type="date" name="data" value="<?php echo htmlspecialchars($filtro_data) ?>">
      <button type="submit" class="btn btn-grigio">🔍 filtra</button>
      <a href="appuntamenti.php" class="btn btn-grigio" style="margin-left:6px">reset</a>
    </form>
  </div>
</div>

<div class="card" style="overflow-x:auto">
  <table>
    <thead><tr><th>data/ora</th><th>cliente</th><th>telefono</th><th>servizio</th><th>durata</th><th>prezzo</th><th>stato</th><th>azioni</th></tr></thead>
    <tbody>
      <?php if (!$appuntamenti): ?>
      <tr><td colspan="8" style="color:var(--text-muted);text-align:center;padding:24px">nessun appuntamento trovato</td></tr>
      <?php endif ?>
      <?php foreach ($appuntamenti as $a): ?>
      <tr>
        <td><?php echo date('d/m/Y H:i', strtotime($a['data_ora'])) ?></td>
        <td><?php echo htmlspecialchars($a['nome'].' '.$a['cognome']) ?></td>
        <td><?php echo htmlspecialchars($a['telefono']) ?></td>
        <td><?php echo htmlspecialchars($a['servizio']) ?></td>
        <td><?php echo $a['durata_minuti'] ?>min</td>
        <td>€<?php echo number_format($a['prezzo'],2,',','.') ?></td>
        <td><span class="badge badge-<?php echo $a['stato'] ?>"><?php echo $a['stato'] ?></span></td>
        <td>
          <form method="post" style="display:inline">
            <input type="hidden" name="azione" value="aggiorna_stato">
            <input type="hidden" name="id" value="<?php echo $a['id'] ?>">
            <select name="stato" style="width:auto;margin:0;padding:4px 6px;font-size:.78rem">
              <?php foreach (['attesa','confermato','completato','annullato'] as $st): ?>
              <option value="<?php echo $st ?>" <?php echo $a['stato']===$st?'selected':'' ?>><?php echo $st ?></option>
              <?php endforeach ?>
            </select>
            <button type="submit" class="btn btn-piccolo btn-grigio" style="margin-left:4px">✓</button>
          </form>
          <form method="post" style="display:inline;margin-left:4px" onsubmit="return confirm('eliminare?')">
            <input type="hidden" name="azione" value="elimina">
            <input type="hidden" name="id" value="<?php echo $a['id'] ?>">
            <button type="submit" class="btn btn-piccolo btn-pericolo">✕</button>
          </form>
        </td>
      </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>

<?php require 'layout_bottom.php'; ?>
