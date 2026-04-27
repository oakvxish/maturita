<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

$messaggio = '';
$errore    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'crea' || $azione === 'modifica') {
        $nome   = $conn->real_escape_string(trim($_POST['nome']));
        $cat    = $conn->real_escape_string(trim($_POST['categoria']       ?? ''));
        $qta    = (int)$_POST['quantita'];
        $soglia = (int)$_POST['soglia_minima'];
        $unita  = $conn->real_escape_string(trim($_POST['unita']           ?? 'pz'));
        $prezzo = (float)str_replace(',', '.', $_POST['prezzo_acquisto']   ?? '0');

        if (!$nome) {
            $errore = 'nome obbligatorio';
        } elseif ($azione === 'crea') {
            $conn->query("INSERT INTO magazzino (salone_id,nome,categoria,quantita,soglia_minima,unita,prezzo_acquisto)
                          VALUES ($sid,'$nome','$cat',$qta,$soglia,'$unita',$prezzo)");
            $messaggio = 'prodotto aggiunto';
        } else {
            $id = (int)$_POST['id'];
            $conn->query("UPDATE magazzino SET nome='$nome',categoria='$cat',quantita=$qta,
                          soglia_minima=$soglia,unita='$unita',prezzo_acquisto=$prezzo
                          WHERE id=$id AND salone_id=$sid");
            $messaggio = 'prodotto aggiornato';
        }
    }

    if ($azione === 'elimina') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM magazzino WHERE id=$id AND salone_id=$sid");
        $messaggio = 'prodotto eliminato';
    }

    if ($azione === 'aggiorna_qta') {
        $id    = (int)$_POST['id'];
        $delta = (int)$_POST['delta'];
        $conn->query("UPDATE magazzino SET quantita=GREATEST(0,quantita+$delta) WHERE id=$id AND salone_id=$sid");
        $messaggio = 'quantitÃ  aggiornata';
    }
}

$prodotti = $conn->query("SELECT * FROM magazzino WHERE salone_id=$sid ORDER BY categoria,nome")->fetch_all(MYSQLI_ASSOC);
$scorte_basse = [];
foreach ($prodotti as $prodotto) {
    if ((int) $prodotto['quantita'] <= (int) $prodotto['soglia_minima']) {
        $scorte_basse[] = $prodotto;
    }
}

$modifica = null;
if (isset($_GET['modifica'])) {
    $mid = (int)$_GET['modifica'];
    $modifica = $conn->query("SELECT * FROM magazzino WHERE id=$mid AND salone_id=$sid")->fetch_assoc();
}

require __DIR__ . '/includes/layout_top.php';
?>

<div class="titolo-pagina">magazzino</div>

<style>
  /* layout dedicato magazzino: due colonne centrate e area lista piu ampia */
  .main {
    max-width: min(1520px, calc(100vw - 48px));
  }

  .magazzino-layout {
    grid-template-columns: minmax(340px, 460px) minmax(760px, 1fr);
    justify-content: center;
    align-items: start;
  }

  .magazzino-layout > .card {
    width: 100%;
  }

  .magazzino-list {
    overflow: visible;
  }

  .magazzino-list table {
    table-layout: fixed;
  }

  .magazzino-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
  }

  .magazzino-actions form,
  .magazzino-actions a {
    margin: 0 !important;
  }

  @media (max-width: 1280px) {
    .main {
      max-width: min(1280px, calc(100vw - 32px));
    }

    .magazzino-layout {
      grid-template-columns: minmax(300px, 420px) minmax(620px, 1fr);
      gap: 20px;
    }
  }

  @media (max-width: 1024px) {
    .magazzino-layout {
      grid-template-columns: 1fr;
    }
  }
</style>

<?php if ($messaggio): ?><div class="avviso avviso-ok"><?php echo $messaggio ?></div><?php endif ?>
<?php if ($errore): ?><div class="avviso avviso-err"><?php echo $errore ?></div><?php endif ?>

<?php if ($scorte_basse): ?>
<div class="avviso" style="background:#fff7ed;color:#92400e;border:1px solid #fed7aa">
  <?php echo count($scorte_basse) ?> prodott<?php echo count($scorte_basse)>1?'i':'o' ?> sotto soglia:
  <?php
    $nomi_scorte_basse = [];
    foreach ($scorte_basse as $prodotto_scorta) {
        $nomi_scorte_basse[] = htmlspecialchars($prodotto_scorta['nome']);
    }
    echo implode(', ', $nomi_scorte_basse);
  ?>
