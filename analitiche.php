<?php
require 'db.php';
require 'auth.php';
$mese = (int)($_GET['mese'] ?? date('m'));
$anno = (int)($_GET['anno'] ?? date('Y'));

$incassi_mensili = $conn->query("
    SELECT DATE_FORMAT(a.data_ora,'%Y-%m') AS periodo,
           COUNT(*) AS n_app,
           COALESCE(SUM(s.prezzo),0) AS totale
    FROM appuntamenti a JOIN servizi s ON a.servizio_id=s.id
    WHERE a.salone_id=$sid AND a.stato='completato'
      AND a.data_ora >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY periodo ORDER BY periodo ASC
")->fetch_all(MYSQLI_ASSOC);

$per_giorno = $conn->query("
    SELECT DAY(data_ora) AS giorno, COUNT(*) AS n
    FROM appuntamenti
    WHERE salone_id=$sid AND MONTH(data_ora)=$mese AND YEAR(data_ora)=$anno AND stato!='annullato'
    GROUP BY giorno ORDER BY giorno
")->fetch_all(MYSQLI_ASSOC);

$top_servizi = $conn->query("
    SELECT s.nome, s.categoria, COUNT(*) AS n, SUM(s.prezzo) AS totale
    FROM appuntamenti a JOIN servizi s ON a.servizio_id=s.id
    WHERE a.salone_id=$sid AND MONTH(a.data_ora)=$mese AND YEAR(a.data_ora)=$anno AND a.stato='completato'
    GROUP BY s.id ORDER BY n DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

$tot_non_ann    = (int)$conn->query("SELECT COUNT(*) AS n FROM appuntamenti WHERE salone_id=$sid AND MONTH(data_ora)=$mese AND YEAR(data_ora)=$anno AND stato!='annullato'")->fetch_assoc()['n'];
$tot_completati = (int)$conn->query("SELECT COUNT(*) AS n FROM appuntamenti WHERE salone_id=$sid AND MONTH(data_ora)=$mese AND YEAR(data_ora)=$anno AND stato='completato'")->fetch_assoc()['n'];
$tasso_occ      = $tot_non_ann > 0 ? round($tot_completati/$tot_non_ann*100) : 0;

$incasso_mese  = (float)$conn->query("SELECT COALESCE(SUM(s.prezzo),0) AS tot FROM appuntamenti a JOIN servizi s ON a.servizio_id=s.id WHERE a.salone_id=$sid AND MONTH(a.data_ora)=$mese AND YEAR(a.data_ora)=$anno AND a.stato='completato'")->fetch_assoc()['tot'];
$nuovi_clienti = (int)$conn->query("SELECT COUNT(*) AS n FROM clienti WHERE salone_id=$sid AND MONTH(created_at)=$mese AND YEAR(created_at)=$anno")->fetch_assoc()['n'];
$ann           = (int)$conn->query("SELECT COUNT(*) AS n FROM appuntamenti WHERE salone_id=$sid AND MONTH(data_ora)=$mese AND YEAR(data_ora)=$anno AND stato='annullato'")->fetch_assoc()['n'];
$ore_lavorate  = round((float)$conn->query("SELECT COALESCE(SUM(s.durata_minuti),0)/60 AS ore FROM appuntamenti a JOIN servizi s ON a.servizio_id=s.id WHERE a.salone_id=$sid AND MONTH(a.data_ora)=$mese AND YEAR(a.data_ora)=$anno AND a.stato='completato'")->fetch_assoc()['ore'], 1);

$nomi_mesi = ['','gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'];

require 'layout_top.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div class="titolo-pagina" style="margin:0">analitiche</div>
  <form method="get" style="display:flex;gap:8px;align-items:center">
    <select name="mese" style="width:auto;margin:0;padding:8px 12px;font-size:.85rem">
      <?php for ($m=1;$m<=12;$m++): ?>
      <option value="<?php echo $m ?>" <?php echo $m===$mese?'selected':'' ?>><?php echo $nomi_mesi[$m] ?></option>
      <?php endfor ?>
    </select>
    <select name="anno" style="width:auto;margin:0;padding:8px 12px;font-size:.85rem">
      <?php for ($a=date('Y');$a>=date('Y')-3;$a--): ?>
      <option value="<?php echo $a ?>" <?php echo $a===$anno?'selected':'' ?>><?php echo $a ?></option>
      <?php endfor ?>
    </select>
    <button type="submit" class="btn btn-grigio btn-piccolo">visualizza</button>
  </form>
</div>

<div class="griglia-4">
  <div class="card">
    <h3>incasso <?php echo $nomi_mesi[$mese] ?></h3>
    <div class="valore" style="color:var(--success)">€<?php echo number_format($incasso_mese,2,',','.') ?></div>
  </div>
  <div class="card">
    <h3>appuntamenti completati</h3>
    <div class="valore"><?php echo $tot_completati ?></div>
  </div>
  <div class="card">
    <h3>tasso occupazione</h3>
    <div class="valore" style="color:var(--accent)"><?php echo $tasso_occ ?>%</div>
    <div class="barra-prog" style="margin-top:8px"><div class="barra-prog-fill" style="width:<?php echo $tasso_occ ?>%"></div></div>
  </div>
  <div class="card">
    <h3>ore lavorate stimate</h3>
    <div class="valore"><?php echo $ore_lavorate ?>h</div>
  </div>
</div>

<div class="griglia-2">
  <div class="card">
    <h3 style="font-size:1rem;color:var(--text);margin-bottom:16px">incasso ultimi 12 mesi</h3>
    <?php if (!$incassi_mensili): ?>
      <p style="color:var(--text-muted);font-size:.88rem">nessun dato ancora</p>
    <?php else: ?>
    <?php $max_inc = max(array_column($incassi_mensili,'totale')) ?: 1; ?>
    <?php foreach ($incassi_mensili as $im): ?>
    <?php [$y,$m2] = explode('-',$im['periodo']); $pct = round($im['totale']/$max_inc*100); ?>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
      <div style="width:70px;font-size:.8rem;color:var(--text-muted);flex-shrink:0"><?php echo $nomi_mesi[(int)$m2] ?> <?php echo $y ?></div>
      <div class="barra-prog" style="flex:1"><div class="barra-prog-fill" style="width:<?php echo $pct ?>%;background:var(--accent2)"></div></div>
      <div style="width:70px;text-align:right;font-size:.82rem;font-weight:600">€<?php echo number_format($im['totale'],0,',','.') ?></div>
    </div>
    <?php endforeach ?>
    <?php endif ?>
  </div>

  <div class="card">
    <h3 style="font-size:1rem;color:var(--text);margin-bottom:16px">appuntamenti per giorno — <?php echo $nomi_mesi[$mese] ?> <?php echo $anno ?></h3>
    <?php if (!$per_giorno): ?>
      <p style="color:var(--text-muted);font-size:.88rem">nessun dato per questo mese</p>
    <?php else: ?>
    <?php $max_g = max(array_column($per_giorno,'n')) ?: 1; ?>
    <?php foreach ($per_giorno as $g): ?>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:7px">
      <div style="width:32px;font-size:.8rem;color:var(--text-muted);flex-shrink:0;text-align:right"><?php echo $g['giorno'] ?></div>
      <div class="barra-prog" style="flex:1"><div class="barra-prog-fill" style="width:<?php echo round($g['n']/$max_g*100) ?>%"></div></div>
      <div style="width:24px;font-size:.8rem;color:var(--text-muted)"><?php echo $g['n'] ?></div>
    </div>
    <?php endforeach ?>
    <?php endif ?>
  </div>
</div>

<?php if ($top_servizi): ?>
<div class="card">
  <h3 style="font-size:1rem;color:var(--text);margin-bottom:16px">top servizi — <?php echo $nomi_mesi[$mese] ?> <?php echo $anno ?></h3>
  <table>
    <thead><tr><th>servizio</th><th>categoria</th><th>prenotazioni</th><th>incasso generato</th></tr></thead>
    <tbody>
      <?php foreach ($top_servizi as $s): ?>
      <tr>
        <td style="font-weight:600"><?php echo htmlspecialchars($s['nome']) ?></td>
        <td style="color:var(--text-muted)"><?php echo htmlspecialchars($s['categoria']) ?></td>
        <td><?php echo $s['n'] ?></td>
        <td style="color:var(--success)">€<?php echo number_format($s['totale'],2,',','.') ?></td>
      </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>
<?php endif ?>

<div class="card">
  <div style="display:flex;gap:32px;flex-wrap:wrap">
    <div>
      <h3>nuovi clienti <?php echo $nomi_mesi[$mese] ?></h3>
      <div class="valore" style="font-size:1.6rem"><?php echo $nuovi_clienti ?></div>
    </div>
    <div>
      <h3>appuntamenti annullati</h3>
      <div class="valore" style="font-size:1.6rem;color:var(--danger)"><?php echo $ann ?></div>
    </div>
    <div>
      <h3>scontrino medio</h3>
      <div class="valore" style="font-size:1.6rem">
        €<?php echo $tot_completati>0 ? number_format($incasso_mese/$tot_completati,2,',','.') : '0,00' ?>
      </div>
    </div>
  </div>
</div>

<?php require 'layout_bottom.php'; ?>