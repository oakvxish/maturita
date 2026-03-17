<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) || empty($_SESSION['salone_id'])) {
    header('Location: login.php');
    exit;
}

// $sid disponibile in tutti i file admin
$sid = (int) $_SESSION['salone_id'];
