<?php
require_once __DIR__ . '/db.php';

/*
restituisce l'orario corrente nel formato usato dal database.
serve per mantenere uniforme la gestione delle date del reset.
*/
function pr_now(): string
{
    return date('Y-m-d H:i:s');
}

/*
cerca un utente partendo da username o email.
restituisce i dati minimi utili al recupero password.
*/
function pr_find_user(mysqli $conn, string $identificativo): ?array
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

/*
conta quanti recovery code attivi esistono per l'utente.
considera validi solo i codici non usati e non revocati.
*/
function pr_count_active_codes(mysqli $conn, int $userId): int
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

/*
controlla rapidamente se l'utente ha almeno un recovery code attivo.
usa il conteggio centrale per evitare logiche duplicate.
*/
function pr_user_has_active_codes(mysqli $conn, int $userId): bool
{
    return pr_count_active_codes($conn, $userId) > 0;
}

/*
genera un singolo recovery code leggibile.
esclude i caratteri ambigui per ridurre gli errori di trascrizione.
*/
function pr_generate_single_recovery_code(): string
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

/*
normalizza il recovery code inserito dall'utente.
rimuove spazi e simboli per confrontarlo sempre nello stesso formato.
*/
function pr_normalize_recovery_code(string $code): string
{
    $code = strtoupper(trim($code));
    $code = preg_replace('/[^A-Z0-9]/', '', $code) ?? '';
    return $code;
}

/*
riformatta un codice normalizzato in blocchi da quattro caratteri.
serve solo per presentazione visiva, non per la verifica.
*/
function pr_format_recovery_code(string $normalized): string
{
    $chunks = str_split($normalized, 4);
    return implode('-', $chunks);
}

/*
scrive nel log gli eventi sensibili del recupero password.
memorizza utente, stato, ip e user agent per tracciare i tentativi.
*/
function pr_log_event(mysqli $conn, ?int $userId, string $eventType, string $status, ?string $detail = null): void
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

/*
revoca in blocco tutti i recovery code ancora attivi dell'utente.
serve quando si genera un nuovo set o quando si forza una pulizia.
*/
function pr_revoke_active_codes(mysqli $conn, int $userId): void
{
    $stmt = $conn->prepare('UPDATE user_recovery_codes SET revoked_at = NOW() WHERE user_id = ? AND used_at IS NULL AND revoked_at IS NULL');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
}

/*
genera un set completo di recovery code, salva solo gli hash e revoca i vecchi.
restituisce i codici in chiaro una sola volta per mostrarli all'utente.
*/
function pr_generate_recovery_codes(mysqli $conn, int $userId, int $generatedByUserId, int $count = 10): array
{
    $codes = [];
    $conn->begin_transaction();

    try {
        pr_revoke_active_codes($conn, $userId);

        $stmt = $conn->prepare('INSERT INTO user_recovery_codes (user_id, code_hash, generated_by_user_id, created_at) VALUES (?, ?, ?, NOW())');
        if (!$stmt) {
            throw new RuntimeException('prepare failed');
        }

        for ($i = 0; $i < $count; $i++) {
            do {
                $plain = pr_generate_single_recovery_code();
                $normalized = pr_normalize_recovery_code($plain);
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
        pr_log_event($conn, $userId, 'recovery_codes_generated', 'success', 'nuovi codici generati');
        return $codes;
    } catch (Throwable $e) {
        $conn->rollback();
        pr_log_event($conn, $userId, 'recovery_codes_generated', 'failed', $e->getMessage());
        return [];
    }
}

/*
verifica un recovery code e lo marca subito come usato.
se il codice non corrisponde o non e piu valido, il reset viene negato.
*/
function pr_consume_recovery_code(mysqli $conn, int $userId, string $codeInput): bool
{
    $normalized = pr_normalize_recovery_code($codeInput);
    if ($normalized === '') {
        pr_log_event($conn, $userId, 'recovery_code_verify', 'failed', 'codice vuoto o non valido');
        return false;
    }

    if (strlen($normalized) !== 12) {
        pr_log_event($conn, $userId, 'recovery_code_verify', 'failed', 'lunghezza recovery code non valida');
        return false;
    }

    $stmt = $conn->prepare('SELECT id, code_hash FROM user_recovery_codes WHERE user_id = ? AND used_at IS NULL AND revoked_at IS NULL ORDER BY id ASC');
    if (!$stmt) {
        pr_log_event($conn, $userId, 'recovery_code_verify', 'failed', 'prepare select fallita');
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
        pr_log_event($conn, $userId, 'recovery_code_verify', 'failed', 'nessun recovery code corrispondente');
        return false;
    }

    $update = $conn->prepare('UPDATE user_recovery_codes SET used_at = NOW() WHERE id = ? AND used_at IS NULL AND revoked_at IS NULL');
    if (!$update) {
        pr_log_event($conn, $userId, 'recovery_code_verify', 'failed', 'prepare update fallita');
        return false;
    }

    $update->bind_param('i', $matchId);
    $update->execute();
    $affected = $update->affected_rows;
    $update->close();

    if ($affected > 0) {
        pr_log_event($conn, $userId, 'recovery_code_verify', 'success', 'recovery code consumato');
        return true;
    }

    pr_log_event($conn, $userId, 'recovery_code_verify', 'failed', 'codice già usato o revocato');
    return false;
}

/*
apre una sessione temporanea autorizzata al cambio password.
la sessione ha scadenza breve e contiene solo i dati strettamente necessari.
*/
function pr_start_reset_session(array $user): void
{
    $_SESSION['password_reset_user_id'] = (int) $user['id'];
    $_SESSION['password_reset_username'] = (string) ($user['username'] ?: ($user['email'] ?: 'utente'));
    $_SESSION['password_reset_expires_at'] = time() + 600;
}

/*
legge la sessione temporanea del reset e controlla se e ancora valida.
se e scaduta, la pulisce subito.
*/
function pr_get_reset_session(): ?array
{
    $userId = (int) ($_SESSION['password_reset_user_id'] ?? 0);
    $username = (string) ($_SESSION['password_reset_username'] ?? '');
    $expiresAt = (int) ($_SESSION['password_reset_expires_at'] ?? 0);

    if ($userId <= 0 || $username === '' || $expiresAt <= time()) {
        pr_clear_reset_session();
        return null;
    }

    return [
        'user_id' => $userId,
        'username' => $username,
        'expires_at' => $expiresAt,
    ];
}

/*
elimina tutti i dati temporanei del reset password dalla sessione.
serve sia a fine reset sia quando il flusso viene annullato o scade.
*/
function pr_clear_reset_session(): void
{
    unset(
        $_SESSION['password_reset_user_id'],
        $_SESSION['password_reset_username'],
        $_SESSION['password_reset_expires_at']
    );
}
