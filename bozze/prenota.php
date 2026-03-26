<?php
require_once __DIR__ . '/db.php';

/*
calcola i conflitti tra la richiesta del cliente e gli appuntamenti gia presenti.
usa gli stati attesa e confermato come slot che occupano davvero il calendario.
*/
function beautifier_trova_conflitti_prenota($conn, $sid, $servizio_id, $data_ora_sql)
{
    $servizio_id = (int) $servizio_id;
    $sid = (int) $sid;
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

    $righe = $conn->query("
        SELECT a.data_ora, s.durata_minuti
        FROM appuntamenti a
        JOIN servizi s ON a.servizio_id=s.id
        WHERE a.salone_id=$sid
          AND a.stato IN ('attesa','confermato')
          AND a.data_ora BETWEEN '$inizio_giorno' AND '$fine_giorno'
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

    $capienza = beautifier_capienza_prenota($conn, $sid);
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
calcola alcuni orari liberi da proporre al cliente nello stesso giorno.
usa slot da 30 minuti tra le 09:00 e le 19:00.
*/
function beautifier_slot_liberi_prenota($conn, $sid, $servizio_id, $data_sql, $limite = 10)
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
        $esito = beautifier_trova_conflitti_prenota($conn, $sid, $servizio_id, $data_ora);
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

$salone_id = (int) ($_GET['id'] ?? $_POST['salone_id'] ?? 0);
$salone = null;

if ($salone_id > 0) {
    $salone = $conn->query("SELECT id, nome_salone FROM saloni WHERE id=$salone_id LIMIT 1")->fetch_assoc();
}

if (!$salone) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>prenotazione</title>
                    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Victor+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/salone.css">
</head>
    <body class="auth-page" data-theme="chiaro">
        <button type="button" class="theme-float" data-theme-toggle><span class="theme-toggle-text">tema scuro</span></button>
<script src="assets/theme.js"></script>
        <div class="wrap"><div class="box">
            <div class="eyebrow">prenotazione</div>
            <h1>ID salone non valido</h1>
            <p>Inserisci un ID salone valido, ad esempio <strong>prenota.php?id=1</strong>.</p>
            <form method="get">
                <label>id salone</label>
                <input type="number" name="id" min="1" required>
                <button type="submit">apri prenotazione</button>
            </form>
        </div></div>
    </body>
    </html>
    <?php
    exit;
}

$sid = (int) $salone['id'];
$nome_salone = htmlspecialchars($salone['nome_salone']);
$tema_prenota = 'chiaro';
$temaResult = $conn->query("SELECT valore FROM impostazioni WHERE salone_id=$sid AND chiave='tema' LIMIT 1");
$temaRow = $temaResult ? $temaResult->fetch_assoc() : null;
if ($temaRow && !empty($temaRow['valore']) && in_array($temaRow['valore'], ['chiaro', 'scuro'], true)) {
    $tema_prenota = $temaRow['valore'];
}
$servizi = [];
$resultServizi = $conn->query("SELECT id, nome, categoria, durata_minuti, prezzo FROM servizi WHERE salone_id=$sid ORDER BY categoria, nome");
if ($resultServizi) {
    while ($row = $resultServizi->fetch_assoc()) {
        $servizi[] = $row;
    }
}

$errore = '';
$successo = false;
$slot_suggeriti = [];
$capienza_prenotazioni = beautifier_capienza_prenota($conn, $sid);
$nome = trim($_POST['_nome'] ?? '');
$cognome = trim($_POST['_cognome'] ?? '');
$telefono = trim($_POST['_telefono'] ?? '');
$email = trim($_POST['_email'] ?? '');
$svid = (int) ($_POST['servizio_id'] ?? 0);
$data_raw = trim($_POST['_data'] ?? '');
$ora = trim($_POST['_ora'] ?? '');
$note = trim($_POST['note'] ?? '');
$servizio_out = '';
$data_fmt = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($nome === '' || $cognome === '' || $svid <= 0 || $data_raw === '') {
        $errore = 'compila i campi obbligatori';
    } else {
        $srv = $conn->query("SELECT id, nome FROM servizi WHERE id=$svid AND salone_id=$sid")->fetch_assoc();
        if (!$srv) {
            $errore = 'servizio non valido';
        } else {
            $n = $conn->real_escape_string($nome);
            $co = $conn->real_escape_string($cognome);
            $te = $conn->real_escape_string($telefono);
            $em = $conn->real_escape_string($email);
            $no = $conn->real_escape_string($note);
            $cliente = null;

            if ($telefono !== '') {
                $cliente = $conn->query("SELECT id FROM clienti WHERE salone_id=$sid AND telefono='$te' LIMIT 1")->fetch_assoc();
            }
            if (!$cliente && $email !== '') {
                $cliente = $conn->query("SELECT id FROM clienti WHERE salone_id=$sid AND email='$em' LIMIT 1")->fetch_assoc();
            }

            if ($cliente) {
                $cid = (int) $cliente['id'];
                $conn->query("UPDATE clienti SET nome='$n', cognome='$co', telefono='$te', email='$em', note='$no' WHERE id=$cid AND salone_id=$sid");
            } else {
                $conn->query("INSERT INTO clienti (salone_id,nome,cognome,telefono,email,note) VALUES ($sid,'$n','$co','$te','$em','$no')");
                $cid = (int) $conn->insert_id;
            }

            $ora_sql = $ora !== '' ? $ora . ':00' : '09:00:00';
            $data_ora_pulita = $data_raw . ' ' . $ora_sql;
            $esito_disponibilita = beautifier_trova_conflitti_prenota($conn, $sid, $svid, $data_ora_pulita);

            if ($esito_disponibilita['conflitti']) {
                $primo_conflitto = $esito_disponibilita['conflitti'][0];
                $errore = 'orario non disponibile: la capienza parallela del salone e piena in quella fascia. scegli un altro orario.';
                $slot_suggeriti = beautifier_slot_liberi_prenota($conn, $sid, $svid, $data_raw);
            } else {
                $data_ora = $conn->real_escape_string($data_ora_pulita);
                $conn->query("INSERT INTO appuntamenti (salone_id,cliente_id,servizio_id,data_ora,stato,note) VALUES ($sid,$cid,$svid,'$data_ora','attesa','$no')");

                $successo = true;
                $servizio_out = htmlspecialchars($srv['nome']);
                $ts = strtotime($data_raw);
                $giorni = ['domenica','lunedì','martedì','mercoledì','giovedì','venerdì','sabato'];
                $mesi = ['','gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'];
                $data_fmt = $giorni[(int) date('w', $ts)] . ' ' . date('j', $ts) . ' ' . $mesi[(int) date('n', $ts)] . ' ' . date('Y', $ts);
            }
        }
    }
}

