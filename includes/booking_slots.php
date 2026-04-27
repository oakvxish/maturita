<?php

/**
 * Determina la capienza parallela del salone (quanti appuntamenti possono convivere nello stesso istante).
 *
 * Strategia:
 * 1) Fonte principale: tabella `user_saloni` (staff attivo collegato al salone).
 * 2) Fallback legacy: tabella `userdata` (vecchio modello utenti).
 * 3) Fallback di sicurezza: almeno 1 slot parallelo.
 *
 * Questo approccio mantiene compatibilita con dati nuovi e storici.
 *
 * @param mysqli $conn Connessione database.
 * @param int|string $sid ID del salone.
 * @return int Capienza minima garantita >= 1.
 */
function capienza_slot_comune($conn, $sid)
{
    $sid = (int) $sid;

    $result = $conn->query("SELECT COUNT(*) AS totale FROM user_saloni WHERE salone_id = $sid AND attivo = 1");
    if ($result) {
        $row = $result->fetch_assoc();
        $totale = (int) ($row['totale'] ?? 0);
        if ($totale > 0) {
            return $totale;
        }
    }

    $result = $conn->query("SELECT COUNT(*) AS totale FROM userdata WHERE salone_id = $sid");
    if ($result) {
        $row = $result->fetch_assoc();
        $totale = (int) ($row['totale'] ?? 0);
        if ($totale > 0) {
            return $totale;
        }
    }

    return 1;
}

/**
 * Definisce le finestre orarie prenotabili per la data indicata.
 *
 * Nota: la pausa pranzo viene gestita come "buco" tra due finestre.
 *
 * @param string $dataSql Data in formato YYYY-MM-DD.
 * @return array<int, array{0:string,1:string}> Coppie [inizio, fine] in formato datetime SQL.
 */
function finestre_slot_comuni($dataSql)
{
    return [
        [$dataSql . ' 09:00:00', $dataSql . ' 12:30:00'],
        [$dataSql . ' 14:00:00', $dataSql . ' 19:00:00'],
    ];
}

/**
 * Restituisce il testo human-friendly delle fasce prenotabili.
 * Usato nei form per spiegare all'utente in quali orari cercare disponibilita.
 *
 * @return string
 */
function testo_fasce_slot_comuni()
{
    return '09:00 - 12:30 e 14:00 - 19:00';
}

/**
 * Valida la data scelta nel flusso prenotazione pubblico.
 *
 * Regole applicate:
 * - formato data valido (`YYYY-MM-DD`);
 * - data non nel passato;
 * - solo giorni da martedi a sabato.
 *
 * @param string $dataSql Data utente.
 * @param int|null $adessoTs Timestamp corrente (iniettabile per test), default `time()`.
 * @return string Messaggio di errore; stringa vuota se la data e valida.
 */
function motivo_data_non_prenotabile($dataSql, $adessoTs = null)
{
    $dataSql = trim((string) $dataSql);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataSql)) {
        return 'data non valida';
    }

    if ($adessoTs === null) {
        $adessoTs = time();
    }

    $oggi = date('Y-m-d', $adessoTs);
    if ($dataSql < $oggi) {
        return 'non puoi prenotare una data passata';
    }

    $giornoSettimana = (int) date('w', strtotime($dataSql . ' 00:00:00'));
    if (in_array($giornoSettimana, [0, 1], true)) {
        return 'il salone accetta prenotazioni da martedi a sabato';
    }

    return '';
}

/**
 * Granularita degli orari mostrati all'utente.
 * Esempio: 30 -> 09:00, 09:30, 10:00...
 *
 * @return int Minuti per ciascun "tick" di slot.
 */
function intervallo_slot_minuti_comuni()
{
    return 30;
}

/**
 * Buffer operativo minimo per gli slot nello stesso giorno.
 * Evita proposte troppo a ridosso dell'orario corrente.
 *
 * @return int Minuti di anticipo minimo richiesto.
 */
function anticipo_minimo_slot_comuni()
{
    return 15;
}

/**
 * Recupera la durata (in minuti) del servizio richiesto nel salone corrente.
 *
 * @param mysqli $conn Connessione database.
 * @param int|string $sid ID salone.
 * @param int|string $servizioId ID servizio.
 * @return int Durata in minuti; 0 se non trovata/non valida.
 */
function durata_servizio_slot_comune($conn, $sid, $servizioId)
{
    $sid = (int) $sid;
    $servizioId = (int) $servizioId;

    $result = $conn->query("SELECT durata_minuti FROM servizi WHERE id = $servizioId AND salone_id = $sid LIMIT 1");
    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();
    return (int) ($row['durata_minuti'] ?? 0);
}

