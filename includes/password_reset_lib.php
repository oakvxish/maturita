<?php
require_once __DIR__ . '/db.php';

/**
 * Restituisce il timestamp corrente nel formato SQL standard del progetto.
 *
 * @return string Datetime `Y-m-d H:i:s`.
 */
function ora_corrente_db(): string
{
    return date('Y-m-d H:i:s');
}

/**
 * Cerca un utente tramite identificativo (username o email).
 *
 * @param mysqli $conn Connessione database.
 * @param string $identificativo Username o email inserita dall'utente.
 * @return array<string,mixed>|null Record utente minimo utile al reset.
 */
function trova_utente_per_accesso(mysqli $conn, string $identificativo): ?array
{
    $stmt = $conn->prepare('SELECT id, username, email, nome FROM userdata WHERE username = ? OR email = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ss', $identificativo, $identificativo);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $user ?: null;
}

/**
 * Conta i recovery code attivi di un utente.
 *
 * Sono considerati attivi solo i codici:
 * - non usati (`used_at IS NULL`)
 * - non revocati (`revoked_at IS NULL`)
 *
 * @param mysqli $conn Connessione database.
 * @param int $userId ID utente.
 * @return int Numero codici attivi.
 */
function conta_codici_recupero_attivi(mysqli $conn, int $userId): int
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM user_recovery_codes WHERE user_id = ? AND used_at IS NULL AND revoked_at IS NULL");
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : ['n' => 0];
    $stmt->close();

    return (int) ($row['n'] ?? 0);
}

/**
 * Shortcut booleano: verifica se esiste almeno un recovery code attivo.
 *
 * @param mysqli $conn Connessione database.
 * @param int $userId ID utente.
 * @return bool True se almeno un codice e disponibile.
 */
function utente_ha_codici_recupero_attivi(mysqli $conn, int $userId): bool
{
    return conta_codici_recupero_attivi($conn, $userId) > 0;
}

/**
 * Genera un recovery code leggibile in formato `XXXX-XXXX-XXXX`.
 *
 * L'alfabeto evita caratteri ambigui (es. O/0, I/1) per ridurre errori umani.
 *
 * @return string Codice in chiaro.
 */
function genera_singolo_codice_recupero(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $parts = [];

    for ($p = 0; $p < 3; $p++) {
        $segment = '';
        for ($i = 0; $i < 4; $i++) {
            $segment .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $parts[] = $segment;
    }

    return implode('-', $parts);
}

/**
 * Normalizza il codice inserito dall'utente prima della verifica.
 *
 * Operazioni:
 * - trim;
 * - uppercase;
 * - rimozione caratteri non alfanumerici.
 *
 * @param string $code Input utente.
 * @return string Codice normalizzato senza separatori.
 */
function normalizza_codice_recupero(string $code): string
{
    $code = strtoupper(trim($code));
    $code = preg_replace('/[^A-Z0-9]/', '', $code) ?? '';
    return $code;
}

/**
 * Formatta un codice normalizzato in blocchi da 4 caratteri.
 * Usato solo per visualizzazione.
 *
 * @param string $normalized Codice senza separatori.
 * @return string Codice formattato con trattini.
 */
function formatta_codice_recupero(string $normalized): string
{
    $chunks = str_split($normalized, 4);
    return implode('-', $chunks);
}

/**
 * Registra un evento nel log audit del reset password.
 *
 * Traccia dati utili a sicurezza e troubleshooting:
 * - utente coinvolto;
 * - tipo evento;
 * - esito;
 * - dettaglio contestuale;
 * - IP e user-agent.
 *
 * @param mysqli $conn Connessione database.
 * @param int|null $userId Utente coinvolto (puo essere null in alcuni scenari).
 * @param string $eventType Tipo evento.
 * @param string $status Esito evento (`success`/`failed`).
 * @param string|null $detail Dettaglio opzionale.
 * @return void
 */
function registra_evento_reset_password(mysqli $conn, ?int $userId, string $eventType, string $status, ?string $detail = null): void
{
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $stmt = $conn->prepare('INSERT INTO password_reset_audit (user_id, event_type, event_status, detail, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    if ($stmt) {
        $stmt->bind_param('isssss', $userId, $eventType, $status, $detail, $ip, $ua);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Revoca tutti i recovery code ancora attivi per l'utente.
 *
 * @param mysqli $conn Connessione database.
 * @param int $userId ID utente.
 * @return void
 */
function revoca_codici_recupero_attivi(mysqli $conn, int $userId): void
{
    $stmt = $conn->prepare('UPDATE user_recovery_codes SET revoked_at = NOW() WHERE user_id = ? AND used_at IS NULL AND revoked_at IS NULL');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Genera un nuovo set di recovery code per un utente.
 *
 * Flusso:
 * 1) revoca codici attivi esistenti;
 * 2) genera nuovi codici in chiaro;
 * 3) salva solo hash nel DB;
 * 4) restituisce i codici in chiaro una sola volta.
 *
 * Il tutto avviene in transazione per garantire coerenza.
 *
 * @param mysqli $conn Connessione database.
 * @param int $userId Utente destinatario dei codici.
 * @param int $generatedByUserId Utente che ha avviato la generazione.
 * @param int $count Numero codici da creare.
 * @return array<int,string> Lista codici in chiaro (vuota in caso di errore).
 */
