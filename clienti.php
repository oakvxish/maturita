<?php
require 'db.php';
require 'auth.php';

$messaggio = '';
$errore    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'crea' || $azione === 'modifica') {
        $nome     = $conn->real_escape_string(trim($_POST['nome']));
        $cognome  = $conn->real_escape_string(trim($_POST['cognome']));
        $telefono = $conn->real_escape_string(trim($_POST['telefono'] ?? ''));
        $email    = $conn->real_escape_string(trim($_POST['email']    ?? ''));
        $tag      = $conn->real_escape_string($_POST['tag']           ?? 'normale');
        $note     = $conn->real_escape_string($_POST['note']          ?? '');
        $chat_id  = $conn->real_escape_string(trim($_POST['whatsapp_chat_id'] ?? ''));

        if (!$nome || !$cognome) {
            $errore = 'nome e cognome obbligatori';
        } elseif ($azione === 'crea') {
            $conn->query("INSERT INTO clienti (salone_id,nome,cognome,telefono,email,tag,note,whatsapp_chat_id)
                          VALUES ($sid,'$nome','$cognome','$telefono','$email','$tag','$note','$chat_id')");
            $messaggio = 'cliente aggiunto';
        } else {
            $id = (int)$_POST['id'];
            $conn->query("UPDATE clienti SET nome='$nome',cognome='$cognome',telefono='$telefono',
                          email='$email',tag='$tag',note='$note',whatsapp_chat_id='$chat_id'
                          WHERE id=$id AND salone_id=$sid");
            $messaggio = 'cliente aggiornato';
        }
    }

    if ($azione === 'elimina') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM clienti WHERE id=$id AND salone_id=$sid");
        $messaggio = 'cliente eliminato';
    }
}

$cerca      = $conn->real_escape_string(trim($_GET['cerca'] ?? ''));
$filtro_tag = $conn->real_escape_string($_GET['tag'] ?? '');

$where = "salone_id=$sid";
if ($cerca)      $where .= " AND (nome LIKE '%$cerca%' OR cognome LIKE '%$cerca%' OR telefono LIKE '%$cerca%' OR email LIKE '%$cerca%')";
if ($filtro_tag) $where .= " AND tag='$filtro_tag'";

$clienti = $conn->query("SELECT * FROM clienti WHERE $where ORDER BY cognome,nome")->fetch_all(MYSQLI_ASSOC);

$modifica = null;
if (isset($_GET['modifica'])) {
    $mid = (int)$_GET['modifica'];
    $modifica = $conn->query("SELECT * FROM clienti WHERE id=$mid AND salone_id=$sid")->fetch_assoc();
}

require 'layout_top.php';
?>

<div class="titolo-pagina">clienti</div>

<?php if ($messaggio): ?><div class="avviso avviso-ok"><?php echo $messaggio ?></div><?php endif ?>
<?php if ($errore): ?><div class="avviso avviso-err"><?php echo $errore ?></div><?php endif ?>

