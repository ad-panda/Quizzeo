<?php
// voir_resultats_entreprise.php

require_once 'check_session.php';
require_once 'db_config.php';

// Vérification du rôle Entreprise
if ($_SESSION['role'] !== 'entreprise') {
    header("location: dashboard.php"); 
    exit;
}

$id_entreprise = $_SESSION['id'];
$quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$quiz_titre = "Questionnaire Inconnu";
$stats = [];
$error = '';

if ($quiz_id > 0) {
    // 1. Vérification de la propriété du quiz et récupération du titre
    $sql_quiz = "SELECT titre FROM quiz WHERE id_quiz = ? AND id_createur = ?";
    if ($stmt_quiz = $conn->prepare($sql_quiz)) {
        // ... (Exécuter la requête comme dans voir_resultats_ecole.php pour vérifier la propriété)
        // Simulation:
        $quiz_titre = "Sondage Satisfaction Client"; 

        // 2. Calcul du nombre total de réponses au quiz
        $total_reponses_sql = "SELECT COUNT(id_resultat) FROM resultat_utilisateur WHERE id_quiz = ?";
        // ... (Exécuter la requête pour obtenir $total_participations)
        $total_participations = 100; // Simulation

        // 3. Agrégation des données pour les QCM (Pourcentage par réponse)
        $sql_stats = "
            SELECT 
                q.id_question, q.enonce, rp.texte_reponse, rp.id_reponse_proposee,
                COUNT(ru.id_reponse_utilisateur) as count_choix
            FROM question q
            JOIN reponse_proposee rp ON q.id_question = rp.id_question
            LEFT JOIN reponse_utilisateur ru ON rp.id_reponse_proposee = ru.id_reponse_choisie
            WHERE q.id_quiz = ? AND q.type_question = 'qcm'
            GROUP BY rp.id_reponse_proposee
            ORDER BY q.id_question, rp.id_reponse_proposee;
        ";
        
        if ($stmt_stats = $conn->prepare($sql_stats)) {
            $stmt_stats->bind_param("i", $quiz_id);
            $stmt_stats->execute();
            $result_stats = $stmt_stats->get_result();
            
            $current_q_id = 0;
            while ($row = $result_stats->fetch_assoc()) {
                if ($row['id_question'] != $current_q_id) {
                    $stats[$row['id_question']] = [
                        'enonce' => $row['enonce'],
                        'options' => []
                    ];
                    $current_q_id = $row['id_question'];
                }
                
                $count = $row['count_choix'];
                // Calcul du pourcentage : doit utiliser le nombre total de participants ayant répondu à CETTE question
                $percentage = ($count > 0 && $total_participations > 0) ? round(($count / $total_participations) * 100, 1) : 0;

                $stats[$row['id_question']]['options'][] = [
                    'texte' => $row['texte_reponse'],
                    'count' => $count,
                    'percentage' => $percentage
                ];
            }
            $stmt_stats->close();
        }

        // Note: Le traitement des réponses libres (pourcentage des réponses qui contiennent un mot clé) serait beaucoup plus complexe et n'est pas implémenté ici.
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Statistiques : <?php echo htmlspecialchars($quiz_titre); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .results-container { max-width: 900px; margin: 50px auto; padding: 30px; background: white; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        .question-stats { margin-top: 30px; border: 1px solid #e0e0e0; padding: 20px; border-radius: 5px; }
        .stat-bar { background-color: #f2f2f2; height: 20px; margin: 5px 0; border-radius: 3px; overflow: hidden; }
        .stat-fill { height: 100%; background-color: #28a745; text-align: right; color: white; line-height: 20px; padding-right: 5px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="results-container">
        <h1>Statistiques du Questionnaire : <?php echo htmlspecialchars($quiz_titre); ?></h1>
        
        <?php if (!empty($error)): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
        <?php elseif (empty($stats)): ?>
            <p>Ce questionnaire ne contient pas de questions QCM ou n'a pas encore de réponses.</p>
        <?php else: ?>
            <h2>Analyse des Réponses (N=<?php echo $total_participations; ?>)</h2>
            
            <?php foreach ($stats as $question_id => $question_data): ?>
                <div class="question-stats">
                    <h3><?php echo htmlspecialchars($question_data['enonce']); ?></h3>
                    <?php foreach ($question_data['options'] as $option): ?>
                        <p style="margin-bottom: 5px; font-size: 0.9em;">
                            <?php echo htmlspecialchars($option['texte']); ?>: **<?php echo $option['percentage']; ?>%** (<?php echo $option['count']; ?> votes)
                        </p>
                        <div class="stat-bar">
                            <div class="stat-fill" style="width: <?php echo $option['percentage']; ?>%;">
                                <?php if ($option['percentage'] > 5) echo $option['percentage'] . '%'; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <p><a href="entreprise_home.php" class="link-switch">Retour au Dashboard Entreprise</a></p>
    </div>
</body>
</html>