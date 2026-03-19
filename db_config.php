<?php
// db_config.php

// --- PARAMÈTRES DE CONNEXION ---
$DB_HOST = '127.0.0.1';
$DB_NAME = 'db_quizzeo'; 
$DB_USER = 'root';
$DB_PASS = ''; 
$DB_CHARSET = 'utf8mb4';

// --- CONNEXION À LA BASE DE DONNÉES ---

// Utilisation des variables pour la connexion mysqli
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// Vérification de la connexion
if ($conn->connect_error) {
    die("ERREUR: Impossible de se connecter à la base de données (" . $conn->connect_errno . ") " . $conn->connect_error);
}

// Définir l'encodage pour éviter les problèmes de caractères spéciaux (MÉTHODE PLUS FIABLE)
mysqli_set_charset($conn, $DB_CHARSET);

// Maintenant, le reste de vos scripts PHP peuvent utiliser la variable $conn.
?>