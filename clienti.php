<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

$messaggio = '';
$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'crea' || $azione === 'modifica') {
        $nome = $conn->real_escape_string(trim($_POST['nome'] ?? ''));
        $cognome = $conn->real_escape_string(trim($_POST['cognome'] ?? ''));
        $telefono = $conn->real_escape_string(trim($_POST['telefono'] ?? ''));
        $email = $conn->real_escape_string(trim($_POST['email'] ?? ''));
        $tag = $conn->real_escape_string($_POST['tag'] ?? 'normale');
        $note = $conn->real_escape_string($_POST['note'] ?? '');

        if (!$nome || !$cognome) {
            $errore = 'nome e cognome sono obbligatori';
        } elseif ($azione === 'crea') {
            $conn->query("INSERT INTO clienti (salone_id,nome,cognome,telefono,email,tag,note) VALUES ($sid,'$nome','$cognome','$telefono','$email','$tag','$note')");
            $messaggio = 'cliente aggiunto';
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $conn->query("UPDATE clienti SET nome='$nome',cognome='$cognome',telefono='$telefono',email='$email',tag='$tag',note='$note' WHERE id=$id AND salone_id=$sid");
            $messaggio = 'cliente aggiornato';
        }
    }

    if ($azione === 'elimina') {
        $id = (int) ($_POST['id'] ?? 0);
        $conn->query("DELETE FROM clienti WHERE id=$id AND salone_id=$sid");
        $messaggio = 'cliente eliminato';
    }
}

$cerca = $conn->real_escape_string(trim($_GET['cerca'] ?? ''));
$filtro_tag = $conn->real_escape_string($_GET['tag'] ?? '');
$where = "salone_id=$sid";

if ($cerca) {
    $where .= " AND (nome LIKE '%$cerca%' OR cognome LIKE '%$cerca%' OR telefono LIKE '%$cerca%' OR email LIKE '%$cerca%')";
}
if ($filtro_tag) {
    $where .= " AND tag='$filtro_tag'";
}

$clienti = [];
$resultClienti = $conn->query("SELECT * FROM clienti WHERE $where ORDER BY cognome,nome");
if ($resultClienti) {
    while ($row = $resultClienti->fetch_assoc()) {
        $clienti[] = $row;
    }
}

$statsTag = [];
$resStats = $conn->query("SELECT tag, COUNT(*) AS n FROM clienti WHERE salone_id=$sid GROUP BY tag");
if ($resStats) {
    while ($r = $resStats->fetch_assoc()) {
        $statsTag[$r['tag']] = (int)$r['n'];
    }
}

$modifica = null;
if (isset($_GET['modifica'])) {
    $mid = (int) $_GET['modifica'];
    $modifica = $conn->query("SELECT * FROM clienti WHERE id=$mid AND salone_id=$sid")->fetch_assoc();
}

require __DIR__ . '/includes/layout_top.php';
?>

<div class="page-head">
    <div class="titolo-pagina">clienti</div>
</div>

<?php if ($messaggio): ?><div class="avviso avviso-ok"><?php echo $messaggio ?></div><?php endif ?>
<?php if ($errore): ?><div class="avviso avviso-err"><?php echo $errore ?></div><?php endif ?>

<div class="metric-grid">
    <div class="metric"><div class="metric-label">totale clienti</div><div class="metric-value"><?php echo count($clienti) ?></div><div class="metric-note">risultati visibili con i filtri attuali</div></div>
    <div class="metric"><div class="metric-label">vip</div><div class="metric-value"><?php echo $statsTag['vip'] ?? 0 ?></div><div class="metric-note">clienti ad alta prioritÃ </div></div>
    <div class="metric"><div class="metric-label">potenziali</div><div class="metric-value"><?php echo $statsTag['potenziale'] ?? 0 ?></div><div class="metric-note">da trasformare in clienti attivi</div></div>
    <div class="metric"><div class="metric-label">inattivi</div><div class="metric-value"><?php echo $statsTag['inattivo'] ?? 0 ?></div><div class="metric-note">utili per recall e follow-up</div></div>
</div>

