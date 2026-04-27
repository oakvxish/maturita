<?php
// Connessione DB e funzioni comuni per il calcolo slot.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/booking_slots.php';

/**
 * Escape HTML centralizzato per output sicuro nel template.
 *
 * @param mixed $value Valore da stampare.
 * @return string Stringa escaped con ENT_QUOTES.
 */
function h($value)
{
  return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Converte una data SQL (`YYYY-MM-DD`) in formato italiano leggibile.
 *
 * @param string $dataSql Data raw.
 * @return string Data formattata o valore originale se non valida.
 */
function data_lunga($dataSql)
{
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dataSql)) {
    return $dataSql;
  }

  $timestamp = strtotime($dataSql);
  if (!$timestamp) {
    return $dataSql;
  }

  $giorni = ['domenica', 'lunedi', 'martedi', 'mercoledi', 'giovedi', 'venerdi', 'sabato'];
  $mesi = ['', 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];

  return $giorni[(int) date('w', $timestamp)] . ' ' . date('j', $timestamp) . ' ' . $mesi[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

/**
 * Calcola l'ora di fine slot da data + ora inizio + durata servizio.
 *
 * @param string $dataSql Data `YYYY-MM-DD`.
 * @param string $ora Ora `HH:MM`.
 * @param int|string $durataMinuti Durata del servizio in minuti.
 * @return string Ora fine `HH:MM` o stringa vuota se input non valido.
 */
function ora_fine_slot($dataSql, $ora, $durataMinuti)
{
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dataSql)) {
    return '';
  }
  if (!preg_match('/^\d{2}:\d{2}$/', (string) $ora)) {
    return '';
  }

  $inizio = strtotime($dataSql . ' ' . $ora . ':00');
  if (!$inizio) {
    return '';
  }

  return date('H:i', strtotime('+' . (int) $durataMinuti . ' minutes', $inizio));
}

// 1) Leggo ID salone da GET (priorita) oppure POST.
$saloneId = (int) ($_GET['id'] ?? $_POST['salone_id'] ?? 0);
$salone = null;

// Recupero il primo salone disponibile per fallback.
$resultPrimoSalone = $conn->query("SELECT id, nome_salone FROM saloni ORDER BY id ASC LIMIT 1");
$primoSalone = $resultPrimoSalone ? $resultPrimoSalone->fetch_assoc() : null;

// Se l'ID e valido, provo a leggere quel salone.
if ($saloneId > 0) {
  $resultSalone = $conn->query("SELECT id, nome_salone FROM saloni WHERE id = $saloneId LIMIT 1");
  $salone = $resultSalone ? $resultSalone->fetch_assoc() : null;
}

// Se non trovo il salone richiesto:
// - su GET faccio redirect al primo salone;
// - su POST continuo sul primo salone per non perdere i dati form.
if (!$salone && $primoSalone) {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    header('Location: home.php?id=' . (int) $primoSalone['id'], true, 302);
    exit;
  }
  $salone = $primoSalone;
}

// Se non esiste nessun salone, mostro errore semplice.
if (!$salone) {
  http_response_code(404);
  echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Portale prenotazione</title></head><body style="font-family:Arial,sans-serif;padding:40px;background:#f9f4ed;color:#1c1917"><h1>Nessun salone disponibile</h1><p>Crea prima un salone dal gestionale oppure apri questa pagina con un id valido, ad esempio <strong>home.php?id=1</strong>.</p></body></html>';
  exit;
}

// Dati base salone corrente.
$sid = (int) $salone['id'];
$nome_salone = (string) $salone['nome_salone'];

// 2) Carico i servizi del salone.
$servizi = [];
$resultServizi = $conn->query("SELECT id, nome, categoria, durata_minuti, prezzo FROM servizi WHERE salone_id = $sid ORDER BY categoria, nome");
if ($resultServizi) {
  while ($row = $resultServizi->fetch_assoc()) {
    $servizi[] = $row;
  }
}

// 3) Preparo card categoria (nome categoria, esempi servizi, prezzo minimo).
$cardServizi = [];
foreach ($servizi as $servizio) {
  $categoria = trim((string) ($servizio['categoria'] ?? ''));
  if ($categoria === '') {
    $categoria = 'servizi';
  }

  $chiave = strtolower($categoria);
  if (!isset($cardServizi[$chiave])) {
    $cardServizi[$chiave] = [
      'categoria' => ucfirst($categoria),
      'nomi' => [],
      'prezzo_minimo' => null,
    ];
  }

  $cardServizi[$chiave]['nomi'][] = (string) $servizio['nome'];

  $prezzoCorrente = (float) $servizio['prezzo'];
  if ($cardServizi[$chiave]['prezzo_minimo'] === null || $prezzoCorrente < $cardServizi[$chiave]['prezzo_minimo']) {
    $cardServizi[$chiave]['prezzo_minimo'] = $prezzoCorrente;
  }
}

// Lista categorie (tenuta per compatibilita con il template).
$categorie_footer = [];
foreach ($cardServizi as $cardServizio) {
  $categorie_footer[] = $cardServizio['categoria'];
}

// 4) Stato iniziale form e variabili UI.
$form = [
  '_nome' => '',
  '_cognome' => '',
  '_telefono' => '',
  '_email' => '',
  'servizio_id' => '',
  '_data' => '',
  '_ora' => '',
  'note' => '',
];
$errore = '';
$info_form = '';
$successo = false;
$riepilogo = [];
$orari_disponibili = [];
$servizioSelezionato = null;
$ha_dati_slot = false;
$motivo_data_slot = '';
$data_min_prenotazione = date('Y-m-d');
$fasce_prenotazione_testo = testo_fasce_slot_comuni();

// Se POST, ricarico valori dal form.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  foreach ($form as $chiave => $valore) {
    $form[$chiave] = trim((string) ($_POST[$chiave] ?? ''));
  }
}

// 5) Servizio selezionato.
$servizioIdCorrente = (int) ($form['servizio_id'] ?: 0);
if ($servizioIdCorrente > 0) {
  foreach ($servizi as $servizioItem) {
    if ((int) $servizioItem['id'] === $servizioIdCorrente) {
      $servizioSelezionato = $servizioItem;
      break;
    }
  }
}

// 6) Validazione data + ricerca slot disponibili.
if ($form['_data'] !== '') {
  $motivo_data_slot = motivo_data_non_prenotabile($form['_data']);
}

$ha_dati_slot = $servizioSelezionato !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['_data']) === 1;

if ($ha_dati_slot && $motivo_data_slot === '') {
  $orari_disponibili = slot_liberi_comuni($conn, $sid, $servizioIdCorrente, $form['_data'], 12);
  if ($form['_ora'] !== '' && !in_array($form['_ora'], $orari_disponibili, true)) {
    $form['_ora'] = '';
  }
} elseif ($motivo_data_slot !== '') {
  $form['_ora'] = '';
}

