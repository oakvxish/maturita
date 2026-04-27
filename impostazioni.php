<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/password_reset_lib.php';


$messaggio = (string) ($_SESSION['flash_impostazioni_ok'] ?? '');
$errore_sicurezza = (string) ($_SESSION['flash_impostazioni_err'] ?? '');
$recovery_codes_visibili = $_SESSION['flash_recovery_codes'] ?? [];
unset($_SESSION['flash_impostazioni_ok'], $_SESSION['flash_impostazioni_err'], $_SESSION['flash_recovery_codes']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? 'salva_impostazioni';

    if ($azione === 'crea_nuovo_salone') {
        $nomeNuovoSalone = trim($_POST['nuovo_nome_salone'] ?? '');

        if ($nomeNuovoSalone === '') {
            $errore_sicurezza = 'inserisci il nome del nuovo salone';
        } else {
            $conn->begin_transaction();

            try {
                $stmtSalone = $conn->prepare('INSERT INTO saloni (nome_salone) VALUES (?)');
                if (!$stmtSalone) {
                    throw new RuntimeException('prepare salone non riuscita');
                }

                $stmtSalone->bind_param('s', $nomeNuovoSalone);
                $stmtSalone->execute();
                $nuovoSaloneId = (int) $conn->insert_id;
                $stmtSalone->close();

                $ruoloNuovo = 'proprietario';
                $stmtPivot = $conn->prepare('INSERT INTO user_saloni (user_id, salone_id, ruolo, attivo) VALUES (?, ?, ?, 1)');
                if (!$stmtPivot) {
                    throw new RuntimeException('prepare collegamento non riuscita');
                }

                $userIdNuovo = (int) $_SESSION['user_id'];
                $stmtPivot->bind_param('iis', $userIdNuovo, $nuovoSaloneId, $ruoloNuovo);
                $stmtPivot->execute();
                $stmtPivot->close();

                $impostazioniNuove = [
                    ['nome_salone', $nomeNuovoSalone],
                    ['iva', '22'],
                    ['valuta', 'EUR'],
                    ['tema', 'scuro'],
                ];

                $stmtCfg = $conn->prepare('INSERT INTO impostazioni (salone_id, chiave, valore) VALUES (?, ?, ?)');
                if (!$stmtCfg) {
                    throw new RuntimeException('prepare impostazioni non riuscita');
                }

                foreach ($impostazioniNuove as $impostazione) {
                    $chiave = $impostazione[0];
                    $valore = $impostazione[1];
                    $stmtCfg->bind_param('iss', $nuovoSaloneId, $chiave, $valore);
                    $stmtCfg->execute();
                }

                $stmtCfg->close();
                $conn->commit();

                $_SESSION['flash_impostazioni_ok'] = 'nuovo salone creato e collegato al proprietario';
                header('Location: impostazioni.php');
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                $errore_sicurezza = 'errore durante la creazione del nuovo salone';
            }
        }
    } elseif ($azione === 'genera_recovery_codes') {
        $codes = genera_codici_recupero($conn, (int) $_SESSION['user_id'], (int) $_SESSION['user_id'], 10);
        if ($codes) {
            $_SESSION['flash_recovery_codes'] = $codes;
            $_SESSION['flash_impostazioni_ok'] = 'nuovi recovery code generati. copiali ora: non verranno mostrati di nuovo';
            header('Location: impostazioni.php');
            exit;
        } else {
            $errore_sicurezza = 'errore durante la generazione dei recovery code';
        }
    } else {
        $campi = ['nome_salone', 'iva', 'valuta'];
        foreach ($campi as $k) {
            $chiave = $conn->real_escape_string($k);
            $val = $conn->real_escape_string(trim($_POST[$k] ?? ''));
            $conn->query("INSERT INTO impostazioni (salone_id,chiave,valore) VALUES ($sid,'$chiave','$val') ON DUPLICATE KEY UPDATE valore='$val'");
        }

        $nome_salone = trim($_POST['nome_salone'] ?? '');
        $_SESSION['nome_salone'] = $nome_salone;
        $nome_salone_sql = $conn->real_escape_string($nome_salone);
        $conn->query("UPDATE saloni SET nome_salone='$nome_salone_sql' WHERE id=$sid");
        $messaggio = 'impostazioni salvate';
    }
}

