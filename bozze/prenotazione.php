<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prenotazione confermata - Lumière</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Victor+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/salone.css">
</head>
<body data-theme="chiaro">

<button type="button" class="theme-float" data-theme-toggle><span class="theme-toggle-text">tema scuro</span></button>
<script src="assets/theme.js"></script>


<?php
require_once __DIR__ . '/db.php';

$servizio_nome = '-';
$servizio_id   = isset($_POST['servizio_id']) ? (int)$_POST['servizio_id'] : 0;

if ($servizio_id > 0) {
    $stmt = $conn->prepare('SELECT nome FROM servizi WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $servizio_id);
        $stmt->execute();
        $stmt->bind_result($servizio_nome);
        $stmt->fetch();
        $stmt->close();
    }
}

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$nome     = h($_POST['_nome']     ?? '');
$cognome  = h($_POST['_cognome']  ?? '');
$telefono = h($_POST['_telefono'] ?? '');
$email    = h($_POST['_email']    ?? '');
$data_raw = $_POST['_data']       ?? '';
$ora      = h($_POST['_ora']      ?? '');
$note     = h($_POST['note']      ?? '');

$data_fmt = '-';
if ($data_raw) {
    $ts = strtotime($data_raw);
    $giorni   = ['domenica','lunedì','martedì','mercoledì','giovedì','venerdì','sabato'];
    $mesi     = ['','gennaio','febbraio','marzo','aprile','maggio','giugno',
                 'luglio','agosto','settembre','ottobre','novembre','dicembre'];
    $data_fmt = $giorni[date('w', $ts)] . ' ' . date('j', $ts) . ' ' . $mesi[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}
?>

<!-- NAV -->
<nav>
  <a href="index.html" class="nav-logo">Lu<em>mière</em></a>
  <a href="index.html#prenota" class="nav-back">torna al sito</a>
</nav>

<!-- HERO STRIP -->
<div class="hero-strip">
  <img src="https://images.unsplash.com/photo-1560066984-138dadb4c035?w=1400&q=80&auto=format&fit=crop"
       alt="Salone Lumière">
  <div class="hero-strip-overlay">
    <span class="hero-strip-tag">lumière · salone di bellezza</span>
    <h1 class="hero-strip-title">Prenotazione <em>confermata</em></h1>
  </div>
</div>

<!-- MAIN -->
<div class="page-wrap">

  <!-- CARD CONFERMA -->
  <div class="conferma-wrap">

    <!-- Colonna immagine -->
    <div class="conferma-img-col">
      <img src="https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=800&q=80&auto=format&fit=crop"
           alt="Trattamento capelli Lumière">
      <div class="conferma-img-badge">✦ ci vediamo presto</div>
    </div>

    <!-- Colonna testo -->
    <div class="conferma-body">

      <div class="check-icon">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
      </div>

      <div>
        <h2 class="conferma-heading">
          Grazie, <em><?= $nome ?></em>.<br>
          La tua richiesta è stata ricevuta.
        </h2>
        <p class="conferma-sub" style="margin-top:12px">
          Il nostro team ti contatterà nelle prossime ore per confermare l'appuntamento via WhatsApp o telefono. Conserva questo riepilogo come riferimento.
        </p>
      </div>

      <!-- Dettagli appuntamento -->
      <div class="dettagli">
        <div class="dettagli-title">riepilogo appuntamento</div>

        <div class="dettaglio-row">
          <div class="dettaglio-icon">✦</div>
          <div class="dettaglio-info">
            <span class="dettaglio-label">trattamento</span>
            <span class="dettaglio-valore"><?= h($servizio_nome) ?></span>
          </div>
        </div>

        <div class="dettaglio-row">
          <div class="dettaglio-icon">📅</div>
          <div class="dettaglio-info">
            <span class="dettaglio-label">data</span>
            <span class="dettaglio-valore"><?= $data_fmt ?></span>
          </div>
        </div>

        <div class="dettaglio-row">
          <div class="dettaglio-icon">⏰</div>
          <div class="dettaglio-info">
            <span class="dettaglio-label">ora richiesta</span>
            <span class="dettaglio-valore"><?= $ora ?: '-' ?></span>
          </div>
        </div>

        <div class="dettaglio-row">
          <div class="dettaglio-icon">👤</div>
          <div class="dettaglio-info">
            <span class="dettaglio-label">cliente</span>
            <span class="dettaglio-valore"><?= $nome . ' ' . $cognome ?></span>
          </div>
        </div>

        <?php if ($telefono): ?>
        <div class="dettaglio-row">
          <div class="dettaglio-icon">📞</div>
          <div class="dettaglio-info">
            <span class="dettaglio-label">telefono</span>
            <span class="dettaglio-valore"><?= $telefono ?></span>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($note): ?>
        <div class="dettaglio-row">
          <div class="dettaglio-icon">📝</div>
          <div class="dettaglio-info">
            <span class="dettaglio-label">note</span>
            <span class="dettaglio-valore"><?= $note ?></span>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- CTA -->
      <div class="conferma-cta">
        <a href="index.html" class="btn-primary">torna alla home</a>
        <a href="index.html#prenota" class="btn-ghost">nuova prenotazione</a>
      </div>

    </div>
  </div><!-- /conferma-wrap -->

  <!-- NOTA WHATSAPP -->
  <div class="nota-strip">
    <p>
      📲 <strong>Riceverai una conferma via WhatsApp</strong> al numero <?= $telefono ?: 'fornito' ?> entro poche ore durante l'orario di apertura (mar – sab, 9:00–19:00). Per urgenze puoi chiamarci al <strong>02 1234 5678</strong>.
    </p>
  </div>

  <!-- GALLERY ISPIRAZIONALE -->
  <div class="gallery-strip">
    <figure>
      <img src="https://images.unsplash.com/photo-1595476108010-b4d1f102b1b1?w=700&q=80&auto=format&fit=crop"
           alt="Manicure">
      <figcaption>unghie</figcaption>
    </figure>
    <figure>
      <img src="https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?w=700&q=80&auto=format&fit=crop"
           alt="Make-up e cura viso">
      <figcaption>viso</figcaption>
    </figure>
    <figure>
      <img src="https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=700&q=80&auto=format&fit=crop"
           alt="Capelli">
      <figcaption>capelli</figcaption>
    </figure>
  </div>

</div><!-- /page-wrap -->

<footer>
  <a href="index.html" class="nav-logo" style="font-size:1.2rem">Lu<em>mière</em></a>
  <span>Via delle Rose 14 · 20121 Milano · 02 1234 5678</span>
  <span>© <?= date('Y') ?> Lumière Salone di Bellezza</span>
</footer>

</body>
</html>