<div class="griglia-2">
    <div class="card">
        <div class="card-title-row">
            <div>
                <div class="eyebrow">anagrafica</div>
                <div class="card-title-strong"><?php echo $modifica ? 'modifica cliente' : 'nuovo cliente' ?></div>
            </div>
        </div>
        <form method="post">
            <input type="hidden" name="azione" value="<?php echo $modifica ? 'modifica' : 'crea' ?>">
            <?php if ($modifica): ?><input type="hidden" name="id" value="<?php echo (int) $modifica['id'] ?>"><?php endif ?>
            <div class="riga-form">
                <div>
                    <label>nome *</label>
                    <input type="text" name="nome" value="<?php echo htmlspecialchars($modifica['nome'] ?? '') ?>" required>
                </div>
                <div>
                    <label>cognome *</label>
                    <input type="text" name="cognome" value="<?php echo htmlspecialchars($modifica['cognome'] ?? '') ?>" required>
                </div>
            </div>
            <div class="riga-form">
                <div>
                    <label>telefono</label>
                    <input type="tel" name="telefono" value="<?php echo htmlspecialchars($modifica['telefono'] ?? '') ?>">
                </div>
                <div>
                    <label>email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($modifica['email'] ?? '') ?>">
                </div>
            </div>
            <div class="riga-form">
                <div>
                    <label>tag cliente</label>
                    <select name="tag">
                        <?php foreach (['normale','vip','potenziale','inattivo'] as $t): ?>
                            <option value="<?php echo $t ?>" <?php echo ($modifica['tag'] ?? 'normale') === $t ? 'selected' : '' ?>><?php echo $t ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div>
                    <label>data creazione</label>
                    <input type="text" value="<?php echo !empty($modifica['created_at']) ? date('d/m/Y', strtotime($modifica['created_at'])) : date('d/m/Y') ?>" readonly>
                </div>
            </div>
            <label>note</label>
            <textarea name="note" rows="4" placeholder="preferenze, allergie, osservazioni, storico utile"><?php echo htmlspecialchars($modifica['note'] ?? '') ?></textarea>
            <div class="toolbar-actions">
                <button type="submit" class="btn"><?php echo $modifica ? 'salva modifiche' : 'aggiungi cliente' ?></button>
                <?php if ($modifica): ?><a href="clienti.php" class="btn btn-grigio">annulla</a><?php endif ?>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-title-row">
            <div>
                <div class="eyebrow">ricerca</div>
                <div class="card-title-strong">trova il cliente giusto</div>
            </div>
        </div>
        <form method="get" class="stack-md">
            <div>
                <label>ricerca libera</label>
                <input type="text" name="cerca" placeholder="nome, cognome, telefono, email" value="<?php echo htmlspecialchars($_GET['cerca'] ?? '') ?>">
            </div>
            <div>
                <label>tag</label>
                <select name="tag">
                    <option value="">tutti</option>
                    <?php foreach (['normale','vip','potenziale','inattivo'] as $t): ?>
                        <option value="<?php echo $t ?>" <?php echo $filtro_tag === $t ? 'selected' : '' ?>><?php echo $t ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="toolbar-actions">
                <button type="submit" class="btn btn-grigio">cerca</button>
                <a href="clienti.php" class="btn btn-grigio">reset</a>
            </div>
        </form>
        <div class="surface-soft mt-16">
            <strong><?php echo count($clienti) ?> risultati</strong>
            <p class="mt-8">La tabella mostra sempre solo quello che corrisponde ai filtri attivi, cosÃ¬ eviti confusione quando il database cresce.</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-title-row">
        <div>
            <div class="eyebrow">elenco</div>
            <div class="card-title-strong">rubrica clienti</div>
        </div>
    </div>

    <?php if (!$clienti): ?>
        <div class="empty-state">
            <strong>Nessun cliente trovato</strong>
            <p>Prova a togliere un filtro oppure crea la prima anagrafica del salone.</p>
            <a href="clienti.php" class="btn btn-grigio">pulisci i filtri</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>cliente</th>
                        <th>contatti</th>
                        <th>tag</th>
                        <th>note</th>
                        <th>dal</th>
                        <th>azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clienti as $c): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($c['cognome'] . ' ' . $c['nome']) ?></strong>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($c['telefono']) ?: '-' ?></div>
                                <div class="table-secondary"><?php echo htmlspecialchars($c['email']) ?: 'nessuna email' ?></div>
                            </td>
                            <td><span class="badge badge-<?php echo $c['tag'] ?>"><?php echo $c['tag'] ?></span></td>
                            <td style="max-width:240px;color:var(--muted)"><?php echo htmlspecialchars($c['note']) ?: '-' ?></td>
                            <td><?php echo !empty($c['created_at']) ? date('d/m/Y', strtotime($c['created_at'])) : '-' ?></td>
                            <td>
                                <div class="toolbar-actions">
                                    <a href="clienti.php?modifica=<?php echo (int) $c['id'] ?>" class="btn btn-piccolo btn-grigio">modifica</a>
                                    <a href="storico_cliente.php?id=<?php echo (int) $c['id'] ?>" class="btn btn-piccolo btn-grigio">scheda</a>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="azione" value="elimina">
                                        <input type="hidden" name="id" value="<?php echo (int) $c['id'] ?>">
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

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>


