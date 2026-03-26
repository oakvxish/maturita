<?php
require 'db.php';
require 'auth.php';
require_proprietario();

$messaggio = '';
$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';
    $nome = $conn->real_escape_string(trim($_POST['nome'] ?? ''));
    $categoria = $conn->real_escape_string(trim($_POST['categoria'] ?? ''));
    $durata = (int) ($_POST['durata_minuti'] ?? 0);
    $prezzo = (float) ($_POST['prezzo'] ?? 0);

    if ($azione === 'crea' || $azione === 'modifica') {
        if (!$nome || $durata <= 0) {
            $errore = 'nome e durata sono obbligatori';
        } elseif ($azione === 'crea') {
            $conn->query("INSERT INTO servizi (salone_id,nome,categoria,durata_minuti,prezzo) VALUES ($sid,'$nome','$categoria',$durata,$prezzo)");
            $messaggio = 'servizio aggiunto';
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $conn->query("UPDATE servizi SET nome='$nome',categoria='$categoria',durata_minuti=$durata,prezzo=$prezzo WHERE id=$id AND salone_id=$sid");
            $messaggio = 'servizio aggiornato';
        }
    }

    if ($azione === 'elimina') {
        $id = (int) ($_POST['id'] ?? 0);
        $conn->query("DELETE FROM servizi WHERE id=$id AND salone_id=$sid");
        $messaggio = 'servizio eliminato';
    }
}

$servizi = $conn->query("SELECT * FROM servizi WHERE salone_id=$sid ORDER BY categoria,nome")->fetch_all(MYSQLI_ASSOC);
$modifica = null;
if (isset($_GET['modifica'])) {
    $mid = (int) $_GET['modifica'];
    $modifica = $conn->query("SELECT * FROM servizi WHERE id=$mid AND salone_id=$sid")->fetch_assoc();
}

$prezzoMedio = 0;
$durataMedia = 0;
if ($servizi) {
    $prezzoMedio = array_sum(array_column($servizi, 'prezzo')) / count($servizi);
    $durataMedia = array_sum(array_column($servizi, 'durata_minuti')) / count($servizi);
}

require 'layout_top.php';
?>

<div class="page-head">
  <div class="titolo-pagina">servizi</div>
  <div class="page-intro">Listino ordinato, durate coerenti e prezzi leggibili. Qui conviene mantenere pochi campi ma sempre aggiornati, perché guidano agenda, preventivi e analitiche.</div>
</div>

<?php if ($messaggio): ?><div class="avviso avviso-ok"><?php echo $messaggio ?></div><?php endif ?>
<?php if ($errore): ?><div class="avviso avviso-err"><?php echo $errore ?></div><?php endif ?>

<div class="metric-grid">
  <div class="metric"><div class="metric-label">servizi</div><div class="metric-value"><?php echo count($servizi) ?></div><div class="metric-note">totale nel listino</div></div>
  <div class="metric"><div class="metric-label">prezzo medio</div><div class="metric-value">€<?php echo number_format($prezzoMedio,0,',','.') ?></div><div class="metric-note">indicatore sintetico del listino</div></div>
  <div class="metric"><div class="metric-label">durata media</div><div class="metric-value"><?php echo (int)round($durataMedia) ?></div><div class="metric-note">minuti per servizio</div></div>
  <div class="metric"><div class="metric-label">controllo</div><div class="metric-value">ok</div><div class="metric-note">listino gestito dal proprietario</div></div>
</div>

<div class="griglia-2">
  <div class="card">
    <div class="card-title-row">
      <div>
        <div class="eyebrow">configurazione</div>
        <div class="card-title-strong"><?php echo $modifica ? 'modifica servizio' : 'nuovo servizio' ?></div>
      </div>
    </div>
    <form method="post">
      <input type="hidden" name="azione" value="<?php echo $modifica ? 'modifica' : 'crea' ?>">
      <?php if ($modifica): ?><input type="hidden" name="id" value="<?php echo $modifica['id'] ?>"><?php endif ?>
      <label>nome servizio *</label>
      <input type="text" name="nome" value="<?php echo htmlspecialchars($modifica['nome'] ?? '') ?>" required>
      <label>categoria</label>
      <input type="text" name="categoria" placeholder="capelli, unghie, viso" value="<?php echo htmlspecialchars($modifica['categoria'] ?? '') ?>">
      <div class="riga-form">
        <div>
          <label>durata in minuti *</label>
          <input type="number" name="durata_minuti" min="5" step="5" value="<?php echo $modifica['durata_minuti'] ?? 60 ?>" required>
        </div>
        <div>
          <label>prezzo (€)</label>
          <input type="number" name="prezzo" min="0" step="0.50" value="<?php echo $modifica['prezzo'] ?? '0.00' ?>">
        </div>
      </div>
      <div class="toolbar-actions">
        <button type="submit" class="btn"><?php echo $modifica ? 'salva servizio' : 'aggiungi servizio' ?></button>
        <?php if ($modifica): ?><a href="servizi.php" class="btn btn-grigio">annulla</a><?php endif ?>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-title-row">
      <div>
        <div class="eyebrow">nota operativa</div>
        <div class="card-title-strong">perché tenere il listino pulito</div>
      </div>
    </div>
    <div class="surface-soft">
      <p>Durata e prezzo sono i due dati più utili per lavorare bene: aiutano a riempire l’agenda, leggere l’incasso e mantenere omogenea la prenotazione pubblica.</p>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-title-row">
    <div>
      <div class="eyebrow">listino</div>
      <div class="card-title-strong">tutti i servizi</div>
    </div>
  </div>

  <?php if (!$servizi): ?>
    <div class="empty-state">
      <strong>Nessun servizio inserito</strong>
      <p>Aggiungi almeno un servizio per iniziare a usare agenda, prenotazione pubblica e report.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>nome</th><th>categoria</th><th>durata</th><th>prezzo</th><th>azioni</th></tr></thead>
        <tbody>
          <?php foreach ($servizi as $s): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($s['nome']) ?></strong></td>
            <td><?php echo htmlspecialchars($s['categoria']) ?: '<span class="table-secondary">senza categoria</span>' ?></td>
            <td><?php echo $s['durata_minuti'] ?> min</td>
            <td>€<?php echo number_format($s['prezzo'],2,',','.') ?></td>
            <td>
              <div class="toolbar-actions">
                <a href="servizi.php?modifica=<?php echo $s['id'] ?>" class="btn btn-piccolo btn-grigio">modifica</a>
                <form method="post" style="display:inline">
                  <input type="hidden" name="azione" value="elimina">
                  <input type="hidden" name="id" value="<?php echo $s['id'] ?>">
                  <button type="submit" class="btn btn-piccolo btn-pericolo">elimina</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>
</div>

<?php require 'layout_bottom.php'; ?>
