<?php
$host = 'localhost';
$db   = 'salone_db';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('errore connessione: ' . $conn->connect_error);
}
