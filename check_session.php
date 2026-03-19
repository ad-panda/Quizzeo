<?php
// check_session.php

session_start();

// Si l'utilisateur n'est PAS connecté, on stocke l'URL et on redirige
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    
    // Mémoriser l'URL complète demandée par l'utilisateur
    // $_SERVER['REQUEST_URI'] contient le chemin complet (ex: /dossier/repondre_quiz.php?id=1)
    $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];

    // Rediriger vers la page de connexion
    header("location: index.html?error=" . urlencode("Vous devez être connecté pour accéder à cette page."));
    exit;
}

// L'utilisateur est connecté. Les variables de session sont disponibles :
// $_SESSION['id']
// $_SESSION['nom_compte']
// $_SESSION['role']
?>