<div class="griglia-2">
  <div class="card">
    <h3 style="font-size:1rem;color:var(--text);margin-bottom:16px"><?php echo $modifica ? 'modifica cliente' : 'nuovo cliente' ?></h3>
    <form method="post">
      <input type="hidden" name="azione" value="<?php echo $modifica ? 'modifica' : 'crea' ?>">
      <?php if ($modifica): ?><input type="hidden" name="id" value="<?php echo $modifica['id'] ?>"><?php endif ?>
      <div class="riga-form">
        <div>
          <label>nome *</label>
          <input type="text" name="nome" value="<?php echo htmlspecialchars($modifica['nome'] ?? '') ?>" required>
        </div>
        <div>
          <label>cognome *</label>
          <input type="text" name="cognome" value="<?php echo htmlspecialchars($modifica['cognome'] ?? '') ?>" required>
        </div>
      </div>
      <div class="riga-form">
        <div>
          <label>telefono</label>
          <input type="tel" name="telefono" value="<?php echo htmlspecialchars($modifica['telefono'] ?? '') ?>">
        </div>
        <div>
          <label>email</label>
          <input type="email" name="email" value="<?php echo htmlspecialchars($modifica['email'] ?? '') ?>">
        </div>
      </div>
      <div class="riga-form">
        <div>
          <label>tag cliente</label>
          <select name="tag">
            <?php foreach (['normale','vip','potenziale','inattivo'] as $t): ?>
            <option value="<?php echo $t ?>" <?php echo ($modifica['tag'] ?? 'normale')===$t?'selected':'' ?>><?php echo $t ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div>
          <label>whatsapp chat id</label>
          <input type="text" name="whatsapp_chat_id" placeholder="39333...@c.us" value="<?php echo htmlspecialchars($modifica['whatsapp_chat_id'] ?? '') ?>">
        </div>
      </div>
      <label>note</label>
      <textarea name="note" rows="2"><?php echo htmlspecialchars($modifica['note'] ?? '') ?></textarea>
      <button type="submit" class="btn"><?php echo $modifica ? '💾 salva modifiche' : '➕ aggiungi' ?></button>
      <?php if ($modifica): ?><a href="clienti.php" class="btn btn-grigio" style="margin-left:8px">annulla</a><?php endif ?>
    </form>
  </div>

  <div class="card">
    <h3 style="font-size:1rem;color:var(--text);margin-bottom:16px">cerca e filtra</h3>
    <form method="get">
      <label>ricerca libera</label>
      <input type="text" name="cerca" placeholder="nome, cognome, telefono, email" value="<?php echo htmlspecialchars($_GET['cerca'] ?? '') ?>">
      <label>filtra per tag</label>
      <select name="tag">
        <option value="">tutti</option>
        <?php foreach (['normale','vip','potenziale','inattivo'] as $t): ?>
        <option value="<?php echo $t ?>" <?php echo $filtro_tag===$t?'selected':'' ?>><?php echo $t ?></option>
        <?php endforeach ?>
      </select>
      <button type="submit" class="btn btn-grigio">🔍 cerca</button>
      <a href="clienti.php" class="btn btn-grigio" style="margin-left:6px">reset</a>
    </form>
    <div style="margin-top:16px;color:var(--text-muted);font-size:.85rem"><?php echo count($clienti) ?> clienti trovati</div>
  </div>
</div>

<div class="card" style="overflow-x:auto">
  <table>
    <thead><tr><th>nome</th><th>telefono</th><th>email</th><th>tag</th><th>note</th><th>dal</th><th>azioni</th></tr></thead>
    <tbody>
      <?php if (!$clienti): ?>
      <tr><td colspan="7" style="color:var(--text-muted);text-align:center;padding:24px">nessun cliente trovato</td></tr>
      <?php endif ?>
      <?php foreach ($clienti as $c): ?>
      <tr>
        <td style="font-weight:600"><?php echo htmlspecialchars($c['cognome'].' '.$c['nome']) ?></td>
        <td><?php echo htmlspecialchars($c['telefono']) ?></td>
        <td><?php echo htmlspecialchars($c['email']) ?></td>
        <td><span class="badge badge-<?php echo $c['tag'] ?>"><?php echo $c['tag'] ?></span></td>
        <td style="color:var(--text-muted);max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo htmlspecialchars($c['note']) ?></td>
        <td style="color:var(--text-muted)"><?php echo date('d/m/Y', strtotime($c['created_at'])) ?></td>
        <td>
          <a href="clienti.php?modifica=<?php echo $c['id'] ?>" class="btn btn-piccolo btn-grigio">✏️</a>
          <a href="storico_cliente.php?id=<?php echo $c['id'] ?>" class="btn btn-piccolo btn-grigio">📋</a>
          <form method="post" style="display:inline;margin-left:4px" onsubmit="return confirm('eliminare il cliente?')">
            <input type="hidden" name="azione" value="elimina">
            <input type="hidden" name="id" value="<?php echo $c['id'] ?>">
            <button type="submit" class="btn btn-piccolo btn-pericolo">✕</button>
          </form>
        </td>
      </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>

<?php require 'layout_bottom.php'; ?>