// 7) Gestione submit.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $azione = (string) ($_POST['azione'] ?? 'prenota');

  // Azione "mostra orari liberi".
  if ($azione === 'aggiorna_orari') {
    if (!$servizioSelezionato || !$ha_dati_slot) {
      $errore = 'scegli prima trattamento e data';
    } elseif ($motivo_data_slot !== '') {
      $errore = $motivo_data_slot;
    } elseif (!$orari_disponibili) {
      $errore = 'nessun orario libero per questo trattamento nella data scelta';
    } else {
      $info_form = 'gli orari liberi sono stati caricati direttamente nel menu "ora disponibile"';
    }
  } else {
    // Azione "prenota".
    if ($form['_nome'] === '' || $form['_cognome'] === '' || $form['_telefono'] === '') {
      $errore = 'compila tutti i campi obbligatori';
    } elseif (!$servizioSelezionato || !$ha_dati_slot) {
      $errore = 'scegli prima trattamento e data';
    } elseif ($motivo_data_slot !== '') {
      $errore = $motivo_data_slot;
    } elseif (!$orari_disponibili) {
      $errore = 'nessun orario libero per questo trattamento nella data scelta';
    } elseif ($form['_ora'] === '' || !in_array($form['_ora'], $orari_disponibili, true)) {
      $errore = 'scegli un orario libero dal menu';
    } else {
      // Escape base per query SQL manuali.
      $nomeSql = $conn->real_escape_string($form['_nome']);
      $cognomeSql = $conn->real_escape_string($form['_cognome']);
      $telefonoSql = $conn->real_escape_string($form['_telefono']);
      $emailSql = $conn->real_escape_string($form['_email']);
      $noteSql = $conn->real_escape_string($form['note']);

      // Cerco cliente per telefono, altrimenti per email (se presente).
      $cliente = null;
      $resultCliente = $conn->query("SELECT id FROM clienti WHERE salone_id = $sid AND telefono = '$telefonoSql' LIMIT 1");
      if ($resultCliente) {
        $cliente = $resultCliente->fetch_assoc();
      }

      if (!$cliente && $form['_email'] !== '') {
        $resultClienteEmail = $conn->query("SELECT id FROM clienti WHERE salone_id = $sid AND email = '$emailSql' LIMIT 1");
        if ($resultClienteEmail) {
          $cliente = $resultClienteEmail->fetch_assoc();
        }
      }

      // Se esiste aggiorno anagrafica, altrimenti creo cliente nuovo.
      if ($cliente) {
        $clienteId = (int) $cliente['id'];
        $conn->query("UPDATE clienti SET nome = '$nomeSql', cognome = '$cognomeSql', telefono = '$telefonoSql', email = '$emailSql', note = '$noteSql' WHERE id = $clienteId AND salone_id = $sid");
      } else {
        $conn->query("INSERT INTO clienti (salone_id, nome, cognome, telefono, email, note) VALUES ($sid, '$nomeSql', '$cognomeSql', '$telefonoSql', '$emailSql', '$noteSql')");
        $clienteId = (int) $conn->insert_id;
      }

      // Inserisco appuntamento in stato "attesa".
      $dataOraSql = $conn->real_escape_string($form['_data'] . ' ' . $form['_ora'] . ':00');
      $conn->query("INSERT INTO appuntamenti (salone_id, cliente_id, servizio_id, data_ora, stato, note) VALUES ($sid, $clienteId, $servizioIdCorrente, '$dataOraSql', 'attesa', '$noteSql')");

      // Imposto stato successo + riepilogo da mostrare a schermo.
      $successo = true;
      $riepilogo = [
        'cliente' => trim($form['_nome'] . ' ' . $form['_cognome']),
        'servizio' => (string) $servizioSelezionato['nome'],
        'data' => data_lunga($form['_data']),
        'ora' => $form['_ora'],
        'contatto' => $form['_telefono'],
      ];

      // Reset form dopo invio.
      $form = [
        '_nome' => '',
        '_cognome' => '',
        '_telefono' => '',
        '_email' => '',
        'servizio_id' => '',
        '_data' => '',
        '_ora' => '',
        'note' => '',
      ];
      $orari_disponibili = [];
      $servizioSelezionato = null;
      $servizioIdCorrente = 0;
      $ha_dati_slot = false;
    }
  }
}

// 8) Variabili finali per il template.
$orari_select = $ha_dati_slot ? $orari_disponibili : [];
$capienza = capienza_slot_comune($conn, $sid);
$riepilogo_servizio = 'da scegliere';
$riepilogo_durata = 'in attesa del trattamento';
$riepilogo_data = 'da scegliere';
$riepilogo_orario = 'da scegliere';
$riepilogo_cliente = 'inserisci nome e recapito';

if ($servizioSelezionato) {
  $riepilogo_servizio = (string) $servizioSelezionato['nome'];
  $riepilogo_durata = (int) $servizioSelezionato['durata_minuti'] . ' minuti';
}

if ($ha_dati_slot) {
  $riepilogo_data = data_lunga($form['_data']);
}

if ($form['_ora'] !== '') {
  $riepilogo_orario = $form['_ora'];
  if ($servizioSelezionato) {
    $oraFine = ora_fine_slot($form['_data'], $form['_ora'], (int) $servizioSelezionato['durata_minuti']);
    if ($oraFine !== '') {
      $riepilogo_orario .= ' - fine ' . $oraFine;
    }
  }
}

