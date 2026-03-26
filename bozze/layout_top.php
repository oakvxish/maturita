<?php
$tema = 'chiaro';

if (isset($conn) && isset($sid)) {
    $temaResult = $conn->query("SELECT valore FROM impostazioni WHERE chiave='tema' AND salone_id=$sid");
    $temaRow = $temaResult ? $temaResult->fetch_assoc() : null;
    if ($temaRow && !empty($temaRow['valore'])) {
        $tema = $temaRow['valore'];
    }

    if (isset($_GET['toggle_tema'])) {
        $tema = $tema === 'scuro' ? 'chiaro' : 'scuro';
        $temaSql = $conn->real_escape_string($tema);
        $conn->query("INSERT INTO impostazioni (salone_id, chiave, valore) VALUES ($sid, 'tema', '$temaSql') ON DUPLICATE KEY UPDATE valore='$temaSql'");

        $params = $_GET;
        unset($params['toggle_tema']);
        $redirect = basename($_SERVER['PHP_SELF']);
        if ($params) {
            $redirect .= '?' . http_build_query($params);
        }
        header('Location: ' . $redirect);
        exit;
    }
}

$nome_salone_nav = $_SESSION['nome_salone'] ?? 'salone';
$pagina_corrente = basename($_SERVER['PHP_SELF'], '.php');
$salone_ruolo = normalizza_ruolo($_SESSION['salone_ruolo'] ?? 'dipendente');
$saloni_menu = $_SESSION['saloni_abilitati'] ?? [];
$flash_errore_layout = function_exists('estrai_flash') ? estrai_flash('flash_errore') : '';
$params_toggle_tema = $_GET ?? [];
$params_toggle_tema['toggle_tema'] = 1;
$toggle_tema_url = basename($_SERVER['PHP_SELF']) . '?' . http_build_query($params_toggle_tema);

$voci_menu = [
    ['file' => 'index.php', 'pagina' => 'index', 'label' => 'dashboard'],
    ['file' => 'appuntamenti.php', 'pagina' => 'appuntamenti', 'label' => 'appuntamenti'],
    ['file' => 'clienti.php', 'pagina' => 'clienti', 'label' => 'clienti'],
    ['file' => 'servizi.php', 'pagina' => 'servizi', 'label' => 'servizi'],
    ['file' => 'magazzino.php', 'pagina' => 'magazzino', 'label' => 'magazzino'],
    ['file' => 'analitiche.php', 'pagina' => 'analitiche', 'label' => 'analitiche'],
    ['file' => 'utenti_salone.php', 'pagina' => 'utenti_salone', 'label' => 'utenti'],
    ['file' => 'impostazioni.php', 'pagina' => 'impostazioni', 'label' => 'impostazioni'],
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($nome_salone_nav); ?> - beautifier</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Victor+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/salone.css">

</head>
<body data-theme="<?php echo htmlspecialchars($tema); ?>">
<video class="theme-video theme-video-light" autoplay muted loop playsinline preload="metadata">
    <source src="assets/beautifier-light-bg.mp4" type="video/mp4">
</video>
<video class="theme-video theme-video-dark" autoplay muted loop playsinline preload="metadata">
    <source src="assets/beautifier-dark-bg.mp4" type="video/mp4">
</video>
<div class="theme-video-overlay" aria-hidden="true"></div>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 3.5C7.85786 3.5 4.5 6.85786 4.5 11C4.5 15.1421 7.85786 18.5 12 18.5C16.1421 18.5 19.5 15.1421 19.5 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <path d="M12 6V11L15.5 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div>
                <div class="brand-name"><?php echo htmlspecialchars($nome_salone_nav); ?></div>
                <div class="brand-sub">gestionale salone</div>
            </div>
        </div>

        <?php if ($saloni_menu): ?>
            <div class="tenant-box">
                <form method="post">
                    <input type="hidden" name="azione_globale" value="cambia_salone">
                    <label for="salone_id">salone attivo</label>
                    <div class="tenant-actions">
                        <select name="salone_id" id="salone_id">
                            <?php foreach ($saloni_menu as $saloneItem): ?>
                                <option value="<?php echo (int) $saloneItem['salone_id']; ?>" <?php echo (int) $saloneItem['salone_id'] === (int) $sid ? 'selected' : ''; ?>><?php echo htmlspecialchars($saloneItem['nome_salone']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-grigio btn-piccolo">cambia</button>
                    </div>
                    <div class="tenant-role">ruolo: <?php echo htmlspecialchars($salone_ruolo); ?></div>
                </form>
            </div>
        <?php endif; ?>

        <nav>
            <?php foreach ($voci_menu as $voce): ?>
                <?php if (utente_puo_vedere_pagina($voce['pagina'], $salone_ruolo)): ?>
                    <a href="<?php echo $voce['file']; ?>" class="<?php echo $pagina_corrente === $voce['pagina'] ? 'attivo' : ''; ?>"><?php echo htmlspecialchars($voce['label']); ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-bottom">
            <a href="logout.php">esci</a>
        </div>
    </aside>

    <main class="main">
        <div class="topbar">
            <div>
                <div class="theme-label">area riservata</div>
                <div class="topbar-title">tema attivo: <?php echo htmlspecialchars($tema); ?></div>
            </div>
            <div class="topbar-actions">
                <a class="topbar-link" href="<?php echo htmlspecialchars($toggle_tema_url); ?>">passa a tema <?php echo $tema === 'scuro' ? 'chiaro' : 'scuro'; ?></a>
                <a class="topbar-link topbar-link-primary" href="prenota.php?id=<?php echo (int) $sid; ?>" target="_blank">apri pagina prenotazione</a>
            </div>
        </div>
        <?php if ($flash_errore_layout): ?>
            <div class="avviso avviso-err"><?php echo htmlspecialchars($flash_errore_layout); ?></div>
        <?php endif; ?>
