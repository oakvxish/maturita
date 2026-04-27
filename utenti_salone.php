<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

require_proprietario();

$messaggio = '';
$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'crea_dipendente') {
        $nome = trim($_POST['nome'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if ($nome === '' || $email === '' || $password === '') {
            $errore = 'compila tutti i campi obbligatori';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errore = 'inserisci un indirizzo email valido';
        } elseif (strlen($password) < 6) {
            $errore = 'la password deve avere almeno 6 caratteri';
        } else {
            $username = $email;

            $stmtEsistente = $conn->prepare(
                'SELECT id, username, email FROM userdata WHERE email = ? OR username = ? LIMIT 1'
            );
            $stmtEsistente->bind_param('ss', $email, $username);
            $stmtEsistente->execute();
            $resultEsistente = $stmtEsistente->get_result();
            $utenteEsistente = $resultEsistente ? $resultEsistente->fetch_assoc() : null;
            $stmtEsistente->close();

            $conn->begin_transaction();

            try {
                if ($utenteEsistente) {
                    $userId = (int) $utenteEsistente['id'];

                    $stmtMembership = $conn->prepare(
                        'SELECT ruolo, attivo FROM user_saloni WHERE user_id = ? AND salone_id = ? LIMIT 1'
                    );
                    $stmtMembership->bind_param('ii', $userId, $sid);
                    $stmtMembership->execute();
                    $resultMembership = $stmtMembership->get_result();
                    $membership = $resultMembership ? $resultMembership->fetch_assoc() : null;
                    $stmtMembership->close();

                    if ($membership) {
                        throw new RuntimeException('questo account Ã¨ giÃ  collegato a questo salone');
                    }

                    $stmtAggiorna = $conn->prepare(
                        'UPDATE userdata SET nome = ?, email = ? WHERE id = ?'
                    );
                    $stmtAggiorna->bind_param('ssi', $nome, $email, $userId);
                    $stmtAggiorna->execute();
                    $stmtAggiorna->close();
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $saloneLegacy = $sid;

                    $stmtNuovo = $conn->prepare(
                        'INSERT INTO userdata (username, password, salone_id, nome, email) VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmtNuovo->bind_param('ssiss', $username, $hash, $saloneLegacy, $nome, $email);
                    $stmtNuovo->execute();
                    $userId = (int) $conn->insert_id;
                    $stmtNuovo->close();
                }

                $ruolo = 'dipendente';
                $stmtPivot = $conn->prepare(
                    'INSERT INTO user_saloni (user_id, salone_id, ruolo, attivo) VALUES (?, ?, ?, 1)'
                );
                $stmtPivot->bind_param('iis', $userId, $sid, $ruolo);
                $stmtPivot->execute();
                $stmtPivot->close();

                $conn->commit();
                $messaggio = 'dipendente creato e collegato al salone';
            } catch (Throwable $e) {
                $conn->rollback();
                $errore = $e instanceof RuntimeException ? $e->getMessage() : 'errore durante la creazione del dipendente';
            }
        }
    }

    if ($azione === 'cambia_stato') {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $nuovoStato = (int) ($_POST['attivo'] ?? 0);

        $stmtTarget = $conn->prepare(
            'SELECT ruolo, attivo FROM user_saloni WHERE user_id = ? AND salone_id = ? LIMIT 1'
        );
        $stmtTarget->bind_param('ii', $targetUserId, $sid);
        $stmtTarget->execute();
        $resultTarget = $stmtTarget->get_result();
        $target = $resultTarget ? $resultTarget->fetch_assoc() : null;
        $stmtTarget->close();

        if (!$target) {
            $errore = 'utente non trovato nel salone corrente';
        } elseif (normalizza_ruolo($target['ruolo'] ?? 'dipendente') === 'proprietario') {
            $errore = 'i proprietari non si gestiscono da questa schermata';
        } else {
            $stmtUpdate = $conn->prepare(
                'UPDATE user_saloni SET attivo = ? WHERE user_id = ? AND salone_id = ?'
            );
            $stmtUpdate->bind_param('iii', $nuovoStato, $targetUserId, $sid);
            $stmtUpdate->execute();
            $stmtUpdate->close();
            $messaggio = $nuovoStato === 1 ? 'accesso dipendente riattivato' : 'accesso dipendente disattivato';
        }
    }
}

$utenti = [];
$sqlUtenti = "
    SELECT
        us.user_id,
        us.ruolo,
        us.attivo,
        us.created_at,
        u.nome,
        u.email,
        u.username
    FROM user_saloni us
    JOIN userdata u ON u.id = us.user_id
    WHERE us.salone_id = $sid
    ORDER BY (us.ruolo = 'proprietario') DESC, us.attivo DESC, COALESCE(u.nome, ''), u.username
";
$resultUtenti = $conn->query($sqlUtenti);
if ($resultUtenti) {
    while ($row = $resultUtenti->fetch_assoc()) {
        $row['ruolo'] = normalizza_ruolo($row['ruolo'] ?? 'dipendente');
        $utenti[] = $row;
    }
}

require __DIR__ . '/includes/layout_top.php';
?>

<div class="titolo-pagina">utenti del salone</div>

<?php if ($messaggio): ?>
    <div class="avviso avviso-ok"><?php echo htmlspecialchars($messaggio); ?></div>
<?php endif; ?>
<?php if ($errore): ?>
    <div class="avviso avviso-err"><?php echo htmlspecialchars($errore); ?></div>
<?php endif; ?>

<div class="griglia-2">
    <div class="card">
        <h3 style="font-size:1rem;color:var(--text);margin-bottom:16px">nuovo dipendente</h3>
        <form method="post">
            <input type="hidden" name="azione" value="crea_dipendente">
            <label>nome e cognome</label>
            <input type="text" name="nome" required>
            <label>email</label>
            <input type="email" name="email" required>
            <label>password iniziale</label>
            <input type="password" name="password" required>
            <button type="submit" class="btn">crea account dipendente</button>
        </form>
        <p style="font-size:.8rem;color:var(--text-muted);margin-top:10px">
            il dipendente accederÃ  con email e password impostate da te. Dopo il primo accesso potrÃ  generare i propri recovery code da impostazioni.
        </p>
    </div>

    <div class="card" style="overflow-x:auto">
        <h3 style="font-size:1rem;color:var(--text);margin-bottom:16px">account collegati</h3>
        <table>
            <thead>
                <tr>
                    <th>utente</th>
                    <th>ruolo</th>
                    <th>stato</th>
                    <th>dal</th>
                    <th>azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$utenti): ?>
                    <tr>
                        <td colspan="5" style="color:var(--text-muted);text-align:center;padding:24px">nessun utente collegato</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($utenti as $utente): ?>
                    <tr>
                        <td>
                            <div style="font-weight:600"><?php echo htmlspecialchars($utente['nome'] ?: $utente['username']); ?></div>
                            <div style="font-size:.8rem;color:var(--text-muted)"><?php echo htmlspecialchars($utente['email'] ?: $utente['username']); ?></div>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo htmlspecialchars($utente['ruolo']); ?>">
                                <?php echo htmlspecialchars($utente['ruolo']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ((int) $utente['attivo'] === 1): ?>
                                <span class="badge badge-confermato">attivo</span>
                            <?php else: ?>
                                <span class="badge badge-annullato">disattivato</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($utente['created_at'])); ?></td>
                        <td>
                            <?php if ($utente['ruolo'] === 'dipendente'): ?>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="azione" value="cambia_stato">
                                    <input type="hidden" name="user_id" value="<?php echo (int) $utente['user_id']; ?>">
                                    <input type="hidden" name="attivo" value="<?php echo (int) $utente['attivo'] === 1 ? 0 : 1; ?>">
                                    <button type="submit" class="btn btn-piccolo <?php echo (int) $utente['attivo'] === 1 ? 'btn-pericolo' : 'btn-grigio'; ?>">
                                        <?php echo (int) $utente['attivo'] === 1 ? 'disattiva' : 'riattiva'; ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span style="font-size:.8rem;color:var(--text-muted)">owner</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>


