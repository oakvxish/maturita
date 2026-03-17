<?php
require 'db.php';
require 'auth.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: clienti.php'); exit; }

// Verifica appartenenza al salone corrente
$cliente = $conn->query("SELECT * FROM clienti WHERE id=$id AND salone_id=$sid")->fetch_assoc();
if (!$cliente) { header('Location: clienti.php'); exit; }

$storico = $conn->query("
    SELECT a.*, s.nome AS servizio, s.prezzo, s.durata_minuti
    FROM appuntamenti a
    JOIN servizi s ON a.servizio_id=s.id
    WHERE a.cliente_id=$id AND a.salone_id=$sid
    ORDER BY a.data_ora DESC
")->fetch_all(MYSQLI_ASSOC);

$totale_speso = 0;
$tot_visite   = 0;
foreach ($storico as $r) {
    if ($r['stato'] === 'completato') {
        $totale_speso += $r['prezzo'];
        $tot_visite++;
    }
}

$messaggi = $conn->query("
    SELECT * FROM whatsapp_log WHERE cliente_id=$id AND salone_id=$sid ORDER BY inviato_il DESC LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

require 'layout_top.php';
?>

<div style="margin-bottom:20px">
  <a href="clienti.php" class="btn btn-grigio btn-piccolo">← torna ai clienti</a>
</div>

<div class="titolo-pagina">
  <?php echo htmlspecialchars($cliente['nome'].' '.$cliente['cognome']) ?>
  <span class="badge badge-<?php echo $cliente['tag'] ?>" style="font-size:.9rem;vertical-align:middle;margin-left:10px"><?php echo $cliente['tag'] ?></span>
</div>

<div class="griglia-4">
  <div class="card"><h3>telefono</h3><div style="font-size:1.1rem;font-weight:600"><?php echo htmlspecialchars($cliente['telefono']?:'—') ?></div></div>
  <div class="card"><h3>email</h3><div style="font-size:1rem;font-weight:600;word-break:break-all"><?php echo htmlspecialchars($cliente['email']?:'—') ?></div></div>
  <div class="card"><h3>visite completate</h3><div class="valore"><?php echo $tot_visite ?></div></div>
  <div class="card"><h3>totale speso</h3><div class="valore" style="color:var(--success)">€<?php echo number_format($totale_speso,2,',','.') ?></div></div>
</div>

<?php if ($cliente['note']): ?>
<div class="card">
  <h3 style="color:var(--text);margin-bottom:8px">note</h3>
  <p style="font-size:.9rem"><?php echo nl2br(htmlspecialchars($cliente['note'])) ?></p>
</div>
<?php endif ?>

<div class="griglia-2">
  <div class="card" style="overflow-x:auto">
    <h3 style="font-size:1rem;color:var(--text);margin-bottom:14px">storico appuntamenti</h3>
    <?php if (!$storico): ?>
      <p style="color:var(--text-muted);font-size:.88rem">nessun appuntamento</p>
    <?php else: ?>
    <table>
      <thead><tr><th>data</th><th>servizio</th><th>prezzo</th><th>stato</th></tr></thead>
      <tbody>
        <?php foreach ($storico as $a): ?>
        <tr>
          <td><?php echo date('d/m/Y H:i', strtotime($a['data_ora'])) ?></td>
          <td><?php echo htmlspecialchars($a['servizio']) ?></td>
          <td>€<?php echo number_format($a['prezzo'],2,',','.') ?></td>
          <td><span class="badge badge-<?php echo $a['stato'] ?>"><?php echo $a['stato'] ?></span></td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
    <?php endif ?>
  </div>

</div>

<?php require 'layout_bottom.php'; ?>
