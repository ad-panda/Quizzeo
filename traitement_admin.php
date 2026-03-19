<?php
// traitement_admin.php

require_once 'check_session.php';
require_once 'db_config.php';

// Vérification stricte du rôle Administrateur
if ($_SESSION['role'] !== 'administrateur') {
    header("location: dashboard.php"); 
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['type'], $_POST['id'], $_POST['action'])) {
    
    $type = $_POST['type']; // 'user' ou 'quiz'
    $id = (int)$_POST['id'];
    $action = $_POST['action']; // 'activate' ou 'deactivate'
    
    $new_status = ($action === 'activate') ? 1 : 0;
    $table = ($type === 'user') ? 'utilisateur' : 'quiz';
    $id_col = ($type === 'user') ? 'id_utilisateur' : 'id_quiz';
    
    $message = '';
    
    // Préparation de la requête de mise à jour
    $sql = "UPDATE {$table} SET est_actif = ? WHERE {$id_col} = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $new_status, $id);
        
        if ($stmt->execute()) {
            $action_text = ($new_status === 1) ? 'Activé' : 'Désactivé';
            $message = "Succès : L'entité de type {$type} (ID: {$id}) a été {$action_text}.";
        } else {
            $message = "Erreur SQL lors de l'action: " . $conn->error;
        }
        $stmt->close();
    } else {
        $message = "Erreur de préparation de la requête.";
    }
    $conn->close();

    // Redirection vers le tableau de bord Admin
    header("location: admin_home.php?message=" . urlencode($message));
    exit;
}
?>