if (!$slot_suggeriti && $svid > 0 && $data_raw !== '') {
    $slot_suggeriti = beautifier_slot_liberi_prenota($conn, $sid, $svid, $data_raw);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prenota - <?php echo $nome_salone ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Victor+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/salone.css">
</head>
<body data-theme="<?php echo htmlspecialchars($tema_prenota); ?>">
    <button type="button" class="theme-float" data-theme-toggle><span class="theme-toggle-text">tema scuro</span></button>
<script src="assets/theme.js"></script>
    <div class="hero">
        <div class="hero-inner">
            <div class="hero-head">
                <div class="hero-mark" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="10" cy="10" r="6.75" stroke="currentColor" stroke-width="1.25"/>
                        <path d="M10 2.75V8.25" stroke="currentColor" stroke-width="1.25" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="hero-label"><?php echo $nome_salone ?></div>
            </div>
            <h1>Prenota il tuo appuntamento.</h1>
            <p class="hero-sub">in questo salone si possono gestire fino a <?php echo $capienza_prenotazioni ?> prenotazioni nello stesso orario, in base agli account attivi.</p>
        </div>
    </div>

    <div class="page">
        <?php if ($successo): ?>
            <div class="avviso ok">Richiesta ricevuta. Il tuo appuntamento e stato registrato in attesa di conferma.</div>
        <?php endif; ?>
        <?php if ($errore): ?>
            <div class="avviso err"><?php echo htmlspecialchars($errore) ?></div>
        <?php endif; ?>

        <div class="grid">
            <div class="card">
                <?php if ($successo): ?>
                    <div class="eyebrow">richiesta inviata</div>
                    <h2>Grazie, <?php echo htmlspecialchars($nome) ?>.</h2>
                    <p class="sub">Abbiamo registrato la tua richiesta presso <?php echo $nome_salone ?>. Qui trovi il riepilogo dei dati inviati.</p>
                    <div class="summary">
                        <div><strong>Cliente</strong><br><?php echo htmlspecialchars(trim($nome . ' ' . $cognome)) ?></div>
                        <div><strong>Servizio</strong><br><?php echo $servizio_out ?></div>
                        <div><strong>Data</strong><br><?php echo htmlspecialchars($data_fmt ?: '-') ?></div>
                        <div><strong>Orario</strong><br><?php echo htmlspecialchars($ora !== '' ? $ora : '09:00') ?></div>
                        <div><strong>Contatto</strong><br><?php echo htmlspecialchars($telefono !== '' ? $telefono : ($email !== '' ? $email : '-')) ?></div>
                        <div><strong>Note</strong><br><?php echo nl2br(htmlspecialchars($note !== '' ? $note : '-')) ?></div>
                    </div>
                    <div style="margin-top:20px">
                        <a href="prenota.php?id=<?php echo $sid ?>" class="btn alt">nuova prenotazione</a>
                    </div>
                <?php else: ?>
                    <div class="eyebrow">prenotazione online</div>
                    <h2>Compila la richiesta.</h2>
                    <p class="sub">Seleziona il servizio, indica data e ora desiderate e lascia i tuoi dati. Se lo slot e occupato, il sistema ti propone orari liberi nello stesso giorno.</p>
                    <form method="post" action="prenota.php?id=<?php echo $sid ?>">
                        <input type="hidden" name="salone_id" value="<?php echo $sid ?>">
                        <div class="row">
                            <div>
                                <label>nome</label>
                                <input type="text" name="_nome" value="<?php echo htmlspecialchars($nome) ?>" required>
                            </div>
                            <div>
                                <label>cognome</label>
                                <input type="text" name="_cognome" value="<?php echo htmlspecialchars($cognome) ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div>
                                <label>telefono</label>
                                <input type="tel" name="_telefono" value="<?php echo htmlspecialchars($telefono) ?>">
                            </div>
                            <div>
                                <label>email</label>
                                <input type="email" name="_email" value="<?php echo htmlspecialchars($email) ?>">
                            </div>
                        </div>
                        <label>servizio</label>
                        <select name="servizio_id" required>
                            <option value="">seleziona</option>
                            <?php foreach ($servizi as $servizio): ?>
                                <option value="<?php echo (int) $servizio['id'] ?>" <?php echo $svid === (int) $servizio['id'] ? 'selected' : '' ?>><?php echo htmlspecialchars($servizio['nome']) ?> - <?php echo (int) $servizio['durata_minuti'] ?> min - €<?php echo number_format((float) $servizio['prezzo'], 2, ',', '.') ?></option>
                            <?php endforeach ?>
                        </select>
                        <div class="row">
                            <div>
                                <label>data</label>
                                <input type="date" name="_data" value="<?php echo htmlspecialchars($data_raw) ?>" required>
                            </div>
                            <div>
                                <label>ora</label>
                                <input type="time" name="_ora" value="<?php echo htmlspecialchars($ora) ?>" required>
                            </div>
                        </div>
                        <?php if ($slot_suggeriti): ?>
                            <div class="availability-box compact-box">
                                <div class="eyebrow">disponibilita calcolata</div>
                                <div class="availability-title">orari liberi per questo servizio</div>
                                <div class="slot-list">
                                    <?php foreach ($slot_suggeriti as $slot): ?>
                                        <span class="slot-chip"><?php echo htmlspecialchars($slot) ?></span>
                                    <?php endforeach ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <label>note</label>
                        <textarea name="note"><?php echo htmlspecialchars($note) ?></textarea>
                        <button type="submit" class="btn">invia richiesta</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="eyebrow">servizi disponibili</div>
                <h2><?php echo $nome_salone ?></h2>
                <p class="sub">Il link pubblico usa l'id del salone: <strong><?php echo $sid ?></strong>.</p>
                <?php if (!$servizi): ?>
                    <p class="sub">Nessun servizio disponibile al momento.</p>
                <?php else: ?>
                    <div class="service-list">
                        <?php foreach ($servizi as $servizio): ?>
                            <div class="service-item">
                                <div class="service-name"><?php echo htmlspecialchars($servizio['nome']) ?></div>
                                <div class="service-meta"><?php echo htmlspecialchars($servizio['categoria']) ?> · <?php echo (int) $servizio['durata_minuti'] ?> min · €<?php echo number_format((float) $servizio['prezzo'], 2, ',', '.') ?></div>
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer>
        © <?php echo date('Y') ?> <?php echo $nome_salone ?>
    </footer>
</body>
</html>
