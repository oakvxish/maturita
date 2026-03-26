<?php
require 'db.php';
require 'auth.php';

/*
conta quante persone attive possono prendere appuntamenti nello stesso salone.
se il conteggio non restituisce nulla, il sistema usa comunque 1 come capienza minima.
*/
function beautifier_capienza_appuntamenti($conn, $sid)
{
    $sid = (int) $sid;
    $result = $conn->query("
        SELECT COUNT(*) AS n
        FROM user_saloni
        WHERE salone_id=$sid
          AND attivo=1
    " );

    $row = $result ? $result->fetch_assoc() : null;
    $capienza = (int) ($row['n'] ?? 0);
    if ($capienza < 1) {
        $capienza = 1;
    }

    return $capienza;
}

/*
calcola i conflitti tra un nuovo appuntamento e quelli gia presenti nello stesso giorno.
ritorna sia i conflitti sia l'orario finale richiesto.
qui il conflitto vero scatta solo quando gli appuntamenti in sovrapposizione
superano o saturano la capienza disponibile del salone.
*/
function beautifier_trova_conflitti_appuntamento($conn, $sid, $servizio_id, $data_ora_sql, $escludi_id = 0)
{
    $servizio_id = (int) $servizio_id;
    $sid = (int) $sid;
    $escludi_id = (int) $escludi_id;

    $servizio = $conn->query("SELECT durata_minuti FROM servizi WHERE id=$servizio_id AND salone_id=$sid LIMIT 1")->fetch_assoc();
    if (!$servizio) {
        return ['conflitti' => [], 'fine_richiesta' => null, 'durata' => 0];
    }

    $durata = (int) $servizio['durata_minuti'];
    $inizio_richiesto = strtotime($data_ora_sql);
    $fine_richiesta = strtotime('+' . $durata . ' minutes', $inizio_richiesto);
    $giorno = date('Y-m-d', $inizio_richiesto);
    $inizio_giorno = $conn->real_escape_string($giorno . ' 00:00:00');
    $fine_giorno = $conn->real_escape_string($giorno . ' 23:59:59');

    $where_escludi = $escludi_id > 0 ? " AND a.id<>$escludi_id" : '';
    $righe = $conn->query("
        SELECT a.id, a.data_ora, a.stato,
               c.nome, c.cognome,
               s.nome AS servizio_nome, s.durata_minuti
        FROM appuntamenti a
        JOIN servizi s ON a.servizio_id=s.id
        LEFT JOIN clienti c ON a.cliente_id=c.id
        WHERE a.salone_id=$sid
          AND a.stato IN ('attesa','confermato')
          AND a.data_ora BETWEEN '$inizio_giorno' AND '$fine_giorno'
          $where_escludi
        ORDER BY a.data_ora ASC
    ");

    $sovrapposti = [];
    if ($righe) {
        while ($r = $righe->fetch_assoc()) {
            $inizio_esistente = strtotime($r['data_ora']);
            $fine_esistente = strtotime('+' . (int) $r['durata_minuti'] . ' minutes', $inizio_esistente);
            if ($inizio_richiesto < $fine_esistente && $fine_richiesta > $inizio_esistente) {
                $sovrapposti[] = $r;
            }
        }
    }

    $capienza = beautifier_capienza_appuntamenti($conn, $sid);
    $conflitti = count($sovrapposti) >= $capienza ? $sovrapposti : [];

    return [
        'conflitti' => $conflitti,
        'sovrapposti' => $sovrapposti,
        'fine_richiesta' => $fine_richiesta,
        'durata' => $durata,
        'capienza' => $capienza
    ];
}

/*
calcola alcuni orari disponibili per lo stesso giorno, usando slot da 30 minuti.
serve per proporre alternative immediate quando l'orario scelto e occupato.
*/
function beautifier_slot_disponibili($conn, $sid, $servizio_id, $data_sql, $limite = 10)
{
    $servizio_id = (int) $servizio_id;
    $sid = (int) $sid;
    $limite = (int) $limite;
    $data_sql = trim((string) $data_sql);
    if ($servizio_id <= 0 || $data_sql === '') {
        return [];
    }

    $apertura = strtotime($data_sql . ' 09:00:00');
    $chiusura = strtotime($data_sql . ' 19:00:00');
    $slot = [];

    for ($ts = $apertura; $ts < $chiusura; $ts = strtotime('+30 minutes', $ts)) {
        $data_ora = date('Y-m-d H:i:s', $ts);
        $esito = beautifier_trova_conflitti_appuntamento($conn, $sid, $servizio_id, $data_ora);
        if (!$esito['durata']) {
            break;
        }
        if ($esito['fine_richiesta'] > $chiusura) {
            continue;
        }
        if (!$esito['conflitti']) {
            $slot[] = date('H:i', $ts);
        }
        if (count($slot) >= $limite) {
            break;
        }
    }

    return $slot;
}

$messaggio = '';
$errore    = '';
$slot_suggeriti = [];

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
            $esito_disponibilita = beautifier_trova_conflitti_appuntamento($conn, $sid, $svid, $data);
            if ($esito_disponibilita['conflitti']) {
                $primo_conflitto = $esito_disponibilita['conflitti'][0];
                $errore = 'orario non disponibile: la capienza parallela del salone e piena in quella fascia. ultimo slot occupato tra le ' . date('H:i', strtotime($primo_conflitto['data_ora'])) . ' e ' . date('H:i', strtotime('+' . (int) $primo_conflitto['durata_minuti'] . ' minutes', strtotime($primo_conflitto['data_ora']))) . '.';
                $slot_suggeriti = beautifier_slot_disponibili($conn, $sid, $svid, date('Y-m-d', strtotime($data)));
            } else {
                $conn->query("INSERT INTO appuntamenti (salone_id,cliente_id,servizio_id,data_ora,stato,note)
                              VALUES ($sid,$cid,$svid,'$data','$stato','$note')");
                $messaggio = 'appuntamento creato';
            }
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
$ricerca_cliente_crea_raw = trim($_GET['cliente'] ?? '');
$ricerca_cliente_crea = $conn->real_escape_string($ricerca_cliente_crea_raw);
$cliente_preselezionato = (int)($_GET['cliente_id'] ?? $_POST['cliente_id'] ?? 0);

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

$where_clienti_crea = "salone_id=$sid";
if ($ricerca_cliente_crea !== '') $where_clienti_crea .= " AND (nome LIKE '%$ricerca_cliente_crea%' OR cognome LIKE '%$ricerca_cliente_crea%' OR telefono LIKE '%$ricerca_cliente_crea%' OR email LIKE '%$ricerca_cliente_crea%')";

$clienti = $conn->query("SELECT id,nome,cognome,telefono,email FROM clienti WHERE $where_clienti_crea ORDER BY cognome,nome LIMIT 80")->fetch_all(MYSQLI_ASSOC);
$totale_clienti_crea = count($clienti);
$servizi = $conn->query("SELECT id,nome,prezzo,durata_minuti FROM servizi WHERE salone_id=$sid ORDER BY nome")->fetch_all(MYSQLI_ASSOC);

$servizio_preselezionato = (int)($_POST['servizio_id'] ?? 0);
$data_ora_precompilata = $_POST['data_ora'] ?? '';
if (!$slot_suggeriti && $servizio_preselezionato > 0 && $data_ora_precompilata !== '') {
    $slot_suggeriti = beautifier_slot_disponibili($conn, $sid, $servizio_preselezionato, date('Y-m-d', strtotime($data_ora_precompilata)));
}

$capienza_salotto = beautifier_capienza_appuntamenti($conn, $sid);
$tot_app = count($appuntamenti);
$tot_attesa = 0;
$tot_confermati = 0;
$tot_completati = 0;
foreach ($appuntamenti as $a) {
    if ($a['stato'] === 'attesa') $tot_attesa++;
    if ($a['stato'] === 'confermato') $tot_confermati++;
    if ($a['stato'] === 'completato') $tot_completati++;
}

require 'layout_top.php';
?>

<div class="page-head">
  <div class="titolo-pagina">appuntamenti</div>
  <div class="page-intro">Creazione rapida, filtro chiaro e cambio stato immediato. L’obiettivo qui è ridurre il numero di passaggi per registrare, confermare o chiudere un appuntamento.</div>
</div>

<?php if ($messaggio): ?><div class="avviso avviso-ok"><?php echo $messaggio ?></div><?php endif ?>
<?php if ($errore): ?><div class="avviso avviso-err"><?php echo $errore ?></div><?php endif ?>

<div class="metric-grid">
  <div class="metric"><div class="metric-label">risultati</div><div class="metric-value"><?php echo $tot_app ?></div><div class="metric-note">appuntamenti visibili con i filtri attivi</div></div>
  <div class="metric"><div class="metric-label">in attesa</div><div class="metric-value" style="color:var(--warn)"><?php echo $tot_attesa ?></div><div class="metric-note">da confermare o spostare</div></div>
  <div class="metric"><div class="metric-label">confermati</div><div class="metric-value"><?php echo $tot_confermati ?></div><div class="metric-note">già fissati in agenda</div></div>
  <div class="metric"><div class="metric-label">completati</div><div class="metric-value" style="color:var(--ok)"><?php echo $tot_completati ?></div><div class="metric-note">già erogati</div></div>
  <div class="metric"><div class="metric-label">slot paralleli</div><div class="metric-value"><?php echo $capienza_salotto ?></div><div class="metric-note">appuntamenti contemporanei gestibili nello stesso orario</div></div>
</div>

<div class="griglia-2">
  <div class="card">
    <div class="card-title-row">
      <div>
        <div class="eyebrow">inserimento</div>
        <div class="card-title-strong">nuovo appuntamento</div>
      </div>
      <div class="helper-text">prima trova il cliente, poi compila il resto</div>
    </div>

    <form method="get" class="toolbar-form">
      <input type="hidden" name="stato" value="<?php echo htmlspecialchars($filtro_stato) ?>">
      <input type="hidden" name="data" value="<?php echo htmlspecialchars($filtro_data) ?>">
      <div class="toolbar-row">
        <div>
          <label>cerca cliente</label>
          <input type="text" name="cliente" value="<?php echo htmlspecialchars($ricerca_cliente_crea_raw) ?>" placeholder="nome, cognome, telefono, email">
        </div>
        <button type="submit" class="btn btn-grigio">trova</button>
        <a href="appuntamenti.php<?php echo ($filtro_stato || $filtro_data) ? '?' . http_build_query(['stato' => $filtro_stato, 'data' => $filtro_data]) : '' ?>" class="btn btn-grigio">reset</a>
      </div>
      <div class="info-soft"><?php echo $ricerca_cliente_crea_raw !== '' ? $totale_clienti_crea . ' clienti trovati' : 'nessuna ricerca attiva' ?></div>
    </form>

    <div class="surface-soft mt-16">in questo salone puoi gestire fino a <strong><?php echo $capienza_salotto ?></strong> appuntamenti nello stesso orario, in base agli account attivi collegati al salone.</div>

    <form method="post" class="mt-16">
      <input type="hidden" name="azione" value="crea">
      <label>cliente *</label>
      <select name="cliente_id" required>
        <option value="">scegli cliente</option>
        <?php foreach ($clienti as $c): ?>
        <option value="<?php echo $c['id'] ?>" <?php echo $cliente_preselezionato === (int)$c['id'] ? 'selected' : '' ?>><?php echo htmlspecialchars($c['cognome'].' '.$c['nome'].($c['telefono'] ? ' · '.$c['telefono'] : '')) ?></option>
        <?php endforeach ?>
      </select>
      <label>servizio *</label>
      <select name="servizio_id" required>
        <option value="">scegli servizio</option>
        <?php foreach ($servizi as $s): ?>
        <option value="<?php echo $s['id'] ?>" <?php echo $servizio_preselezionato === (int)$s['id'] ? "selected" : "" ?>><?php echo htmlspecialchars($s['nome']) ?> · €<?php echo $s['prezzo'] ?> · <?php echo $s['durata_minuti'] ?> min</option>
        <?php endforeach ?>
      </select>
      <div class="riga-form">
        <div>
          <label>data e ora *</label>
          <input type="datetime-local" name="data_ora" value="<?php echo htmlspecialchars($data_ora_precompilata) ?>" required>
        </div>
        <div>
          <label>stato iniziale</label>
          <select name="stato">
            <option value="attesa">in attesa</option>
            <option value="confermato">confermato</option>
          </select>
        </div>
      </div>
      <label>note</label>
      <textarea name="note" rows="2" placeholder="note opzionali per il team o per il cliente"><?php echo htmlspecialchars($_POST['note'] ?? '') ?></textarea>
      <button type="submit" class="btn">aggiungi appuntamento</button>
    </form>

    <?php if ($slot_suggeriti): ?>
      <div class="availability-box mt-16">
        <div class="eyebrow">disponibilita calcolata</div>
        <div class="availability-title">orari liberi nello stesso giorno</div>
        <div class="slot-list">
          <?php foreach ($slot_suggeriti as $slot): ?>
            <span class="slot-chip"><?php echo htmlspecialchars($slot) ?></span>
          <?php endforeach ?>
        </div>
      </div>
    <?php endif ?>
  </div>

  <div class="card">
    <div class="card-title-row">
      <div>
        <div class="eyebrow">filtro</div>
        <div class="card-title-strong">trova appuntamenti</div>
      </div>
    </div>
    <form method="get" class="stack-md">
      <div>
        <label>stato</label>
        <select name="stato">
          <option value="">tutti</option>
          <?php foreach (['attesa','confermato','completato','annullato'] as $st): ?>
          <option value="<?php echo $st ?>" <?php echo $filtro_stato===$st?'selected':'' ?>><?php echo $st ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div>
        <label>data</label>
        <input type="date" name="data" value="<?php echo htmlspecialchars($filtro_data) ?>">
      </div>
      <div class="toolbar-actions">
        <button type="submit" class="btn btn-grigio">applica filtri</button>
        <a href="appuntamenti.php" class="btn btn-grigio">reset</a>
      </div>
    </form>
    <div class="surface-soft mt-16">
      <strong>Best effort operativo</strong>
      <p class="mt-8">La lista è pensata per cercare, confrontare e agire subito. Per questo il cambio stato resta in tabella e non in una pagina separata.</p>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-title-row">
    <div>
      <div class="eyebrow">agenda</div>
      <div class="card-title-strong">elenco appuntamenti</div>
    </div>
    <div class="helper-text">stato modificabile direttamente dalla riga</div>
  </div>

  <?php if (!$appuntamenti): ?>
    <div class="empty-state">
      <strong>Nessun appuntamento trovato</strong>
      <p>Prova a cambiare i filtri oppure inserisci un nuovo appuntamento dal pannello qui sopra.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>quando</th><th>cliente</th><th>servizio</th><th>durata</th><th>prezzo</th><th>stato</th><th>azioni</th></tr></thead>
        <tbody>
          <?php foreach ($appuntamenti as $a): ?>
          <tr>
            <td>
              <strong><?php echo date('d/m/Y', strtotime($a['data_ora'])) ?></strong>
              <div class="table-secondary"><?php echo date('H:i', strtotime($a['data_ora'])) ?></div>
            </td>
            <td>
              <strong><?php echo htmlspecialchars($a['nome'].' '.$a['cognome']) ?></strong>
              <div class="table-secondary"><?php echo htmlspecialchars($a['telefono']) ?: 'senza telefono' ?></div>
            </td>
            <td><?php echo htmlspecialchars($a['servizio']) ?></td>
            <td><?php echo $a['durata_minuti'] ?> min</td>
            <td>€<?php echo number_format($a['prezzo'],2,',','.') ?></td>
            <td><span class="badge badge-<?php echo $a['stato'] ?>"><?php echo $a['stato'] ?></span></td>
            <td>
              <div class="toolbar-actions">
                <form method="post" style="display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap">
                  <input type="hidden" name="azione" value="aggiorna_stato">
                  <input type="hidden" name="id" value="<?php echo $a['id'] ?>">
                  <select name="stato" style="width:auto;min-width:138px;margin:0">
                    <?php foreach (['attesa','confermato','completato','annullato'] as $st): ?>
                    <option value="<?php echo $st ?>" <?php echo $a['stato']===$st?'selected':'' ?>><?php echo $st ?></option>
                    <?php endforeach ?>
                  </select>
                  <button type="submit" class="btn btn-piccolo btn-grigio">salva</button>
                </form>
                <form method="post" style="display:inline">
                  <input type="hidden" name="azione" value="elimina">
                  <input type="hidden" name="id" value="<?php echo $a['id'] ?>">
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
