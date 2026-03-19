<?php
// repondre_quiz.php

require_once 'check_session.php';
require_once 'db_config.php';

$quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$id_utilisateur = $_SESSION['id'];
$quiz_data = null;
$questions_list = [];
$error = '';

if ($quiz_id > 0) {
    // 1. Vérification du statut du quiz (doit être 'lance' et actif)
    $sql_quiz = "SELECT titre, description, est_actif FROM quiz WHERE id_quiz = ? AND statut = 'lance'";
    if ($stmt_quiz = $conn->prepare($sql_quiz)) {
        $stmt_quiz->bind_param("i", $quiz_id);
        $stmt_quiz->execute();
        $result_quiz = $stmt_quiz->get_result();
        
        if ($result_quiz->num_rows === 0) {
            $error = "Ce quiz est introuvable ou n'est pas encore lancé.";
        } else {
            $quiz_data = $result_quiz->fetch_assoc();
            if ($quiz_data['est_actif'] == 0) {
                // Exigence du PDF : Quiz désactivé par l'Admin doit être indisponible par les utilisateurs [cite: 42]
                $error = "Ce questionnaire a été désactivé par l'administrateur.";
            } else {
                
                // 2. Récupération des questions et réponses proposées
                $sql_questions = "
                    SELECT q.id_question, q.enonce, q.type_question, q.points, rp.id_reponse_proposee, rp.texte_reponse
                    FROM question q
                    LEFT JOIN reponse_proposee rp ON q.id_question = rp.id_question
                    WHERE q.id_quiz = ?
                    ORDER BY q.id_question, rp.id_reponse_proposee
                ";
                
                if ($stmt_q = $conn->prepare($sql_questions)) {
                    $stmt_q->bind_param("i", $quiz_id);
                    $stmt_q->execute();
                    $result_q = $stmt_q->get_result();
                    
                    // Stockage des questions dans un tableau structuré
                    while ($row = $result_q->fetch_assoc()) {
                        $q_id = $row['id_question'];
                        if (!isset($questions_list[$q_id])) {
                            $questions_list[$q_id] = [
                                'enonce' => $row['enonce'],
                                'type' => $row['type_question'],
                                'points' => $row['points'],
                                'reponses' => []
                            ];
                        }
                        if ($row['id_reponse_proposee'] !== null) {
                            $questions_list[$q_id]['reponses'][] = [
                                'id_reponse' => $row['id_reponse_proposee'],
                                'texte' => $row['texte_reponse']
                            ];
                        }
                    }
                } else {
                    $error = "Erreur lors de la préparation des questions.";
                }
            }
        }
        $stmt_quiz->close();
    } else {
        $error = "Erreur de connexion à la base de données.";
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
    <title>Répondre au Quiz - <?php echo htmlspecialchars($quiz_data['titre'] ?? 'Quizeo'); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .quiz-view-container { max-width: 900px; margin: 50px auto; padding: 30px; background: white; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        .question-block { margin-bottom: 30px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .qcm-option { display: block; margin-bottom: 8px; }
        .error-message { color: red; text-align: center; }
    </style>
</head>
<body>
    <div class="quiz-view-container">
        <?php if (!empty($error)): ?>
            <h1 class="error-message">Erreur : <?php echo htmlspecialchars($error); ?></h1>
            <p><a href="dashboard.php" class="link-switch">Retour au Dashboard</a></p>
        <?php else: ?>
            <h1>Quiz : <?php echo htmlspecialchars($quiz_data['titre']); ?></h1>
            <p><?php echo htmlspecialchars($quiz_data['description']); ?></p>

            <form action="traitement_reponse_quiz.php" method="POST">
                <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
                
                <?php $q_num = 1; foreach ($questions_list as $q_id => $question): ?>
                    <div class="question-block">
                        <h3>Question <?php echo $q_num++; ?> : <?php echo htmlspecialchars($question['enonce']); ?> 
                            <?php if ($question['points'] > 0) echo "(Points: {$question['points']})"; ?>
                        </h3>

                        <?php if ($question['type'] === 'qcm'): ?>
                            <?php foreach ($question['reponses'] as $reponse): ?>
                                <label class="qcm-option">
                                    <input type="radio" name="reponse_qcm[<?php echo $q_id; ?>]" value="<?php echo $reponse['id_reponse']; ?>" required>
                                    <?php echo htmlspecialchars($reponse['texte']); ?>
                                </label>
                            <?php endforeach; ?>
                        
                        <?php elseif ($question['type'] === 'reponse_libre'): ?>
                            <textarea name="reponse_texte[<?php echo $q_id; ?>]" rows="4" style="width: 98%;" required></textarea>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="btn-login" style="width: 100%;">Soumettre le Quiz</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>