$cfg = [];
$resultCfg = $conn->query("SELECT chiave,valore FROM impostazioni WHERE salone_id=$sid");
if ($resultCfg) {
    while ($r = $resultCfg->fetch_assoc()) {
        $cfg[$r['chiave']] = $r['valore'];
    }
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($basePath === '.' || $basePath === '/') {
    $basePath = '';
}
$link_pub = $scheme . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/home.php?id=' . (int) $sid;
$recovery_codes_attivi = conta_codici_recupero_attivi($conn, (int) $_SESSION['user_id']);
$cfg['tema'] = 'scuro';

require __DIR__ . '/includes/layout_top.php';
?>

<div class="page-head">
    <div class="titolo-pagina">impostazioni</div>
</div>

<?php if ($messaggio): ?><div class="avviso avviso-ok"><?php echo $messaggio ?></div><?php endif ?>
<?php if ($errore_sicurezza): ?><div class="avviso avviso-err"><?php echo htmlspecialchars($errore_sicurezza); ?></div><?php endif ?>

<div class="griglia-2">
    <div class="card">
        <div class="card-title-row">
            <div>
                <div class="eyebrow">generali</div>
                <div class="card-title-strong">profilo salone</div>
            </div>
        </div>
        <form method="post">
            <label>nome salone</label>
            <input type="text" name="nome_salone" value="<?php echo htmlspecialchars($cfg['nome_salone'] ?? ($_SESSION['nome_salone'] ?? '')) ?>">
            <div class="riga-form">
                <div>
                    <label>aliquota iva (%)</label>
                    <input type="number" name="iva" min="0" max="100" step="0.5" value="<?php echo htmlspecialchars($cfg['iva'] ?? '22') ?>">
                </div>
                <div>
                    <label>valuta</label>
                    <select name="valuta">
                        <?php foreach (['EUR','USD','GBP','CHF'] as $v): ?>
                            <option value="<?php echo $v ?>" <?php echo ($cfg['valuta'] ?? 'EUR') === $v ? 'selected' : '' ?>><?php echo $v ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn">salva impostazioni</button>
        </form>
    </div>

    <div class="card">
        <div class="card-title-row">
            <div>
                <div class="eyebrow">prenotazione online</div>
                <div class="card-title-strong">link pubblico del salone</div>
            </div>
        </div>
        <label>id salone</label>
        <input type="text" value="<?php echo (int) $sid ?>" readonly>
        <label>link diretto</label>
        <textarea rows="4" readonly><?php echo htmlspecialchars($link_pub) ?></textarea>
        <div class="surface-soft">
            <p>La pagina pubblica usa lâ€™id del salone. Il link Ã¨ pronto per essere copiato e condiviso senza altre configurazioni.</p>
        </div>
    </div>
</div>



<div class="griglia-2">
    <div class="card">
        <div class="card-title-row">
            <div>
                <div class="eyebrow">multi salone</div>
                <div class="card-title-strong">crea un altro salone</div>
            </div>
        </div>
        <form method="post" class="stack-md">
            <input type="hidden" name="azione" value="crea_nuovo_salone">
            <div class="field">
                <label>nome nuovo salone</label>
                <input type="text" name="nuovo_nome_salone" placeholder="es. beautifier studio centro" required>
            </div>
            <button type="submit" class="btn">crea e collega al proprietario</button>
        </form>
        <div class="surface-soft">
            <p>Il nuovo salone viene creato subito e questo account viene collegato come proprietario. Poi lo trovi nel selettore del salone attivo nella sidebar.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-title-row">
            <div>
                <div class="eyebrow">account corrente</div>
                <div class="card-title-strong">profilo proprietario</div>
            </div>
        </div>
        <div class="surface-soft stack-md">
            <div>
                <div class="metric-label">nome visualizzato</div>
                <div class="detail-value"><?php echo htmlspecialchars((string) ($_SESSION['user_label'] ?? ($_SESSION['username'] ?? 'account'))); ?></div>
            </div>
            <div>
                <div class="metric-label">salone attivo</div>
                <div class="detail-value"><?php echo htmlspecialchars((string) ($_SESSION['nome_salone'] ?? 'salone')); ?></div>
            </div>
            <div>
                <div class="metric-label">saloni collegati</div>
                <div class="detail-value"><?php echo count($_SESSION['saloni_abilitati'] ?? []); ?></div>
            </div>
        </div>
    </div>
</div>


<div class="card">
    <div class="card-title-row">
        <div>
            <div class="eyebrow">sicurezza account</div>
            <div class="card-title-strong">recovery code monouso</div>
        </div>
        <div class="helper-text"><?php echo (int) $recovery_codes_attivi; ?> attivi</div>
    </div>

    <div class="stack-lg">
        <div class="surface-soft stack-md">
            <div>
                <div class="metric-label">stato attuale</div>
                <div class="detail-value"><?php echo $recovery_codes_attivi > 0 ? 'set presente' : 'nessun codice attivo'; ?></div>
            </div>
        </div>

        <?php if ($recovery_codes_visibili): ?>
            <div class="summary reset-summary">
                <div>
                    <div class="metric-label">copiali adesso</div>
                </div>
                <div class="recovery-code-list">
                    <?php foreach ($recovery_codes_visibili as $code): ?>
                        <div class="recovery-code-item"><?php echo htmlspecialchars($code); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" class="stack-md">
            <input type="hidden" name="azione" value="genera_recovery_codes">
            <button type="submit" class="btn"><?php echo $recovery_codes_attivi > 0 ? 'rigenera recovery code' : 'genera recovery code'; ?></button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>



