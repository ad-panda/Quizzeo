<?php
// lancer_quiz.php

require_once 'check_session.php';
require_once 'db_config.php';

$id_createur = $_SESSION['id'];
$quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$message = '';
$error = '';
$lien_acces = '';

if ($quiz_id) {
    // 1. Vérification de la propriété et du statut (doit être en cours d'écriture)
    $sql_check = "SELECT statut, id_createur FROM quiz WHERE id_quiz = ?";
    // ... (Exécuter la requête pour s'assurer que l'utilisateur est le propriétaire et que le statut est 'en_cours_ecriture')

    // Simulation de la vérification (à implémenter complètement)
    $is_valid = true; // Remplacez par la vraie logique de vérification

    if ($is_valid) {
        // 2. Mise à jour du statut
        $sql_update = "UPDATE quiz SET statut = 'lance' WHERE id_quiz = ?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("i", $quiz_id);
            if ($stmt_update->execute()) {
                
                // 3. Génération du lien simple d'accès direct au quiz (Exigence du PDF)
                $lien_acces = "repondre_quiz.php?id=" . $quiz_id;
                $message = "Le quiz est désormais **LANCÉ** ! Le lien d'accès direct est prêt.";
            } else {
                $error = "Erreur lors du changement de statut : " . $conn->error;
            }
            $stmt_update->close();
        }
    } else {
        $error = "Action non autorisée ou quiz déjà lancé/terminé.";
    }

} else {
    $error = "ID du quiz manquant.";
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Lancement du Quiz - Quizeo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .launch-container { max-width: 600px; margin: 50px auto; padding: 30px; background: white; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); text-align: center; }
        .success-box { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .error-box { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="launch-container">
        <h1>Lancement du Quiz</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif (!empty($message)): ?>
            <div class="success-box">
                <h2>Succès !</h2>
                <p><?php echo htmlspecialchars($message); ?></p>
                
                <h3>Lien d'accès :</h3>
                <input type="text" value="<?php echo htmlspecialchars($lien_acces); ?>" style="width: 90%; padding: 10px; border: 1px dashed #007bff;" readonly>
                <p>Partagez ce lien avec vos étudiants ou participants.</p>
            </div>
        <?php endif; ?>
        
        <a href="dashboard.php" class="link-switch">Retour au Dashboard</a>
    </div>
</body>
</html>