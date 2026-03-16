<?php
// Cron ogni 30 minuti: */30 * * * * php /percorso/reminder_cron.php
// Processa i reminder per TUTTI i saloni attivi

require 'db.php';

function invia_wa(string $chat_id, string $testo, string $url, string $token): bool
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

function gia_inviato(mysqli $conn, int $app_id, int $salone_id): bool
{
    $r = $conn->query("
        SELECT COUNT(*) AS n FROM whatsapp_log
        WHERE tipo='reminder' AND salone_id=$salone_id
          AND messaggio LIKE '%app#$app_id%'
          AND inviato_il >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");
    return (int)$r->fetch_assoc()['n'] > 0;
}

// Recupera tutti i saloni con api configurata
$saloni = $conn->query("
    SELECT s.id AS salone_id,
           MAX(CASE WHEN i.chiave='whatsapp_api_url'   THEN i.valore END) AS api_url,
           MAX(CASE WHEN i.chiave='whatsapp_api_token' THEN i.valore END) AS api_token,
           MAX(CASE WHEN i.chiave='reminder_24h'       THEN i.valore END) AS r24,
           MAX(CASE WHEN i.chiave='reminder_2h'        THEN i.valore END) AS r2
    FROM saloni s
    JOIN impostazioni i ON i.salone_id=s.id
    WHERE i.chiave IN ('whatsapp_api_url','whatsapp_api_token','reminder_24h','reminder_2h')
    GROUP BY s.id
")->fetch_all(MYSQLI_ASSOC);

$totale_inviati = 0;

foreach ($saloni as $salone) {
    $sid       = (int)$salone['salone_id'];
    $api_url   = $salone['api_url']   ?? '';
    $api_token = $salone['api_token'] ?? '';
    $r24       = $salone['r24']       ?? '0';
    $r2        = $salone['r2']        ?? '0';

    if (!$api_url) continue;

    if ($r24 === '1') {
        $res = $conn->query("
            SELECT a.id, a.data_ora, c.nome, c.cognome, c.whatsapp_chat_id, c.id AS cid, s.nome AS servizio
            FROM appuntamenti a
            JOIN clienti c ON a.cliente_id=c.id
            JOIN servizi s ON a.servizio_id=s.id
            WHERE a.salone_id=$sid AND a.stato IN ('attesa','confermato')
              AND a.data_ora BETWEEN DATE_ADD(NOW(), INTERVAL 23 HOUR) AND DATE_ADD(NOW(), INTERVAL 25 HOUR)
              AND c.whatsapp_chat_id != ''
        ");
        foreach ($res->fetch_all(MYSQLI_ASSOC) as $a) {
            if (gia_inviato($conn, $a['id'], $sid)) continue;
            $testo = "ciao {$a['nome']} ti ricordiamo l'appuntamento per {$a['servizio']} domani alle " . date('H:i', strtotime($a['data_ora'])) . " — app#{$a['id']}";
            $stato = invia_wa($a['whatsapp_chat_id'], $testo, $api_url, $api_token) ? 'inviato' : 'errore';
            $t  = $conn->real_escape_string($testo);
            $ci = $conn->real_escape_string($a['whatsapp_chat_id']);
            $conn->query("INSERT INTO whatsapp_log (salone_id,cliente_id,chat_id,messaggio,tipo,stato) VALUES ($sid,{$a['cid']},'$ci','$t','reminder','$stato')");
            if ($stato === 'inviato') $totale_inviati++;
        }
    }

    if ($r2 === '1') {
        $res = $conn->query("
            SELECT a.id, a.data_ora, c.nome, c.cognome, c.whatsapp_chat_id, c.id AS cid, s.nome AS servizio
            FROM appuntamenti a
            JOIN clienti c ON a.cliente_id=c.id
            JOIN servizi s ON a.servizio_id=s.id
            WHERE a.salone_id=$sid AND a.stato IN ('attesa','confermato')
              AND a.data_ora BETWEEN DATE_ADD(NOW(), INTERVAL 90 MINUTE) AND DATE_ADD(NOW(), INTERVAL 150 MINUTE)
              AND c.whatsapp_chat_id != ''
        ");
        foreach ($res->fetch_all(MYSQLI_ASSOC) as $a) {
            if (gia_inviato($conn, $a['id'], $sid)) continue;
            $testo = "ciao {$a['nome']} tra 2 ore hai appuntamento per {$a['servizio']} alle " . date('H:i', strtotime($a['data_ora'])) . " — app#{$a['id']}";
            $stato = invia_wa($a['whatsapp_chat_id'], $testo, $api_url, $api_token) ? 'inviato' : 'errore';
            $t  = $conn->real_escape_string($testo);
            $ci = $conn->real_escape_string($a['whatsapp_chat_id']);
            $conn->query("INSERT INTO whatsapp_log (salone_id,cliente_id,chat_id,messaggio,tipo,stato) VALUES ($sid,{$a['cid']},'$ci','$t','reminder','$stato')");
            if ($stato === 'inviato') $totale_inviati++;
        }
    }
}

echo date('Y-m-d H:i:s') . " — saloni processati: " . count($saloni) . " — reminder inviati: $totale_inviati\n";