/**
 * Carica tutti gli appuntamenti "occupanti" in un giorno specifico.
 *
 * Considera solo stati che bloccano disponibilita:
 * - `attesa`
 * - `confermato`
 *
 * Inoltre pre-calcola timestamp inizio/fine per evitare conversioni ripetute
 * durante i controlli sugli slot.
 *
 * @param mysqli $conn Connessione database.
 * @param int|string $sid ID salone.
 * @param string $giornoSql Data YYYY-MM-DD.
 * @param int|string $escludiId ID appuntamento da ignorare (utile in modifica), default 0.
 * @return array<int, array<string,mixed>> Lista appuntamenti con campi tecnici `_inizio_ts` e `_fine_ts`.
 */
function appuntamenti_giorno_slot_comuni($conn, $sid, $giornoSql, $escludiId = 0)
{
    $sid = (int) $sid;
    $escludiId = (int) $escludiId;
    $giorno = $conn->real_escape_string($giornoSql);
    $whereEscludi = $escludiId > 0 ? " AND a.id <> $escludiId" : '';

    $result = $conn->query("
        SELECT a.id, a.data_ora, a.stato, s.durata_minuti
        FROM appuntamenti a
        JOIN servizi s ON s.id = a.servizio_id
        WHERE a.salone_id = $sid
          AND a.stato IN ('attesa', 'confermato')
          AND DATE(a.data_ora) = '$giorno'
          $whereEscludi
        ORDER BY a.data_ora ASC
    ");

    $appuntamenti = [];
    if (!$result) {
        return $appuntamenti;
    }

    while ($row = $result->fetch_assoc()) {
        // Precomputo temporale: ottimizza tutte le verifiche di overlap successive.
        $inizio = strtotime($row['data_ora']);
        $durata = (int) ($row['durata_minuti'] ?? 0);
        $fine = strtotime('+' . $durata . ' minutes', $inizio);

        $row['_inizio_ts'] = $inizio;
        $row['_fine_ts'] = $fine;
        $appuntamenti[] = $row;
    }

    return $appuntamenti;
}

/**
 * Arrotonda un timestamp al prossimo confine slot.
 *
 * Esempio:
 * - step 30 min
 * - timestamp 10:17 -> 10:30
 *
 * @param int|float $timestamp Timestamp Unix.
 * @param int $stepMinuti Dimensione slot in minuti.
 * @return int Timestamp allineato al prossimo step.
 */
function allinea_timestamp_slot_successivo($timestamp, $stepMinuti)
{
    $stepSecondi = max(1, (int) $stepMinuti) * 60;
    return (int) (ceil($timestamp / $stepSecondi) * $stepSecondi);
}

/**
 * Restituisce gli appuntamenti che si sovrappongono alla fascia richiesta.
 *
 * Condizione di overlap (intervalli semi-aperti):
 * [inizioRichiesto, fineRichiesta) interseca [inizioEsistente, fineEsistente)
 * se:
 *   inizioRichiesto < fineEsistente
 *   AND
 *   fineRichiesta > inizioEsistente
 *
 * @param int $inizioRichiesto Timestamp inizio fascia richiesta.
 * @param int $fineRichiesta Timestamp fine fascia richiesta.
 * @param array<int,array<string,mixed>> $appuntamenti Appuntamenti giorno gia pre-caricati.
 * @return array<int,array<string,mixed>> Solo appuntamenti sovrapposti.
 */
function appuntamenti_sovrapposti_slot_comuni($inizioRichiesto, $fineRichiesta, $appuntamenti)
{
    $sovrapposti = [];
    foreach ($appuntamenti as $app) {
        $inizioEsistente = (int) ($app['_inizio_ts'] ?? 0);
        $fineEsistente = (int) ($app['_fine_ts'] ?? 0);

        if ($inizioRichiesto < $fineEsistente && $fineRichiesta > $inizioEsistente) {
            $sovrapposti[] = $app;
        }
    }
    return $sovrapposti;
}

/**
 * Calcola conflitti per un singolo appuntamento richiesto.
 *
 * Importante:
 * - `sovrapposti` contiene TUTTE le sovrapposizioni.
 * - `conflitti` viene popolato solo se le sovrapposizioni saturano la capienza.
 *
 * Quindi puo esistere overlap senza blocco, se il salone ha ancora posti paralleli disponibili.
 *
 * @param mysqli $conn Connessione database.
 * @param int|string $sid ID salone.
 * @param int|string $servizioId ID servizio richiesto.
 * @param string $dataOraSql Datetime richiesto (YYYY-MM-DD HH:MM:SS).
 * @param int|string $escludiId Appuntamento da ignorare (update), default 0.
 * @return array{
 *   conflitti: array<int,array<string,mixed>>,
 *   sovrapposti: array<int,array<string,mixed>>,
 *   fine_richiesta: int|null,
 *   durata: int,
 *   capienza: int
 * }
 */
function trova_conflitti_slot_comuni($conn, $sid, $servizioId, $dataOraSql, $escludiId = 0)
{
    $sid = (int) $sid;
    $servizioId = (int) $servizioId;
    $escludiId = (int) $escludiId;
    $capienza = capienza_slot_comune($conn, $sid);

    $inizioRichiesto = strtotime((string) $dataOraSql);
    if ($inizioRichiesto === false) {
        return ['conflitti' => [], 'sovrapposti' => [], 'fine_richiesta' => null, 'durata' => 0, 'capienza' => $capienza];
    }

    $durata = durata_servizio_slot_comune($conn, $sid, $servizioId);
    if ($durata <= 0) {
        return ['conflitti' => [], 'sovrapposti' => [], 'fine_richiesta' => null, 'durata' => 0, 'capienza' => $capienza];
    }

    $fineRichiesta = strtotime('+' . $durata . ' minutes', $inizioRichiesto);
    $giorno = date('Y-m-d', $inizioRichiesto);
    $appuntamenti = appuntamenti_giorno_slot_comuni($conn, $sid, $giorno, $escludiId);
    $sovrapposti = appuntamenti_sovrapposti_slot_comuni($inizioRichiesto, $fineRichiesta, $appuntamenti);

    return [
        'conflitti' => count($sovrapposti) >= $capienza ? $sovrapposti : [],
        'sovrapposti' => $sovrapposti,
        'fine_richiesta' => $fineRichiesta,
        'durata' => $durata,
        'capienza' => $capienza,
    ];
}

/**
 * Calcola gli slot realmente prenotabili per data+servizio.
 *
 * Regole principali:
 * - valida input e data prenotabile;
 * - rispetta durata del servizio;
 * - rispetta finestre orarie (es. pausa pranzo esclusa);
 * - nel giorno corrente applica anticipo minimo;
 * - propone slot allineati al passo configurato (default 30 min);
 * - applica capienza parallela del salone.
 *
 * Ottimizzazione:
 * gli appuntamenti del giorno vengono caricati una sola volta e riusati per tutti gli slot.
 *
 * @param mysqli $conn Connessione database.
 * @param int|string $sid ID salone.
 * @param int|string $servizioId ID servizio.
 * @param string $dataSql Data richiesta YYYY-MM-DD.
 * @param int|string $limite Numero massimo di slot da restituire.
 * @param int|null $adessoTs Timestamp corrente (iniettabile per test), default `time()`.
 * @return array<int,string> Lista orari nel formato HH:MM.
 */
function slot_liberi_comuni($conn, $sid, $servizioId, $dataSql, $limite = 12, $adessoTs = null)
{
    $sid = (int) $sid;
    $servizioId = (int) $servizioId;
    $limite = (int) $limite;
    $dataSql = trim((string) $dataSql);

    if ($sid <= 0 || $servizioId <= 0 || $limite <= 0) {
        return [];
    }

    if ($adessoTs === null) {
        $adessoTs = time();
    }

    if (motivo_data_non_prenotabile($dataSql, $adessoTs) !== '') {
        return [];
    }

    $durata = durata_servizio_slot_comune($conn, $sid, $servizioId);
    if ($durata <= 0) {
        return [];
    }

    $capienza = capienza_slot_comune($conn, $sid);
    $appuntamenti = appuntamenti_giorno_slot_comuni($conn, $sid, $dataSql);
    $slotLiberi = [];
    $stepMinuti = intervallo_slot_minuti_comuni();

    $oggi = date('Y-m-d', $adessoTs);
    $cutoffGiornaliero = null;
    if ($dataSql === $oggi) {
        // Nel giorno corrente non proponiamo slot troppo vicini al "now".
        $cutoffGiornaliero = $adessoTs + (anticipo_minimo_slot_comuni() * 60);
    }

    foreach (finestre_slot_comuni($dataSql) as $finestra) {
        $inizio = strtotime($finestra[0]);
        $fine = strtotime($finestra[1]);
        if ($inizio === false || $fine === false || $inizio >= $fine) {
            continue;
        }

        $startLoop = $inizio;
        if ($cutoffGiornaliero !== null && $cutoffGiornaliero > $startLoop) {
            $startLoop = $cutoffGiornaliero;
        }
        // Allinea il primo candidato al prossimo confine utile (es. xx:00 / xx:30).
        $startLoop = allinea_timestamp_slot_successivo($startLoop, $stepMinuti);

        for ($ts = $startLoop; $ts < $fine; $ts += ($stepMinuti * 60)) {
            $fineRichiesta = strtotime('+' . $durata . ' minutes', $ts);
            // Lo slot e valido solo se la durata completa rientra nella finestra.
            if ($fineRichiesta > $fine) {
                continue;
            }

            $sovrapposti = appuntamenti_sovrapposti_slot_comuni($ts, $fineRichiesta, $appuntamenti);
            // Slot libero se la sovrapposizione non satura la capienza parallela.
            if (count($sovrapposti) < $capienza) {
                $slotLiberi[] = date('H:i', $ts);
            }

            if (count($slotLiberi) >= $limite) {
                break 2;
            }
        }
    }

    return $slotLiberi;
}
