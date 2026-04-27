<?php
$host = 'localhost';
$db   = 'salone_db';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die('errore connessione: ' . $conn->connect_error);
}


