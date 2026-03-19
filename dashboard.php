<?php
// dashboard.php

session_start();

// Vérification de la connexion (très important pour la sécurité)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.html");
    exit;
}

// ----------------------------------------------------
// GESTION DE LA REDIRECTION VERS L'URL MEMORISÉE (Quiz)
// ----------------------------------------------------

if (isset($_SESSION['redirect_to'])) {
    $redirect_url = $_SESSION['redirect_to'];
    
    // Nettoyer la variable de session immédiatement après usage
    unset($_SESSION['redirect_to']);
    
    // Rediriger vers l'URL mémorisée (le quiz)
    header('Location: ' . $redirect_url);
    exit;
}

// ----------------------------------------------------
// REDIRECTION NORMALE (Si aucun quiz n'est en attente)
// ----------------------------------------------------

$user_role = $_SESSION['role'];

// LOGIQUE DE REDIRECTION BASÉE SUR LE RÔLE
switch ($user_role) {
    // CORRECTION : S'assurer que les liens pointent vers .php
    case 'administrateur':
        header('Location: admin_home.php');
        exit;
    case 'ecole':
        header('Location: ecole_home.php');
        exit;
    case 'entreprise':
        header('Location: entreprise_home.php');
        exit;
    case 'simple_utilisateur':
        header('Location: utilisateur_home.php');
        exit;
    default:
        // Cas d'un rôle inconnu (sécurité)
        session_destroy();
        header('Location: index.html?error=' . urlencode('Rôle utilisateur non valide.'));
        exit;
}
?>