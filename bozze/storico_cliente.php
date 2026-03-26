<?php
require 'db.php';
require 'auth.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: clienti.php');
    exit;
}

$cliente = $conn->query("SELECT * FROM clienti WHERE id=$id AND salone_id=$sid")->fetch_assoc();
if (!$cliente) {
    header('Location: clienti.php');
    exit;
}

$storico = [];
$resultStorico = $conn->query("SELECT a.*, s.nome AS servizio, s.prezzo, s.durata_minuti FROM appuntamenti a JOIN servizi s ON a.servizio_id=s.id WHERE a.cliente_id=$id AND a.salone_id=$sid ORDER BY a.data_ora DESC");
if ($resultStorico) {
    while ($row = $resultStorico->fetch_assoc()) {
        $storico[] = $row;
    }
}

$totale_speso = 0;
$tot_visite = 0;
$ultimo_app = null;
foreach ($storico as $r) {
    if ($ultimo_app === null) {
        $ultimo_app = $r;
    }
    if ($r['stato'] === 'completato') {
        $totale_speso += (float) $r['prezzo'];
        $tot_visite++;
    }
}

require 'layout_top.php';
?>

<div style="margin-bottom:20px">
    <a href="clienti.php" class="btn btn-grigio btn-piccolo">torna ai clienti</a>
</div>

<div class="titolo-pagina">
    <?php echo htmlspecialchars($cliente['nome'] . ' ' . $cliente['cognome']) ?>
    <span class="badge badge-<?php echo $cliente['tag'] ?>" style="margin-left:10px;vertical-align:middle"><?php echo $cliente['tag'] ?></span>
</div>

<div class="griglia-4">
    <div class="card"><h3>telefono</h3><div style="font-size:1.1rem;font-weight:600"><?php echo htmlspecialchars($cliente['telefono'] ?: '-') ?></div></div>
    <div class="card"><h3>email</h3><div style="font-size:1rem;font-weight:600;word-break:break-all"><?php echo htmlspecialchars($cliente['email'] ?: '-') ?></div></div>
    <div class="card"><h3>visite completate</h3><div class="valore"><?php echo $tot_visite ?></div></div>
    <div class="card"><h3>totale speso</h3><div class="valore" style="color:var(--success)">€<?php echo number_format($totale_speso, 2, ',', '.') ?></div></div>
</div>

<div class="griglia-2">
    <div class="card">
        <h3>scheda cliente</h3>
        <div style="display:grid;gap:12px;font-size:.95rem">
            <div><strong>cliente dal:</strong> <?php echo !empty($cliente['created_at']) ? date('d/m/Y', strtotime($cliente['created_at'])) : '-' ?></div>
            <div><strong>ultimo appuntamento:</strong> <?php echo $ultimo_app ? date('d/m/Y H:i', strtotime($ultimo_app['data_ora'])) : '-' ?></div>
            <div><strong>ultimo servizio:</strong> <?php echo $ultimo_app ? htmlspecialchars($ultimo_app['servizio']) : '-' ?></div>
            <div><strong>note:</strong><br><?php echo $cliente['note'] ? nl2br(htmlspecialchars($cliente['note'])) : '-' ?></div>
        </div>
    </div>

    <div class="card">
        <h3>riepilogo</h3>
        <div style="display:grid;gap:12px;font-size:.95rem">
            <div><strong>tag:</strong> <?php echo htmlspecialchars($cliente['tag']) ?></div>
            <div><strong>spesa media:</strong> <?php echo $tot_visite > 0 ? '€' . number_format($totale_speso / $tot_visite, 2, ',', '.') : '-' ?></div>
            <div><strong>stato relazione:</strong> <?php echo $tot_visite > 0 ? 'cliente attivo' : 'nessuna visita completata' ?></div>
        </div>
    </div>
</div>

<div class="card">
    <h3>storico appuntamenti</h3>
    <?php if (!$storico): ?>
        <p style="color:var(--muted);font-size:.92rem">nessun appuntamento registrato</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>data</th>
                    <th>servizio</th>
                    <th>durata</th>
                    <th>prezzo</th>
                    <th>stato</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($storico as $a): ?>
                    <tr>
                        <td><?php echo date('d/m/Y H:i', strtotime($a['data_ora'])) ?></td>
                        <td><?php echo htmlspecialchars($a['servizio']) ?></td>
                        <td><?php echo (int) $a['durata_minuti'] ?> min</td>
                        <td>€<?php echo number_format((float) $a['prezzo'], 2, ',', '.') ?></td>
                        <td><span class="badge badge-<?php echo $a['stato'] ?>"><?php echo $a['stato'] ?></span></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>

<?php require 'layout_bottom.php'; ?>
