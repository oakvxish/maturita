<?php
/* variabili di layout usate in tutta la navbar */
$tema              = 'scuro';
$nome_salone_nav   = $_SESSION['nome_salone'] ?? 'salone';
$pagina_corrente   = basename($_SERVER['PHP_SELF'], '.php');
$salone_ruolo      = normalizza_ruolo($_SESSION['salone_ruolo'] ?? 'dipendente');
$saloni_menu       = $_SESSION['saloni_abilitati'] ?? [];
$flash_errore_layout = function_exists('estrai_flash') ? estrai_flash('flash_errore') : '';

/* versione css â€” cambia questo numero per forzare il refresh nel browser */
$css_v = '5';

/* voci di navigazione â€” visibilitÃ  filtrata per ruolo */
$voci_menu = [
    ['file' => 'index.php',         'pagina' => 'index',         'label' => 'dashboard'],
    ['file' => 'appuntamenti.php',  'pagina' => 'appuntamenti',  'label' => 'appuntamenti'],
    ['file' => 'clienti.php',       'pagina' => 'clienti',       'label' => 'clienti'],
    ['file' => 'servizi.php',       'pagina' => 'servizi',       'label' => 'servizi'],
    ['file' => 'magazzino.php',     'pagina' => 'magazzino',     'label' => 'magazzino'],
    ['file' => 'analitiche.php',    'pagina' => 'analitiche',    'label' => 'analitiche'],
    ['file' => 'utenti_salone.php', 'pagina' => 'utenti_salone', 'label' => 'utenti'],
    ['file' => 'impostazioni.php',  'pagina' => 'impostazioni',  'label' => 'impostazioni'],
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($nome_salone_nav); ?> â€” beautify</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Victor+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- ?v= forza il browser a scaricare il css aggiornato invece di usare la cache -->
    <link rel="stylesheet" href="assets/salone.css?v=<?php echo $css_v; ?>">
</head>
<body data-theme="<?php echo htmlspecialchars($tema); ?>">

<!-- video di sfondo -->
<video class="theme-video theme-video-dark" autoplay muted loop playsinline preload="metadata">
    <source src="assets/beautifier-light-bg.mp4" type="video/mp4">
</video>
<div class="theme-video-overlay" aria-hidden="true"></div>

<!-- navbar dynamic island -->
<header class="di-nav" id="di-nav">

    <!-- riga principale: logo | link | azioni -->
    <div class="di-inner">

        <!-- logo -->
        <a href="index.php" class="di-brand" aria-label="<?php echo htmlspecialchars($nome_salone_nav); ?>">
            <img src="assets/logo.svg" class="di-brand-logo" alt="beautify" aria-hidden="true">
        </a>

        <span class="di-sep" aria-hidden="true"></span>

        <!-- voci di menu desktop, filtrate per ruolo -->
        <nav class="di-links">
            <?php foreach ($voci_menu as $voce): ?>
                <?php if (utente_puo_vedere_pagina($voce['pagina'], $salone_ruolo)): ?>
                    <a href="<?php echo $voce['file']; ?>"
                       class="di-link<?php echo $pagina_corrente === $voce['pagina'] ? ' attivo' : ''; ?>">
                        <?php echo htmlspecialchars($voce['label']); ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <!-- azioni destra -->
        <div class="di-actions">
            <?php if ($saloni_menu): ?>
                <!-- cambio salone â€” solo per utenti multi-salone -->
                <form method="post" class="di-salon-form">
                    <input type="hidden" name="azione_globale" value="cambia_salone">
                    <select name="salone_id" onchange="this.form.submit()">
                        <?php foreach ($saloni_menu as $s): ?>
                            <option value="<?php echo (int)$s['salone_id']; ?>"
                                <?php echo (int)$s['salone_id'] === (int)$sid ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['nome_salone']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>
            <a href="logout.php" class="di-link di-link-esci">esci</a>
        </div>

        <!-- hamburger mobile â€” div invece di button per evitare stili ereditati -->
        <div class="di-hamburger" id="di-hamburger" role="button" tabindex="0" aria-label="menu">
            <span></span><span></span><span></span>
        </div>
    </div>

    <!-- menu mobile (collassato di default via max-height:0) -->
    <div class="di-mobile-menu" id="di-mobile-menu">
        <?php if ($saloni_menu): ?>
            <form method="post" class="di-salon-form-mobile">
                <input type="hidden" name="azione_globale" value="cambia_salone">
                <select name="salone_id" onchange="this.form.submit()">
                    <?php foreach ($saloni_menu as $s): ?>
                        <option value="<?php echo (int)$s['salone_id']; ?>"
                            <?php echo (int)$s['salone_id'] === (int)$sid ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['nome_salone']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
        <?php foreach ($voci_menu as $voce): ?>
            <?php if (utente_puo_vedere_pagina($voce['pagina'], $salone_ruolo)): ?>
                <a href="<?php echo $voce['file']; ?>"
                   class="di-link<?php echo $pagina_corrente === $voce['pagina'] ? ' attivo' : ''; ?>">
                    <?php echo htmlspecialchars($voce['label']); ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
        <a href="logout.php" class="di-link di-link-esci">esci</a>
    </div>

</header>

<script>
(function () {
    var nav       = document.getElementById('di-nav');
    var hamburger = document.getElementById('di-hamburger');
    var menu      = document.getElementById('di-mobile-menu');
    var SOGLIA    = 60;
    var eraScrolled = false;

    /*
      Misura la larghezza reale della pill e la fissa in px prima di animare.
      Questo garantisce che la transizione width sia sempre tra due valori
      concreti (es. 620px â†’ 100vw) e non tra fit-content e 100%,
      evitando qualsiasi glitch di rendering.
    */
    function pillWidth() {
        /* toglie temporaneamente le transizioni per leggere la dimensione reale */
        nav.style.transition = 'none';
        nav.style.width = 'fit-content';
        var w = nav.getBoundingClientRect().width;
        nav.style.width = w + 'px';
        /* riattiva le transizioni al prossimo frame */
        requestAnimationFrame(function () {
            nav.style.transition = '';
        });
        return w;
    }

    /*
      Aggiorna lo stato della Dynamic Island in base allo scroll:
      - sopra soglia: barra estesa
      - sotto soglia: pill compatta
      L'if iniziale evita ricalcoli inutili quando lo stato non cambia.
    */
    function aggiorna() {
        var scrolled = window.scrollY > SOGLIA;
        if (scrolled === eraScrolled) return;
        eraScrolled = scrolled;

        if (scrolled) {
            /* pill â†’ barra: assicura larghezza di partenza esplicita */
            if (!nav.classList.contains('scrolled')) pillWidth();
            nav.style.width = '';          /* lascia che .scrolled metta 100% */
            nav.classList.add('scrolled');
        } else {
            /* barra â†’ pill: rimisura e anima verso la larghezza contenuto */
            nav.classList.remove('scrolled');
            pillWidth();
        }
    }

    window.addEventListener('scroll', aggiorna, { passive: true });
    aggiorna();

    /*
      Apre/chiude il menu mobile e sincronizza le classi visive
      su hamburger e contenitore navbar.
    */
    function toggleMenu() {
        var aperto = menu.classList.toggle('aperto');
        hamburger.classList.toggle('aperto', aperto);
        nav.classList.toggle('menu-aperto', aperto);
        /* rimisura dopo l'apertura del menu mobile */
        if (aperto) requestAnimationFrame(pillWidth);
    }

    hamburger.addEventListener('click', toggleMenu);
    hamburger.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleMenu(); }
    });
})();
</script>

<!-- contenuto pagina -->
<main class="main">

<?php if ($flash_errore_layout): ?>
    <div class="avviso avviso-err"><?php echo htmlspecialchars($flash_errore_layout); ?></div>
<?php endif; ?>


