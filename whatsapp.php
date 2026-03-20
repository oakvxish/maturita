<?php
require 'db.php';
require 'auth.php';

$messaggio = '';
$errore    = '';

$api_url   = $conn->query("SELECT valore FROM impostazioni WHERE chiave='whatsapp_api_url'   AND salone_id=$sid")->fetch_assoc()['valore'] ?? '';
$api_token = $conn->query("SELECT valore FROM impostazioni WHERE chiave='whatsapp_api_token' AND salone_id=$sid")->fetch_assoc()['valore'] ?? '';

function invia_whatsapp(string $chat_id, string $testo, string $url, string $token): bool
{
    if (!$url || !$chat_id) return false;
    $ch = curl_init($url . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token","Content-Type: application/json"],
        CURLOPT_POSTFIELDS     => json_encode(['chatId'=>$chat_id,'message'=>$testo]),
        CURLOPT_TIMEOUT        => 8,
    ]);
    curl_exec($ch);
    $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
    curl_close($ch);
    return $ok;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'singolo') {
        $cliente_id = (int)$_POST['cliente_id'];
        $testo      = trim($_POST['testo']);
        $tipo       = $conn->real_escape_string($_POST['tipo'] ?? 'manuale');

        if (!$cliente_id || !$testo) {
            $errore = 'scegli un cliente e scrivi il messaggio';
        } else {
            // Verifica appartenenza al salone
            $cli = $conn->query("SELECT * FROM clienti WHERE id=$cliente_id AND salone_id=$sid")->fetch_assoc();
            if (!$cli) { $errore = 'cliente non trovato'; }
            else {
                $testo = str_replace(['{{nome}}','{{cognome}}'], [$cli['nome'],$cli['cognome']], $testo);
                $stato_msg = 'in_coda';
                if ($cli['whatsapp_chat_id']) {
                    $ok = invia_whatsapp($cli['whatsapp_chat_id'], $testo, $api_url, $api_token);
                    $stato_msg = $ok ? 'inviato' : 'errore';
                }
                $t  = $conn->real_escape_string($testo);
                $ci = $conn->real_escape_string($cli['whatsapp_chat_id'] ?? '');
                $conn->query("INSERT INTO whatsapp_log (salone_id,cliente_id,chat_id,messaggio,tipo,stato)
                              VALUES ($sid,$cliente_id,'$ci','$t','$tipo','$stato_msg')");
                $messaggio = match($stato_msg) {
                    'inviato' => 'messaggio inviato',
                    'errore'  => 'errore invio — salvato nel log',
                    default   => 'nessun chat_id configurato — salvato in coda',
                };
            }
        }
    }

    if ($azione === 'broadcast') {
        $tag       = $conn->real_escape_string($_POST['tag_broadcast'] ?? '');
        $testo_tpl = trim($_POST['testo_broadcast'] ?? '');

        if (!$testo_tpl) {
            $errore = 'scrivi il testo del messaggio';
        } else {
            $where = $tag ? "salone_id=$sid AND tag='$tag'" : "salone_id=$sid";
            $dest  = $conn->query("SELECT * FROM clienti WHERE $where")->fetch_all(MYSQLI_ASSOC);
            $inviati = 0;
            foreach ($dest as $c) {
                $tf = str_replace(['{{nome}}','{{cognome}}'], [$c['nome'],$c['cognome']], $testo_tpl);
                $stato_msg = 'in_coda';
                if ($c['whatsapp_chat_id']) {
                    $ok = invia_whatsapp($c['whatsapp_chat_id'], $tf, $api_url, $api_token);
                    $stato_msg = $ok ? 'inviato' : 'errore';
                    if ($ok) $inviati++;
                }
                $tfe = $conn->real_escape_string($tf);
                $cie = $conn->real_escape_string($c['whatsapp_chat_id'] ?? '');
                $conn->query("INSERT INTO whatsapp_log (salone_id,cliente_id,chat_id,messaggio,tipo,stato)
                              VALUES ($sid,{$c['id']},'$cie','$tfe','broadcast','$stato_msg')");
            }
            $messaggio = "broadcast inviato a $inviati su " . count($dest) . " clienti";
        }
    }
}

$clienti     = $conn->query("SELECT id,nome,cognome,tag,whatsapp_chat_id FROM clienti WHERE salone_id=$sid ORDER BY cognome,nome")->fetch_all(MYSQLI_ASSOC);
$log         = $conn->query("
    SELECT w.*, c.nome, c.cognome
    FROM whatsapp_log w
    LEFT JOIN clienti c ON w.cliente_id=c.id
    WHERE w.salone_id=$sid
    ORDER BY w.inviato_il DESC LIMIT 40
")->fetch_all(MYSQLI_ASSOC);

$pre_cliente = (int)($_GET['cliente_id'] ?? 0);

require 'layout_top.php';
?>

<div class="titolo-pagina">whatsapp</div>

<?php if ($messaggio): ?><div class="avviso avviso-ok"><?php echo $messaggio ?></div><?php endif ?>
<?php if ($errore): ?><div class="avviso avviso-err"><?php echo $errore ?></div><?php endif ?>

<?php if (!$api_url): ?>
<div class="avviso" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a">
  ⚠️ nessun endpoint configurato — vai in <a href="impostazioni.php" style="color:inherit;font-weight:600">impostazioni</a> per aggiungere l'url api
</div>
<?php endif ?>

<div class="griglia-2">
  <div class="card">
    <h3 style="font-size:1rem;color:var(--text);margin-bottom:16px">messaggio singolo</h3>
    <form method="post">
      <input type="hidden" name="azione" value="singolo">
      <label>cliente</label>
      <select name="cliente_id" required>
        <option value="">scegli cliente</option>
        <?php foreach ($clienti as $c): ?>
        <option value="<?php echo $c['id'] ?>" <?php echo $c['id']===$pre_cliente?'selected':'' ?>>
          <?php echo htmlspecialchars($c['cognome'].' '.$c['nome']) ?>
          <?php echo $c['whatsapp_chat_id']?'':'⚠️' ?>
        </option>
        <?php endforeach ?>
      </select>
      <label>tipo</label>
      <select name="tipo">
        <option value="manuale">manuale</option>
        <option value="reminder">reminder</option>
        <option value="coupon">coupon</option>
      </select>
      <label>testo — usa {{nome}} e {{cognome}}</label>
      <textarea name="testo" rows="5" placeholder="ciao {{nome}} ricorda il tuo appuntamento…" required></textarea>
      <button type="submit" class="btn">📤 invia</button>
    </form>
  </div>

  <div class="card">
    <h3 style="font-size:1rem;color:var(--text);margin-bottom:16px">broadcast a gruppo</h3>
    <form method="post">
      <input type="hidden" name="azione" value="broadcast">
      <label>invia a clienti con tag</label>
      <select name="tag_broadcast">
        <option value="">tutti i clienti</option>
        <?php foreach (['normale','vip','potenziale','inattivo'] as $t): ?>
        <option value="<?php echo $t ?>"><?php echo $t ?></option>
        <?php endforeach ?>
      </select>
      <label>testo — usa {{nome}} e {{cognome}}</label>
      <textarea name="testo_broadcast" rows="5" placeholder="ciao {{nome}} abbiamo una promozione per te…" required></textarea>
      <button type="submit" class="btn" onclick="return confirm('inviare a tutti i clienti selezionati?')">📢 invia broadcast</button>
    </form>
  </div>
</div>

<div class="card" style="overflow-x:auto">
  <h3 style="font-size:1rem;color:var(--text);margin-bottom:14px">log messaggi</h3>
  <table>
    <thead><tr><th>data</th><th>cliente</th><th>tipo</th><th>stato</th><th>messaggio</th></tr></thead>
    <tbody>
      <?php if (!$log): ?>
        <tr><td colspan="5" style="color:var(--text-muted);text-align:center;padding:16px">nessun messaggio ancora</td></tr>
      <?php endif ?>
      <?php foreach ($log as $l): ?>
      <tr>
        <td style="white-space:nowrap"><?php echo date('d/m H:i', strtotime($l['inviato_il'])) ?></td>
        <td><?php echo htmlspecialchars($l['nome'].' '.$l['cognome']) ?></td>
        <td><span class="badge badge-normale"><?php echo $l['tipo'] ?></span></td>
        <td style="color:<?php echo $l['stato']==='inviato'?'var(--success)':($l['stato']==='errore'?'var(--danger)':'var(--warning)') ?>"><?php echo $l['stato'] ?></td>
        <td style="color:var(--text-muted);max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
          <?php echo htmlspecialchars(mb_substr($l['messaggio'],0,100)) ?>
        </td>
      </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>

<?php require 'layout_bottom.php'; ?>
