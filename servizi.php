<?php
require 'db.php';
require 'auth.php';

$messaggio = '';
$errore    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $azione = $_POST['azione'] ?? '';

  if ($azione === 'crea' || $azione === 'modifica') {
    $nome   = $conn->real_escape_string(trim($_POST['nome']));
    $cat    = $conn->real_escape_string(trim($_POST['categoria'] ?? ''));
    $durata = (int)$_POST['durata_minuti'];
    $prezzo = (float)str_replace(',', '.', $_POST['prezzo'] ?? '0');

    if (!$nome || !$durata) {
      $errore = 'nome e durata obbligatori';
    } elseif ($azione === 'crea') {
      $conn->query("INSERT INTO servizi (salone_id,nome,categoria,durata_minuti,prezzo)
                          VALUES ($sid,'$nome','$cat',$durata,$prezzo)");
      $messaggio = 'servizio aggiunto';
    } else {
      $id = (int)$_POST['id'];
      $conn->query("UPDATE servizi SET nome='$nome',categoria='$cat',durata_minuti=$durata,prezzo=$prezzo
                          WHERE id=$id AND salone_id=$sid");
      $messaggio = 'servizio aggiornato';
    }
  }

  if ($azione === 'elimina') {
    $id = (int)$_POST['id'];
    $conn->query("DELETE FROM servizi WHERE id=$id AND salone_id=$sid");
    $messaggio = 'servizio eliminato';
  }
}

$servizi = $conn->query("SELECT * FROM servizi WHERE salone_id=$sid ORDER BY categoria,nome")->fetch_all(MYSQLI_ASSOC);

$modifica = null;
if (isset($_GET['modifica'])) {
  $mid = (int)$_GET['modifica'];
  $modifica = $conn->query("SELECT * FROM servizi WHERE id=$mid AND salone_id=$sid")->fetch_assoc();
}

require 'layout_top.php';
?>

<div class="titolo-pagina">servizi</div>

<?php if ($messaggio): ?><div class="avviso avviso-ok"><?php echo $messaggio ?></div><?php endif ?>
<?php if ($errore): ?><div class="avviso avviso-err"><?php echo $errore ?></div><?php endif ?>

<div class="griglia-2">
  <div class="card">
    <h3 style="font-size:1rem;color:var(--text);margin-bottom:16px"><?php echo $modifica ? 'modifica servizio' : 'nuovo servizio' ?></h3>
    <form method="post">
      <input type="hidden" name="azione" value="<?php echo $modifica ? 'modifica' : 'crea' ?>">
      <?php if ($modifica): ?><input type="hidden" name="id" value="<?php echo $modifica['id'] ?>"><?php endif ?>
      <label>nome servizio *</label>
      <input type="text" name="nome" value="<?php echo htmlspecialchars($modifica['nome'] ?? '') ?>" required>
      <label>categoria</label>
      <input type="text" name="categoria" placeholder="capelli, unghie, viso…" value="<?php echo htmlspecialchars($modifica['categoria'] ?? '') ?>">
      <div class="riga-form">
        <div>
          <label>durata (minuti) *</label>
          <input type="number" name="durata_minuti" min="5" step="5" value="<?php echo $modifica['durata_minuti'] ?? 60 ?>" required>
        </div>
        <div>
          <label>prezzo (€)</label>
          <input type="number" name="prezzo" min="0" step="0.50" value="<?php echo $modifica['prezzo'] ?? '0.00' ?>">
        </div>
      </div>
      <button type="submit" class="btn"><?php echo $modifica ? '💾 salva' : '➕ aggiungi' ?></button>
      <?php if ($modifica): ?><a href="servizi.php" class="btn btn-grigio" style="margin-left:8px">annulla</a><?php endif ?>
    </form>
  </div>

  <div class="card" style="overflow-x:auto">
    <h3 style="font-size:1rem;color:var(--text);margin-bottom:14px">tutti i servizi</h3>
    <table>
      <thead>
        <tr>
          <th>nome</th>
          <th>categoria</th>
          <th>durata</th>
          <th>prezzo</th>
          <th>azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$servizi): ?>
          <tr>
            <td colspan="5" style="color:var(--text-muted);text-align:center;padding:16px">nessun servizio ancora</td>
          </tr>
        <?php endif ?>
        <?php foreach ($servizi as $s): ?>
          <tr>
            <td style="font-weight:600"><?php echo htmlspecialchars($s['nome']) ?></td>
            <td style="color:var(--text-muted)"><?php echo htmlspecialchars($s['categoria']) ?></td>
            <td><?php echo $s['durata_minuti'] ?>min</td>
            <td>€<?php echo number_format($s['prezzo'], 2, ',', '.') ?></td>
            <td>
              <a href="servizi.php?modifica=<?php echo $s['id'] ?>" class="btn btn-piccolo btn-grigio">✏️</a>
              <form method="post" style="display:inline;margin-left:4px" onsubmit="return confirm('eliminare?')">
                <input type="hidden" name="azione" value="elimina">
                <input type="hidden" name="id" value="<?php echo $s['id'] ?>">
                <button type="submit" class="btn btn-piccolo btn-pericolo">✕</button>
              </form>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<?php require 'layout_bottom.php'; ?>