if ($form['_nome'] !== '' || $form['_telefono'] !== '') {
  $riepilogoCliente = trim($form['_nome'] . ' ' . $form['_cognome']);
  if ($riepilogoCliente === '') {
    $riepilogoCliente = $form['_telefono'];
  } elseif ($form['_telefono'] !== '') {
    $riepilogoCliente .= ' - ' . $form['_telefono'];
  }
  $riepilogo_cliente = $riepilogoCliente;
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LumiÃ¨re â€” Prenotazioni</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --cream: #f9f4ed;
            --warm-white: #fffdf9;
            --charcoal: #1c1917;
            --muted: #78716c;
            --gold: #b5924c;
            --gold-light: #e8d5b0;
            --border: #e8e0d5;
            --nav-h: 70px;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Jost', sans-serif;
            background: var(--warm-white);
            color: var(--charcoal);
            overflow-x: hidden;
        }

        nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 200;
            height: var(--nav-h);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 clamp(20px, 5vw, 60px);
            background: #f9f4ed;
            border-bottom: 1px solid transparent;
            transition: border-color 0.3s;
        }

        nav.scrolled {
            border-bottom-color: var(--border);
        }

        .nav-logo {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.55rem;
            font-weight: 300;
            letter-spacing: 0.12em;
            color: var(--charcoal);
            text-decoration: none;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-logo svg {
            width: 32px;
            height: 32px;
        }

        .nav-logo em {
            font-style: italic;
            color: var(--gold);
        }

        .nav-links {
            display: flex;
            gap: clamp(20px, 3vw, 40px);
            list-style: none;
        }

        .nav-links a {
            font-size: 0.72rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--muted);
            text-decoration: none;
            transition: color 0.2s;
            white-space: nowrap;
        }

        .nav-links a:hover {
            color: var(--charcoal);
        }

        .nav-cta {
            font-size: 0.7rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--gold);
            border: 1px solid var(--gold);
            padding: 9px 20px;
            text-decoration: none;
            white-space: nowrap;
            transition: all 0.25s;
            flex-shrink: 0;
        }

        .nav-cta:hover {
            background: var(--gold);
            color: #fff;
        }


        .hero {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
            padding-top: var(--nav-h);
        }

        .hero-left {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: clamp(40px, 6vw, 80px) clamp(24px, 4vw, 60px) clamp(40px, 6vw, 80px) clamp(24px, 6vw, 80px);
        }

        .hero-tag {
            font-size: 0.68rem;
            letter-spacing: 0.35em;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 14px;
            animation: fadeUp 0.7s ease 0.1s both;
        }

        .hero-tag::before {
            content: '';
            width: 36px;
            height: 1px;
            background: var(--gold);
            flex-shrink: 0;
        }

        .hero-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2.8rem, 5.5vw, 5.2rem);
            font-weight: 300;
            line-height: 1.05;
            color: var(--charcoal);
            margin-bottom: 24px;
            animation: fadeUp 0.7s ease 0.25s both;
        }

        .hero-title em {
            font-style: italic;
            color: var(--gold);
        }

        .hero-text {
            font-size: clamp(0.85rem, 1.5vw, 0.95rem);
            line-height: 1.85;
            color: var(--muted);
            max-width: 380px;
            margin-bottom: 40px;
            font-weight: 300;
            animation: fadeUp 0.7s ease 0.4s both;
        }

        .hero-btns {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
            animation: fadeUp 0.7s ease 0.55s both;
        }

        .btn-primary {
            background: var(--charcoal);
            color: var(--cream);
            padding: 15px 32px;
            text-decoration: none;
            font-size: 0.72rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            transition: all 0.25s;
            white-space: nowrap;
        }

        .btn-primary:hover {
            background: var(--gold);
        }

        .btn-ghost {
            color: var(--charcoal);
            text-decoration: none;
            font-size: 0.72rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: color 0.2s;
            white-space: nowrap;
        }

        .btn-ghost:hover {
            color: var(--gold);
        }

        .btn-ghost::after {
            content: 'Ã¢â€ â€™';
            font-size: 1rem;
        }

        .hero-right {
            position: relative;
            overflow: hidden;
            background: var(--cream);
        }

        .hero-img-placeholder {
            width: 100%;
            height: 100%;
            min-height: 400px;
            position: relative;
        }

        .hero-img-placeholder img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
            display: block;
        }

        .hero-badge {
            position: absolute;
            bottom: 40px;
            left: -20px;
            background: var(--charcoal);
            color: var(--gold-light);
            padding: 18px 26px;
            font-family: 'Cormorant Garamond', serif;
            font-size: 0.85rem;
            font-style: italic;
            letter-spacing: 0.05em;
            box-shadow: 0 20px 60px rgba(28, 25, 23, 0.25);
        }

        .hero-badge strong {
            display: block;
            font-size: 1.9rem;
            font-weight: 300;
            font-style: normal;
        }

        .servizi {
            padding: clamp(60px, 8vw, 100px) clamp(20px, 6vw, 80px);
            background: var(--cream);
        }

        .section-header {
            text-align: center;
            margin-bottom: clamp(40px, 6vw, 70px);
        }

        .section-tag {
            font-size: 0.68rem;
            letter-spacing: 0.35em;
            text-transform: uppercase;
            color: var(--gold);
            display: block;
            margin-bottom: 18px;
        }

        .section-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2rem, 4vw, 3.4rem);
            font-weight: 300;
            line-height: 1.15;
        }

        .section-title em {
            font-style: italic;
            color: var(--gold);
        }

        .servizi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2px;
        }

        .servizio-card {
            background: var(--warm-white);
            padding: clamp(24px, 3vw, 40px) clamp(20px, 2.5vw, 32px);
            border: 1px solid var(--border);
            position: relative;
            transition: all 0.3s;
            overflow: hidden;
        }

        .servizio-card::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--gold);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s;
        }

        .servizio-card:hover {
            background: var(--charcoal);
        }

        .servizio-card:hover .servizio-nome,
        .servizio-card:hover .servizio-desc {
            color: var(--cream);
        }

        .servizio-card:hover .servizio-prezzo {
            color: var(--gold);
        }

        .servizio-card:hover::before {
            transform: scaleX(1);
        }

        .servizio-card:hover .servizio-num {
            color: rgba(181, 146, 76, 0.2);
        }

        .servizio-num {
            font-family: 'Cormorant Garamond', serif;
            font-size: 3rem;
            font-weight: 300;
            color: var(--border);
            line-height: 1;
            margin-bottom: 18px;
            transition: color 0.3s;
        }

        .servizio-nome {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.25rem;
            font-weight: 400;
            color: var(--charcoal);
            margin-bottom: 10px;
            transition: color 0.3s;
        }

        .servizio-desc {
            font-size: 0.8rem;
            line-height: 1.7;
            color: var(--muted);
            margin-bottom: 18px;
            transition: color 0.3s;
        }

        .servizio-prezzo {
            font-size: 0.72rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--gold);
            font-weight: 500;
            transition: color 0.3s;
        }

        .about {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 60vh;
        }

        .about-img {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: flex-end;
            min-height: 360px;
        }

        .about-img img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .about-img::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(28, 25, 23, 0.55) 0%, rgba(28, 25, 23, 0.1) 60%, transparent 100%);
        }

        .about-img-quote {
            position: relative;
            z-index: 2;
            padding: 32px;
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(1rem, 2vw, 1.35rem);
            font-style: italic;
            font-weight: 300;
            color: rgba(255, 255, 255, 0.95);
            line-height: 1.5;
        }

        .about-text {
            padding: clamp(40px, 6vw, 80px) clamp(24px, 5vw, 80px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: var(--charcoal);
        }

        .about-text .section-tag {
            text-align: left;
        }

        .about-text .section-title {
            color: var(--cream);
            text-align: left;
            margin-bottom: 24px;
        }

        .about-text p {
            font-size: 0.9rem;
            line-height: 1.85;
            color: #a8a29e;
            font-weight: 300;
            margin-bottom: 18px;
        }

        .about-stats {
            display: flex;
            gap: clamp(24px, 4vw, 48px);
            margin-top: 36px;
            padding-top: 36px;
            border-top: 1px solid #3d3835;
            flex-wrap: wrap;
        }

        .stat-num {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2rem, 3.5vw, 2.8rem);
            font-weight: 300;
            color: var(--gold);
            display: block;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.68rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #78716c;
            margin-top: 5px;
            display: block;
        }

        .prenotazione {
            padding: clamp(60px, 8vw, 100px) clamp(20px, 6vw, 80px);
            background: var(--warm-white);
            position: relative;
            overflow: hidden;
        }

        .prenotazione-deco {
            position: absolute;
            top: 40px;
            right: 40px;
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(4rem, 9vw, 9rem);
            font-weight: 300;
            color: var(--border);
            line-height: 1;
            pointer-events: none;
            user-select: none;
            letter-spacing: -0.02em;
        }

        .prenotazione-inner {
            display: grid;
            grid-template-columns: 1fr 1.4fr;
            gap: clamp(40px, 6vw, 80px);
            align-items: start;
            max-width: 1100px;
            position: relative;
        }

        .prenotazione-info .section-title {
            text-align: left;
            margin-bottom: 20px;
        }

        .prenotazione-info>p {
            font-size: 0.88rem;
            line-height: 1.8;
            color: var(--muted);
            font-weight: 300;
            margin-bottom: 28px;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 18px;
        }

        .info-icon {
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            border: 1px solid var(--border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            color: var(--gold);
        }

        .info-label {
            font-size: 0.68rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--muted);
            display: block;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 0.88rem;
            color: var(--charcoal);
            font-weight: 400;
        }

        .form-card {
            background: var(--cream);
            padding: clamp(28px, 4vw, 50px);
            border: 1px solid var(--border);
            position: relative;
        }

        .form-card::before {
            content: '';
            position: absolute;
            top: -1px;
            left: 40px;
            right: 40px;
            height: 2px;
            background: var(--gold);
        }

        .form-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.55rem;
            font-weight: 400;
            margin-bottom: 4px;
        }

        .form-subtitle {
            font-size: 0.78rem;
            color: var(--muted);
            margin-bottom: 32px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 0.65rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 7px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 0;
            background: var(--warm-white);
            color: var(--charcoal);
            font-family: 'Jost', sans-serif;
            font-size: 0.88rem;
            font-weight: 300;
            transition: border-color 0.2s, box-shadow 0.2s;
            appearance: none;
            -webkit-appearance: none;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(181, 146, 76, 0.08);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%2378716c' fill='none' stroke-width='1.5'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 36px;
            cursor: pointer;
        }

        .form-note {
            font-size: 0.74rem;
            color: var(--muted);
            margin-bottom: 24px;
            line-height: 1.65;
            padding: 12px 14px;
            background: var(--warm-white);
            border-left: 2px solid var(--gold-light);
        }

        .inline-alert {
            padding: 14px 16px;
            margin-bottom: 18px;
            border: 1px solid var(--border);
            background: var(--warm-white);
            font-size: 0.82rem;
            line-height: 1.6;
        }

        .inline-alert.ok {
            color: #1f6b4f;
            border-color: rgba(31, 107, 79, 0.25);
        }

        .inline-alert.err {
            color: #9c2f2f;
            border-color: rgba(156, 47, 47, 0.25);
        }

        .inline-alert.info {
            color: #6b4b24;
            border-color: rgba(181, 146, 76, 0.28);
            background: rgba(255, 249, 240, 0.92);
        }

        .availability-box {
            margin-bottom: 18px;
            padding: 16px;
            border: 1px solid var(--border);
            background: var(--warm-white);
        }

        .availability-title {
            font-size: 0.68rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 10px;
        }

        .slot-list {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .slot-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border: 1px solid var(--border);
            font-size: 0.8rem;
            color: var(--charcoal);
            background: #fff;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
            margin-bottom: 22px;
        }

        .summary-item {
            padding: 14px;
            background: var(--warm-white);
            border: 1px solid var(--border);
        }

        .summary-item strong {
            display: block;
            font-size: 0.68rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .form-flow {
            display: grid;
            gap: 22px;
        }

        .form-step {
            padding: 24px;
            border: 1px solid rgba(184, 144, 96, 0.16);
            background: rgba(255, 255, 255, 0.46);
        }

        .form-step-head {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 16px;
            align-items: start;
            margin-bottom: 18px;
        }

        .form-step-num {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            border: 1px solid rgba(184, 144, 96, 0.22);
            background: rgba(255, 249, 240, 0.92);
            color: var(--ink-soft-luxury);
            font-size: 0.88rem;
            font-weight: 600;
            letter-spacing: 0.08em;
        }

        .form-step-label {
            font-size: 0.66rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 4px;
        }

        .form-step-title {
            font-family: var(--font-serif);
            font-size: 1.35rem;
            font-weight: 600;
            line-height: 1.1;
            color: var(--ink-deep);
            margin-bottom: 6px;
        }

        .form-step-text {
            font-size: 0.84rem;
            line-height: 1.7;
            color: #7a665a;
        }

        .form-lead {
            display: grid;
            gap: 18px;
        }

        .form-lead-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            align-items: start;
        }

        .form-step-actions {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            grid-column: 1 / -1;
            padding-top: 2px;
        }

        .form-step-actions .form-submit {
            min-width: 280px;
            width: auto;
            padding-inline: 30px;
        }

        .field-hint {
            font-size: 0.78rem;
            line-height: 1.7;
            color: var(--muted);
            margin-top: 8px;
        }

        .field-hint strong {
            color: var(--charcoal);
            font-weight: 500;
        }

        .form-review {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .form-review-item {
            padding: 14px 16px;
            border: 1px solid rgba(184, 144, 96, 0.16);
            background: rgba(255, 255, 255, 0.74);
        }

        .form-review-item strong {
            display: block;
            margin-bottom: 6px;
            font-size: 0.66rem;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .form-review-item span {
            display: block;
            color: var(--ink-deep);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .form-actions button {
            flex: 1 1 220px;
        }

        .form-step .form-actions {
            margin-top: 6px;
        }

        .form-submit {
            width: 100%;
            padding: 16px;
            background: var(--charcoal);
            color: var(--gold-light);
            border: none;
            cursor: pointer;
            font-family: 'Jost', sans-serif;
            font-size: 0.72rem;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            transition: all 0.25s;
            font-weight: 500;
        }

        .form-submit:hover {
            background: var(--gold);
            color: #fff;
        }

        .form-submit.alt {
            background: transparent;
            color: var(--charcoal);
            border: 1px solid var(--charcoal);
        }

        .form-submit.alt:hover {
            background: var(--charcoal);
            color: var(--warm-white);
        }

        .testimonial {
            background: var(--cream);
            padding: clamp(50px, 7vw, 80px) clamp(20px, 6vw, 80px);
            text-align: center;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .stars {
            color: var(--gold);
            font-size: 1rem;
            letter-spacing: 4px;
            margin-bottom: 24px;
        }

        .testimonial-text {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(1.2rem, 2.5vw, 1.9rem);
            font-weight: 300;
            font-style: italic;
            line-height: 1.55;
            color: var(--charcoal);
            max-width: 680px;
            margin: 0 auto 20px;
        }

        .testimonial-author {
            font-size: 0.7rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--muted);
        }

        footer {
            background: var(--charcoal);
            color: #a8a29e;
            padding: clamp(48px, 6vw, 60px) clamp(20px, 6vw, 80px) clamp(32px, 4vw, 40px);
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1fr;
            gap: clamp(32px, 5vw, 60px);
        }

        .footer-brand .nav-logo {
            color: var(--gold-light);
            display: block;
            margin-bottom: 14px;
        }

        .footer-brand p {
            font-size: 0.82rem;
            line-height: 1.75;
            font-weight: 300;
        }

        .footer-col h4 {
            font-size: 0.66rem;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 18px;
        }

        .footer-col ul {
            list-style: none;
        }

        .footer-col ul li {
            margin-bottom: 9px;
        }

        .footer-col ul li a {
            color: #78716c;
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 300;
            transition: color 0.2s;
        }

        .footer-col ul li a:hover {
            color: var(--gold-light);
        }

        .footer-col p {
            font-size: 0.82rem;
            line-height: 1.8;
            font-weight: 300;
        }

        .footer-bottom {
            background: var(--charcoal);
            padding: 18px clamp(20px, 6vw, 80px);
            border-top: 1px solid #2c2926;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .footer-bottom p {
            font-size: 0.73rem;
            color: #57534e;
            font-weight: 300;
        }

        .footer-bottom a {
            color: var(--gold);
            text-decoration: none;
            font-size: 0.73rem;
        }

        :root {
            --rose: #f2e2d8;
            --rose-strong: #dcb9a7;
            --panel: rgba(255, 251, 246, 0.78);
            --ivory: #fffaf4;
            --champagne: #ead7ba;
            --champagne-strong: #c79a5a;
            --ink-deep: #161110;
            --ink-soft-luxury: #3a2a22;
            --shadow-soft: 0 22px 60px rgba(60, 37, 26, 0.12);
            --shadow-card: 0 36px 100px rgba(34, 21, 16, 0.18);
            --font-body: 'Jost', sans-serif;
            --font-serif: 'Cormorant Garamond', serif;
            --font-display: 'Burgues Script', 'Cormorant Garamond', serif;
        }

        body {
            font-family: var(--font-body);
            background:
                radial-gradient(circle at top left, rgba(220, 185, 167, 0.30) 0%, transparent 28%),
                radial-gradient(circle at right 12%, rgba(199, 154, 90, 0.12) 0%, transparent 22%),
                linear-gradient(180deg, #fffdf9 0%, #f7ede2 48%, #fffaf4 100%);
        }

        #servizi,
        #about,
        #prenota,
        #contatti {
            scroll-margin-top: calc(var(--nav-h) + 26px);
        }

        nav {
            top: 12px;
            left: 50%;
            right: auto;
            transform: translateX(-50%);
            width: min(1360px, calc(100% - 28px));
            background: linear-gradient(180deg, rgba(255, 252, 248, 0.94) 0%, rgba(255, 248, 241, 0.82) 100%);
            border-bottom: 1px solid rgba(199, 154, 90, 0.14);
            border-radius: 26px;
            box-shadow: 0 10px 38px rgba(52, 35, 28, 0.07);
        }

        nav.scrolled {
            border-bottom-color: rgba(199, 154, 90, 0.24);
            box-shadow: 0 18px 46px rgba(52, 35, 28, 0.12);
        }

        .hero-title,
        .section-title,
        .form-title {
            font-family: var(--font-display);
            font-weight: 400;
            letter-spacing: 0.01em;
        }

        .hero-title em,
        .section-title em {
            font-family: var(--font-serif);
            font-style: italic;
        }

        .nav-logo {
            font-size: clamp(2rem, 3vw, 2.8rem);
            letter-spacing: 0.02em;
            line-height: 1;
            color: var(--ink-deep);
            text-shadow: 0 10px 28px rgba(199, 154, 90, 0.10);
        }

        .hero {
            position: relative;
            overflow: clip;
            align-items: stretch;
            max-width: 1360px;
            min-height: min(940px, calc(100vh - var(--nav-h) - 48px));
            margin: calc(var(--nav-h) + 26px) auto 0;
            padding-top: 0;
            border: 1px solid rgba(199, 154, 90, 0.16);
            box-shadow: 0 42px 120px rgba(34, 21, 16, 0.16);
            grid-template-columns: minmax(0, 0.92fr) minmax(0, 1.08fr);
            background:
                linear-gradient(90deg, rgba(255, 252, 247, 0.98) 0%, rgba(255, 250, 244, 0.96) 54%, rgba(243, 228, 214, 0.92) 54%, rgba(234, 214, 194, 0.96) 100%);
        }

        .hero::before {
            content: '';
            position: absolute;
            top: calc(var(--nav-h) - 120px);
            left: -140px;
            width: min(42vw, 520px);
            aspect-ratio: 1;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(199, 154, 90, 0.18) 0%, rgba(220, 185, 167, 0.12) 46%, transparent 72%);
            pointer-events: none;
        }

        .hero::after {
            content: '';
            position: absolute;
            top: 110px;
            right: min(8vw, 96px);
            width: min(30vw, 320px);
            height: min(40vw, 460px);
            border: 1px solid rgba(255, 255, 255, 0.35);
            pointer-events: none;
        }

        .hero-left,
        .hero-right,
        .servizi,
        .about,
        .prenotazione {
            position: relative;
            z-index: 1;
        }

        .hero-left {
            max-width: 620px;
            padding: clamp(72px, 9vw, 124px) clamp(32px, 4.8vw, 72px) clamp(64px, 8vw, 104px);
        }

        .hero-tag,
        .section-tag {
            color: var(--champagne-strong);
            letter-spacing: 0.34em;
        }

        .hero-title {
            font-size: clamp(4.2rem, 9vw, 7rem);
            line-height: 0.84;
            margin-bottom: 30px;
            text-wrap: balance;
            color: var(--ink-deep);
            text-shadow: 0 18px 34px rgba(199, 154, 90, 0.08);
        }

        .hero-text {
            max-width: 500px;
            font-size: 1rem;
            color: #69564c;
            margin-bottom: 34px;
        }

        .btn-primary,
        .nav-cta,
        .form-submit {
            border-radius: 999px;
            box-shadow: 0 14px 30px rgba(32, 24, 22, 0.12);
        }

        .btn-primary,
        .nav-cta {
            background: linear-gradient(135deg, #241b19 0%, #584135 100%);
            border: none;
        }

        .btn-primary:hover,
        .nav-cta:hover,
        .form-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 34px rgba(32, 24, 22, 0.18);
        }

        .btn-ghost {
            font-weight: 500;
        }

        .hero-details {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-top: 34px;
            max-width: 560px;
        }

        .hero-detail {
            padding: 16px 18px;
            border: 1px solid rgba(199, 154, 90, 0.16);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.78) 0%, rgba(252, 245, 238, 0.88) 100%);
            box-shadow: 0 16px 36px rgba(74, 50, 39, 0.08);
        }

        .hero-detail strong {
            display: block;
            margin-bottom: 4px;
            color: var(--ink-deep);
            font-size: 0.8rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-weight: 600;
        }

        .hero-detail span {
            display: block;
            color: #7a665a;
            font-size: 0.82rem;
            line-height: 1.5;
        }

        .hero-right {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(20px, 3vw, 36px);
            background:
                radial-gradient(circle at top right, rgba(255, 255, 255, 0.68) 0%, transparent 38%),
                linear-gradient(180deg, rgba(236, 220, 205, 0.72) 0%, rgba(223, 193, 172, 0.88) 100%);
        }

        .hero-img-placeholder {
            position: relative;
            width: 100%;
            min-height: 620px;
            border-radius: 0;
            overflow: hidden;
            border: 1px solid rgba(255, 248, 240, 0.46);
            box-shadow: var(--shadow-card);
        }

        .hero-img-placeholder img {
            width: 100%;
            height: 100%;
            min-height: 620px;
            object-fit: cover;
            object-position: center top;
            display: block;
            filter: saturate(0.88) contrast(1.04) brightness(0.96);
        }

        .hero-img-placeholder::before {
            content: '';
            position: absolute;
            inset: 26px;
            border: 1px solid rgba(255, 250, 243, 0.30);
            z-index: 1;
            pointer-events: none;
        }

        .hero-img-placeholder::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(32, 24, 22, 0.04) 0%, rgba(32, 24, 22, 0.22) 100%);
            pointer-events: none;
        }

        .hero-badge {
            position: absolute;
            z-index: 2;
            left: 28px;
            bottom: 28px;
            padding: 18px 24px 22px;
            border-radius: 0;
            background: linear-gradient(180deg, #17110f 0%, #100c0b 100%);
            border: 1px solid rgba(234, 215, 186, 0.30);
            box-shadow: 0 24px 64px rgba(24, 18, 16, 0.28);
        }

        .hero-badge strong {
            display: block;
            color: #fff5e8;
            font-size: 2.9rem;
            line-height: 0.9;
        }

        .hero-badge span {
            color: rgba(255, 240, 224, 0.78);
            font-size: 0.74rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
        }

        .servizi {
            padding-inline: clamp(22px, 4vw, 42px);
            padding-block: clamp(86px, 10vw, 128px);
            background:
                radial-gradient(circle at top center, rgba(220, 185, 167, 0.16) 0%, transparent 28%),
                radial-gradient(circle at bottom right, rgba(199, 154, 90, 0.10) 0%, transparent 24%),
                linear-gradient(180deg, #fbf6f0 0%, #f6ede4 100%);
        }

        .section-header {
            max-width: 820px;
            margin-inline: auto;
        }

        .section-title {
            font-size: clamp(3rem, 6vw, 4.8rem);
            line-height: 0.9;
            margin-inline: auto;
            max-width: 14ch;
        }

        .servizi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 28px;
            max-width: 1140px;
            margin: 0 auto;
            align-items: start;
        }

        .servizio-card {
            border-radius: 0;
            border-color: rgba(184, 144, 96, 0.16);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.84) 0%, rgba(250, 245, 238, 0.98) 100%);
            box-shadow: 0 28px 62px rgba(74, 50, 39, 0.10);
        }

        .servizio-card:hover {
            background: linear-gradient(180deg, #2d211d 0%, #1c1412 100%);
            transform: translateY(-10px);
            box-shadow: 0 34px 78px rgba(32, 24, 22, 0.22);
        }

        .servizio-card::before {
            inset: 0;
            height: auto;
            opacity: 0.10;
            background: linear-gradient(135deg, rgba(199, 154, 90, 0.58) 0%, transparent 58%);
            transform: none;
        }

        .servizio-num {
            color: rgba(199, 154, 90, 0.34);
        }

        .about {
            max-width: 1280px;
            margin: 0 auto;
            grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
            border: 1px solid rgba(234, 215, 186, 0.12);
            box-shadow: 0 34px 90px rgba(20, 14, 12, 0.20);
            background: linear-gradient(180deg, #1b1412 0%, #261b18 100%);
        }

        .about-img {
            min-height: 620px;
        }

        .about-img::before {
            content: '';
            position: absolute;
            inset: 24px;
            border: 1px solid rgba(255, 240, 224, 0.24);
            z-index: 1;
            pointer-events: none;
        }

        .about-img-quote {
            max-width: 360px;
            padding: 24px 28px;
            background: #241a17;
            border-left: 2px solid rgba(234, 215, 186, 0.72);
        }

        .about-text {
            padding: clamp(56px, 6vw, 88px) clamp(30px, 5vw, 72px);
            background:
                radial-gradient(circle at top left, rgba(199, 154, 90, 0.14) 0%, transparent 34%),
                linear-gradient(180deg, #1b1412 0%, #2f221d 100%);
        }

        .about-text p {
            color: rgba(255, 248, 241, 0.72);
        }

        .about-stats {
            border-top-color: rgba(234, 215, 186, 0.18);
            gap: 18px;
        }

        .about-stats>div {
            min-width: 140px;
            padding: 16px 0 0;
        }

        .stat-num {
            text-shadow: 0 12px 24px rgba(199, 154, 90, 0.14);
        }

        .prenotazione {
            padding-inline: clamp(22px, 4vw, 42px);
            padding-block: clamp(92px, 10vw, 132px);
            background:
                radial-gradient(circle at top right, rgba(220, 185, 167, 0.18) 0%, transparent 24%),
                radial-gradient(circle at bottom left, rgba(199, 154, 90, 0.08) 0%, transparent 22%),
                linear-gradient(180deg, #fffaf5 0%, #f8efe7 100%);
        }

        .prenotazione-inner {
            max-width: 1180px;
            margin: 0 auto;
            align-items: stretch;
            grid-template-columns: minmax(300px, 0.78fr) minmax(0, 1.22fr);
            gap: clamp(34px, 5vw, 72px);
        }

        .prenotazione-info {
            padding: 28px 0;
            position: sticky;
            top: calc(var(--nav-h) + 26px);
            align-self: start;
        }

        .form-card {
            align-self: start;
            border-radius: 0;
            border-color: rgba(184, 144, 96, 0.20);
            background:
                radial-gradient(circle at top right, rgba(199, 154, 90, 0.08) 0%, transparent 24%),
                #fffbf6;
            box-shadow: var(--shadow-card);
        }

        .form-card::before {
            left: 28px;
            right: 28px;
            height: 3px;
            border-radius: 999px;
            background: linear-gradient(90deg, rgba(184, 144, 96, 0) 0%, rgba(184, 144, 96, 1) 50%, rgba(184, 144, 96, 0) 100%);
        }

        .form-subtitle,
        .prenotazione-info>p {
            color: #6d6059;
        }

        .form-title {
            margin-bottom: 8px;
            color: var(--ink-deep);
        }

        .info-item {
            padding: 14px 0;
            border-bottom: 1px solid rgba(199, 154, 90, 0.14);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-icon {
            border-color: rgba(199, 154, 90, 0.24);
            background: #fff9f2;
            box-shadow: 0 12px 28px rgba(199, 154, 90, 0.08);
        }

        .form-group input,
        .form-group select,
        .form-group textarea,
        .summary-item,
        .availability-box {
            border-radius: 0;
            border-color: rgba(184, 144, 96, 0.18);
            background: #fffdf9;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }

        .summary-item {
            box-shadow: 0 18px 38px rgba(74, 50, 39, 0.06);
        }

        .summary-item strong,
        .availability-title,
        .form-note {
            color: var(--ink-soft-luxury);
        }

        .field-hint {
            color: #7a665a;
        }

        .form-step,
        .form-review-item {
            border-color: rgba(199, 154, 90, 0.18);
        }

        .form-note {
            border-left-color: rgba(199, 154, 90, 0.42);
            background: linear-gradient(180deg, #fffbf6 0%, #f7efe7 100%);
        }

        .slot-chip {
            border-radius: 999px;
            background: #f2e2d8;
            border-color: rgba(199, 154, 90, 0.24);
        }

        .testimonial {
            position: relative;
            max-width: 1220px;
            margin: 0 auto;
            border: 1px solid rgba(199, 154, 90, 0.16);
            box-shadow: 0 30px 84px rgba(34, 21, 16, 0.12);
            background: linear-gradient(180deg, rgba(255, 250, 245, 0.92) 0%, rgba(246, 237, 228, 0.96) 100%);
        }

        .testimonial::before,
        .testimonial::after {
            content: '';
            position: absolute;
            top: 50%;
            width: min(18vw, 220px);
            height: 1px;
            background: linear-gradient(90deg, transparent 0%, rgba(199, 154, 90, 0.50) 100%);
        }

        .testimonial::before {
            left: clamp(20px, 5vw, 60px);
        }

        .testimonial::after {
            right: clamp(20px, 5vw, 60px);
            transform: scaleX(-1);
        }

        .testimonial-text {
            font-size: clamp(2rem, 4vw, 3.4rem);
            line-height: 0.98;
            max-width: 14ch;
        }

        footer {
            margin-top: clamp(38px, 6vw, 64px);
            background:
                radial-gradient(circle at top left, rgba(186, 146, 76, 0.16) 0%, transparent 24%),
                linear-gradient(180deg, #241b19 0%, #16100f 100%);
        }

        .footer-brand p,
        .footer-col p,
        .footer-col ul li a {
            color: rgba(255, 239, 220, 0.62);
        }

        .footer-col h4 {
            color: var(--gold-light);
        }

        .footer-bottom {
            background: #181110;
            border-top-color: rgba(234, 215, 186, 0.12);
        }

        .servizi,
        .prenotazione {
            border-top: 1px solid rgba(199, 154, 90, 0.12);
        }

        .servizi-grid,
        .prenotazione-inner,
        .about,
        footer,
        .footer-bottom {
            width: min(1240px, calc(100% - 28px));
            margin-left: auto;
            margin-right: auto;
        }

        .prenotazione-info {
            max-width: 360px;
        }

        .prenotazione-info .section-title {
            margin-bottom: 16px;
        }

        .prenotazione-info>p {
            margin-bottom: 12px;
        }

        .info-item {
            margin-bottom: 0;
        }

        .info-icon {
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.08em;
        }

        .form-flow {
            gap: 18px;
        }

        .form-step {
            padding: clamp(16px, 2.1vw, 24px);
        }

        .form-review-item span {
            text-wrap: pretty;
        }

        @media (max-width: 1280px) {
            .hero {
                grid-template-columns: minmax(0, 0.98fr) minmax(0, 1.02fr);
            }

            .hero-left {
                max-width: 100%;
                padding: clamp(54px, 7vw, 92px) clamp(24px, 3.4vw, 46px) clamp(48px, 6vw, 76px);
            }

            .hero-title {
                font-size: clamp(3.4rem, 7.4vw, 5.8rem);
            }

            .hero-details {
                max-width: none;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .prenotazione-inner {
                grid-template-columns: minmax(280px, 0.85fr) minmax(0, 1.15fr);
                gap: clamp(24px, 4vw, 44px);
            }

            .form-review {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .footer-bottom {
                width: min(1240px, calc(100% - 28px));
            }
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(28px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1024px) {
            .hero-details {
                grid-template-columns: 1fr;
                max-width: 460px;
            }

            .servizi-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            footer {
                grid-template-columns: 1fr 1fr;
            }

            .footer-brand {
                grid-column: 1 / -1;
            }

            .prenotazione-inner {
                grid-template-columns: 1fr;
            }

            .prenotazione-info {
                max-width: 100%;
                position: static;
                padding-top: 0;
            }

            .prenotazione-deco {
                display: none;
            }
        }

        @media (max-width: 768px) {
            :root {
                --nav-h: 64px;
            }

            nav {
                top: 8px;
                width: calc(100% - 16px);
                border-radius: 20px;
                height: auto;
                min-height: var(--nav-h);
                flex-wrap: wrap;
                gap: 10px 14px;
                padding: 10px 16px;
            }

            .nav-links {
                order: 3;
                width: 100%;
                gap: 10px;
                justify-content: flex-start;
                overflow-x: auto;
                scrollbar-width: thin;
                padding-bottom: 2px;
            }

            .nav-links a {
                font-size: 0.66rem;
                letter-spacing: 0.16em;
            }

            .nav-cta {
                margin-left: auto;
                font-size: 0.62rem;
                padding: 8px 14px;
            }

            .hero {
                grid-template-columns: 1fr;
                min-height: auto;
                margin-top: calc(var(--nav-h) + 18px);
                width: calc(100% - 16px);
            }

            .hero-right {
                order: -1;
                height: 55vw;
                min-height: 220px;
            }

            .hero-img-placeholder {
                min-height: 0;
                height: 100%;
            }

            .hero-badge {
                display: none;
            }

            .hero-left {
                padding: 36px 20px 52px;
            }

            .hero-title {
                font-size: clamp(3rem, 14vw, 4.5rem);
                line-height: 0.9;
            }

            .section-title {
                font-size: clamp(2.5rem, 12vw, 3.6rem);
                max-width: none;
            }

            .nav-logo {
                font-size: 1.9rem;
            }

            .hero-text {
                max-width: 100%;
            }

            .hero-btns {
                flex-direction: column;
                align-items: stretch;
                gap: 14px;
            }

            .hero-details {
                grid-template-columns: 1fr;
            }

            .btn-primary {
                text-align: center;
                padding: 16px;
            }

            .btn-ghost {
                justify-content: center;
            }

            .servizi-grid {
                grid-template-columns: 1fr;
            }

            .about {
                grid-template-columns: 1fr;
            }

            .about-img {
                min-height: 260px;
            }

            .about-img-inner {
                font-size: 6rem;
            }

            .about-text {
                padding: 40px 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-lead-grid {
                grid-template-columns: 1fr;
            }

            .form-step-actions {
                align-items: stretch;
            }

            .form-step-actions .form-submit {
                width: 100%;
                min-width: 0;
                padding-inline: 20px;
            }

            .form-step {
                padding: 18px;
            }

            .form-review {
                grid-template-columns: 1fr;
            }

            .prenotazione-info {
                position: static;
            }

            .form-card {
                padding: 28px 20px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .form-step-head {
                grid-template-columns: 1fr;
            }

            .form-step-num {
                width: 34px;
                height: 34px;
            }

            footer {
                grid-template-columns: 1fr;
                gap: 36px;
                width: calc(100% - 16px);
            }

            .testimonial::before,
            .testimonial::after {
                display: none;
            }

            .footer-brand {
                grid-column: auto;
            }

            .footer-bottom {
                width: calc(100% - 16px);
                padding: 18px 14px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
        }

        @media (max-width: 400px) {
            .hero-title {
                font-size: 2.4rem;
            }

            .nav-logo {
                font-size: 1.3rem;
            }

            .about-img-quote {
                font-size: 0.9rem;
            }
        }
    </style>
</head>

<body>

    <!-- nav -->
    <nav id="nav">
        <a href="#" class="nav-logo">
            <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="20" cy="20" r="18" stroke="#b5924c" stroke-width="2"/>
                <path d="M20 8 L20 32 M12 20 L28 20" stroke="#b5924c" stroke-width="1.5"/>
                <circle cx="20" cy="20" r="4" fill="#b5924c"/>
            </svg>
            <span>LumiÃ¨re</span>
        </a>
        <ul class="nav-links">
            <li><a href="#servizi">servizi</a></li>
            <li><a href="#about">chi siamo</a></li>
            <li><a href="#prenota">prenota</a></li>
            <li><a href="#contatti">contatti</a></li>
        </ul>
        <a href="#prenota" class="nav-cta">prenota ora</a>
    </nav>

    <!-- hero -->
    <section class="hero">
        <div class="hero-left">
            <div class="hero-tag">Milano Â· dal 2010</div>
            <h1 class="hero-title">La bellezza<br>ÃƒÂ¨ un'<em>arte</em><br>da coltivare</h1>
            <p class="hero-text">
                Un rifugio di cura e raffinatezza nel cuore di Milano.
                Ogni trattamento ÃƒÂ¨ pensato per esaltare la tua unicitÃƒÂ ,
                con tecniche all'avanguardia e prodotti selezionati.
            </p>
            <div class="hero-btns">
                <a href="#prenota" class="btn-primary">prenota appuntamento</a>
                <a href="#servizi" class="btn-ghost">scopri i servizi</a>
            </div>
            <div class="hero-details">
                <div class="hero-detail">
                    <strong>atelier privato</strong>
                    <span>rituali su misura in un ambiente raccolto e silenzioso</span>
                </div>
                <div class="hero-detail">
                    <strong>signature care</strong>
                    <span>trattamenti premium con consulenza dedicata e risultati raffinati</span>
                </div>
                <div class="hero-detail">
                    <strong>beauty concierge</strong>
                    <span>prenotazione assistita e attenzioni personalizzate in ogni visita</span>
                </div>
            </div>
        </div>
        <div class="hero-right">
            <div class="hero-img-placeholder">
                <img src="https://images.unsplash.com/photo-1728488448472-16a259c6ba7c?q=80&w=870&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" alt="Salone di bellezza a Milano">

                <div class="hero-badge">
                    <span>clienti soddisfatte</span>
                    <strong>2.400+</strong>
                </div>
            </div>
        </div>
    </section>

    <!-- servizi -->
    <section class="servizi" id="servizi">
        <div class="section-header">
            <span class="section-tag">i nostri servizi</span>
            <h2 class="section-title">Tutto ciÃƒÂ² di cui<br>hai bisogno, in un <em>unico posto</em></h2>
        </div>
        <div class="servizi-grid">
            <?php if ($cardServizi): ?>
              <?php $indiceCarta = 1; ?>
              <?php foreach ($cardServizi as $cardServizio): ?>
                <?php
                $nomi = array_slice($cardServizio['nomi'], 0, 3);
                $descrizione = implode(', ', $nomi);
                if (count($cardServizio['nomi']) > 3) {
                  $descrizione .= ' e altri trattamenti disponibili.';
                } else {
                  $descrizione .= '.';
                }
                ?>
                <div class="servizio-card">
                    <div class="servizio-num"><?php echo str_pad((string) $indiceCarta, 2, '0', STR_PAD_LEFT); ?></div>
                    <div class="servizio-nome"><?php echo h($cardServizio['categoria']); ?></div>
                    <p class="servizio-desc"><?php echo h(ucfirst($descrizione)); ?></p>
                    <div class="servizio-prezzo">da &euro;<?php echo number_format((float) $cardServizio['prezzo_minimo'], 2, ',', '.'); ?></div>
                </div>
                <?php $indiceCarta++; ?>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="servizio-card">
                  <div class="servizio-num">01</div>
                  <div class="servizio-nome">Capelli</div>
                  <p class="servizio-desc">Taglio, piega, colorazione e trattamenti rigeneranti con i migliori prodotti professionali.</p>
                  <div class="servizio-prezzo">da Ã¢â€šÂ¬20</div>
              </div>
              <div class="servizio-card">
                  <div class="servizio-num">02</div>
                  <div class="servizio-nome">Unghie</div>
                  <p class="servizio-desc">Manicure, pedicure e nail art personalizzata. Mani e piedi sempre impeccabili.</p>
                  <div class="servizio-prezzo">da Ã¢â€šÂ¬25</div>
              </div>
              <div class="servizio-card">
                  <div class="servizio-num">03</div>
                  <div class="servizio-nome">Viso</div>
                  <p class="servizio-desc">Pulizie profonde, trattamenti idratanti e rituali anti-etÃƒÂ  per una pelle luminosa.</p>
                  <div class="servizio-prezzo">da Ã¢â€šÂ¬40</div>
              </div>
              <div class="servizio-card">
                  <div class="servizio-num">04</div>
                  <div class="servizio-nome">Corpo</div>
                  <p class="servizio-desc">Ceretta, epilazione e trattamenti corpo per una pelle vellutata e curata nei minimi dettagli.</p>
                  <div class="servizio-prezzo">da Ã¢â€šÂ¬20</div>
              </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- about -->
    <section class="about" id="about">
        <div class="about-img">
            <img src="https://plus.unsplash.com/premium_photo-1666990806921-a9bf7dc72efe?q=80&w=746&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" alt="Interno salone LumiÃ¨re">
            <div class="about-img-quote">"La cura di sÃƒÂ© non ÃƒÂ¨ un lusso,<br>ÃƒÂ¨ una necessitÃƒÂ  dell'anima."</div>
        </div>
        <div class="about-text">
            <span class="section-tag">chi siamo</span>
            <h2 class="section-title" style="color:var(--cream)">Sedici anni di<br><em>passione</em> e cura</h2>
            <p>LumiÃ¨re nasce nel 2010 nel centro di Milano: uno spazio in cui ogni cliente si sente ascoltata, valorizzata e curata con attenzione. Nel tempo abbiamo perfezionato ogni dettaglio, dalla qualitÃ  dei prodotti alla formazione continua del team.</p>
            <p>Il nostro approccio ÃƒÂ¨ semplice: ci prendiamo il tempo necessario per capire le tue esigenze e trasformarle in risultati che durano. Nessuna fretta, solo attenzione e professionalitÃƒÂ .</p>
            <div class="about-stats">
                <div><span class="stat-num">14</span><span class="stat-label">anni di esperienza</span></div>
                <div><span class="stat-num">12</span><span class="stat-label">professioniste</span></div>
                <div><span class="stat-num">98%</span><span class="stat-label">clienti soddisfatte</span></div>
            </div>
        </div>
    </section>

    <!-- prenotazione -->
    <section class="prenotazione" id="prenota">
        <div class="prenotazione-deco">PRENOTA</div>
        <div class="prenotazione-inner">

            <div class="prenotazione-info">
                <span class="section-tag">prenota</span>
                <h2 class="section-title">Regalati un<br>momento <em>tutto tuo</em></h2>
                <p>Prenota il tuo appuntamento in pochi passaggi. Ti confermeremo la richiesta direttamente dal salone.</p>
                <div class="info-item">
                    <div class="info-icon">01</div>
                    <div><span class="info-label">fasce orarie</span><span class="info-value"><?php echo h($fasce_prenotazione_testo); ?></span></div>
                </div>
                <div class="info-item">
                    <div class="info-icon">02</div>
                    <div><span class="info-label">capienza salone</span><span class="info-value"><?php echo (int) $capienza; ?> postazioni attive</span></div>
                </div>
                <div class="info-item">
                    <div class="info-icon">03</div>
                    <div><span class="info-label">contatto diretto</span><span class="info-value">+39 02 8295 4411</span></div>
                </div>
            </div>

            <div class="form-card">
                <h3 class="form-title">Prenota il tuo appuntamento</h3>

                <?php if ($successo): ?>
                  <div class="inline-alert ok">Richiesta inviata correttamente. L'appuntamento ÃƒÂ¨ stato registrato in attesa di conferma.</div>
                  <div class="summary-grid">
                      <div class="summary-item"><strong>cliente</strong><?php echo h($riepilogo['cliente']); ?></div>
                      <div class="summary-item"><strong>servizio</strong><?php echo h($riepilogo['servizio']); ?></div>
                      <div class="summary-item"><strong>data</strong><?php echo h($riepilogo['data']); ?></div>
                      <div class="summary-item"><strong>ora</strong><?php echo h($riepilogo['ora']); ?></div>
                      <div class="summary-item"><strong>contatto</strong><?php echo h($riepilogo['contatto']); ?></div>
                      <div class="summary-item"><strong>salone</strong><?php echo h($nome_salone); ?></div>
                  </div>
                <?php endif; ?>

                <?php if ($errore): ?>
                  <div class="inline-alert err"><?php echo h($errore); ?></div>
                <?php endif; ?>

                <?php if ($info_form): ?>
                  <div class="inline-alert info"><?php echo h($info_form); ?></div>
                <?php endif; ?>

                <form method="POST" action="home.php?id=<?php echo $sid; ?>#prenota">
                    <input type="hidden" name="salone_id" value="<?php echo $sid; ?>">
                    <div class="form-flow">
                        <div class="form-step">
                            <div class="form-step-head">
                                <div class="form-step-num">1</div>
                                <div>
                                    <div class="form-step-label">primo passaggio</div>
                                    <h3 class="form-step-title">scegli trattamento e giorno</h3>
                                    <p class="form-step-text">Parti da qui: il sistema usa la durata reale del servizio per mostrarti solo gli orari davvero prenotabili.</p>
                                </div>
                            </div>

                            <div class="form-lead">
                                <div class="form-lead-grid">
                                    <div class="form-group">
                                        <label>trattamento *</label>
                                        <select name="servizio_id" required>
                                            <option value="">scegli il servizio</option>
                                            <?php foreach ($servizi as $servizio): ?>
                                              <option value="<?php echo (int) $servizio['id']; ?>" <?php echo (int) $form['servizio_id'] === (int) $servizio['id'] ? 'selected' : ''; ?>>
                                                  <?php echo h($servizio['nome']); ?> - <?php echo (int) $servizio['durata_minuti']; ?> min - &euro;<?php echo number_format((float) $servizio['prezzo'], 2, ',', '.'); ?>
                                              </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if ($servizioSelezionato): ?>
                                          <div class="field-hint"><strong>durata selezionata:</strong> <?php echo (int) $servizioSelezionato['durata_minuti']; ?> minuti.</div>
                                        <?php else: ?>
                                          <div class="field-hint">scegli prima il trattamento: da qui parte il calcolo corretto degli slot.</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-group">
                                        <label>giorno preferito *</label>
                                        <input type="date" name="_data" value="<?php echo h($form['_data']); ?>" min="<?php echo h($data_min_prenotazione); ?>" required>
                                        <?php if ($motivo_data_slot !== '' && $form['_data'] !== ''): ?>
                                          <div class="field-hint"><strong><?php echo h($motivo_data_slot); ?></strong></div>
                                        <?php else: ?>
                                          <div class="field-hint">Gli orari vengono filtrati in base alla durata del trattamento, ai giorni di apertura e alla disponibilitÃƒÂ  reale del salone.</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-step-actions">
                                        <button type="submit" name="azione" value="aggiorna_orari" class="form-submit alt" formnovalidate>mostra orari liberi</button>
                                    </div>
                                </div>

                                <div class="form-review">
                                    <div class="form-review-item">
                                        <strong>trattamento</strong>
                                        <span><?php echo h($riepilogo_servizio); ?></span>
                                    </div>
                                    <div class="form-review-item">
                                        <strong>durata</strong>
                                        <span><?php echo h($riepilogo_durata); ?></span>
                                    </div>
                                    <div class="form-review-item">
                                        <strong>giorno</strong>
                                        <span><?php echo h($riepilogo_data); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-step">
                            <div class="form-step-head">
                                <div class="form-step-num">2</div>
                                <div>
                                    <div class="form-step-label">secondo passaggio</div>
                                    <h3 class="form-step-title">scegli uno slot libero</h3>
                                    <p class="form-step-text">Dopo aver aggiornato gli orari, il menu mostra direttamente gli slot compatibili con il servizio scelto.</p>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>ora disponibile *</label>
                                <select name="_ora" required>
                                    <?php if (!$servizioSelezionato || !$ha_dati_slot): ?>
                                      <option value="">scegli prima trattamento e giorno</option>
                                    <?php elseif ($motivo_data_slot !== ''): ?>
                                      <option value=""><?php echo h($motivo_data_slot); ?></option>
                                    <?php elseif (!$orari_select): ?>
                                      <option value="">nessun orario libero per questa data</option>
                                    <?php else: ?>
                                      <option value="">scegli un orario libero</option>
                                      <?php foreach ($orari_select as $orario): ?>
                                        <?php $oraFine = ora_fine_slot($form['_data'], $orario, (int) $servizioSelezionato['durata_minuti']); ?>
                                        <?php $labelOrario = $orario . ($oraFine !== '' ? ' - fine ' . $oraFine : ''); ?>
                                        <option value="<?php echo h($orario); ?>" <?php echo $form['_ora'] === $orario ? 'selected' : ''; ?>><?php echo h($labelOrario); ?></option>
                                      <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <?php if ($motivo_data_slot !== '' && $form['_data'] !== ''): ?>
                                  <div class="field-hint"><?php echo h($motivo_data_slot); ?></div>
                                <?php elseif ($servizioSelezionato && $ha_dati_slot && $orari_select): ?>
                                  <div class="field-hint">
                                      <strong><?php echo count($orari_select); ?> slot liberi</strong> per <?php echo h(strtolower((string) $servizioSelezionato['nome'])); ?> il <?php echo h(data_lunga($form['_data'])); ?>.
                                  </div>
                                <?php elseif ($servizioSelezionato && $ha_dati_slot): ?>
                                  <div class="field-hint">Nessuno slot libero per questa combinazione di trattamento e data. Cambia giorno o scegli un altro servizio.</div>
                                <?php else: ?>
                                  <div class="field-hint">Compila prima il primo passaggio e premi <strong>mostra orari liberi</strong>.</div>
                                <?php endif; ?>
                            </div>

                            <div class="form-review">
                                <div class="form-review-item">
                                    <strong>slot trovati</strong>
                                    <span>
                                        <?php if (!$servizioSelezionato || !$ha_dati_slot): ?>
                                          in attesa della tua scelta
                                        <?php elseif ($motivo_data_slot !== '' && $form['_data'] !== ''): ?>
                                          <?php echo h($motivo_data_slot); ?>
                                        <?php elseif (!$orari_select): ?>
                                          nessuno slot disponibile
                                        <?php else: ?>
                                          <?php echo count($orari_select); ?> orari prenotabili
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="form-review-item">
                                    <strong>fasce prenotabili</strong>
                                    <span><?php echo h($fasce_prenotazione_testo); ?> Ã‚Â· mar - sab</span>
                                </div>
                                <div class="form-review-item">
                                    <strong>slot scelto</strong>
                                    <span><?php echo h($riepilogo_orario); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="form-step">
                            <div class="form-step-head">
                                <div class="form-step-num">3</div>
                                <div>
                                    <div class="form-step-label">terzo passaggio</div>
                                    <h3 class="form-step-title">lascia i tuoi recapiti</h3>
                                    <p class="form-step-text">Inserisci solo i dati essenziali per confermare la richiesta. Le note restano facoltative.</p>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>nome *</label>
                                    <input type="text" name="_nome" value="<?php echo h($form['_nome']); ?>" placeholder="il tuo nome" autocomplete="given-name" required>
                                </div>
                                <div class="form-group">
                                    <label>cognome *</label>
                                    <input type="text" name="_cognome" value="<?php echo h($form['_cognome']); ?>" placeholder="il tuo cognome" autocomplete="family-name" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>telefono *</label>
                                    <input type="tel" name="_telefono" value="<?php echo h($form['_telefono']); ?>" placeholder="+39 333 123 4567" autocomplete="tel" inputmode="tel" required>
                                    <div class="field-hint">Lo usiamo per ricontattarti e confermare l'appuntamento.</div>
                                </div>
                                <div class="form-group">
                                    <label>email</label>
                                    <input type="email" name="_email" value="<?php echo h($form['_email']); ?>" placeholder="nome.cognome@email.com" autocomplete="email">
                                    <div class="field-hint">Facoltativa, utile come recapito secondario e per ritrovare piÃƒÂ¹ facilmente la tua scheda cliente.</div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>note o richieste particolari</label>
                                <textarea name="note" placeholder="allergie, preferenze, dettagli sul servizio..."><?php echo h($form['note']); ?></textarea>
                                <div class="field-hint">Campo facoltativo: aggiungi qui informazioni utili per preparare al meglio la visita.</div>
                            </div>

                            <div class="form-review">
                                <div class="form-review-item">
                                    <strong>cliente</strong>
                                    <span><?php echo h($riepilogo_cliente); ?></span>
                                </div>
                                <div class="form-review-item">
                                    <strong>salone</strong>
                                    <span><?php echo h($nome_salone); ?></span>
                                </div>
                                <div class="form-review-item">
                                    <strong>riepilogo appuntamento</strong>
                                    <span><?php echo h($riepilogo_servizio . ' - ' . $riepilogo_data . ' - ' . $riepilogo_orario); ?></span>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" name="azione" value="prenota" class="form-submit">invia la richiesta</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

        </div>
    </section>


    <!-- footer -->
    <footer id="contatti">
        <div class="footer-brand">
            <a href="#" class="nav-logo">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="20" cy="20" r="18" stroke="#e8d5b0" stroke-width="2"/>
                    <path d="M20 8 L20 32 M12 20 L28 20" stroke="#e8d5b0" stroke-width="1.5"/>
                    <circle cx="20" cy="20" r="4" fill="#e8d5b0"/>
                </svg>
                <span>LumiÃ¨re</span>
            </a>
            <p>Un luogo dove prendersi cura di sÃ© diventa un'esperienza concreta, elegante e rilassante.</p>
        </div>
        <div class="footer-col">
            <h4>servizi</h4>
            <ul>
                <li><a href="#prenota">capelli</a></li>
                <li><a href="#prenota">unghie</a></li>
                <li><a href="#prenota">viso</a></li>
                <li><a href="#prenota">corpo</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>info</h4>
            <ul>
                <li><a href="#about">chi siamo</a></li>
                <li><a href="#prenota">prenota</a></li>
                <li><a href="#">gift card</a></li>
                <li><a href="#">lavora con noi</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>contatti</h4>
            <p>Via Brera 24<br>20121 Milano MI</p>
            <br>
            <p>+39 02 8295 4411<br>info@lumiere-milano.it</p>
            <br>
            <p>Mar Ã¢â‚¬â€œ Sab<br>9:00 Ã¢â‚¬â€œ 19:00</p>
        </div>
    </footer>

    <div class="footer-bottom">
        <p>Â© 2026 LumiÃ¨re Â· P.IVA 12345678901</p>
        <a href="index.php">accesso gestionale Ã¢â€ â€™</a>
    </div>

</body>

</html>