function genera_codici_recupero(mysqli $conn, int $userId, int $generatedByUserId, int $count = 10): array
{
    $codes = [];
    $conn->begin_transaction();

    try {
        revoca_codici_recupero_attivi($conn, $userId);

        $stmt = $conn->prepare('INSERT INTO user_recovery_codes (user_id, code_hash, generated_by_user_id, created_at) VALUES (?, ?, ?, NOW())');
        if (!$stmt) {
            throw new RuntimeException('prepare failed');
        }

        for ($i = 0; $i < $count; $i++) {
            do {
                $plain = genera_singolo_codice_recupero();
                $normalized = normalizza_codice_recupero($plain);
            } while ($normalized === '');

            $hash = password_hash($normalized, PASSWORD_DEFAULT);
            $stmt->bind_param('isi', $userId, $hash, $generatedByUserId);
            if (!$stmt->execute()) {
                throw new RuntimeException('insert failed');
            }

            $codes[] = $plain;
        }

        $stmt->close();
        $conn->commit();
        registra_evento_reset_password($conn, $userId, 'recovery_codes_generated', 'success', 'nuovi codici generati');
        return $codes;
    } catch (Throwable $e) {
        $conn->rollback();
        registra_evento_reset_password($conn, $userId, 'recovery_codes_generated', 'failed', $e->getMessage());
        return [];
    }
}

/**
 * Verifica un recovery code e, se valido, lo consuma in modo atomico.
 *
 * Regole:
 * - accetta solo codice normalizzato di lunghezza attesa;
 * - cerca tra codici attivi;
 * - se trova match, marca immediatamente `used_at`.
 *
 * Ogni esito viene tracciato nell'audit log.
 *
 * @param mysqli $conn Connessione database.
 * @param int $userId ID utente.
 * @param string $codeInput Codice inserito.
 * @return bool True se verifica+consumo completati.
 */
function verifica_e_consuma_codice_recupero(mysqli $conn, int $userId, string $codeInput): bool
{
    $normalized = normalizza_codice_recupero($codeInput);
    if ($normalized === '') {
        registra_evento_reset_password($conn, $userId, 'recovery_code_verify', 'failed', 'codice vuoto o non valido');
        return false;
    }

    if (strlen($normalized) !== 12) {
        registra_evento_reset_password($conn, $userId, 'recovery_code_verify', 'failed', 'lunghezza recovery code non valida');
        return false;
    }

    $stmt = $conn->prepare('SELECT id, code_hash FROM user_recovery_codes WHERE user_id = ? AND used_at IS NULL AND revoked_at IS NULL ORDER BY id ASC');
    if (!$stmt) {
        registra_evento_reset_password($conn, $userId, 'recovery_code_verify', 'failed', 'prepare select fallita');
        return false;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $matchId = 0;

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (password_verify($normalized, (string) $row['code_hash'])) {
                $matchId = (int) $row['id'];
                break;
            }
        }
    }
    $stmt->close();

    if ($matchId <= 0) {
        registra_evento_reset_password($conn, $userId, 'recovery_code_verify', 'failed', 'nessun recovery code corrispondente');
        return false;
    }

    $update = $conn->prepare('UPDATE user_recovery_codes SET used_at = NOW() WHERE id = ? AND used_at IS NULL AND revoked_at IS NULL');
    if (!$update) {
        registra_evento_reset_password($conn, $userId, 'recovery_code_verify', 'failed', 'prepare update fallita');
        return false;
    }

    $update->bind_param('i', $matchId);
    $update->execute();
    $affected = $update->affected_rows;
    $update->close();

    if ($affected > 0) {
        registra_evento_reset_password($conn, $userId, 'recovery_code_verify', 'success', 'recovery code consumato');
        return true;
    }

    registra_evento_reset_password($conn, $userId, 'recovery_code_verify', 'failed', 'codice giÃ  usato o revocato');
    return false;
}

/**
 * Avvia una sessione temporanea autorizzata al cambio password.
 *
 * La sessione contiene solo i dati indispensabili e scade rapidamente.
 *
 * @param array<string,mixed> $user Record utente minimo.
 * @return void
 */
function avvia_sessione_reset_password(array $user): void
{
    $_SESSION['password_reset_user_id'] = (int) $user['id'];
    $_SESSION['password_reset_username'] = (string) ($user['username'] ?: ($user['email'] ?: 'utente'));
    $_SESSION['password_reset_expires_at'] = time() + 600;
}

/**
 * Legge e valida la sessione temporanea del reset password.
 *
 * Se la sessione e mancante/invalida/scaduta, viene pulita subito.
 *
 * @return array{user_id:int,username:string,expires_at:int}|null Dati sessione o null.
 */
function leggi_sessione_reset_password(): ?array
{
    $userId = (int) ($_SESSION['password_reset_user_id'] ?? 0);
    $username = (string) ($_SESSION['password_reset_username'] ?? '');
    $expiresAt = (int) ($_SESSION['password_reset_expires_at'] ?? 0);

    if ($userId <= 0 || $username === '' || $expiresAt <= time()) {
        pulisci_sessione_reset_password();
        return null;
    }

    return [
        'user_id' => $userId,
        'username' => $username,
        'expires_at' => $expiresAt,
    ];
}

/**
 * Pulisce completamente la sessione temporanea del reset password.
 *
 * @return void
 */
function pulisci_sessione_reset_password(): void
{
    unset(
        $_SESSION['password_reset_user_id'],
        $_SESSION['password_reset_username'],
        $_SESSION['password_reset_expires_at']
    );
}



