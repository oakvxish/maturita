<!DOCTYPE html>
<html lang="it">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prenotazione confermata - Lumière</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    :root {
      --cream: #f9f4ed;
      --warm-white: #fffdf9;
      --charcoal: #1c1917;
      --muted: #78716c;
      --gold: #b5924c;
      --gold-light: #e8d5b0;
      --border: #e8e0d5;
      --nav-h: 70px;
    }

    body {
      font-family: 'Jost', sans-serif;
      background: var(--warm-white);
      color: var(--charcoal);
      overflow-x: hidden;
      min-height: 100vh;
    }

    /* ── NAV ── */
    nav {
      height: var(--nav-h);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 clamp(20px, 5vw, 60px);
      background: rgba(249, 244, 237, 0.97);
      border-bottom: 1px solid var(--border);
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .nav-logo {
      font-family: 'Cormorant Garamond', serif;
      font-size: 1.55rem;
      font-weight: 300;
      letter-spacing: 0.12em;
      color: var(--charcoal);
      text-decoration: none;
    }

    .nav-logo em {
      font-style: italic;
      color: var(--gold);
    }

    .nav-back {
      font-size: 0.7rem;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--muted);
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: color 0.2s;
    }

    .nav-back:hover {
      color: var(--charcoal);
    }

    .nav-back::before {
      content: '←';
      font-size: 0.95rem;
    }

    /* ── HERO STRIP ── */
    .hero-strip {
      position: relative;
      height: 280px;
      overflow: hidden;
    }

    .hero-strip img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      filter: brightness(0.55);
    }

    .hero-strip-overlay {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 10px;
      text-align: center;
    }

    .hero-strip-tag {
      font-size: 0.65rem;
      letter-spacing: 0.4em;
      text-transform: uppercase;
      color: var(--gold-light);
    }

    .hero-strip-title {
      font-family: 'Cormorant Garamond', serif;
      font-size: clamp(2rem, 5vw, 3.8rem);
      font-weight: 300;
      color: #fff;
      line-height: 1.1;
    }

    .hero-strip-title em {
      font-style: italic;
      color: var(--gold-light);
    }

    /* ── MAIN LAYOUT ── */
    .page-wrap {
      max-width: 1100px;
      margin: 0 auto;
      padding: clamp(40px, 6vw, 80px) clamp(20px, 4vw, 40px);
    }

    /* ── CONFERMA CARD ── */
    .conferma-wrap {
      display: grid;
      grid-template-columns: 1fr 1.1fr;
      gap: 0;
      background: var(--warm-white);
      border: 1px solid var(--border);
      animation: fadeUp 0.6s ease both;
    }

    @keyframes fadeUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .conferma-img-col {
      position: relative;
      overflow: hidden;
      min-height: 420px;
    }

    .conferma-img-col img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      transition: transform 6s ease;
    }

    .conferma-img-col:hover img {
      transform: scale(1.04);
    }

    .conferma-img-badge {
      position: absolute;
      bottom: 28px;
      right: -1px;
      background: var(--gold);
      color: #fff;
      padding: 14px 22px;
      font-size: 0.68rem;
      letter-spacing: 0.2em;
      text-transform: uppercase;
    }

    .conferma-body {
      padding: clamp(32px, 4vw, 52px);
      display: flex;
      flex-direction: column;
      gap: 28px;
    }

    .check-icon {
      width: 52px;
      height: 52px;
      border-radius: 50%;
      background: var(--gold);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .check-icon svg {
      width: 24px;
      height: 24px;
      stroke: #fff;
      fill: none;
      stroke-width: 2.5;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    .conferma-heading {
      font-family: 'Cormorant Garamond', serif;
      font-size: clamp(1.8rem, 3vw, 2.6rem);
      font-weight: 300;
      line-height: 1.15;
      color: var(--charcoal);
    }

    .conferma-heading em {
      font-style: italic;
      color: var(--gold);
    }

    .conferma-sub {
      font-size: 0.88rem;
      color: var(--muted);
      line-height: 1.7;
      font-weight: 300;
    }

    /* ── DETTAGLI ── */
    .dettagli {
      background: var(--cream);
      padding: 24px;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .dettagli-title {
      font-size: 0.65rem;
      letter-spacing: 0.3em;
      text-transform: uppercase;
      color: var(--gold);
      margin-bottom: 4px;
    }

    .dettaglio-row {
      display: flex;
      align-items: flex-start;
      gap: 14px;
    }

    .dettaglio-icon {
      font-size: 1rem;
      flex-shrink: 0;
      margin-top: 2px;
    }

    .dettaglio-info {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .dettaglio-label {
      font-size: 0.65rem;
      letter-spacing: 0.15em;
      text-transform: uppercase;
      color: var(--muted);
    }

    .dettaglio-valore {
      font-size: 0.95rem;
      font-weight: 400;
      color: var(--charcoal);
    }

    /* ── CTA ── */
    .conferma-cta {
      display: flex;
      gap: 14px;
      flex-wrap: wrap;
    }

    .btn-primary {
      background: var(--charcoal);
      color: var(--cream);
      padding: 14px 28px;
      text-decoration: none;
      font-size: 0.7rem;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      transition: background 0.25s;
    }

    .btn-primary:hover {
      background: var(--gold);
    }

    .btn-ghost {
      color: var(--charcoal);
      text-decoration: none;
      font-size: 0.7rem;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      display: flex;
      align-items: center;
      gap: 8px;
      border: 1px solid var(--border);
      padding: 14px 24px;
      transition: all 0.25s;
    }

    .btn-ghost:hover {
      border-color: var(--gold);
      color: var(--gold);
    }

    /* ── GALLERY STRIP ── */
    .gallery-strip {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 2px;
      margin-top: clamp(32px, 5vw, 60px);
      animation: fadeUp 0.7s ease 0.2s both;
    }

    .gallery-strip figure {
      position: relative;
      overflow: hidden;
      aspect-ratio: 4/3;
    }

    .gallery-strip figure img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      transition: transform 5s ease;
      filter: brightness(0.85);
    }

    .gallery-strip figure:hover img {
      transform: scale(1.06);
      filter: brightness(1);
    }

    .gallery-strip figcaption {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: linear-gradient(to top, rgba(28, 25, 23, 0.75) 0%, transparent 100%);
      padding: 20px 16px 14px;
      font-size: 0.68rem;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: var(--gold-light);
    }

    /* ── NOTE ── */
    .nota-strip {
      margin-top: clamp(24px, 4vw, 48px);
      padding: 24px 28px;
      border-left: 2px solid var(--gold);
      background: var(--cream);
      animation: fadeUp 0.7s ease 0.35s both;
    }

    .nota-strip p {
      font-size: 0.88rem;
      color: var(--muted);
      line-height: 1.75;
      font-weight: 300;
    }

    .nota-strip strong {
      color: var(--charcoal);
      font-weight: 400;
    }

    /* ── FOOTER ── */
    footer {
      margin-top: 80px;
      padding: 28px clamp(20px, 5vw, 60px);
      border-top: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
      font-size: 0.75rem;
      color: var(--muted);
      letter-spacing: 0.05em;
      flex-wrap: wrap;
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 700px) {
      .conferma-wrap {
        grid-template-columns: 1fr;
      }

      .conferma-img-col {
        min-height: 220px;
      }

      .gallery-strip {
        grid-template-columns: 1fr 1fr;
      }

      .gallery-strip figure:last-child {
        display: none;
      }
    }
  </style>
</head>

<body>

  <?php
  /* ─────────────────────────────────────────────────────────────
   Usa la connessione condivisa db.php — nessuna credenziale
   hardcodata in questa pagina.
───────────────────────────────────────────────────────────── */
  require_once __DIR__ . '/db.php';

  // Recupera nome servizio in modo sicuro con prepared statement
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

  // Dati cliente - escape per output HTML
  function h($v)
  {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
  }

  $nome     = h($_POST['_nome']     ?? '');
  $cognome  = h($_POST['_cognome']  ?? '');
  $telefono = h($_POST['_telefono'] ?? '');
  $email    = h($_POST['_email']    ?? '');
  $data_raw = $_POST['_data']       ?? '';
  $ora      = h($_POST['_ora']      ?? '');
  $note     = h($_POST['note']      ?? '');

  // Formatta data in italiano
  $data_fmt = '-';
  if ($data_raw) {
    $ts = strtotime($data_raw);
    $giorni   = ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'];
    $mesi     = [
      '',
      'gennaio',
      'febbraio',
      'marzo',
      'aprile',
      'maggio',
      'giugno',
      'luglio',
      'agosto',
      'settembre',
      'ottobre',
      'novembre',
      'dicembre'
    ];
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
          <svg viewBox="0 0 24 24">
            <polyline points="20 6 9 17 4 12" />
          </svg>
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