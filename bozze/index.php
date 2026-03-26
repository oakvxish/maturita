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

<div class="page-head">
  <div class="titolo-pagina">dashboard</div>
  <div class="page-intro">Vista rapida di oggi.</div>
</div>

<div class="metric-grid">
  <div class="metric">
    <div class="metric-label">clienti totali</div>
    <div class="metric-value"><?php echo $tot_clienti ?></div>
    <div class="metric-note">nel salone attivo</div>
  </div>
  <div class="metric">
    <div class="metric-label">appuntamenti oggi</div>
    <div class="metric-value"><?php echo $app_oggi ?></div>
    <div class="metric-note">giornata attiva</div>
  </div>
  <div class="metric">
    <div class="metric-label">da confermare</div>
    <div class="metric-value" style="color:var(--warn)"><?php echo $app_attesa ?></div>
    <div class="metric-note">richiedono azione</div>
  </div>
  <div class="metric">
    <div class="metric-label">incasso mese</div>
    <div class="metric-value" style="color:var(--ok)">€<?php echo number_format($incasso_mese,2,',','.') ?></div>
    <div class="metric-note">mese corrente</div>
  </div>
</div>

<div class="quick-grid">
  <a class="quick-link" href="appuntamenti.php"><strong>Nuovo appuntamento</strong><span>apri agenda</span></a>
  <a class="quick-link" href="clienti.php"><strong>Nuovo cliente</strong><span>nuovo contatto</span></a>
  <a class="quick-link" href="prenota.php?id=<?php echo (int)$sid; ?>" target="_blank"><strong>Pagina pubblica</strong><span>anteprima cliente</span></a>
</div>

<div class="griglia-2">
  <div class="card">
    <div class="card-title-row">
      <div>
        <div class="eyebrow">agenda</div>
        <div class="card-title-strong">prossimi appuntamenti</div>
      </div>
      <a href="appuntamenti.php" class="btn btn-grigio btn-piccolo">vedi tutti</a>
    </div>

    <?php if (!$prossimi): ?>
      <div class="empty-state">
        <strong>Nessun appuntamento in programma</strong>
        <p>qui compariranno i prossimi ingressi.</p>
        <a href="appuntamenti.php" class="btn">crea il primo appuntamento</a>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>data e ora</th><th>cliente</th><th>servizio</th><th>stato</th></tr></thead>
          <tbody>
            <?php foreach ($prossimi as $a): ?>
            <tr>
              <td><?php echo date('d/m H:i', strtotime($a['data_ora'])) ?></td>
              <td><?php echo htmlspecialchars($a['nome'].' '.$a['cognome']) ?></td>
              <td><?php echo htmlspecialchars($a['servizio']) ?></td>
              <td><span class="badge badge-<?php echo $a['stato'] ?>"><?php echo $a['stato'] ?></span></td>
            </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    <?php endif ?>
  </div>

  <div class="card">
    <div class="card-title-row">
      <div>
        <div class="eyebrow">resa</div>
        <div class="card-title-strong">servizi più richiesti</div>
      </div>
      <div class="helper-text">mese corrente</div>
    </div>

    <?php if (!$per_servizio): ?>
      <div class="empty-state">
        <strong>Nessun dato disponibile</strong>
        <p>i dati appariranno qui.</p>
      </div>
    <?php else: ?>
      <?php $max = max(array_column($per_servizio,'n')) ?: 1; ?>
      <div class="stack-md">
        <?php foreach ($per_servizio as $s): ?>
        <div>
          <div style="display:flex;justify-content:space-between;gap:12px;margin-bottom:6px">
            <strong><?php echo htmlspecialchars($s['nome']) ?></strong>
            <span class="table-secondary"><?php echo $s['n'] ?> appuntamenti</span>
          </div>
          <div class="barra-prog"><div class="barra-prog-fill" style="width:<?php echo round($s['n']/$max*100) ?>%"></div></div>
        </div>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </div>
</div>

<?php if ($occupazione): ?>
<div class="card">
  <div class="card-title-row">
    <div>
      <div class="eyebrow">carico</div>
      <div class="card-title-strong">orari più pieni</div>
    </div>
    <div class="helper-text">fasce più richieste</div>
  </div>
  <div class="list-inline">
    <?php foreach ($occupazione as $o): ?>
    <div class="surface-soft" style="min-width:120px;text-align:center">
      <div style="font-size:1.7rem;color:var(--accent-strong)"><?php echo sprintf('%02d:00',$o['ora']) ?></div>
      <div class="table-secondary"><?php echo $o['n'] ?> appuntamenti</div>
    </div>
    <?php endforeach ?>
  </div>
</div>
<?php endif ?>

<?php require 'layout_bottom.php'; ?>
