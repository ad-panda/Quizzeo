<?php
// liste_quiz_actifs.php

require_once 'check_session.php';
require_once 'db_config.php';

// Seul le simple utilisateur est censé répondre aux quiz via cette page
if ($_SESSION['role'] !== 'simple_utilisateur') {
    header("location: dashboard.php"); 
    exit;
}

$quiz_actifs = [];
$id_utilisateur = $_SESSION['id'];

// Récupérer les quiz lancés et actifs, auxquels l'utilisateur n'a PAS ENCORE répondu.
$sql = "
    SELECT 
        q.id_quiz, q.titre, q.description, u_creator.nom_compte as createur
    FROM quiz q
    JOIN utilisateur u_creator ON q.id_createur = u_creator.id_utilisateur
    WHERE q.statut = 'lance' 
      AND q.est_actif = 1 
      -- Exclure les quiz auxquels cet utilisateur a déjà un résultat
      AND q.id_quiz NOT IN (
          SELECT id_quiz FROM resultat_utilisateur WHERE id_utilisateur = ?
      )
    ORDER BY q.date_creation DESC;
";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $id_utilisateur);
    $stmt->execute();
    $quiz_actifs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Quiz Actifs Disponibles - Quizeo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .list-container { max-width: 900px; margin: 50px auto; padding: 30px; background: white; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        .quiz-item { border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-left: 5px solid #28a745; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="list-container">
        <h1>Questionnaires Disponibles</h1>
        <p>Cliquez sur un quiz pour commencer.</p>

        <?php if (empty($quiz_actifs)): ?>
            <p>Il n'y a actuellement aucun quiz actif auquel vous n'avez pas encore participé.</p>
        <?php else: ?>
            <?php foreach ($quiz_actifs as $quiz): ?>
                <div class="quiz-item">
                    <h3><?php echo htmlspecialchars($quiz['titre']); ?></h3>
                    <p>Créateur : <?php echo htmlspecialchars($quiz['createur']); ?></p>
                    <p><?php echo nl2br(htmlspecialchars($quiz['description'])); ?></p>
                    <a href="repondre_quiz.php?id=<?php echo $quiz['id_quiz']; ?>" class="btn-login" style="display: inline-block; width: auto; padding: 8px 15px; margin-top: 5px;">Commencer le Quiz</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <p style="margin-top: 20px;"><a href="utilisateur_home.php" class="link-switch">Retour au Dashboard</a></p>
    </div>
</body>
</html>