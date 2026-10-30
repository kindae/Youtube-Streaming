<?php
// Ancienne adresse : redirection vers le site principal
$requete = $_SERVER['QUERY_STRING'] ?? '';
header("Location: ../" . ($requete !== '' ? 'index.php?' . $requete : ''), true, 301);
exit;
