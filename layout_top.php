<?php
$tema = 'chiaro';

if (isset($conn) && isset($sid)) {
    $temaResult = $conn->query("SELECT valore FROM impostazioni WHERE chiave='tema' AND salone_id=$sid");
    $temaRow = $temaResult ? $temaResult->fetch_assoc() : null;

    if ($temaRow && !empty($temaRow['valore'])) {
        $tema = $temaRow['valore'];
    }
}

$nome_salone_nav = $_SESSION['nome_salone'] ?? 'salone';
$pagina_corrente = basename($_SERVER['PHP_SELF'], '.php');
$salone_ruolo = normalizza_ruolo($_SESSION['salone_ruolo'] ?? 'dipendente');
$saloni_menu = $_SESSION['saloni_abilitati'] ?? [];
$flash_errore_layout = function_exists('estrai_flash') ? estrai_flash('flash_errore') : '';

$voci_menu = [
    ['file' => 'index.php', 'pagina' => 'index', 'icona' => '📊', 'label' => 'dashboard'],
    ['file' => 'appuntamenti.php', 'pagina' => 'appuntamenti', 'icona' => '📅', 'label' => 'appuntamenti'],
    ['file' => 'clienti.php', 'pagina' => 'clienti', 'icona' => '👥', 'label' => 'clienti'],
    ['file' => 'servizi.php', 'pagina' => 'servizi', 'icona' => '💅', 'label' => 'servizi'],
    ['file' => 'magazzino.php', 'pagina' => 'magazzino', 'icona' => '📦', 'label' => 'magazzino'],
    ['file' => 'analitiche.php', 'pagina' => 'analitiche', 'icona' => '📈', 'label' => 'analitiche'],
    ['file' => 'utenti_salone.php', 'pagina' => 'utenti_salone', 'icona' => '🔐', 'label' => 'utenti'],
    ['file' => 'impostazioni.php', 'pagina' => 'impostazioni', 'icona' => '⚙️', 'label' => 'impostazioni'],
];
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($nome_salone_nav); ?> — gestionale</title>
    <style>
        :root {
            --bg: <?php echo $tema === 'scuro' ? '#18181b' : '#f4f4f5'; ?>;
            --surface: <?php echo $tema === 'scuro' ? '#27272a' : '#ffffff'; ?>;
            --sidebar: <?php echo $tema === 'scuro' ? '#1e1e21' : '#2d2d2d'; ?>;
            --text: <?php echo $tema === 'scuro' ? '#e4e4e7' : '#1a1a1a'; ?>;
            --text-muted: <?php echo $tema === 'scuro' ? '#71717a' : '#6b7280'; ?>;
            --accent: #9a79e8;
            --accent2: #a855f7;
            --border: <?php echo $tema === 'scuro' ? '#3f3f46' : '#e4e4e7'; ?>;
            --danger: #ef4444;
            --success: #22c55e;
            --warning: #f59e0b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 240px;
            min-height: 100vh;
            background: var(--sidebar);
            display: flex;
            flex-direction: column;
            padding: 24px 0;
            flex-shrink: 0;
        }

        .sidebar .logo {
            color: var(--accent);
            font-size: 1rem;
            font-weight: 700;
            padding: 0 20px 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar .logo-sub {
            font-size: .7rem;
            color: #8b8b93;
            padding: 0 20px 18px;
            border-bottom: 1px solid #444;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .tenant-box {
            padding: 14px 20px 0;
        }

        .tenant-box label {
            display: block;
            font-size: .66rem;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #8b8b93;
            margin-bottom: 6px;
        }

        .tenant-box select {
            width: 100%;
            padding: 9px 10px;
            border: 1px solid #444;
            border-radius: 8px;
            background: rgba(255, 255, 255, .04);
            color: #f4f4f5;
            font-size: .84rem;
        }

        .tenant-role {
            margin-top: 8px;
            font-size: .72rem;
            color: #a1a1aa;
        }

        .sidebar nav {
            padding: 14px 0;
            flex: 1;
        }

        .sidebar nav a {
            display: block;
            padding: 10px 20px;
            color: #bbb;
            text-decoration: none;
            font-size: .9rem;
            border-left: 3px solid transparent;
            transition: background .15s;
        }

        .sidebar nav a:hover {
            background: rgba(255, 255, 255, .05);
            color: #fff;
        }

        .sidebar nav a.attivo {
            color: var(--accent);
            border-left-color: var(--accent);
            background: rgba(154, 121, 232, .08);
        }

        .sidebar .logout {
            padding: 12px 20px;
            border-top: 1px solid #444;
            margin-top: auto;
        }

        .sidebar .logout a {
            color: #666;
            font-size: .8rem;
            text-decoration: none;
        }

        .sidebar .logout a:hover {
            color: #ef4444;
        }

        .sidebar .versione {
            padding: 6px 20px;
            color: #444;
            font-size: .72rem;
        }

        .main {
            flex: 1;
            padding: 28px 32px;
            overflow-x: hidden;
        }

        .titolo-pagina {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 24px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .card h3 {
            font-size: .95rem;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .card .valore {
            font-size: 2rem;
            font-weight: 700;
        }

        .griglia-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .griglia-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 10px 12px;
            font-size: .8rem;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            font-size: .88rem;
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: rgba(0, 0, 0, .02);
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 99px;
            font-size: .75rem;
            font-weight: 600;
        }

        .badge-vip {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-normale {
            background: var(--border);
            color: var(--text-muted);
        }

        .badge-potenziale {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-inattivo {
            background: #f3f4f6;
            color: #6b7280;
        }

        .badge-attesa {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-confermato {
            background: #dcfce7;
            color: #166534;
        }

        .badge-completato {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-annullato {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-proprietario {
            background: #ede9fe;
            color: #6d28d9;
        }

        .badge-dipendente {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 7px;
            border: none;
            cursor: pointer;
            font-size: .85rem;
            font-weight: 600;
            text-decoration: none;
            background: var(--accent);
            color: #fff;
        }

        .btn:hover {
            opacity: .85;
        }

        .btn-grigio {
            background: var(--border);
            color: var(--text);
        }

        .btn-pericolo {
            background: var(--danger);
        }

        .btn-piccolo {
            padding: 4px 10px;
            font-size: .78rem;
        }

        form label {
            display: block;
            font-size: .85rem;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        form input,
        form select,
        form textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--border);
            border-radius: 7px;
            background: var(--bg);
            color: var(--text);
            font-size: .88rem;
            margin-bottom: 14px;
        }

        form input:focus,
        form select:focus,
        form textarea:focus {
            outline: 2px solid var(--accent);
            border-color: transparent;
        }

        .riga-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .avviso {
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: .88rem;
        }

        .avviso-ok {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .avviso-err {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .barra-prog {
            background: var(--border);
            border-radius: 99px;
            height: 8px;
        }

        .barra-prog-fill {
            height: 8px;
            border-radius: 99px;
            background: var(--accent);
        }

        @media (max-width: 768px) {
            .griglia-4 {
                grid-template-columns: repeat(2, 1fr);
            }

            .griglia-2 {
                grid-template-columns: 1fr;
            }

            .riga-form {
                grid-template-columns: 1fr;
            }

            .sidebar {
                width: 92px;
            }

            .tenant-box {
                padding: 10px 8px 0;
            }

            .tenant-box label,
            .tenant-role {
                display: none;
            }

            .tenant-box select {
                padding: 6px;
                font-size: .74rem;
            }

            .sidebar .logo,
            .sidebar .logo-sub,
            .sidebar nav a span,
            .sidebar .versione,
            .sidebar .logout a span {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="logo">✂ <?php echo htmlspecialchars($nome_salone_nav); ?></div>
        <div class="logo-sub">@<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div>

        <?php if (count($saloni_menu) > 1): ?>
            <div class="tenant-box">
                <form method="post">
                    <input type="hidden" name="azione_globale" value="cambia_salone">
                    <label>tenant attivo</label>
                    <select name="salone_id" onchange="this.form.submit()">
                        <?php foreach ($saloni_menu as $saloneItem): ?>
                            <option
                                value="<?php echo (int) $saloneItem['salone_id']; ?>"
                                <?php echo (int) $saloneItem['salone_id'] === (int) $sid ? 'selected' : ''; ?>
                            >
                                <?php echo htmlspecialchars($saloneItem['nome_salone']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <div class="tenant-role">ruolo: <?php echo htmlspecialchars($salone_ruolo); ?></div>
            </div>
        <?php endif; ?>

        <nav>
            <?php foreach ($voci_menu as $voce): ?>
                <?php if (!utente_puo_vedere_pagina($voce['pagina'], $salone_ruolo)): ?>
                    <?php continue; ?>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars($voce['file']); ?>" class="<?php echo $pagina_corrente === $voce['pagina'] ? 'attivo' : ''; ?>">
                    <?php echo $voce['icona']; ?> <span><?php echo htmlspecialchars($voce['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="logout"><a href="logout.php">🚪 <span>esci</span></a></div>
        <div class="versione">v11.2</div>
    </div>
    <div class="main">
        <?php if ($flash_errore_layout !== ''): ?>
            <div class="avviso avviso-err"><?php echo htmlspecialchars($flash_errore_layout); ?></div>
        <?php endif; ?>
