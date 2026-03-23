<?php
require 'db.php';
require 'auth.php';

$oggi = date('Y-m-d');

$tot_clienti  = (int)$conn->query("SELECT COUNT(*) AS n FROM clienti WHERE salone_id=$sid")->fetch_assoc()['n'];
$app_oggi     = (int)$conn->query("SELECT COUNT(*) AS n FROM appuntamenti WHERE salone_id=$sid AND DATE(data_ora)='$oggi'")->fetch_assoc()['n'];
$app_attesa   = (int)$conn->query("SELECT COUNT(*) AS n FROM appuntamenti WHERE salone_id=$sid AND stato='attesa'")->fetch_assoc()['n'];
$incasso_mese = (float)$conn->query("
    SELECT COALESCE(SUM(s.prezzo),0) AS tot
    FROM appuntamenti a JOIN servizi s ON a.servizio_id=s.id
    WHERE a.salone_id=$sid AND a.stato='completato'
      AND MONTH(a.data_ora)=MONTH(NOW()) AND YEAR(a.data_ora)=YEAR(NOW())
")->fetch_assoc()['tot'];

$prossimi = $conn->query("
    SELECT a.*, c.nome, c.cognome, s.nome AS servizio
    FROM appuntamenti a
    JOIN clienti c ON a.cliente_id=c.id
    JOIN servizi s ON a.servizio_id=s.id
    WHERE a.salone_id=$sid AND a.data_ora >= NOW() AND a.stato != 'annullato'
    ORDER BY a.data_ora ASC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

$per_servizio = $conn->query("
    SELECT s.nome, COUNT(*) AS n
    FROM appuntamenti a JOIN servizi s ON a.servizio_id=s.id
    WHERE a.salone_id=$sid AND MONTH(a.data_ora)=MONTH(NOW())
    GROUP BY s.id ORDER BY n DESC LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

$occupazione = $conn->query("
    SELECT HOUR(data_ora) AS ora, COUNT(*) AS n
    FROM appuntamenti
    WHERE salone_id=$sid AND MONTH(data_ora)=MONTH(NOW()) AND YEAR(data_ora)=YEAR(NOW())
    GROUP BY HOUR(data_ora) ORDER BY n DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

require 'layout_top.php';
?>

<div class="titolo-pagina">dashboard</div>

<div class="griglia-4">
  <div class="card">
    <h3>clienti totali</h3>
    <div class="valore"><?php echo $tot_clienti ?></div>
  </div>
  <div class="card">
    <h3>appuntamenti oggi</h3>
    <div class="valore"><?php echo $app_oggi ?></div>
  </div>
  <div class="card">
    <h3>in attesa conferma</h3>
    <div class="valore" style="color:var(--warning)"><?php echo $app_attesa ?></div>
  </div>
  <div class="card">
    <h3>incasso mese</h3>
    <div class="valore" style="color:var(--success)">€<?php echo number_format($incasso_mese, 2, ',', '.') ?></div>
  </div>
</div>

<div class="griglia-2">
  <div class="card">
    <h3 style="font-size:1rem;color:var(--text);margin-bottom:14px">prossimi appuntamenti</h3>
    <?php if (!$prossimi): ?>
      <p style="color:var(--text-muted);font-size:.88rem">nessun appuntamento in programma</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>data/ora</th>
            <th>cliente</th>
            <th>servizio</th>
            <th>stato</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($prossimi as $a): ?>
            <tr>
              <td><?php echo date('d/m H:i', strtotime($a['data_ora'])) ?></td>
              <td><?php echo htmlspecialchars($a['nome'] . ' ' . $a['cognome']) ?></td>
              <td><?php echo htmlspecialchars($a['servizio']) ?></td>
              <td><span class="badge badge-<?php echo $a['stato'] ?>"><?php echo $a['stato'] ?></span></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    <?php endif ?>
    <div style="margin-top:14px"><a href="appuntamenti.php" class="btn btn-piccolo">vedi tutti</a></div>
  </div>

  <div class="card">
    <h3 style="font-size:1rem;color:var(--text);margin-bottom:14px">servizi più richiesti questo mese</h3>
    <?php if (!$per_servizio): ?>
      <p style="color:var(--text-muted);font-size:.88rem">nessun dato ancora</p>
    <?php else: ?>
      <?php $max = max(array_column($per_servizio, 'n')) ?: 1; ?>
      <?php foreach ($per_servizio as $s): ?>
        <div style="margin-bottom:12px">
          <div style="display:flex;justify-content:space-between;font-size:.85rem;margin-bottom:4px">
            <span><?php echo htmlspecialchars($s['nome']) ?></span>
            <span style="color:var(--text-muted)"><?php echo $s['n'] ?></span>
          </div>
          <div class="barra-prog">
            <div class="barra-prog-fill" style="width:<?php echo round($s['n'] / $max * 100) ?>%"></div>
          </div>
        </div>
      <?php endforeach ?>
    <?php endif ?>
  </div>
</div>

<?php if ($occupazione): ?>
  <div class="card">
    <h3 style="font-size:1rem;color:var(--text);margin-bottom:14px">orari più affollati questo mese</h3>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <?php foreach ($occupazione as $o): ?>
        <div style="text-align:center;background:rgba(154,121,232,.12);border-radius:8px;padding:10px 16px">
          <div style="font-size:1.2rem;font-weight:700;color:var(--accent)"><?php echo sprintf('%02d:00', $o['ora']) ?></div>
          <div style="font-size:.78rem;color:var(--text-muted)"><?php echo $o['n'] ?> app</div>
        </div>
      <?php endforeach ?>
    </div>
  </div>
<?php endif ?>

<?php require 'layout_bottom.php'; ?>