</div>
<?php endif ?>

<div class="griglia-2 magazzino-layout">
  <div class="card">
    <h3 style="font-size:1rem;color:var(--text);margin-bottom:16px"><?php echo $modifica ? 'modifica prodotto' : 'nuovo prodotto' ?></h3>
    <form method="post">
      <input type="hidden" name="azione" value="<?php echo $modifica ? 'modifica' : 'crea' ?>">
      <?php if ($modifica): ?><input type="hidden" name="id" value="<?php echo $modifica['id'] ?>"><?php endif ?>
      <label>nome prodotto *</label>
      <input type="text" name="nome" value="<?php echo htmlspecialchars($modifica['nome'] ?? '') ?>" required>
      <div class="riga-form">
        <div>
          <label>categoria</label>
          <input type="text" name="categoria" placeholder="shampoo, tinteâ€¦" value="<?php echo htmlspecialchars($modifica['categoria'] ?? '') ?>">
        </div>
        <div>
          <label>unitÃ </label>
          <input type="text" name="unita" placeholder="pz, ml, kgâ€¦" value="<?php echo htmlspecialchars($modifica['unita'] ?? 'pz') ?>">
        </div>
      </div>
      <div class="riga-form">
        <div>
          <label>quantitÃ  attuale</label>
          <input type="number" name="quantita" min="0" value="<?php echo $modifica['quantita'] ?? 0 ?>">
        </div>
        <div>
          <label>soglia minima</label>
          <input type="number" name="soglia_minima" min="0" value="<?php echo $modifica['soglia_minima'] ?? 5 ?>">
        </div>
      </div>
      <label>prezzo acquisto (â‚¬)</label>
      <input type="number" name="prezzo_acquisto" min="0" step="0.01" value="<?php echo $modifica['prezzo_acquisto'] ?? '0.00' ?>">
      <button type="submit" class="btn"><?php echo $modifica ? 'salva' : 'aggiungi' ?></button>
      <?php if ($modifica): ?><a href="magazzino.php" class="btn btn-grigio" style="margin-left:8px">annulla</a><?php endif ?>
    </form>
  </div>

  <div class="card magazzino-list">
    <h3 style="font-size:1rem;color:var(--text);margin-bottom:14px">prodotti in magazzino</h3>
    <table>
      <thead><tr><th>nome</th><th>cat.</th><th>qta</th><th>soglia</th><th>prezzo</th><th>azioni</th></tr></thead>
      <tbody>
        <?php if (!$prodotti): ?>
          <tr><td colspan="6" style="color:var(--text-muted);text-align:center;padding:16px">nessun prodotto ancora</td></tr>
        <?php endif ?>
        <?php foreach ($prodotti as $p): ?>
        <?php $sotto = $p['quantita'] <= $p['soglia_minima']; ?>
        <tr>
          <td style="font-weight:600"><?php echo htmlspecialchars($p['nome']) ?></td>
          <td style="color:var(--text-muted)"><?php echo htmlspecialchars($p['categoria']) ?></td>
          <td style="<?php echo $sotto?'color:var(--danger);font-weight:700':'' ?>">
            <?php echo $p['quantita'] ?> <?php echo htmlspecialchars($p['unita']) ?><?php echo $sotto ? ' sotto soglia' : '' ?>
          </td>
          <td><?php echo $p['soglia_minima'] ?></td>
          <td>â‚¬<?php echo number_format($p['prezzo_acquisto'],2,',','.') ?></td>
          <td>
            <div class="magazzino-actions">
              <form method="post">
                <input type="hidden" name="azione" value="aggiorna_qta">
                <input type="hidden" name="id" value="<?php echo $p['id'] ?>">
                <input type="hidden" name="delta" value="1">
                <button type="submit" class="btn btn-piccolo">+</button>
              </form>
              <form method="post">
                <input type="hidden" name="azione" value="aggiorna_qta">
                <input type="hidden" name="id" value="<?php echo $p['id'] ?>">
                <input type="hidden" name="delta" value="-1">
                <button type="submit" class="btn btn-piccolo btn-grigio">-</button>
              </form>
              <a href="magazzino.php?modifica=<?php echo $p['id'] ?>" class="btn btn-piccolo btn-grigio">modifica</a>
              <form method="post">
                <input type="hidden" name="azione" value="elimina">
                <input type="hidden" name="id" value="<?php echo $p['id'] ?>">
                <button type="submit" class="btn btn-piccolo btn-pericolo">elimina</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>


