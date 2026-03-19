<?php
// voir_resultats_ecole.php

require_once 'check_session.php';
require_once 'db_config.php';

// Vérification du rôle École
if ($_SESSION['role'] !== 'ecole') {
    header("location: dashboard.php"); 
    exit;
}

$id_ecole = $_SESSION['id'];
$quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$quiz_titre = "Quiz Inconnu";
$results_list = [];
$error = '';

if ($quiz_id > 0) {
    // 1. Vérification de la propriété du quiz et récupération du titre
    $sql_quiz = "SELECT titre FROM quiz WHERE id_quiz = ? AND id_createur = ?";
    if ($stmt_quiz = $conn->prepare($sql_quiz)) {
        $stmt_quiz->bind_param("ii", $quiz_id, $id_ecole);
        $stmt_quiz->execute();
        $result_quiz = $stmt_quiz->get_result();
        if ($result_quiz->num_rows == 1) {
            $quiz_titre = $result_quiz->fetch_assoc()['titre'];

            // 2. Récupération des noms des participants et de leurs notes
            // Jointure sur utilisateur pour récupérer le nom/prénom
            $sql_results = "
                SELECT 
                    u.nom_compte, u.nom, u.prenom, ru.note_totale, ru.date_soumission
                FROM resultat_utilisateur ru
                JOIN utilisateur u ON ru.id_utilisateur = u.id_utilisateur
                WHERE ru.id_quiz = ?
                ORDER BY ru.date_soumission DESC;
            ";

            if ($stmt_results = $conn->prepare($sql_results)) {
                $stmt_results->bind_param("i", $quiz_id);
                $stmt_results->execute();
                $results_list = $stmt_results->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_results->close();
            } else {
                $error = "Erreur de préparation de la requête de résultats.";
            }

        } else {
            $error = "Quiz introuvable ou vous n'êtes pas le propriétaire.";
        }
        $stmt_quiz->close();
    }
} else {
    $error = "ID du quiz manquant.";
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Résultats : <?php echo htmlspecialchars($quiz_titre); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .results-container { max-width: 900px; margin: 50px auto; padding: 30px; background: white; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        .results-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .results-table th, .results-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .results-table th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class="results-container">
        <h1>Résultats du Quiz : <?php echo htmlspecialchars($quiz_titre); ?></h1>
        
        <?php if (!empty($error)): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
        <?php elseif (empty($results_list)): ?>
            <p>Aucun étudiant n'a encore répondu à ce quiz.</p>
        <?php else: ?>
            <h2>Liste des Notes</h2>
            <table class="results-table">
                <thead>
                    <tr>
                        <th>Nom du Compte</th>
                        <th>Nom & Prénom</th>
                        <th>Note Obtenue</th>
                        <th>Date de Soumission</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results_list as $result): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($result['nom_compte']); ?></td>
                            <td><?php echo htmlspecialchars($result['nom']) . ' ' . htmlspecialchars($result['prenom']); ?></td>
                            <td>**<?php echo htmlspecialchars($result['note_totale'] ?? 'N/A'); ?>** / 10</td>
                            <td><?php echo date('d/m/Y H:i', strtotime($result['date_soumission'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <p><a href="ecole_home.php" class="link-switch">Retour au Dashboard École</a></p>
    </div>
</body>
</html>