<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function normalizza_ruolo(string $ruolo): string
{
    if ($ruolo === 'proprietario') {
        return 'proprietario';
    }

    return 'dipendente';
}

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

function trova_salone_corrente(array $saloni, int $saloneId): ?array
{
    foreach ($saloni as $salone) {
        if ((int) $salone['salone_id'] === $saloneId) {
            return $salone;
        }
    }

    return null;
}

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

function require_proprietario(): void
{
    $ruolo = $_SESSION['salone_ruolo'] ?? 'dipendente';
    if (normalizza_ruolo($ruolo) !== 'proprietario') {
        $_SESSION['flash_errore'] = 'non hai i permessi per accedere a questa sezione';
        header('Location: index.php');
        exit;
    }
}

function estrai_flash(string $chiave): string
{
    if (empty($_SESSION[$chiave])) {
        return '';
    }

    $valore = (string) $_SESSION[$chiave];
    unset($_SESSION[$chiave]);

    return $valore;
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

if (!utente_puo_vedere_pagina($pagina_corrente_auth, $salone_ruolo_corrente)) {
    $_SESSION['flash_errore'] = 'non hai i permessi per accedere a questa sezione';
    header('Location: index.php');
    exit;
}
