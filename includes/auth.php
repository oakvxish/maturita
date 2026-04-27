<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

/**
 * Normalizza il ruolo letto dal database in un set controllato.
 *
 * Regola applicata:
 * - solo `proprietario` mantiene privilegi elevati;
 * - qualsiasi altro valore viene forzato a `dipendente`.
 *
 * Questo evita comportamenti imprevisti quando il dato sorgente e incompleto
 * o contiene valori legacy/non validi.
 *
 * @param string $ruolo Ruolo grezzo.
 * @return string `proprietario` oppure `dipendente`.
 */
function normalizza_ruolo(string $ruolo): string
{
    if ($ruolo === 'proprietario') {
        return 'proprietario';
    }

    return 'dipendente';
}

/**
 * Carica tutti i saloni attivi associati all'utente.
 *
 * Ordinamento:
 * - prima eventuali saloni con ruolo `proprietario`;
 * - poi nome salone in ordine alfabetico.
 *
 * @param mysqli $conn Connessione database.
 * @param int $userId ID utente autenticato.
 * @return array<int,array<string,mixed>> Elenco saloni abilitati per la sessione.
 */
function carica_saloni_utente(mysqli $conn, int $userId): array
{
    $saloni = [];
    $sql = "
        SELECT
            us.salone_id,
            us.ruolo,
            us.attivo,
            s.nome_salone
        FROM user_saloni us
        JOIN saloni s ON s.id = us.salone_id
        WHERE us.user_id = $userId
          AND us.attivo = 1
        ORDER BY (us.ruolo = 'proprietario') DESC, s.nome_salone ASC
    ";

    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['ruolo'] = normalizza_ruolo($row['ruolo'] ?? 'dipendente');
            $saloni[] = $row;
        }
    }

    return $saloni;
}

/**
 * Trova il salone corrente in base all'ID salvato in sessione.
 *
 * @param array<int,array<string,mixed>> $saloni Saloni abilitati.
 * @param int $saloneId ID salone desiderato.
 * @return array<string,mixed>|null Salone trovato o null.
 */
function trova_salone_corrente(array $saloni, int $saloneId): ?array
{
    foreach ($saloni as $salone) {
        if ((int) $salone['salone_id'] === $saloneId) {
            return $salone;
        }
    }

    return null;
}

/**
 * Verifica autorizzazione pagina in base al ruolo corrente.
 *
 * @param string $pagina Nome pagina senza estensione.
 * @param string $ruolo Ruolo utente nel salone corrente.
 * @return bool True se l'accesso e consentito.
 */
function utente_puo_vedere_pagina(string $pagina, string $ruolo): bool
{
    $ruolo = normalizza_ruolo($ruolo);

    $pagine_tutti = [
        'index',
        'appuntamenti',
        'clienti',
        'storico_cliente',
        'magazzino',
    ];

    $pagine_proprietario = [
        'analitiche',
        'impostazioni',
        'servizi',
        'utenti_salone',
    ];

    if (in_array($pagina, $pagine_tutti, true)) {
        return true;
    }

    if ($ruolo === 'proprietario' && in_array($pagina, $pagine_proprietario, true)) {
        return true;
    }

    return false;
}

/**
 * Guardia di sicurezza per sezioni riservate al proprietario.
 *
 * Se il ruolo corrente non e sufficiente:
 * - salva un messaggio flash;
 * - reindirizza in dashboard;
 * - interrompe l'esecuzione.
 *
 * @return void
 */
function require_proprietario(): void
{
    $ruolo = $_SESSION['salone_ruolo'] ?? 'dipendente';
    if (normalizza_ruolo($ruolo) !== 'proprietario') {
        $_SESSION['flash_errore'] = 'non hai i permessi per accedere a questa sezione';
        header('Location: index.php');
        exit;
    }
}

/**
 * Legge e consuma un messaggio flash dalla sessione.
 *
 * Pattern "read-once": il valore viene eliminato subito dopo la lettura.
 *
 * @param string $chiave Chiave sessione.
 * @return string Messaggio flash o stringa vuota.
 */
function estrai_flash(string $chiave): string
{
    if (empty($_SESSION[$chiave])) {
        return '';
    }

    $valore = (string) $_SESSION[$chiave];
    unset($_SESSION[$chiave]);

    return $valore;
}


/**
 * Restituisce l'etichetta utente piu leggibile per UI e navbar.
 *
 * Priorita:
 * 1) nome anagrafico
 * 2) email
 * 3) username
 * 4) fallback statico `account`
 *
 * @param mysqli $conn Connessione database.
 * @param int $userId ID utente corrente.
 * @return string Label pronta per output.
 */
function utente_label_corrente(mysqli $conn, int $userId): string
{
    $stmt = $conn->prepare('SELECT nome, email, username FROM userdata WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return (string) ($_SESSION['username'] ?? 'account');
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return (string) ($_SESSION['username'] ?? 'account');
    }

    $nome = trim((string) ($row['nome'] ?? ''));
    $email = trim((string) ($row['email'] ?? ''));
    $username = trim((string) ($row['username'] ?? ''));

    if ($nome !== '') {
        return $nome;
    }

    if ($email !== '') {
        return $email;
    }

    if ($username !== '') {
        return $username;
    }

    return 'account';
}

$userId = (int) $_SESSION['user_id'];

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['azione_globale'] ?? '') === 'cambia_salone'
) {
    $saloniSwitch = carica_saloni_utente($conn, $userId);
    $nuovoSaloneId = (int) ($_POST['salone_id'] ?? 0);
    $nuovoSalone = trova_salone_corrente($saloniSwitch, $nuovoSaloneId);

    if ($nuovoSalone) {
        $_SESSION['salone_id'] = (int) $nuovoSalone['salone_id'];
        $_SESSION['nome_salone'] = $nuovoSalone['nome_salone'];
        $_SESSION['salone_ruolo'] = $nuovoSalone['ruolo'];
        $_SESSION['saloni_abilitati'] = $saloniSwitch;
    }

    header('Location: ' . basename($_SERVER['PHP_SELF']));
    exit;
}

$saloni_abilitati = carica_saloni_utente($conn, $userId);

if (!$saloni_abilitati) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

$saloneIdSessione = (int) ($_SESSION['salone_id'] ?? 0);
$salone_corrente = trova_salone_corrente($saloni_abilitati, $saloneIdSessione);

if (!$salone_corrente) {
    $salone_corrente = $saloni_abilitati[0];
}

$sid = (int) $salone_corrente['salone_id'];
$salone_ruolo_corrente = normalizza_ruolo($salone_corrente['ruolo'] ?? 'dipendente');
$pagina_corrente_auth = basename($_SERVER['PHP_SELF'], '.php');

$_SESSION['salone_id'] = $sid;
$_SESSION['nome_salone'] = $salone_corrente['nome_salone'];
$_SESSION['salone_ruolo'] = $salone_ruolo_corrente;
$_SESSION['saloni_abilitati'] = $saloni_abilitati;
$_SESSION['user_label'] = utente_label_corrente($conn, $userId);

if (!utente_puo_vedere_pagina($pagina_corrente_auth, $salone_ruolo_corrente)) {
    $_SESSION['flash_errore'] = 'non hai i permessi per accedere a questa sezione';
    header('Location: index.php');
    exit;
}


